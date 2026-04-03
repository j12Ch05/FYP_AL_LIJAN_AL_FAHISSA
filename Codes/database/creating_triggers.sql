
DELIMITER $$

CREATE TRIGGER `delete_teaching_after_course`
AFTER DELETE ON `course`
FOR EACH ROW
BEGIN
    DELETE FROM `teaching`
    WHERE `course_code` = OLD.`course_code`
      AND `course_lang` = OLD.`course_lang`;
END$$

CREATE TRIGGER `delete_teaching_after_course_disabled`
AFTER UPDATE ON `course`
FOR EACH ROW
BEGIN
    IF NEW.`isActive` = 0 AND OLD.`isActive` <> 0 THEN
        DELETE FROM `teaching`
        WHERE `course_code` = OLD.`course_code`
          AND `course_lang` = OLD.`course_lang`;
    END IF;
END$$

DELIMITER ;
