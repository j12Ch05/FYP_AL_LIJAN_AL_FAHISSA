CREATE TABLE professor (
  prof_file_nb INT NOT NULL,
  prof_first_name VARCHAR(30) NOT NULL,
  prof_last_name VARCHAR(30) NOT NULL,
  prof_birth_date DATE NOT NULL,
  prof_address VARCHAR(40) DEFAULT NULL,
  prof_phone VARCHAR(10) DEFAULT NULL,
  prof_email VARCHAR(40) NOT NULL,
  prof_password VARCHAR(40) NOT NULL,
  dep_id ENUM('math','css','pe','bio','bioch','chem') NOT NULL,
  isAdmin BOOLEAN DEFAULT FALSE,
  prof_category ENUM('Tenured/لملاك','Full_time/متفرغ','Part_time/متعاقد بالساعة') NOT NULL,
  PRIMARY KEY (prof_file_nb)
);

CREATE TABLE department (
  dep_id ENUM('math','css','pe','bio','bioch','chem') NOT NULL,
  dep_name VARCHAR(30) NOT NULL,
  chair_person_file_nb INT NOT NULL,
  PRIMARY KEY (dep_id)
);

CREATE TABLE major (
  major_id VARCHAR(6) NOT NULL,
  major_name VARCHAR(15) NOT NULL,
  dep_id ENUM('math','css','pe','bio','bioch','chem') NOT NULL,
  PRIMARY KEY (major_id)
);

CREATE TABLE course (
  course_code VARCHAR(6) NOT NULL,
  course_name VARCHAR(40) NOT NULL,
  course_credit_nb INT NOT NULL,
  course_hours_nb INT NOT NULL,
  course_lang ENUM('E','F') NOT NULL,
  course_semester_nb INT NOT NULL,
  course_level ENUM('L1','L2','L3','M1') NOT NULL,
  course_category ENUM('common','optional','mandatory') NOT NULL,
  course_student_nb INT NOT NULL,
  major_id VARCHAR(6) NOT NULL,
  isActive BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (course_code, course_lang)
);

CREATE TABLE teaching (
  course_code VARCHAR(6) NOT NULL,
  course_lang ENUM('E','F') NOT NULL,
  prof_file_nb INT NOT NULL,
  uni_year VARCHAR(6) NOT NULL,
  PRIMARY KEY (course_code, course_lang, prof_file_nb),
  CONSTRAINT fk_teaching_course FOREIGN KEY (course_code, course_lang) REFERENCES course (course_code, course_lang) ON DELETE CASCADE,
  CONSTRAINT fk_teaching_prof FOREIGN KEY (prof_file_nb) REFERENCES professor (prof_file_nb) ON DELETE CASCADE
);

CREATE TABLE correctors (
  course_code VARCHAR(6) NOT NULL,
  prof_file_nb INT NOT NULL,
  second_corrector_file_nb INT NOT NULL,
  third_corrector_file_nb INT DEFAULT NULL,
  session_number ENUM('P1','F1','P2','F2','S2') NOT NULL,
  total_copies_nb INT UNSIGNED NOT NULL,
  start_date DATE DEFAULT NULL,
  end_date DATE DEFAULT NULL,
  course_lang ENUM('E','F') NOT NULL
);