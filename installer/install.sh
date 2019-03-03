#!/bin/bash

# All configuration goes to /etc/apache2/envvars (www user environment). We create KARRAMBA* vars there.
# If you are on a hosting server with users that cannot be trust and/or if you cannot write to /etc/apache2/envvars
# then you need to find your way to propagate these variables for www-data user. Or just make them constants in
# karramba code.

# ====================== START OF CONFIG ============================

	KARRAMBA_DB_USER='karramba'  
	KARRAMBA_DB_PASS='secret'   # You really need to change it here!
	KARRAMBA_DB_HOST='127.0.0.1'  

	KARRAMBA_LANG="en"	

	# Your email for DB failure reports, etc.
	KARRAMBA_NOTIFY="user@gmail.com"  
	
	# I have a separate tool where a new student can add himself. You can provide
	# an URL for "create a new student account" form. Nevermind otherwise.
	KARRAMBA_NEW_STUDENT_FORM_URL=""	  
	
	# I have a separate tool where a new student can add himself. You can provide
	# a secret for students to use "create a new student account" form. Nevermind otherwise.
	KARRAMBA_NEW_STUDENT_SECRET=""	  
	
	# If you need to share variables with another PHP system, then perhaps you want to match the session_name()
	# Otherwise you don't need it.
	KARRAMBA_ADM_SESSION_NAME='karramba_admin'
	
	# If students passwords are composed of digits only, you may set this to one.
	# In android we can set the context keyboard to numbers then.
	KARRAMBA_STUDENT_INT_PASS=0
	
	# Install spreadsheet support for teachers to download students results
	SPREADSHEET_SUPPORT=0
	
	# Location of karramba on the disk
	WWW_DIR="/var/www/ssl/karramba"

# ====================== END OF CONFIG ============================
	
# Run this shell script to setup the project. 


# init #{{{
USER=`id -ru`
[ "X$USER" == "X0" ] && { echo "Don't run as root / sudo"; exit; }
cd $WWW_DIR

# www-data user needs DB vars. They are kept in www-data environment: /etc/apache2/envvars #{{{
temp=`mktemp`
sudo cat /etc/apache2/envvars | grep -v KARRAMBA_ > $temp
echo "export KARRAMBA_DB_USER='$KARRAMBA_DB_USER'" >> $temp
echo "export KARRAMBA_DB_PASS='$KARRAMBA_DB_PASS'" >> $temp
echo "export KARRAMBA_DB_HOST='$KARRAMBA_DB_HOST'" >> $temp
echo "export KARRAMBA_LANG='$KARRAMBA_LANG'" >> $temp
echo "export KARRAMBA_NOTIFY='$KARRAMBA_NOTIFY'" >> $temp
echo "export KARRAMBA_NEW_STUDENT_FORM_URL='$KARRAMBA_NEW_STUDENT_FORM_URL'" >> $temp
echo "export KARRAMBA_NEW_STUDENT_SECRET='$KARRAMBA_NEW_STUDENT_SECRET'" >> $temp
echo "export KARRAMBA_ADM_SESSION_NAME='$KARRAMBA_ADM_SESSION_NAME'" >> $temp
echo "export KARRAMBA_STUDENT_INT_PASS='$KARRAMBA_STUDENT_INT_PASS'" >> $temp
sudo cp $temp /etc/apache2/envvars
rm $temp

sudo -u postgres psql -lqt | cut -d \| -f 1 | grep -qw 'karramba' && { 
	echo 
	echo "karramba already exists in psql. You may wish to call";
	echo
	echo 'sudo -u postgres psql -c "DROP DATABASE karramba"; sudo -u postgres psql -c "DROP USER karramba"' 
	exit
}

#}}}
# psql#{{{
sudo -u postgres psql << EOF
CREATE DATABASE karramba WITH ENCODING='UTF8';
CREATE USER $KARRAMBA_DB_USER WITH PASSWORD '$KARRAMBA_DB_PASS';

\c karramba;

CREATE TABLE teachers (id SERIAL PRIMARY KEY, first_name text, last_name text, email text, password text);
CREATE TABLE students (id SERIAL PRIMARY KEY, first_name text, last_name text, index int, group_id int, password text);
CREATE TABLE groups (id SERIAL PRIMARY KEY, group_name text);

INSERT INTO teachers (first_name , last_name , email , password) VALUES ('Jaimie' , 'Lannister' , 'a@com' , '1');
INSERT INTO teachers (first_name , last_name , email , password) VALUES ('Tyrion' , 'Lannister' , 'b@com' , '1');
INSERT INTO students (first_name , last_name, index , group_id , password) VALUES ('Jon' , 'Snow'  ,  9991 , 1 , '1');
INSERT INTO students (first_name , last_name, index , group_id , password) VALUES ('Ned' , 'Stark' ,  9992 , 1 , '1');
INSERT INTO groups (id, group_name) VALUES (1, 'North');

