#!/bin/bash

# All configuration goes to /etc/apache2/envvars (www user environment). We create KARRAMBA* vars there.
KARRAMBA_DB_USER='karramba'  
KARRAMBA_DB_PASS='secret'  
KARRAMBA_DB_HOST='127.0.0.1'  

KARRAMBA_LANG="en"	

# Your email for DB failure reports, etc.
KARRAMBA_NOTIFY="user@gmail.com"  

# I have a separate tool where a new student can add himself. You can provide
# an URL for "create a new student account" form. Nevermind otherwise.
KARRAMBA_NEW_STUDENT_FORM_URL=""	  

# I have a separate tool where a new student can add himself. You can provide
# a secret for students to use "create a new student account" form. Nevermind otherwise.
KARRAMBA_NEW_STUDENT_SECRET="secret"	  

# These are only needed if you choose to use a dblink to another DB containing teachers and students.
# Navigate to "The tricky part" below to learn more
EXTERNAL_DBNAME='x'
EXTERNAL_USER='x'
EXTERNAL_PASS='x'
EXTERNAL_HOST='127.0.0.1'

# End of configuration. Run this shell script to setup the project. Then restart apache so that www user rereads his environent.




# init #{{{
USER=`id -ru`
[ "X$USER" == "X0" ] && { echo "Don't run as root / sudo"; exit; }

[ "X$KARRAMBA_DB_PASS" == 'Xsecret' ] && { 
	echo "KARRAMBA_DB_PASS needs to be changed from the default='secret'."; 
	echo
	exit;
} 
#}}}
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
sudo cp $temp /etc/apache2/envvars
rm $temp

[ "X$1" == "Xclear" ] && { 
	echo "sudo -u postgres psql -c \"DROP DATABASE $KARRAMBA_DB_USER\"";
	echo "sudo -u postgres psql -c \"DROP USER $KARRAMBA_DB_USER\"";
	echo "enter or ctrl+c";
	read;
	sudo -u postgres psql -c "DROP DATABASE $KARRAMBA_DB_USER";
	sudo -u postgres psql -c "DROP USER $KARRAMBA_DB_USER";
}

sudo -u postgres psql -lqt | cut -d \| -f 1 | grep -qw 'karramba' && { 
	echo ""
	echo "karramba already exists in psql. You may wish to call";
	echo "DROP DATABASE $KARRAMBA_DB_USER; DROP USER $KARRAMBA_DB_USER" 
	echo "by running:"
	echo ""
	echo "	bash install.sh clear";
	echo ""
	exit
}

#}}}
# psql#{{{
sudo -u postgres psql << EOF
CREATE DATABASE karramba;
CREATE USER $KARRAMBA_DB_USER WITH PASSWORD '$KARRAMBA_DB_PASS';

\c karramba;

-- The tricky part. You need to somehow provide the following 3 views. 
-- One way is to just create these 3 TABLES, filling them with data, and creating VIEWS.	
-- Another way is to use the CREATE EXTENSION dblink method below to connect to some other students/teachers DB in your institution.
-- 	
-- 	VIEW students:
--
-- 	  id  |    first_name    |         last_name         |   index    | group_id |  password  
-- 	------+------------------+---------------------------+------------+----------+--------------------
-- 	 2104 | Maciej           | Sobiechowski              |      11036 |     1030 |  bf7e74a46c11e45ec
-- 	 2105 | Artur            | Sroka                     |      11037 |     1030 |  z2@49d0cc0e00d652
-- 	 ...  | .....			 | ....						 |      ..... |     .... |  .................
-- 
-- 	
-- 	VIEW groups:
--
-- 	  id  |     group_name      
-- 	------+---------------------
-- 	    1 | Erasmus
-- 	  	3 | ND-BW1
-- 	  ... | ...
--
-- 	
-- 	
-- 	VIEW teachers:
--
-- 	 id  | first_name  |        last_name        |        email        |       password       
-- 	-----+-------------+-------------------------+---------------------+----------------------
-- 	 614 | Marta       | Adamowska               | m@gmail.com 		   | bf7e74a46c11e45ec
-- 	 663 | Tomasz      | Wdowski 			     | z@gmail.com 		   | z2@49d0cc0e00d652
-- 	 ... | ........    | ..........              | ........ 		   | ..................
--
-- 	

-- CREATE EXTENSION dblink;
-- 
-- CREATE VIEW students AS SELECT id, imie AS first_name, nazwisko AS last_name, index, grupa AS group_id, index AS password
-- FROM dblink('dbname=$EXTERNAL_DBNAME  host=$EXTERNAL_HOST user=$EXTERNAL_USER password=$EXTERNAL_PASS', 'SELECT id, imie, nazwisko, index, grupa, index FROM studenci')  as foo (id integer, imie text, nazwisko text, index integer, grupa integer, password integer);
-- 
-- CREATE VIEW groups as SELECT id, grupa as group_name from dblink('dbname=$EXTERNAL_DBNAME  host=$EXTERNAL_HOST user=$EXTERNAL_USER password=$EXTERNAL_PASS', 'SELECT id, grupa FROM grupy')  as foo (id integer, grupa text);
-- 
-- CREATE VIEW teachers as SELECT id, imie as first_name, nazwisko as last_name, login as email, haslo as password
-- from dblink('dbname=$EXTERNAL_DBNAME  host=$EXTERNAL_HOST user=$EXTERNAL_USER password=$EXTERNAL_PASS', 'SELECT id, imie, nazwisko, login, haslo  FROM pracownicy WHERE dydaktyk is true')  as foo (id integer, imie text, nazwisko text, login text, haslo text);

-- End of the tricky part


CREATE TABLE quizes (
    id SERIAL PRIMARY KEY,
    quiz_name text,
	timeout integer,
    how_many integer,
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

REVOKE ALL PRIVILEGES ON DATABASE karramba from $KARRAMBA_DB_USER;
REVOKE ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public from $KARRAMBA_DB_USER;

GRANT ALL PRIVILEGES ON DATABASE karramba to karramba;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO karramba;
GRANT ALL PRIVILEGES  ON ALL SEQUENCES IN SCHEMA public TO karramba;

GRANT ALL PRIVILEGES ON DATABASE karramba TO $KARRAMBA_DB_USER;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO $KARRAMBA_DB_USER;
GRANT ALL PRIVILEGES  ON ALL SEQUENCES IN SCHEMA public TO $KARRAMBA_DB_USER;

EOF
echo;
#}}}

sudo service apache2 restart
sudo chown -R www-data ../img/
sudo chmod -R 775 ../img/
