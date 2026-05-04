--Inserting some data into table major
INSERT INTO majors (major_id, major_name, dep_id) 
VALUES('B', 'Biology', 'bio'),
      ('C', 'Chemistry', 'che'),
      ('BC', 'Biochemistry', 'che'),
      ('E', 'Electronics', 'pe'),
      ('I', 'Computer Science', 'css'),
      ('M', 'Mathematics', 'math'),
      ('P', 'Physics', 'pe'),
      ('S', 'Statistics', 'css');

--inserting some data into table department
INSERT INTO department (dep_id, dep_name,chair_person_file_nb) 
VALUES('bio','علوم الأحياء','100'),
      ('che', 'كيمياء', '102'),
      ('pe', 'الفيزياء والإلكترونيك', '103'),
      ('css', 'المعلوماتية والاحصاء', '104'),
      ('math', 'الرياضيات', '105');