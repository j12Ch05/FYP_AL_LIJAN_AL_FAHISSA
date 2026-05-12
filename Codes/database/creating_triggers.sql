-- ============================================================
-- creating_triggers.sql
-- Run AFTER creating_tables.sql on a fresh database
--
-- Schema: course PK = (course_code, course_lang, major_id, uni_year)
--         teaching FK includes uni_year; correctors stores uni_year per row
-- ============================================================

DELIMITER $$

-- Update department chair when a new admin is made
DROP TRIGGER IF EXISTS `after_professor_make_admin`$$
CREATE TRIGGER `after_professor_make_admin`
AFTER UPDATE ON `professor`
FOR EACH ROW
BEGIN
    IF NEW.isAdmin = 1 AND OLD.isAdmin = 0 AND NEW.dep_id IS NOT NULL THEN
        UPDATE department
        SET chair_person_file_nb = NEW.prof_file_nb
        WHERE dep_id = NEW.dep_id;
    END IF;
END$$

-- Delete teaching rows when the parent course is deleted
DROP TRIGGER IF EXISTS `delete_teaching_after_course`$$
CREATE TRIGGER `delete_teaching_after_course`
AFTER DELETE ON `course`
FOR EACH ROW
BEGIN
    DELETE FROM `teaching`
    WHERE `course_code` = OLD.`course_code`
      AND `course_lang` = OLD.`course_lang`
      AND `major_id`    = OLD.`major_id`
      AND `uni_year`    = OLD.`uni_year`;
END$$

-- Delete corrector rows when a teaching row is deleted
DROP TRIGGER IF EXISTS `after_teaching_delete`$$
CREATE TRIGGER `after_teaching_delete`
AFTER DELETE ON `teaching`
FOR EACH ROW
BEGIN
    DELETE FROM `correctors`
    WHERE course_code  = OLD.course_code
      AND course_lang  = OLD.course_lang
      AND major_id     = OLD.major_id
      AND uni_year     = OLD.uni_year
      AND prof_file_nb = OLD.prof_file_nb;
END$$

-- Auto-create corrector rows when a teaching row is inserted
DROP TRIGGER IF EXISTS `after_teaching_insert`$$
CREATE TRIGGER `after_teaching_insert`
AFTER INSERT ON `teaching`
FOR EACH ROW
BEGIN
    INSERT INTO correctors (
        course_code, prof_file_nb,
        second_corrector_file_nb, third_corrector_file_nb,
        session_nb, course_lang, major_id, uni_year
    )
    SELECT
        NEW.course_code, NEW.prof_file_nb,
        NULL, NULL,
        CASE
            WHEN k.course_semester_nb = 1 THEN 'sem1'
            WHEN k.course_semester_nb = 2 THEN 'sem2'
            ELSE NULL
        END,
        NEW.course_lang, NEW.major_id, NEW.uni_year
    FROM teaching c
    JOIN course k ON k.course_code = c.course_code
                 AND k.course_lang = c.course_lang
                 AND k.major_id = c.major_id
                 AND k.uni_year = c.uni_year
    WHERE c.course_code = NEW.course_code
      AND c.course_lang = NEW.course_lang
      AND c.major_id    = NEW.major_id
      AND c.uni_year    = NEW.uni_year
      AND c.prof_file_nb = NEW.prof_file_nb
      AND k.course_semester_nb IN (1, 2)
      AND NOT EXISTS (
          SELECT 1 FROM correctors x
          WHERE x.course_code = NEW.course_code
            AND x.course_lang = NEW.course_lang
            AND x.major_id    = NEW.major_id
            AND x.uni_year    = NEW.uni_year
            AND x.prof_file_nb = NEW.prof_file_nb
            AND x.session_nb  = CASE
                WHEN k.course_semester_nb = 1 THEN 'sem1'
                WHEN k.course_semester_nb = 2 THEN 'sem2'
                ELSE NULL
            END
      );

    INSERT INTO correctors (
        course_code, prof_file_nb,
        second_corrector_file_nb, third_corrector_file_nb,
        session_nb, course_lang, major_id, uni_year
    )
    SELECT
        NEW.course_code, NEW.prof_file_nb,
        NULL, NULL,
        'sess2', NEW.course_lang, NEW.major_id, NEW.uni_year
    FROM DUAL
    WHERE NOT EXISTS (
        SELECT 1 FROM correctors x
        WHERE x.course_code = NEW.course_code
          AND x.course_lang = NEW.course_lang
          AND x.major_id    = NEW.major_id
          AND x.uni_year    = NEW.uni_year
          AND x.prof_file_nb = NEW.prof_file_nb
          AND x.session_nb  = 'sess2'
    );
END$$

