<?php
session_start();
ini_set('error_reporting', E_ALL);
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
$_SESSION['current_date']=date("Y-m-d");
$_SESSION['krr']=new karramba();

# debug/*{{{*/

function dd() {
	foreach(func_get_args() as $v) {
		echo "<pre>";
		$out=print_r($v,1);
		echo htmlspecialchars($out);
		echo "</pre>";
	}
	echo "<br><br><br><br>";
}
function dd2($arr) {
	$out=print_r($arr,1);
	echo $out;
}

/*}}}*/
function fix_nulls($arr) { /*{{{*/
	// Prepares data for db insert: trims and s//NULL/

	foreach($arr as $k=>$v) { 
		$arr[$k]=trim($v);
		if($v=='') { $arr[$k]=NULL; }
	}
	return $arr;
}
/*}}}*/
class karramba {/*{{{*/
	// On init we load all messages from messages/en.csv. This way we don't have missing texts in case translations are not complete.
	// Then messages/$language.csv is loaded to replace some/all en strings.

	public function __construct(){
		// KARRAMBA_LANG is setup in /etc/apache2/envvars
		$lang=getenv("KARRAMBA_LANG");
		if(is_dir("installer")) {  
			die("The installer folder cannot be left in the karramba www tree. You need to remove it.");
		}

		foreach (file("messages/en.csv") as $row) {                                                                                   
			$x=explode(";", $row);
			if(count($x)!=2){
				$this->fatal("Something wrong with messages/$lang.csv file - each line must have a single semicolon");
			}
			$_SESSION[trim($x[0])]=trim($x[1]);
		}
		foreach (file("messages/$lang.csv") as $row) {                                                                                   
			$x=explode(";", $row);
			if(count($x)!=2){
				$this->fatal("Something wrong with messages/$lang.csv file - each line must have a single semicolon");
			}
			$_SESSION[trim($x[0])]=trim($x[1]);
			
		}   
	}

/*}}}*/
	private function reportbug($arr) {/*{{{*/
		// KARRAMBA_NOTIFY is setup in /etc/apache2/envvars
		$reportquery=join("\n" , array('--------' , date("G:i:s"), $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $_SERVER['REQUEST_URI'], $arr[0] , $arr[1] , $arr[2] , "\n\n"));
		mail(getenv("KARRAMBA_NOTIFY"), 'Karramba bug!', "$reportquery", "from: karramba"); 
		echo "<fatal>".$arr[0]."</fatal>"; 
		echo "<br><br><br><br><br><a href=".$_SESSION['home_url']."><img id=home src=css/home.svg></a>";
		die();
}
/*}}}*/
	public function htmlHead() { /*{{{*/
		// $inline_css may display a distinct background. Useful for distinguishing karramba devel version.
		$inline_css='';
		if(is_file(dirname($_SERVER['SCRIPT_FILENAME'])."/css/workinprogress.jpg")) {
			$inline_css="<style> body { background-image: url(".dirname($_SERVER['SCRIPT_NAME'])."/css/workinprogress.jpg); background-repeat: repeat; background-attachment: fixed; } </style>"; 
		}

		echo "
		<!DOCTYPE html>
		<html> 
		<head>
			<meta charset='utf-8'>
			<title>karramba</title>
			<link rel='stylesheet' href='css/css.css'>
			$inline_css
			<link rel='stylesheet' href='js/jquery-ui.min.css'>
			<meta name='viewport' content='width=device-width, initial-scale=1, minimum-scale=1'>
			<script src='js/jquery-3.1.1.min.js'></script>
			<script src='js/jquery-ui.min.js'></script>
			<script src='js/00karramba.js'></script>
			<link rel='stylesheet' href='https://use.fontawesome.com/releases/v5.7.2/css/all.css' integrity='sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr' crossorigin='anonymous'>
		</head>
		<body>
		";
    }
/*}}}*/
	public function debugKarramba() { /*{{{*/
		$post=print_r($_POST,1);
		$get=print_r($_GET,1);
		$server=print_r($_SERVER,1);
		$sess=$_SESSION;
		foreach(array_keys($sess) as $k) {
			if(preg_match("/i18n/", $k)) 
				unset($sess[$k]);
		}
		$s=print_r($sess,1);
		echo "
		<debug_request>
			POST<pre>$post</pre>
			GET<pre>$get</pre>
			<br><br><br><br>
		</debug_request>

		<debug_session>
			SESSION<pre>$s</pre>
			<br><br><br><br>
		</debug_session>

		<debug_server>
			SERVER<pre>$server</pre>
			<br><br><br><br>
		</debug_server>
		";
		if(in_array($_SERVER['HTTP_HOST'], array('localhost', '127.0.0.1'))) {
			echo "<div style='position:absolute; top:0px; left:1000px'><div id=need_debug_request class=rlink>req</div> <div id=need_debug_session class=rlink>ses</div> <div id=need_debug_server class=rlink>srv</div></div>";
		}
    }
/*}}}*/

