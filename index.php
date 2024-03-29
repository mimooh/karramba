<?PHP
session_name(getenv("KARRAMBA_SESSION_NAME"));
require_once("libKarramba.php");

function login_form(){/*{{{*/
	// KARRAMBA_NEW_STUDENT_FORM_URL comes from /etc/apache2/envvars
	extract($_SESSION);

	if(empty(getenv("KARRAMBA_STUDENT_INT_PASS"))) { 
		$type='password';
	} else {
		$type='number';
	}

	echo "
	<form method=POST>
	<br>
	<center>
	<div style='font-size: 14px'>

	$i18n_last_name<br>
	<input style='font-size:14px' type=text name=student_login id='inputStudentLogin' size=32 placeholder='$i18n_first_two_letters ...'> 
	<br><br>
	$i18n_password <br>    

	<input style='font-size:14px' size=32 type=$type name='password' autocomplete='off'> 
	<input type=hidden id='inputHiddenStudentId' name=studentIdFromLogin> <br><br>
	<input style='font-size:14px' type=submit name=logMeIn value=$i18n_submit><br>
	<br><br>
	<img style='margin:0px; background:none' src=img/karramba.png><br>
	</form>
	<a class=blink style='font-size:11px' href=".getenv("KARRAMBA_NEW_STUDENT_FORM_URL").">New student</a>
	<a class=rlink style='font-size:11px' href=admin.php>Admin</a>
	</div>
	</center>
	"; 

}/*}}}*/
function get_karramba_student_id() {# {{{
	// First we try jquery-ui:
	if(!empty($_POST['studentIdFromLogin'])) { 
		return $_POST['studentIdFromLogin'];
	}
	// Google Chrome likes to put their autofill on top of jquery.
	// Then we parse id=857 out of the input field
	// [student_login] => Testowy Test [0.GR.TESTOWA] |857
	if(!empty($_POST['student_login'])) { 
		$arr=explode("|", $_POST['student_login']);
		if(!empty($arr[1])) { 
			return $arr[1];
		}
	}
	return NULL;
}
/*}}}*/
function logged_outside() {/*{{{*/
	// We are already logged elsewhere
	$row=$_SESSION['krr']->query("SELECT id,group_id, last_name, first_name, index FROM students WHERE index=$1", array($_SESSION['student_id']));
	if(empty($row)) { die("<br><br>logged outside, but not in karramba database"); }
	$_SESSION+=$row[0];
	$_SESSION['student']=$row[0]['last_name']." ".$row[0]['first_name'];
	$_SESSION['karramba_student_id']=$row[0]['id'];
	$_SESSION['home_url']=getenv("KARRAMBA_STUDENT_HOME_URL");
	$_SESSION['group_name']=$_SESSION['krr']->query("SELECT group_name FROM groups WHERE id=$1", array($row[0]['group_id']))[0]['group_name'];
}
/*}}}*/
function do_login(){/*{{{*/
	if(isset($_SESSION['student_id'])) { 
		logged_outside(); 
		return; 
	}else{
		header("location:/");  //redirect to login page
		#header("location:/student/?wroc_do=karramba");  //redirect to login page
	}
	if(!isset($_POST['logMeIn'])) { return; }
	extract($_SESSION);

	$karrarmba_student_id=get_karramba_student_id();
	if(empty($karrarmba_student_id))        { $krr->msg($i18n_bad_login); return; }
	if(empty($_POST['password'])) { $krr->msg($i18n_bad_login); return; }

	$row=$krr->query("SELECT id as karrarmba_student_id, group_id, last_name, first_name, password, index FROM students WHERE id=$1", array($karrarmba_student_id));

	if(isset($row) && ($row[0]['password']==$_POST['password'] || $row[0]['index']==$_POST['password'])) {
		$_SESSION+=$row[0];
		$_SESSION['student']=$row[0]['last_name']." ".$row[0]['first_name'];
		$_SESSION['karramba_student_id']=$row[0]['karramba_student_id'];
		$_SESSION['home_url']=$_SERVER['SCRIPT_NAME'];
		$_SESSION['group_name']=$krr->query("SELECT group_name FROM groups WHERE id=$1", array($row[0]['group_id']))[0]['group_name'];
	} else {
		$krr->msg($i18n_bad_login);
	}

	login_form(); 
}/*}}}*/
function do_logout(){/*{{{*/
	$url=$_SESSION['home_url'];
	$_SESSION=array();
	header("Location: $url");
}/*}}}*/
function check_cheetaz(){/*{{{*/
	//This function checks if someone has submited test on students behalf from another phone (outside the class room) - 
	//We are checking the HTTP_USER_AGENT variable
	extract($_SESSION);
	$db_agent=$_SESSION['krr']->query("SELECT student_started, agent from randomized_quizes where student_id =$1 and agent is not null and student_started > NOW() - INTERVAL '30 MINUTES' order by id desc limit 1", array($_SESSION['karramba_student_id']) ); // getting last HTTP_USER_AGENT of user from database within 30 minutest period
	if(!empty($db_agent)){ //if is empty -> student has not submited any tests within the INTERVAL
		if($_SERVER['HTTP_USER_AGENT']!=$db_agent[0]['agent']  ){ //current HTTP_USER_AGENT varies from that stored in DB
#		dd(array($db_agent, $_SERVER['HTTP_USER_AGENT']) $student_results);
			$_SESSION['krr']->fatal("Err 007"); //some random error 
		}
	}
}
/*}}}*/
function choose_quiz() {/*{{{*/
	// After student is logged he needs to see the quizes for him
	// quiz_deactivation IS NULL means the quiz has expired
	# echo "SELECT * from students order by group_id" | psql karramba
	# echo "SELECT * from groups where id=1" | psql karramba
	echo "<br>";
	$hanging=$_SESSION['krr']->query("SELECT randomized_id,quiz_instance_id,group_name,quiz_name,student_deadline FROM r 
	WHERE student_id=$1 AND quiz_deactivation IS NOT NULL AND student_finished IS NULL", array($_SESSION['karramba_student_id']));
	if(!empty($hanging[0])) {
		extract($hanging[0]);
		$_SESSION['krr']->cannot($_SESSION['i18n_need_to_complete']);
		echo "<br><FORM><input type=hidden name=randomized_id value='$randomized_id'><input type=hidden name=quiz_instance_id value='$quiz_instance_id'>PIN: <input type=number name=pin style='width:60px'> <input type=submit value='$group_name / $quiz_name'></FORM>";
	}  else {
		echo "<FORM><input type=hidden name=quiz_instance_id value=1>PIN: <input type=number name=pin value=1111 style='width:60px'> <input type=submit value=ExampleQuiz></FORM>";
		foreach($_SESSION['krr']->query("SELECT id FROM quizes_instances WHERE group_id=$1 AND quiz_deactivation IS NOT NULL AND quiz_deactivation > now()", array($_SESSION['group_id'])) as $r) { 
			$group_name=$_SESSION['krr']->query("SELECT t.group_name FROM groups t, quizes_instances q WHERE t.id=q.group_id AND q.id=$1", array($r['id']))[0]['group_name'];
			$qq=$_SESSION['krr']->query("SELECT t.quiz_name FROM quizes t, quizes_instances q WHERE t.id=q.quiz_id AND q.id=$1", array($r['id'])); 
			if(isset($qq[0]['quiz_name'])) { // quiz started but removed by the owner (very rare)
				$quiz_name=$qq[0]['quiz_name'];
				echo "<br><FORM><input type=hidden name=quiz_instance_id value=".$r['id'].">PIN: <input type=number name=pin style='width:60px'> <input type=submit value='$group_name / $quiz_name'></FORM>";
			}
		}
	}
}
/*}}}*/
function display_quiz() {/*{{{*/
	is_student_allowed();
	$quiz=which_quiz_to_serve();
	$out=[];
	foreach(array_keys($quiz['questions_presented']) as $k) {
		$out[]="<page class='invisible' id=p".($k+1)."><question>".$quiz['questions_presented'][$k]."</question>";
		foreach($quiz['answers_presented'][$k] as $a) {
			$out[]="<answer class='answer_no'><input type=hidden name=student_answers[] value=0>$a</answer>";
		}
		$out[]="</page>";
	}
	$json_questions_presented=json_encode($quiz['questions_presented']);
	$json_answers_presented=json_encode($quiz['answers_presented']);
	
	echo "
	<FORM method=POST id=karramba method=post action=answers.php> 
		<input type=hidden name=json_questions_presented value='$json_questions_presented'>
		<input type=hidden name=json_answers_presented value='$json_answers_presented'>
		<input type=hidden name=quiz_instance_id value=".$_GET['quiz_instance_id'].">
		<input type=hidden name=randomized_id value=".$quiz['randomized_id'].">
		<timeout>".$quiz['timeout']."</timeout>
		<navigation> <prev>&lt; </prev> <next> &gt; </next>
			<top-middle>  <a href=".$_SESSION['home_url']."><img id=home src=css/home.svg></a> <question-number> <qnumber>1</qnumber>/<qtotal>".count($quiz['questions_presented'])."</qtotal></question-number> <clock>999</clock> </top-middle>
		</navigation>
		".join($out)."

		<center><br><input class=invisible type=submit value='".$_SESSION['i18n_submit_complete_quiz']."' id=karrambaSubmit></center>
	</FORM>
	<script> startQuiz(); </script>
	";
}
/*}}}*/
function is_student_allowed() {/*{{{*/
	// Test1: correct PIN?
	$krr=$_SESSION['krr'];
	if($_GET['quiz_instance_id'] > 1) {  # not ExampleQuiz
		#$r=$krr->querydd("SELECT pin FROM quizes_instances WHERE id=$1", array($_GET['quiz_instance_id']));
		$r=$krr->query("SELECT pin FROM quizes_instances WHERE id=$1", array($_GET['quiz_instance_id']));
		if($r[0]['pin']!=$_REQUEST['pin']) {
			$krr->fatal($_SESSION['i18n_wrong_pin']);
		}
	} else {
		if($_REQUEST['pin']!='1111') {
			$krr->fatal($_SESSION['i18n_wrong_pin']);
		}
	}

	// Test2: quiz submitted?
	$r=$krr->query("SELECT randomized_id FROM r WHERE student_id=$1 AND quiz_instance_id=$2 AND student_finished IS NOT NULL", array($_SESSION['karramba_student_id'], $_GET['quiz_instance_id'])) ;
	if(!empty($r)) { 
		$krr->fatal($_SESSION['i18n_quiz_submited_before']);
	}
	
}
/*}}}*/
function which_quiz_to_serve() {/*{{{*/
	// Either the interrupted quiz or the new quiz 
	# psql karramba -c "SELECT quiz_name,student,group_name,student_finished,student_deadline, quiz_deactivation,student_answers_vector  FROM r"
	$hanging=$_SESSION['krr']->query("SELECT randomized_id,student_started FROM r WHERE student_id=$1 AND student_finished IS NULL", array($_SESSION['karramba_student_id']));

	if(!empty($hanging[0])) {
		return $_SESSION['krr']->db_serve_interrupted_quiz($hanging[0]['randomized_id'], 0);
	} else {
		return make_randomized_quiz();
	}
	
}
/*}}}*/
function make_randomized_quiz(){/*{{{*/
	// howto/*{{{*/
	// This function creates a single random quiz instance. 
	// ExampleQuiz (id=1) is not to be reordered: instead questions are getting more and more difficult. Also ExampleQuiz is crafted to show the student how bad it is to guess in this system.
	// The quiz is composed of N questions, randomly picked from say 130 questions defined in database.
	// There are 3 proposed answers for each question that are presented to the student.
	// The order of these proposed answers is shuffeled, therefore the database must know what is the real correct vector of answers.

	// The example quiz for N=4:
	// $qv=				array( 124 , 1   , 15 , 8   ) Vector of randomized indices of questions from db. Here student receives a quiz of 4 questions: 124, 1, 15, 8.

	// Atoms for building $ov and $cv. Atoms are single questions and atoms later form quiz.
	// $correct =		array(0,1,0);				  Atom vector of correct anwers from db for question 124. It is the truth for what is right for the question.
	// $answer_indices= this array will be shuffled from default=array(0,1,2) to some other random order
	// Say we shuffled the indices into 1,0,2, then:
	// $acv =			array(1,0,0);				  Atom vector for building $cv for question 124. Serves to shuffle $correct via indices. Will end with join($acv) into string. 
	// $aov =			array(1,0,2);				  Atom vector for building $ov for question 124. Will end with join($aov) into string.
	// $acv is a pair with $aov now. 

	// Collecting $acv and $aov into a single quiz
	// $cv=				array( '100' , '010' , '111', '100' ) Vector of correct answers after all shuffles. Here for question id 124 the correct answer is a). Answers b) and c) are wrong.
	// $ov=				array( '021' , '201' , '120', '012' ) Vector of proposed answers after all shuffles. The student will see: Question_124: a)anwer_0  b)answer_2  c)answer_1
	// $sqa=			array('alice has a cat' , 'alice has a dog' , 'alice has a parrot'),

	// $questions=		array( 
	//						"What does alice have?",
	//						"is x>2?"
	//					);
	// $answers=		array( 
	//						array('alice has a cat' , 'alice has a dog' , 'alice has a parrot') ,
	//						array('x=2'             , 'x=3'             , 'x=0')                ,
	//					);
/*}}}*/
	# psql karramba -c "SELECT id FROM questions WHERE id IN (930,931,932) ORDER BY RANDOM()"

	$krr=$_SESSION['krr'];
	$qv=[];
	$cv=[];
	$ov=[];
	$answers=[];
	if($_GET['quiz_instance_id']==1) {
		$r=$krr->query("SELECT * FROM questions WHERE quiz_id=1 AND deleted = FALSE ORDER BY id");
	} else {
		$questions_ids_in_sections=make_randomized_quiz_sections();
		$r=$krr->query("SELECT * FROM questions WHERE id IN ($questions_ids_in_sections) ORDER BY RANDOM()");
	}
	foreach($r as $q){
		$qv[]=$q['id']; 
		$questions[]=$q['question'];
		$correct=str_split($q['correct_vector']);
		$acv=[];
		$aov=[];
		$sqa=[];
		$answer_indices=array(0,1,2);
		shuffle($answer_indices);
		foreach($answer_indices as $x){
			$acv[]=$correct[$x]; 
			$aov[]=$x; 
			$sqa[]=$q["answer$x"];
		}
		$answers[]=$sqa;
		$cv[]=join($acv); 
		$ov[]=join($aov); 
	}
	return db_insert_randomized_quiz($questions,$answers,$cv,$ov,$qv);

} /*}}}*/
function make_randomized_quiz_sections() {/*{{{*/
	// This is where we draw the questions and we take sections under account. 
	// We handle the potential problems here:
	// * empty $sections > $sections=1
	// * total number of questions / sections is not an integer
	// * how many / sections is not an integer

	$krr=$_SESSION['krr'];
	$rr=$krr->query("SELECT quiz_id FROM quizes_instances WHERE id=$1", array($_GET['quiz_instance_id']));
	$qid=$rr[0]['quiz_id'];
	$rr=$krr->query("SELECT quizes.id as qid, quizes.sections, quizes.how_many, questions.id as question_id FROM questions LEFT JOIN quizes on questions.quiz_id=quizes.id WHERE questions.deleted=FALSE AND quizes.id=$1 ORDER BY questions.id", array($qid));
	if(empty($rr)) { $_SESSION['krr']->fatal("Quiz has no questions"); }
	$sections=$rr[0]['sections'];
	$how_many=$rr[0]['how_many'];
	if(empty($sections)) { $sections=1; }
	$q_total=count($rr);
	$q_per_section=round($how_many/$sections);
	$chunked=array_chunk($rr, round($q_total/$sections));
	unset($chunked[$sections]);
	$collect=[];
	foreach($chunked as $k=>$v) { 
		$random_keys=array_rand($v,$q_per_section);
		foreach($random_keys as $rn) { 
			$collect[]=$v[$rn]['question_id'];
		}
	}
	return implode(",", $collect);
}
/*}}}*/
function register_used_questions($quiz_id,$questions_ids) { #{{{
	// After the questions have been picked up we also register that these questions
	// were ever used. This is the most reasonable way for update_quiz() to prevent
	// the growing of the questions list on each save. That's because we cannot simply
	// remove the old versions of questions and loose the history of student past quizes.
	    
	// question_id UNIQUE CONSTRAINT, we don't need duplicates, hence "ON CONFLICT DO NOTHING"

	foreach($questions_ids as $qq) { 
		$_SESSION['krr']->query("INSERT INTO used_questions(quiz_id,question_id) VALUES($1,$2) ON CONFLICT DO NOTHING", array($quiz_id, $qq));
	}
}
/*}}}*/
function db_insert_randomized_quiz($questions,$answers,$cv,$ov,$qv) { /*{{{*/
	
	$timeout=$_SESSION['krr']->query("SELECT q.timeout FROM quizes q, quizes_instances i WHERE q.id=i.quiz_id AND i.id=$1", array($_GET['quiz_instance_id']))[0]['timeout'];
	$deadline=date("Y-m-d H:i:s", strtotime("+ $timeout minutes"));

	$r=$_SESSION['krr']->query("SELECT quiz_id, teacher_id FROM quizes_instances WHERE id=$1", array($_GET['quiz_instance_id']))[0];
	$query_params=array(join(",", $qv), join(",", $ov), join(",", $cv), $_SESSION['karramba_student_id'], $_GET['quiz_instance_id'], $r['quiz_id'], $r['teacher_id'], $deadline);

	$id=$_SESSION['krr']->query("INSERT INTO randomized_quizes(questions_vector, order_vector, correct_answers_vector, student_id, quiz_instance_id, quiz_id, teacher_id, student_deadline) VALUES ($1, $2, $3, $4, $5, $6, $7, $8) RETURNING id", $query_params)[0]['id'];
	register_used_questions($r['quiz_id'], $qv);

	$timeout=strtotime($deadline)-time();
	return array('serve'=>'new_quiz', 'questions_presented'=>$questions,'answers_presented'=>$answers,'randomized_id'=>$id, 'timeout'=>$timeout);
}/*}}}*/

function menu(){/*{{{*/
	extract($_SESSION);
	if(isset($_SESSION['KARRAMBA_EXTRA_MENUS']['student_menu'])) { $extra_menus=$_SESSION['KARRAMBA_EXTRA_MENUS']['student_menu']; }  else { $extra_menus=''; }
	echo "
	<student_menu>
	$extra_menus
	<a href=?q class=rlink>".$_SESSION['i18n_logout']." ".$_SESSION['last_name']." / ".$_SESSION['group_name']."</a>
	</student_menu>";
}/*}}}*/
function main() {/*{{{*/
	$_SESSION['krr']->htmlHead();
	if(isset($_GET['q']))   { do_logout(); }

	if(!isset($_SESSION['karramba_student_id'])) {
		do_login(); 
	} 

	if(isset($_SESSION['karramba_student_id'])) {
		if(empty($_GET))                        { menu(); }
		if(isset($_GET['quiz_instance_id']))    { display_quiz(); }
		else                                    { choose_quiz(); }

	}
}
/*}}}*/

main();
?>