-- Update correctors when teaching is updated (enable/disable / key or prof changes)
DROP TRIGGER IF EXISTS `after_teaching_update`$$
CREATE TRIGGER `after_teaching_update`
AFTER UPDATE ON `teaching`
FOR EACH ROW
BEGIN
    IF NEW.isActive = 0 THEN
        DELETE FROM correctors
        WHERE course_code  = OLD.course_code
          AND course_lang  = OLD.course_lang
          AND major_id     = OLD.major_id
          AND uni_year     = OLD.uni_year
          AND prof_file_nb = OLD.prof_file_nb;
    ELSE
        IF OLD.isActive = 0 THEN
            INSERT INTO correctors (
                course_code, prof_file_nb,
                second_corrector_file_nb, third_corrector_file_nb,
                session_nb, course_lang, major_id, uni_year
            )
            SELECT
                NEW.course_code, NEW.prof_file_nb,
                NULL, NULL,
                CASE
                    WHEN k.course_semester_nb = 1 THEN 'sem1'
                    WHEN k.course_semester_nb = 2 THEN 'sem2'
                    ELSE NULL
                END,
                NEW.course_lang, NEW.major_id, NEW.uni_year
            FROM teaching c
            JOIN course k ON k.course_code = c.course_code
                         AND k.course_lang = c.course_lang
                         AND k.major_id = c.major_id
                         AND k.uni_year = c.uni_year
            WHERE c.course_code = NEW.course_code
              AND c.course_lang = NEW.course_lang
              AND c.major_id    = NEW.major_id
              AND c.uni_year    = NEW.uni_year
              AND c.prof_file_nb = NEW.prof_file_nb
              AND k.course_semester_nb IN (1, 2)
              AND NOT EXISTS (
                  SELECT 1 FROM correctors x
                  WHERE x.course_code = NEW.course_code
                    AND x.course_lang = NEW.course_lang
                    AND x.major_id    = NEW.major_id
                    AND x.uni_year    = NEW.uni_year
                    AND x.prof_file_nb = NEW.prof_file_nb
                    AND x.session_nb  = CASE
                        WHEN k.course_semester_nb = 1 THEN 'sem1'
                        WHEN k.course_semester_nb = 2 THEN 'sem2'
                        ELSE NULL
                    END
              );

            INSERT INTO correctors (
                course_code, prof_file_nb,
                second_corrector_file_nb, third_corrector_file_nb,
                session_nb, course_lang, major_id, uni_year
            )
            SELECT
                NEW.course_code, NEW.prof_file_nb,
                NULL, NULL,
                'sess2', NEW.course_lang, NEW.major_id, NEW.uni_year
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM correctors x
                WHERE x.course_code = NEW.course_code
                  AND x.course_lang = NEW.course_lang
                  AND x.major_id    = NEW.major_id
                  AND x.uni_year    = NEW.uni_year
                  AND x.prof_file_nb = NEW.prof_file_nb
                  AND x.session_nb  = 'sess2'
            );
        END IF;

        -- Keep correctors aligned when any teaching key column changes
        IF OLD.course_code <> NEW.course_code
           OR OLD.course_lang <> NEW.course_lang
           OR OLD.major_id <> NEW.major_id
           OR OLD.uni_year <> NEW.uni_year
           OR OLD.prof_file_nb <> NEW.prof_file_nb THEN
            UPDATE correctors
            SET course_code  = NEW.course_code,
                course_lang  = NEW.course_lang,
                major_id     = NEW.major_id,
                uni_year     = NEW.uni_year,
                prof_file_nb = NEW.prof_file_nb
            WHERE course_code  = OLD.course_code
              AND course_lang  = OLD.course_lang
              AND major_id     = OLD.major_id
              AND uni_year     = OLD.uni_year
              AND prof_file_nb = OLD.prof_file_nb;
        END IF;
    END IF;
END$$

-- Course PK changes: cascade to correctors (teaching follows FK ON UPDATE CASCADE)
DROP TRIGGER IF EXISTS `after_course_update`$$
CREATE TRIGGER `after_course_update`
AFTER UPDATE ON `course`
FOR EACH ROW
BEGIN
    IF OLD.course_code <> NEW.course_code
       OR OLD.course_lang <> NEW.course_lang
       OR OLD.major_id <> NEW.major_id
       OR OLD.uni_year <> NEW.uni_year THEN
        UPDATE correctors
        SET course_code = NEW.course_code,
            course_lang = NEW.course_lang,
            major_id    = NEW.major_id,
            uni_year    = NEW.uni_year
        WHERE course_code = OLD.course_code
          AND course_lang = OLD.course_lang
          AND major_id    = OLD.major_id
          AND uni_year    = OLD.uni_year;
    END IF;

    IF OLD.course_semester_nb <> NEW.course_semester_nb AND NEW.course_semester_nb IN (1, 2) THEN
        UPDATE correctors
        SET session_nb = CASE
                WHEN NEW.course_semester_nb = 1 THEN 'sem1'
                WHEN NEW.course_semester_nb = 2 THEN 'sem2'
                ELSE session_nb
            END
        WHERE course_code = NEW.course_code
          AND course_lang = NEW.course_lang
          AND major_id    = NEW.major_id
          AND uni_year    = NEW.uni_year
          AND session_nb IN ('sem1', 'sem2');
    END IF;
END$$

DELIMITER ;