-- The above are fake teachers, students and groups. In production you could use CREATE EXTENSION dblink:
-- CREATE EXTENSION dblink;
-- CREATE VIEW students AS SELECT id, imie AS first_name, nazwisko AS last_name, index, grupa AS group_id, index AS password
-- FROM dblink('dbname=EXTERNAL_DBNAME  host=EXTERNAL_HOST user=EXTERNAL_USER password=EXTERNAL_PASS', 'SELECT id, imie, nazwisko, index, grupa, index FROM studenci')  as foo (id integer, imie text, nazwisko text, index integer, grupa integer, password integer);
-- CREATE VIEW groups as SELECT id, grupa as group_name from dblink('dbname=EXTERNAL_DBNAME  host=EXTERNAL_HOST user=EXTERNAL_USER password=EXTERNAL_PASS', 'SELECT id, grupa FROM grupy')  as foo (id integer, grupa text);
-- CREATE VIEW teachers as SELECT id, imie as first_name, nazwisko as last_name, login as email, haslo as password
-- from dblink('dbname=EXTERNAL_DBNAME  host=EXTERNAL_HOST user=EXTERNAL_USER password=EXTERNAL_PASS', 'SELECT id, imie, nazwisko, login, haslo  FROM pracownicy WHERE dydaktyk is true')  as foo (id integer, imie text, nazwisko text, login text, haslo text);


CREATE TABLE quizes (
    id SERIAL PRIMARY KEY,
    quiz_name text,
	timeout integer,
    how_many integer,
    sections integer,
	grades_thresholds text
);

CREATE TABLE quizes_owners (
    id SERIAL PRIMARY KEY,
    quiz_id integer,
    teacher_id integer
);

CREATE TABLE quizes_instances (
    id SERIAL PRIMARY KEY,
    quiz_id integer,
	teacher_id integer,
	group_id integer,
	pin text, 
    quiz_activation timestamp without time zone DEFAULT now(),
	quiz_deactivation timestamp without time zone,

	-- After the quiz is finshed we will display some random animation 
    final_anim_color0 text,
    final_anim_color1 text,
	final_anim_time integer,
	final_anim_left0 integer,
	final_anim_left1 integer,
	final_anim_left2 integer,
	final_anim_top0 integer,
	final_anim_top1 integer,
	final_anim_top2 integer
);


CREATE TABLE randomized_quizes (
    id serial primary key,
    quiz_id integer,
	quiz_instance_id integer,
    student_id integer,
    student_started timestamp without time zone DEFAULT now(),
    student_finished timestamp without time zone,
    student_deadline timestamp without time zone,
	teacher_id integer,
    questions_vector text,
    order_vector text,
    correct_answers_vector text,
    student_answers_vector text,
	points numeric(4,1),
	grade numeric(2,1)
);

CREATE TABLE questions (
    id serial primary key,
    quiz_id integer references quizes(id) ON DELETE CASCADE,
    question text,
    answer0 text,
    answer1 text,
    answer2 text,
    correct_vector text,
	deleted boolean default false
);

CREATE VIEW v_results as SELECT 
	r.id, 
	q.quiz_name, 
	t.last_name, 
	s.last_name as students_last_name, 
	s.first_name, g.group_name, 
	r.student_started,r.points, 
	r.grade 

	from randomized_quizes r 
	join teachers t on (t.id=r.teacher_id) 
	join quizes q on (q.id=r.quiz_id) 
	join students s on (r.student_id=s.id) 
	join groups g on (s.group_id=g.id);


CREATE OR REPLACE VIEW r AS 

	SELECT 

	randomized_quizes.id as randomized_id,
	teachers.last_name || ' ' || teachers.first_name as teacher,
	quizes.quiz_name,
	students.last_name || ' ' || students.first_name as student,
	groups.group_name,
	randomized_quizes.student_started,
	randomized_quizes.student_finished,
    randomized_quizes.student_deadline,
	quizes_instances.quiz_deactivation,
	quizes_instances.pin,

	randomized_quizes.student_answers_vector,
	randomized_quizes.correct_answers_vector,
    randomized_quizes.questions_vector,
    randomized_quizes.order_vector,
	quizes_instances.id as quiz_instance_id,
	quizes.id as quiz_id,
	teachers.id as teacher_id,
	students.id as student_id,
	randomized_quizes.points,
	randomized_quizes.grade

	FROM 

	quizes, randomized_quizes, students, groups, quizes_instances, teachers

	WHERE 

	randomized_quizes.student_id = students.id AND
	students.group_id = groups.id AND
	randomized_quizes.quiz_instance_id= quizes_instances.id AND
	randomized_quizes.quiz_id = quizes.id AND
	randomized_quizes.teacher_id = teachers.id
;

CREATE TABLE used_questions(id SERIAL PRIMARY KEY, quiz_id int, question_id int);
ALTER TABLE used_questions ADD CONSTRAINT u_q FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE RESTRICT; 
ALTER TABLE used_questions ADD UNIQUE (quiz_id, question_id);

