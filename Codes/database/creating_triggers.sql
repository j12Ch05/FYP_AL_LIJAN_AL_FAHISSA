
DELIMITER $$

-- delete the teaching if the course is deleted
CREATE TRIGGER `delete_teaching_after_course`
AFTER DELETE ON `course`
FOR EACH ROW
BEGIN
    DELETE FROM `teaching`
    WHERE `course_code` = OLD.`course_code`
      AND `course_lang` = OLD.`course_lang`;
END$$


DELIMITER ;

-- delete the correctors if teaching has deleted something
DELIMITER //

CREATE TRIGGER after_teaching_delete
AFTER DELETE ON teaching
FOR EACH ROW
BEGIN
    DELETE FROM correctors
    WHERE course_code = OLD.course_code
      AND course_lang = OLD.course_lang
      AND prof_file_nb = OLD.prof_file_nb;
END;
//

DELIMITER ;

DELIMITER $$
-- filling the correctors with the course semester and session 2
DROP TRIGGER IF EXISTS after_teaching_insert$$
CREATE TRIGGER after_teaching_insert
AFTER INSERT ON teaching
FOR EACH ROW
BEGIN
    INSERT INTO correctors (
        course_code,
        prof_file_nb,
        second_corrector_file_nb,
        third_corrector_file_nb,
        session_nb,
        course_lang
    )
    SELECT
        NEW.course_code,
        NEW.prof_file_nb,
        NULL,
        NULL,
        CASE
            WHEN c.course_semester_nb = 1 THEN 'sem1'
            WHEN c.course_semester_nb = 2 THEN 'sem2'
            ELSE NULL
        END,
        NEW.course_lang
    FROM course c
    WHERE c.course_code = NEW.course_code
      AND c.course_lang = NEW.course_lang
      AND c.course_semester_nb IN (1, 2)
      AND NOT EXISTS (
          SELECT 1
          FROM correctors x
          WHERE x.course_code = NEW.course_code
            AND x.course_lang = NEW.course_lang
            AND x.session_nb = CASE
                WHEN c.course_semester_nb = 1 THEN 'sem1'
                WHEN c.course_semester_nb = 2 THEN 'sem2'
                ELSE NULL
            END
      );

    INSERT INTO correctors (
        course_code,
        prof_file_nb,
        second_corrector_file_nb,
        third_corrector_file_nb,
        session_nb,
        course_lang
    )
    SELECT
        NEW.course_code,
        NEW.prof_file_nb,
        NULL,
        NULL,
        'sess2',
        NEW.course_lang
    FROM DUAL
    WHERE NOT EXISTS (
        SELECT 1
        FROM correctors x
        WHERE x.course_code = NEW.course_code
          AND x.course_lang = NEW.course_lang
          AND x.session_nb = 'sess2'
    );
END$$

DELIMITER $$
-- update correctors if any update in teaching occured
DROP TRIGGER IF EXISTS after_teaching_update$$
CREATE TRIGGER after_teaching_update
AFTER UPDATE ON teaching
FOR EACH ROW
BEGIN
    IF NEW.isActive = 0 THEN
        DELETE FROM correctors
        WHERE course_code = OLD.course_code
          AND course_lang = OLD.course_lang
          AND prof_file_nb = OLD.prof_file_nb;
    ELSE
        IF OLD.isActive = 0 THEN
            INSERT INTO correctors (
                course_code,
                prof_file_nb,
                second_corrector_file_nb,
                third_corrector_file_nb,
                session_nb,
                course_lang
            )
            SELECT
                NEW.course_code,
                NEW.prof_file_nb,
                NULL,
                NULL,
                CASE
                    WHEN c.course_semester_nb = 1 THEN 'sem1'
                    WHEN c.course_semester_nb = 2 THEN 'sem2'
                    ELSE NULL
                END,
                NEW.course_lang
            FROM course c
            WHERE c.course_code = NEW.course_code
              AND c.course_lang = NEW.course_lang
              AND c.course_semester_nb IN (1, 2)
              AND NOT EXISTS (
                  SELECT 1
                  FROM correctors x
                  WHERE x.course_code = NEW.course_code
                    AND x.course_lang = NEW.course_lang
                    AND x.session_nb = CASE
                        WHEN c.course_semester_nb = 1 THEN 'sem1'
                        WHEN c.course_semester_nb = 2 THEN 'sem2'
                        ELSE NULL
                    END
              );

            INSERT INTO correctors (
                course_code,
                prof_file_nb,
                second_corrector_file_nb,
                third_corrector_file_nb,
                session_nb,
                course_lang
            )
            SELECT
                NEW.course_code,
                NEW.prof_file_nb,
                NULL,
                NULL,
                'sess2',
                NEW.course_lang
            FROM DUAL
            WHERE NOT EXISTS (
                SELECT 1
                FROM correctors x
                WHERE x.course_code = NEW.course_code
                  AND x.course_lang = NEW.course_lang
                  AND x.session_nb = 'sess2'
            );
        END IF;

        IF OLD.prof_file_nb <> NEW.prof_file_nb THEN
            UPDATE correctors
            SET prof_file_nb = NEW.prof_file_nb
            WHERE course_code = NEW.course_code
              AND course_lang = NEW.course_lang
              AND prof_file_nb = OLD.prof_file_nb;
        END IF;
    END IF;
END$$

-- same as previous but if the semester changed
DROP TRIGGER IF EXISTS after_course_update$$
CREATE TRIGGER after_course_update
AFTER UPDATE ON course
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
          AND session_nb IN ('sem1', 'sem2');
    END IF;
END$$

DELIMITER ;