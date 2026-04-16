-- ============================================================
-- creating_triggers.sql
-- Run AFTER creating_tables.sql on a fresh database
-- Uses major_id in all correctors operations
-- ============================================================

DELIMITER $$

-- Delete teaching rows when the parent course is deleted
DROP TRIGGER IF EXISTS `delete_teaching_after_course`$$
CREATE TRIGGER `delete_teaching_after_course`
AFTER DELETE ON `course`
FOR EACH ROW
BEGIN
    DELETE FROM `teaching`
    WHERE `course_code` = OLD.`course_code`
      AND `course_lang` = OLD.`course_lang`
      AND `major_id`    = OLD.`major_id`;
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
      AND prof_file_nb = OLD.prof_file_nb;
END$$

-- Auto-create corrector rows when a teaching row is inserted
DROP TRIGGER IF EXISTS `after_teaching_insert`$$
CREATE TRIGGER `after_teaching_insert`
AFTER INSERT ON `teaching`
FOR EACH ROW
BEGIN
    -- Semester row (sem1 or sem2)
    INSERT INTO correctors (
        course_code, prof_file_nb,
        second_corrector_file_nb, third_corrector_file_nb,
        session_nb, course_lang, major_id
    )
    SELECT
        NEW.course_code, NEW.prof_file_nb,
        NULL, NULL,
        CASE
            WHEN c.course_semester_nb = 1 THEN 'sem1'
            WHEN c.course_semester_nb = 2 THEN 'sem2'
            ELSE NULL
        END,
        NEW.course_lang, NEW.major_id
    FROM course c
    WHERE c.course_code = NEW.course_code
      AND c.course_lang = NEW.course_lang
      AND c.major_id    = NEW.major_id
      AND c.course_semester_nb IN (1, 2)
      AND NOT EXISTS (
          SELECT 1 FROM correctors x
          WHERE x.course_code = NEW.course_code
            AND x.course_lang = NEW.course_lang
            AND x.major_id    = NEW.major_id
            AND x.session_nb  = CASE
                WHEN c.course_semester_nb = 1 THEN 'sem1'
                WHEN c.course_semester_nb = 2 THEN 'sem2'
                ELSE NULL
            END
      );

    -- Session 2 row
    INSERT INTO correctors (
        course_code, prof_file_nb,
        second_corrector_file_nb, third_corrector_file_nb,
        session_nb, course_lang, major_id
    )
    SELECT
        NEW.course_code, NEW.prof_file_nb,
        NULL, NULL,
        'sess2', NEW.course_lang, NEW.major_id
    FROM DUAL
    WHERE NOT EXISTS (
        SELECT 1 FROM correctors x
        WHERE x.course_code = NEW.course_code
          AND x.course_lang = NEW.course_lang
          AND x.major_id    = NEW.major_id
          AND x.session_nb  = 'sess2'
    );
END$$

-- Update correctors when teaching is updated (enable/disable/change prof)
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
          AND prof_file_nb = OLD.prof_file_nb;
    ELSE
        IF OLD.isActive = 0 THEN
            INSERT INTO correctors (
                course_code, prof_file_nb,
                second_corrector_file_nb, third_corrector_file_nb,
                session_nb, course_lang, major_id
            )
            SELECT
                NEW.course_code, NEW.prof_file_nb,
                NULL, NULL,
                CASE
                    WHEN c.course_semester_nb = 1 THEN 'sem1'
                    WHEN c.course_semester_nb = 2 THEN 'sem2'
                    ELSE NULL
                END,
                NEW.course_lang, NEW.major_id
            FROM course c
            WHERE c.course_code = NEW.course_code
              AND c.course_lang = NEW.course_lang
              AND c.major_id    = NEW.major_id
              AND c.course_semester_nb IN (1, 2)
              AND NOT EXISTS (
                  SELECT 1 FROM correctors x
                  WHERE x.course_code = NEW.course_code
                    AND x.course_lang = NEW.course_lang
                    AND x.major_id    = NEW.major_id
                    AND x.session_nb  = CASE
                        WHEN c.course_semester_nb = 1 THEN 'sem1'
                        WHEN c.course_semester_nb = 2 THEN 'sem2'
                        ELSE NULL
                    END
              );

            INSERT INTO correctors (
                course_code, prof_file_nb,
                second_corrector_file_nb, third_corrector_file_nb,
                session_nb, course_lang, major_id
            )
            SELECT
                NEW.course_code, NEW.prof_file_nb,
                NULL, NULL,
                'sess2', NEW.course_lang, NEW.major_id
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1 FROM correctors x
                WHERE x.course_code = NEW.course_code
                  AND x.course_lang = NEW.course_lang
                  AND x.major_id    = NEW.major_id
                  AND x.session_nb  = 'sess2'
            );
        END IF;

        IF OLD.prof_file_nb <> NEW.prof_file_nb THEN
            UPDATE correctors
            SET prof_file_nb = NEW.prof_file_nb
            WHERE course_code  = NEW.course_code
              AND course_lang  = NEW.course_lang
              AND major_id     = NEW.major_id
              AND prof_file_nb = OLD.prof_file_nb;
        END IF;
    END IF;
END$$

-- Update correctors session_nb when course semester changes
DROP TRIGGER IF EXISTS `after_course_update`$$
CREATE TRIGGER `after_course_update`
AFTER UPDATE ON `course`
FOR EACH ROW
BEGIN
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
          AND session_nb IN ('sem1', 'sem2');
    END IF;
END$$

DELIMITER ;