-- Karramba depends on this Karramba Example test; do not remove!
INSERT INTO quizes(quiz_name , how_many , timeout) VALUES('ExampleQuiz' , 10 , 10);
INSERT INTO quizes_owners(quiz_id, teacher_id) VALUES(1, 0);
INSERT INTO quizes_instances(quiz_id) VALUES(1); 
INSERT INTO questions(quiz_id , question , answer0 , answer1 , answer2 , correct_vector) VALUES(1 , '1+1'                                               , '=2'          , '=3'          , '=3-1'            , '101');
INSERT INTO questions(quiz_id , question , answer0 , answer1 , answer2 , correct_vector) VALUES(1 , 'Who is he?<br><img src=img/ExampleQuiz/1.jpg>'     , 'Einstein'    , 'Newton'      , 'Archimedes'      , '100');
INSERT INTO questions(quiz_id , question , answer0 , answer1 , answer2 , correct_vector) VALUES(1 , 'Choose related<br><img src=img/ExampleQuiz/2.jpg>' , 'Star Trek'   , 'Yoda'        , 'Star Wars'       , '011');
INSERT INTO questions(quiz_id , question , answer0 , answer1 , answer2 , correct_vector) VALUES(1 , 'pi'                                                , '3.14'        , '3.141'       , '3.1415'          , '111');
INSERT INTO questions(quiz_id , question , answer0 , answer1 , answer2 , correct_vector) VALUES(1 , 'Choose related<br><img src=img/ExampleQuiz/4.jpg>' , 'Queen'       , 'Beatles'     , 'Freddie'         , '101');
INSERT INTO questions(quiz_id , question , answer0 , answer1 , answer2 , correct_vector) VALUES(1 , 'e'                                                 , '2.71'        , '2.714'       , '2.7142'          , '100');
INSERT INTO questions(quiz_id , question , answer0 , answer1 , answer2 , correct_vector) VALUES(1 , 'Choose related<br><img src=img/ExampleQuiz/5.jpg>' , 'Bowie'       , 'Stardust'    , 'Ziggy'           , '111');
INSERT INTO questions(quiz_id , question , answer0 , answer1 , answer2 , correct_vector) VALUES(1 , 'Who is he?<br><img src=img/ExampleQuiz/6.jpg>'     , 'Goethe'      , 'Gauss'       , 'Leibniz'         , '010');
INSERT INTO questions(quiz_id , question , answer0 , answer1 , answer2 , correct_vector) VALUES(1 , 'Who is he?<br><img src=img/ExampleQuiz/9.jpg>'     , 'Ned Stark'   , 'John Snow'   , 'Henry Whitehead' , '010');
INSERT INTO questions(quiz_id , question , answer0 , answer1 , answer2 , correct_vector) VALUES(1 , 'Choose related<br><img src=img/ExampleQuiz/8.jpg>' , 'Mathematics' , 'Nobel Prize' , 'Biology'         , '010');

COMMENT ON table randomized_quizes is 'Set of all questions, corect answers for given student';
COMMENT ON column randomized_quizes.questions_vector is 'vector of questions_id sep. with coma eg. 132,157,158';
COMMENT ON column randomized_quizes.order_vector is 'shuffled order of answers eg 021,012,201 ';
COMMENT ON column randomized_quizes.correct_answers_vector is 'vector of correct answers for shuffled questions 101,001,111';

ALTER USER $KARRAMBA_DB_USER WITH PASSWORD '$KARRAMBA_DB_PASS';
ALTER DATABASE karramba OWNER TO $KARRAMBA_DB_USER;

EOF

psql -qAt karramba -c "SELECT tablename FROM pg_tables WHERE schemaname = 'public';" | while read i; do
	psql karramba -c "ALTER TABLE $i OWNER TO $KARRAMBA_DB_USER;" 
done

psql -qAt karramba -c "SELECT sequence_name FROM information_schema.sequences WHERE sequence_schema= 'public';" | while read i; do
	psql karramba -c "ALTER TABLE $i OWNER TO $KARRAMBA_DB_USER;" 
done

psql -qAt karramba -c "SELECT table_name FROM information_schema.views WHERE table_schema= 'public';" | while read i; do
	psql karramba -c "ALTER TABLE $i OWNER TO $KARRAMBA_DB_USER;" 
done

#}}}
# final#{{{
echo;

[ "X$SPREADSHEET_SUPPORT" == "X1" ] && { 
	echo "Installing xls producer";
	sudo apt-get install composer php-xml php-gd php-mbstring php-zip;
	composer require phpoffice/phpspreadsheet;
}

echo "Restarting apache..."
sudo service apache2 restart

sudo chgrp -R www-data "img/"
sudo chmod -R g+s "img/"
sudo chmod -R 775 "img/"

echo "Default student: Snow Jon, password: 1"
echo "Default teacher: a@com, password: 1"
#}}}