	public function prepare_pg_insert($arr) {/*{{{*/
		// Prepare $keys and $dolars from $_POST['arr'] for the query:
		// Example $arr=array("name"=>"Kowalski", "telefon"=>"612");
		// query("INSERT INTO przewozy($keys) VALUES($dolars) RETURNING id", $_POST['arr']);
		$keys=array_keys($arr);
		$dolars=[];
		foreach(array_keys($keys) as $k) {
			$dolars[]="\$".($k+1);
		}
		return array('keys'=>join(",", $keys), 'dolars'=>join(",", $dolars));
	}
/*}}}*/
	public function prepare_pg_update($arr) {/*{{{*/
		// Example $arr=array("name"=>"Kowalski", "telefon"=>"612");
		// $uu="name=$1, telefon=$2
		// query("UPDATE t set $uu WHERE id=1"), $_POST['arr']);
		
		$uu=[];
		foreach(array_keys($arr) as $k=>$v) {
			$uu[]="$v=\$".($k+1);
		}
		return implode(", ",$uu);
	}
/*}}}*/

	public function fatal($msg) {/*{{{*/
		echo "<fatal> $msg </fatal>";
		echo "<br><br><br><br><br><br><a href=".$_SESSION['home_url']."><img id=home src=css/home.svg></a>";
		die();
	}
/*}}}*/
	public function extractDate($date_str) {/*{{{*/
		return substr($date_str, 0, 10);
	}
/*}}}*/
	public function extractTime($date_str) {/*{{{*/
		return substr($date_str, 11, 8);
	}
/*}}}*/
	public function extractDateAndTime($date_str) {/*{{{*/
		return substr($date_str, 0, 19);
	}
/*}}}*/
	public function msg($msg) {/*{{{*/
		echo "<msg>$msg</msg>";
	}
/*}}}*/
	public function cannot($msg) {/*{{{*/
		echo "<cannot>$msg</cannot>";
	}
/*}}}*/
	public function evaluate_student_answers($student_answers, $correct_answers, $questions_presented, $answers_presented) {/*{{{*/
		$details='';
		$total_points=0;
		for($i=0; $i<count($student_answers); $i++) {
			if($correct_answers[$i]==$student_answers[$i]) {
				$score=1;
				$total_points+=$score;
				$score_str="+".number_format((float)$score, 1, '.', '');
				$color="green";
			} else { 
				$score=0;
				$total_points+=$score;
				$score_str="+".number_format((float)$score, 1, '.', '');
				$color="red";
			}
			$total_points_str=number_format((float)$total_points, 1, '.', '');
			$details.="<tr><th>".join(
				"<tr><td>", 
				array($questions_presented[$i] ,$answers_presented[$i][0] ,$answers_presented[$i][1] ,$answers_presented[$i][2] , 
				"<green>".$correct_answers[$i]."</green> 
				<tr><td><$color>".$student_answers[$i]."</$color> 
				<span style='float:right'><$color>$score_str</$color> = $total_points_str</span>", 
				"<div style='height:80px'></div>"));
		}
		return array($student_answers, $total_points, "<table style='width:340px'>$details</table>");
	}
/*}}}*/
	public function db_serve_interrupted_quiz($interrupted_randomized_id, $present_answers=0) {/*{{{*/
		extract($_SESSION);
		$r=$krr->query("SELECT * FROM r WHERE randomized_id=$1", array($interrupted_randomized_id))[0];

		$set=[];
		foreach(explode(",", $r['questions_vector']) as $q_id) {
			$rr=$krr->query("SELECT * FROM questions WHERE id=$1", array($q_id));
			if(isset($rr[0])) { 
				$set[]=$rr[0];
			}
		}
		$student_answers=explode(",", $r['student_answers_vector']);
		$correct_answers=explode(",", $r['correct_answers_vector']);
		$questions_presented=[];
		$answers_presented=[];
		$order=explode(",", $r['order_vector']);
		for($i=0; $i<count($set); $i++ ) {
			$order_atoms=str_split($order[$i]);
			$questions_presented[]=$set[$i]['question'];
			$answers_presented[]=array($set[$i]['answer'.$order_atoms[0]], $set[$i]['answer'.$order_atoms[1]], $set[$i]['answer'.$order_atoms[2]]);
		}
		$e=$this->evaluate_student_answers($student_answers, $correct_answers, $questions_presented, $answers_presented);
		$total_points=$e[1];
		$details=$e[2];

		if($present_answers==1) { 
			echo "<h3>".$r['student'].": $total_points</h3>";
			echo "$details";
		}

		$timeout=strtotime($r['student_deadline'])-time();
		return array('serve'=>'interrupted', 'questions_presented'=>$questions_presented, 'answers_presented'=>$answers_presented, 'randomized_id'=>$interrupted_randomized_id, 'timeout'=>$timeout); 
	
	} /*}}}*/
	public function process_grades_thresholds($str) {/*{{{*/
		// This can be used in two scenarios:
		// a) validate teacher's configuration input - we don't care about what is returned, just validate
		// b) evaluate student's test result
		//
		// Parse $str
		//		50%:3.0; 60%:3.5; 70%:4.0; 80%:4.5; 90%:5.0
		//
		// into
		//
		// 	$t threshold => grade
		// 	(
		// 		[50] =>  3.0 
		// 		[60] =>  3.5 
		// 		[70] =>  4.0 
		// 		[80] =>  4.5 
		// 		[90] =>  5.0 
		// 	)

		$t=[];
		foreach(preg_split("/;/", $str) as $i) { 
			$i=trim($i);
			$arr=explode(":", $i);
			$threshold=explode("%",$arr[0])[0];
			$grade=$arr[1];
			$t[$threshold]=$grade;
		}
		foreach($t as $threshold=>$grade) {
			if(!preg_match("/\d\.\d/", $grade) or !preg_match("/\d*/", $threshold)) { 
				$_SESSION['krr']->fatal($_SESSION['i18n_fatal_grades_thresholds']); 
			}
		}
		return $t;
	}
/*}}}*/
	public function query($qq,$arr=[],$success=0) { /*{{{*/
		// You only need to tweak pg_* functions to switch from postgres to sqlite/mysql/anything.
		// Authorization was setup during installation, see KARRAMBA vars in /etc/apache2/envvars

        extract($_SESSION);
		$caller=debug_backtrace()[1]['function'];

		$connect=pg_connect("dbname=karramba host=".getenv("KARRAMBA_DB_HOST")." user=".getenv("KARRAMBA_DB_USER")." password=".getenv("KARRAMBA_DB_PASS"));
		($result=pg_query_params($connect, $qq, $arr)) || $this->reportBug(array("db error\n\ncaller: $caller()\n\n", "$qq", pg_last_error($connect)));
		$k=pg_fetch_all($result);
		if($success==1) { echo "<msg>OK</msg>"; }
		if(is_array($k)) { 
			return $k;
		} else {
			return array();
		}

    }
/*}}}*/
	public function querydd($qq,$arr=[]){ /*{{{*/
		# query debugger
		echo "$qq ";
		print_r($arr);
		echo "<br>";
		return array();
    }
	/*}}}*/
}

