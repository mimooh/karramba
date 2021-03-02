<?php
session_name('student');
require_once("libKarramba.php");
function should_we_accept_quiz_submit() {/*{{{*/
	// We check how much time student took on our clock. We give them +1 minute extra time (network problems etc.)
	extract($_SESSION);
	$r=$krr->query("SELECT student_deadline - now() AS period FROM r WHERE randomized_id=$1", array($_POST['randomized_id']));
	if(empty($r)) { 
		return; # karrambaExample
	}
	$minutes_left=explode(":",$r[0]['period'])[1];
	
	if($minutes_left < -1) {
		$krr->query("UPDATE randomized_quizes SET student_finished=now() WHERE id=$1", array($_POST['randomized_id']));
		$krr->fatal("$i18n_late_quiz_submission");
	}
}
/*}}}*/
function do_we_have_anything_to_display() {/*{{{*/
	// Handle page refresh:
	if(empty($_SESSION['krr']->query("SELECT * FROM randomized_quizes WHERE id=$1 ", array($_POST['randomized_id'])))) { 
		echo "<a href=".$_SESSION['home_url']."><img src=css/home.svg></a></div>";
		exit();
	}
}
/*}}}*/
function animation_on_quiz_complete($points) {/*{{{*/
	// This function prevents against unfair quiz participants scenario:
	// Student is in the classroom but not logged at all. Instead, another student
	// is logged on his behalf from outside of the classroom. Therefore, we have an option
	// to check the final animations on participants devices when they are leaving the classroom.
	// Well, they have their options to hack against our solution, but then it will be time for
	// our move, depending on what they have invented.

	# psql karramba -c "SELECT * FROM quizes_instances where final_anim_top0 is not null"

	extract($_SESSION['krr']->query("SELECT * FROM quizes_instances WHERE id=$1 ", array($_POST['quiz_instance_id']))[0]);

	echo "
	<style>
		#finalGradient {
			background-color: $final_anim_color0;
		}

		#movingObject{
			position: absolute; 
			left:${final_anim_left0}px;
			top:${final_anim_top0}px;
			background-color: $final_anim_color1;
			border: 1px solid #fff;
			font-size:40px;
			animation: movingObject  ${final_anim_time}s infinite alternate;
		}
		@keyframes movingObject {
			0%   { left:${final_anim_left0}px ; top:${final_anim_top0}px ; }
			50%  { left:${final_anim_left1}px ; top:${final_anim_top1}px ; }
			100% { left:${final_anim_left2}px ; top:${final_anim_top2}px ; }
		}
		pins {
			font-size: 20px;
			opacity: 0.2;
			display: inline-block;
			color: #fff;
			padding: 2px; 
		}

	</style>
	<div id=movingObject style='padding:20px; text-align: center'>$pin</div>
	";

	for($i=0; $i<500; $i++) { 
		echo "<pins>$pin</pins>";
	}
}
/*}}}*/
function points2grade($points) {/*{{{*/
	// Student scored $points
	// Student could have scored at maximum $how_many points

	$r=$_SESSION['krr']->query("SELECT quizes.how_many, quizes.grades_thresholds FROM quizes, quizes_instances WHERE quizes.id=quizes_instances.quiz_id AND quizes_instances.id=$1 ", array($_POST['quiz_instance_id']))[0];
	extract($r);
	$t=$_SESSION['krr']->process_grades_thresholds($grades_thresholds);
	$student_result=$points/$how_many*100;

	foreach(array_reverse($t,1) as $k=>$v) { 
		if($student_result >= $k) { 
			return $v;
		}
	}
	return '2.0';

}
/*}}}*/
function results(){/*{{{*/
	// We only display student result as the final score - we don't want our questions to leak
	// Well, they will be leaking anyway, but let's make it harder. 
	// The ExampleQuiz (quiz_id=1) is an exception - we display detailed results (questions and answers)
	// We allow to run ExampleQuiz multiple times, therefore we delete the instance from db after presenting answers:
	// $_SESSION['krr']->query("DELETE FROM randomized_quizes WHERE id=$3", array($_POST['randomized_id']));
	$questions_presented=json_decode($_POST['json_questions_presented'],1);
	$answers_presented=json_decode($_POST['json_answers_presented'],1);

	$student_answers=[];
	foreach(array_chunk($_REQUEST['student_answers'], 3) as $arr) { 
		$student_answers[]=join($arr);
	}
	$r=$_SESSION['krr']->query("SELECT correct_answers_vector FROM randomized_quizes WHERE id=$1 ", array($_POST['randomized_id']))[0]['correct_answers_vector'];
	$correct_answers=explode(',',$r);
	$e=$_SESSION['krr']->evaluate_student_answers($student_answers, $correct_answers, $questions_presented, $answers_presented);
	$student_answers=$e[0];
	$points=$e[1];
	$details=$e[2];
	echo "<div id=finalGradient style='min-height:1800px; width:100%'><div id=result_box> <a href=".$_SESSION['home_url']."><img src=css/home.svg></a> Result: $points / ".count($student_answers)."<br>".$_SESSION['last_name']." </div>";
	$_SESSION['student_results'][$_POST['randomized_id']]=$points;//to be checked later if nesesery
		if($_POST['quiz_instance_id']==1) { 
			echo "$details";
			$_SESSION['krr']->query("DELETE FROM randomized_quizes WHERE id=$1", array($_POST['randomized_id']));
		} else {
			animation_on_quiz_complete($points);
			$grade=points2grade($points);
			$_SESSION['krr']->query("UPDATE randomized_quizes SET student_answers_vector=$1, points=$2, grade=$3, student_finished=now() WHERE id=$4", array(join(",",$student_answers), $points, $grade, $_POST['randomized_id']));
		}
	echo "</div>";
}/*}}}*/
function menu(){/*{{{*/
	return;
	// for debuging only
	extract($_SESSION);
	echo "<student_menu> 
		  <div style='float:right; margin-right:10px'>
			 <div id=need_debug_request class=rlink>req</div>
			 <div id=need_debug_session class=rlink>ses</div>
			 <div id=need_debug_server class=rlink>srv</div>
			 <a href=/index.php?q class=rlink>$i18n_logout $last_name $first_name</a>
		  </div>
		  </student_menu>
		  ";
}/*}}}*/
function main() {/*{{{*/
	$_SESSION['krr']->htmlHead();
	menu();

	if(isset($_POST['student_answers'])){
		should_we_accept_quiz_submit(); 
		do_we_have_anything_to_display(); 
		results();
	}
	#$_SESSION['krr']->debugKarramba();
}
/*}}}*/
main();
?>
