<?php
session_name('karramba_admin');
require_once("libKarramba.php");
function getStudentLogin($str) {/*{{{*/
	$result=[];
	foreach($_SESSION['krr']->query("SELECT s.id, s.index, s.last_name, s.first_name, g.group_name FROM students s, groups g WHERE s.last_name ILIKE $1 AND s.group_id=g.id ORDER BY last_name", array($str.'%')) as $row) {
		extract($row);
		$result[]=array("$last_name $first_name [$group_name]", "$id");
	}
	echo json_encode($result);
}
/*}}}*/
function studentsSolvingQuizMonitor() {/*{{{*/
	//return;
	$result='';
	$total=0;

	foreach($_SESSION['krr']->query("SELECT quiz_name,group_name,student,student_started,points FROM r WHERE quiz_deactivation IS NOT NULL AND teacher_id=$1", array($_SESSION['teacher_id'])) as $row) {
		$total++;
		extract($row);
		$started=$_SESSION['krr']->extractTime($student_started);
		if (empty($points)) { 
			$result.="<tr><td>$quiz_name<td>$group_name<td>$student<td>$started<td><black>".$_SESSION['i18n_didnt_complete']."</black>";
		} else {
			$result.="<tr><td>$quiz_name<td>$group_name<td>$student<td>$started<td><green>$points</green>";
		}
	}
	echo json_encode(array($result,$total));
	
}
/*}}}*/

if (isset($_GET['studentLoginChars']))          { getStudentLogin($_GET['studentLoginChars']); }
if (isset($_GET['studentsSolvingQuizMonitor'])) { studentsSolvingQuizMonitor(); }
?>

