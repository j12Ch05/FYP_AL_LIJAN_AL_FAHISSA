
DELIMITER $$

CREATE TRIGGER `delete_teaching_after_course`
AFTER DELETE ON `course`
FOR EACH ROW
BEGIN
    DELETE FROM `teaching`
    WHERE `course_code` = OLD.`course_code`
      AND `course_lang` = OLD.`course_lang`;
END$$






DELIMITER ;

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