alter table professor
add constraint fk_prof_to_dep
foreign key (dep_id) references department(dep_id);

alter table department
add constraint fk_chair_person
foreign key(chair_person_file_nb) references professor(prof_file_nb);

alter table course
add constraint fk_course_major
foreign key(major_id) references major(major_id);

alter table correctors
add constraint fk_correctors_teaching
foreign key (course_code,course_lang,prof_file_nb) references teaching(course_code,course_lang,prof_file_nb);

alter table major
add constraint fk_major_dep
foreign key (dep_id) references department(dep_id);