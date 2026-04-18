CREATE TABLE professor (
  prof_file_nb INT NOT NULL,
  prof_first_name VARCHAR(30) NOT NULL,
  prof_last_name VARCHAR(30) NOT NULL,
  prof_father_name VARCHAR(30) NOT NULL,
  prof_birth_date DATE NOT NULL,
  prof_address VARCHAR(40) DEFAULT NULL,
  prof_phone VARCHAR(10) DEFAULT NULL,
  prof_email VARCHAR(40) NOT NULL unique,
  prof_password VARCHAR(255) NOT NULL,
  dep_id VARCHAR(7) NOT NULL,
  isAdmin BOOLEAN DEFAULT FALSE,
  prof_category ENUM('متعاقد بالساعة','متفرغ','ملاك') DEFAULT 'متعاقد بالساعة',
  reset_date datetime DEFAULT NULL,
  PRIMARY KEY (prof_file_nb)
);

CREATE TABLE department (
  dep_id VARCHAR(7) NOT NULL,
  dep_name VARCHAR(30) NOT NULL,
  chair_person_file_nb INT NOT NULL,
  PRIMARY KEY (dep_id)
);

-- 1. Create the major table (Parent of Course)
CREATE TABLE major (
  major_id VARCHAR(6) NOT NULL,
  major_name VARCHAR(60) NOT NULL,
  dep_id VARCHAR(7) NOT NULL,
  PRIMARY KEY (major_id)
);

-- 2. Create the course table with the 3-column Primary Key
CREATE TABLE course (
  course_code VARCHAR(30) NOT NULL,
  course_name VARCHAR(255) NOT NULL,
  course_credit_nb INT NOT NULL,
  course_hours_nb INT NOT NULL,
  course_lang VARCHAR(2) NOT NULL,
  course_semester_nb INT NOT NULL,
  course_level ENUM('L1','L2','L3','M1') NOT NULL,
  course_category ENUM('common', 'optional', 'mandatory') NOT NULL,
  major_id VARCHAR(6) NOT NULL,
  isActive BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (course_code, course_lang, major_id),
  CONSTRAINT fk_course_major FOREIGN KEY (major_id) REFERENCES major (major_id)
);

-- 3. Create the teaching table (Child of Course)
CREATE TABLE teaching (
  course_code VARCHAR(30) NOT NULL, -- Length matched to course table
  course_lang VARCHAR(2) NOT NULL,
  major_id VARCHAR(6) NOT NULL,    -- Added to match the Course Primary Key
  prof_file_nb INT NOT NULL,
  uni_year VARCHAR(15) NOT NULL,
  isActive BOOLEAN DEFAULT 1,
  PRIMARY KEY (course_code, course_lang, major_id, prof_file_nb),
  -- Foreign Key must now reference all three parts of the Course PK
  CONSTRAINT fk_teaching_course 
    FOREIGN KEY (course_code, course_lang, major_id) 
    REFERENCES course (course_code, course_lang, major_id) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_teaching_prof 
    FOREIGN KEY (prof_file_nb) REFERENCES professor (prof_file_nb) 
    ON DELETE CASCADE
);

-- 4. Create the correctors table (tracks exam corrector assignments per course/major)
CREATE TABLE correctors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  course_code VARCHAR(30) NOT NULL,
  prof_file_nb INT NOT NULL,
  second_corrector_file_nb INT DEFAULT NULL,
  third_corrector_file_nb INT DEFAULT NULL,
  session_nb VARCHAR(7) DEFAULT NULL,
  course_lang VARCHAR(2) NOT NULL,
  major_id VARCHAR(6) NOT NULL
);