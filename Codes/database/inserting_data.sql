--Inserting some data into table major
INSERT INTO majors (major_id, major_name, dep_id) 
VALUES('B', 'Biology', 'bio'),
      ('BC', 'Biochemistry', 'bioch'),
      ('C', 'Chemistry', 'che'),
      ('E', 'Electronics', 'pe'),
      ('I', 'Computer Science', 'css'),
      ('M', 'Mathematics', 'math'),
      ('P', 'Physics', 'pe'),
      ('S', 'Statistics', 'css');

--inserting some data into table department
INSERT INTO department (dep_id, dep_name,chair_person_file_nb) 
VALUES('bio','Biology','100'),
      ('bioch','Biochemistry', '101'),
      ('che', 'Chemistry', '102'),
      ('pe', 'Physics and Electronics', '103'),
      ('css', 'Computer Science and Statistics', '104'),
      ('math', 'Mathematics', '105');