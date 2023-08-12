<?PHP
session_name(getenv("KARRAMBA_SESSION_NAME"));
require_once("libKarramba.php");
require_once("manage_students.php");
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
# test


function teacher_login_form(){/*{{{*/
	extract($_SESSION);
	$_SESSION['home_url']=$_SERVER['SCRIPT_NAME'];
	if(isset($_SESSION['KARRAMBA_EXTRA_MENUS']['admin_greeter'])) { $extra_menus="<br><br>".$_SESSION['KARRAMBA_EXTRA_MENUS']['admin_greeter']."<br><br>"; }  else { $extra_menus=''; }

	echo "
	<FORM METHOD=POST>
	<center>
	$extra_menus
	<br><br>
	$i18n_teacher login
	<br><br>
	Email<br>
	<input type=text name=teacher_email size=30> 
	<br><br>
	$i18n_password <br>    
	<input size=30 type=password name='password'> 
	<br><br>
	<input type=submit name=logMeIn value=$i18n_submit>
	</center>
	
	</FORM>"; 

}/*}}}*/
function logged_outside(){/*{{{*/
	// We may be authenticated in another system, which is indicated by ['user_id'] and the matching session_name().
	// But check here if we are really ok
	$row=$_SESSION['krr']->query("SELECT id as teacher_id, last_name, first_name FROM teachers WHERE id=$1", array($_SESSION['user_id']));
	if(!empty($row[0]['last_name'])) { 
		$_SESSION+=$row[0];
		$_SESSION['teacher_in']=1;
		$_SESSION['home_url']=getenv("KARRAMBA_ADMIN_HOME_URL");
	} 

}/*}}}*/
function teacher_do_login(){/*{{{*/
	extract($_SESSION);
	if(empty($_POST['teacher_email'])) {  
		$krr->msg($i18n_bad_login);
		return;
	}
	$row=$krr->query("SELECT id as teacher_id, last_name, first_name, password FROM teachers WHERE email=$1", array($_POST['teacher_email']));

	if(isset($row[0]) && $row[0]['password']==$_POST['password']) {
		unset($row[0]['password']);
		$_SESSION+=$row[0];
		$_SESSION['teacher_in']=1;
		header("Location: admin.php");
		exit();
	} else {
		$krr->msg($i18n_bad_login);
	}
}/*}}}*/
function do_logout(){/*{{{*/
	$url=$_SESSION['home_url'];
	$_SESSION=array();
	header("Location: $url");
}/*}}}*/
function quizes_configure(){/*{{{*/
	# psql karramba -c "select * from questions limit 100"
	# psql karramba -c "select * from quizes_owners"
	extract($_SESSION);
	echo "<table> <thead><th>$i18n_quiz<th>$i18n_all_questions<th>$i18n_how_many_questions_short<th>$i18n_how_much_time_short<th>$i18n_delete";

	$r=$krr->query("
		SELECT qz.quiz_name, qz.how_many, qz.timeout, qz.id, count(q.*) FROM quizes qz
		LEFT JOIN questions q on (qz.id=q.quiz_id) 
			WHERE 
			qz.id in (SELECT quiz_id FROM quizes_owners WHERE teacher_id=$1) 
			AND
			q.deleted = FALSE
			OR 
			quiz_name='ExampleQuiz' 
		GROUP BY qz.id ORDER BY 1" , 
		array($_SESSION['teacher_id'])
	);
	// Hard to make in a single query: separate query collect all new quizes, which have no questions yet
	$newq=$krr->query("SELECT qq.* FROM quizes_owners qo JOIN quizes qq ON qq.id=qo.quiz_id WHERE qo.teacher_id=$1 and qo.quiz_id not in (select quiz_id from questions)", array($_SESSION['teacher_id']));
	foreach($newq as $nn) { 
		$nn['count']='new';
		$r[]=$nn;
	}
	if(!empty($r)) { 
		foreach($r as $q){
			extract($q);
			if($quiz_name=='ExampleQuiz') { 
				$remove_button='-';
			} else {
				$remove_button="<FORM METHOD=POST><input type=hidden name=id value='$id'><input type=submit name=quiz_remove value=!></FORM>";
			}
			echo "
			<tr>
			<td> <a href=?quiz_configure=$id class=blink>$quiz_name</a>
			<td> $count
			<td> $how_many
			<td> $timeout
			<td> $remove_button
			";
		}
		echo "</table>";
		echo "
		<FORM METHOD=POST style='float:right'> 
		<br><br>
		<input type=text size=24 name=quiz_name_add placeholder='$i18n_quiz_name'>
		<input type=submit name=quiz_add value=$i18n_add>
		</FORM>
		";
	}
}/*}}}*/
function isChecked($val) {/*{{{*/
	if($val==1) { return 'div-yes'; }
	return 'div-no'; 
}
/*}}}*/
function select_groups_form() {/*{{{*/
	echo "<sliding_div id=groups_list>";
	echo "
	<FORM method=POST>
		<div style='width:1px'><help title='".$_SESSION['i18n_help_select_groups']."'></help></div>
		<div style='position: fixed; left:0px; top:0px;'>
			<input class=blink style='opacity:0.3; background:none'  type=button value='".$_SESSION['i18n_activate_quiz']."' id='finished_selecting_groups'>
			<input type=submit value='".$_SESSION['i18n_cancel']."'>
		</div>
		
		<input type=hidden name=run_quiz>
		<input class=groups_collector_ids type=hidden name=active_groups_ids value=''>
		<input class=quizes_collector_ids type=hidden name=active_quizes_ids value=''>
	</FORM>";
	foreach($_SESSION['krr']->query("SELECT * FROM quizes WHERE id IN (SELECT quiz_id FROM quizes_owners WHERE teacher_id=$1) ORDER BY quiz_name", array($_SESSION['teacher_id'])) as $arr) { 
		extract($arr);
		$varClass=isChecked(0);
		echo "<div class='$varClass quizes'><input type=hidden value=$id>$quiz_name</div>";
	}
	echo "<hr>";

	foreach($_SESSION['krr']->query("SELECT * FROM groups ORDER BY group_name") as $arr) { 
		extract($arr);
		$varClass=isChecked(0);
		echo "<div class='$varClass groups'><input type=hidden value=$id>$group_name</div><br>";
	}
	echo "<br><br><br>";
	echo "</sliding_div>";	
}
/*}}}*/
function run_quizes(){/*{{{*/
	expire_old_quizes();
	select_groups_form();
	echo "<display_clock>00:00:00</display_clock>";
	extract($_SESSION);
	echo "<div id='choose_groups_button' class='blink'>$i18n_select_quiz</div><br><br>"; 
	$r=$krr->query("SELECT i.*, q.quiz_name FROM quizes_instances i, quizes q WHERE q.id=i.quiz_id AND i.teacher_id=$1 AND i.quiz_deactivation IS NOT NULL ORDER BY i.quiz_deactivation DESC" , array($_SESSION['teacher_id']));
	if(!empty($r)) { 
		echo "<table id=running_quizes><thead><th>$i18n_quiz<th><help title='$i18n_help_pin'>PIN</help><th>$i18n_groups<th><help title='$i18n_help_active_until'>$i18n_quiz_active_until</help>";
		echo "<tbody>";
		foreach($r as $q){
			extract($q);
			$deactivation_time=$krr->extractTime($quiz_deactivation);
			$operation="<FORM method=POST><input type=submit value='$i18n_deactivate_quiz'><input type=hidden name=stop_quiz value=$id></FORM>";
			$status="$deactivation_time"; 
			$group=format_group($group_id);
			echo "<tr><td>$quiz_name<td><green>$pin</green><td>$group<br>$operation<td>$status";
		}
		echo "</tbody></table>";
	}

}/*}}}*/
function final_animation() {/*{{{*/
	// Prevent hacking: for each test there is a unique final animation. 
	// All students should have the same animation and we may wish to see what is displayed on their phones.

	return array(
		'final_anim_color0' => join(array("rgb(", rand(0,255), ",", rand(0,255), ",", rand(0,255), ")")),
		'final_anim_color1' => join(array("rgb(", rand(0,255), ",", rand(0,255), ",", rand(0,255), ")")),
		'final_anim_time'   => rand(1,6),
		'final_anim_left0'  => rand(0,300),
		'final_anim_left1'  => rand(0,300),
		'final_anim_left2'  => rand(0,300),
		'final_anim_top0'   => rand(0,300),
		'final_anim_top1'   => rand(0,300),
		'final_anim_top2'   => rand(0,300)
	);

}
/*}}}*/

function end_expired_quizes() {/*{{{*/
	// If empty student_finished AND student_deadline has passed now() then the randomized_quiz has expired. 
	// We set grade=2 and points=0 for each such quiz.
	# psql karramba -c "SELECT points,grade,quiz_id,student_id,student_started,student_finished,student_deadline,teacher_id  from randomized_quizes"
	# psql karramba -c "SELECT pin,quiz_deactivation from quizes_instances where quiz_deactivation is not null"
	# psql karramba -c "SELECT student,pin,quiz_deactivation,student_finished from r "
	# psql karramba -c "SELECT * from questions"
	$_SESSION['krr']->query("
	UPDATE randomized_quizes SET points=0, grade=2, student_finished=now() 
	WHERE 
	student_finished IS NULL AND 
	student_deadline + INTERVAL '1 MINUTES' < NOW() AND
	teacher_id = $1
	",
	array($_SESSION['teacher_id'])
	);
}
/*}}}*/
function create_pin() {/*{{{*/
	// PINs serves two purposes:
	// 1. Student cannot by accident join another quiz, in another classroom.
	// 2. Students from outside cannot hack into our quiz. Well... someone can still pass the PIN to the outside of the classroom.

	// We want a uniq PIN for our quiz. 
	// We could have a recursive function that checks if the PIN already exists in database, but recursion can be deadly...
	// If we really cannot in 20 draws find a free PIN (unlikely) then we just fallback to pin=5555, which isn't that crucial.
	$candidate_pins=[];
	for($i=0; $i<10; $i++) {
		$candidate_pins[]=rand(0,9999);
	}
	$used=[];
	foreach($_SESSION['krr']->query("SELECT pin FROM quizes_instances") as $p) { 
		$used[]=$p['pin'];
	}

	foreach($candidate_pins as $pin) {
		if(!in_array($pin, $used)) {
			return str_pad($pin,4,"0",STR_PAD_LEFT);
		}
	}

	return 5555;
}
/*}}}*/
function run_quiz(){/*{{{*/
	// Students have 20 minutes to login and choose their quiz. 
	// Still we are not sure what we risk by playing with this time.
	extract($_SESSION);
	end_expired_quizes();
	if(empty($_POST['active_quizes_ids'])) { 
		return;
	}
	#$quiz_deactivation=date("Y-m-d G:i:s", strtotime("+20 minutes"));
	$quiz_deactivation=date("Y-m-d G:i:s", strtotime("+12 hours"));
	foreach(explode(",", $_POST['active_quizes_ids']) as $run_quiz) { 
		$r=$krr->query("SELECT * FROM quizes_instances WHERE quiz_deactivation IS NOT NULL AND teacher_id=$1 AND quiz_id=$2 AND group_id=ANY($3)" , 
		array($_SESSION['teacher_id'], $run_quiz, "{".$_POST['active_groups_ids']."}"));
		if (empty($r)) { 
			$pin=create_pin();
			$anim=final_animation();
			foreach(explode(",",$_POST['active_groups_ids']) as $gid) { 
				$arr=array($run_quiz, $gid, $_SESSION['teacher_id'], $quiz_deactivation, $pin, $anim['final_anim_color0'], $anim['final_anim_color1'], $anim['final_anim_time'], $anim['final_anim_left0'], $anim['final_anim_left1'], $anim['final_anim_left2'], $anim['final_anim_top0'], $anim['final_anim_top1'], $anim['final_anim_top2']);
				if(in_array("",$arr)) { $krr->fatal("$i18n_something_went_wrong"); }
				$krr->query("INSERT INTO quizes_instances (quiz_id, group_id, teacher_id, quiz_deactivation, pin, final_anim_color0, final_anim_color1, final_anim_time, final_anim_left0, final_anim_left1, final_anim_left2, final_anim_top0, final_anim_top1, final_anim_top2) 
				VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14)", $arr);
				header("Location: ?run_quizes");
			}
		} else {
			$krr->cannot("You are running this quiz already!");
		}
	}
}/*}}}*/
function stop_quiz(){/*{{{*/
	extract($_SESSION);
	$krr->query("UPDATE quizes_instances SET quiz_deactivation=NULL, pin=NULL WHERE id=$1" , array($_POST['stop_quiz']));
}/*}}}*/
function expire_old_quizes(){/*{{{*/
	extract($_SESSION);
	$krr->query("UPDATE quizes_instances SET quiz_deactivation=NULL WHERE quiz_deactivation < now()");
}/*}}}*/
function upload_images_form() {/*{{{*/
	if(! is_writable("img/")) { 
		echo "<dropzone_form class=invisible>";
		echo "<img id=close_dropzone src=css/close.svg style='float:right'><br>";
		echo "<br><br>".$_SESSION['i18n_img_not_writable'];
		echo "</dropzone_form>";
	} else {
		echo "<dropzone_form class=invisible>";
		echo "<img id=close_dropzone src=css/close.svg style='float:right'><br>";
		echo $_SESSION['i18n_howto_upload_images'];
		echo "<br><br><link rel='stylesheet' href='css/dropzone.css'>";
		echo "<script src='js/dropzone.js'></script>";
		echo "<FORM ACTION=uploadViaDropzone.php?id=".$_GET['quiz_configure']." class='dropzone'></FORM>";
		echo "</dropzone_form>";
	}
}
/*}}}*/

function pipes2imgs($record) { #{{{
	$dest="img/".$_SESSION['teacher_id']."/".$_GET['quiz_configure'];
	foreach($record as $k => $v) {
		$z=explode("||", $v);
		if(count($z)>1) { 
			$img="<img src=$dest/".trim($z[1]).">";
			$record[$k]=trim($z[0])."<br>$img";
		}
	}
	return $record;
}
/*}}}*/
function imgs2pipes($record) { #{{{
	foreach($record as $k => $v) {
		$z=explode("<br><img src=", $v);
		if(count($z)>1) { 
			$img=basename(substr($z[1], 0, -1)); // z[1]="/some/path/1.jpg>"
			$record[$k]=$z[0]." || ".$img; 
		}
	}
	return $record;
}
/*}}}*/
function quiz_configure(){/*{{{*/
	// Courtesy of update_quiz(): in case of faulty teacher's textarea it is saved in $_SESSION['textarea_save']. 
	// We stop serving textarea from $_SESSION['textarea_save'] and server data from database:
	// a) if user changes his ?quiz_configure for another request (means he is done with configuration)
	//		[QUERY_STRING] => quiz_configure=6
	//      [HTTP_REFERER] => http://localhost/karramba/devel/admin.php?quiz_configure=6
	// b) if update_quiz() clears variable $_SESSION['textarea_save'] 

	extract($_SESSION);
	if (explode("?", $_SERVER['HTTP_REFERER'])[1] != $_SERVER['QUERY_STRING']) { unset($_SESSION['textarea_save']); }
	$r=$krr->query("SELECT question,answer0,answer1,answer2,correct_vector  FROM questions WHERE quiz_id=$1 AND deleted = FALSE ORDER BY id" , array($_GET['quiz_configure']));
	if(empty($r)) { $r=array(); } 
	if(!isset($_SESSION['textarea_save'])) { 
		$textarea='';
		foreach($r as $record) {
			$record=imgs2pipes($record);
			$textarea.=implode("\n", $record)."\n\n";
		}
	} else {
		$textarea=$_SESSION['textarea_save'];
	}
	$preview='';
	foreach($r as $record) {
		$record['correct_vector']="<green>$record[correct_vector]</green>";
		$record[]="<div style='height:80px'></div>";
		$preview.="<tr><th>".join("<tr><td>", $record);
	}
	display_configured_quiz($textarea);
	display_configured_quiz_preview($preview);
} /*}}}*/
function list_images() {/*{{{*/
	if($_GET['quiz_configure']==1) { return ''; }
	$path="img/".$_SESSION['teacher_id'].'/'.$_GET['quiz_configure'];
	if(!is_dir($path)) {
		return;
	}
	$images='';
	foreach(scandir($path) as $f) {
		if(preg_match("/^[A-Za-z0-9_]+\.(png|PNG|jpg|JPG|jpeg|JPEG)$/", $f)) {
			$images.="<a class=rlink href='$path/$f' target=_blank>$f</a>";
		}
	}
	if (empty($images)) { 
		return; 
	} else {
		extract ($_SESSION);
		return "<br>$i18n_images_for_this_quiz: <br><br>$images";
	}
}
/*}}}*/
function list_owners() {/*{{{*/
	$owners=[];
	foreach($_SESSION['krr']->query("SELECT last_name FROM teachers WHERE id IN (SELECT teacher_id FROM quizes_owners WHERE quiz_id=$1) AND id!=$2 ORDER BY last_name", array($_GET['quiz_configure'], $_SESSION['teacher_id'])) as $arr) { 
		$owners[]=$arr['last_name'];
	}
	return join(", ", $owners);
}
/*}}}*/
function display_configured_quiz($textarea) { /*{{{*/
	extract($_SESSION);
	$current_owners=list_owners();
	manage_owners();
	upload_images_form(); 
	$r=$krr->query("SELECT quiz_name, how_many, timeout, grades_thresholds, sections FROM quizes WHERE id=$1" , array($_GET['quiz_configure']))[0];
	if(empty($r['how_many']))  { $how_many=5; } else { $how_many=$r['how_many']; }
	if(empty($r['sections']))  { $sections=1; } else { $sections=$r['sections']; }
	if(empty($r['timeout']))   { $timeout=5; } else { $timeout=$r['timeout']; }
	if(empty($r['grades_thresholds'])) { $grades_thresholds='40%:3.0; 50%:3.5; 65%:4.0; 75%:4.5; 85%:5.0'; } else { $grades_thresholds=$r['grades_thresholds']; }
	$display="<table>";
	if($_GET['quiz_configure']==1) {

		$display.="<textarea id=q_textarea name=questions_textarea readonly cols=60 rows=20>$textarea</textarea>";
	} else {
		$display.="
			<FORM METHOD=POST>
			<tr><td colspan=2 style='text-align: center'>
			<input type=submit name=quiz_update value='$i18n_update'>
			<div class=blink id=q_instructions>$i18n_instructions</div>
			<tr><td>

			<textarea id=q_textarea name=questions_textarea cols=60 rows=18>$textarea</textarea><br>
			<td>
				<table style='background-color: #044;'>
				<tr><td>$i18n_how_many_questions_long <td><input type=text name=how_many size=1 value='$how_many' required pattern='\d*'> 
				<tr><td>$i18n_how_many_sections <help title='$i18n_how_many_sections_howto'> </help><td><input type=text required pattern='[0-9]*' size=1 name=sections value=$sections>
				<tr><td>$i18n_how_much_time_long <td><input type=text pattern='\d*' name=timeout size=1 value='$timeout' required>
				<tr><td>$i18n_grades_thresholds<br>
				
				<input style='margin-top: 4px' type=text name=grades_thresholds value='$grades_thresholds' size=45 required><td>
				<tr><td><div id='choose_owners_button' class='blink'>$i18n_share_quiz</div><br>$current_owners
				</table>
			</FORM><br>
			<q_howto class=invisible>
			<img id=close_dropzone src=css/close.svg style='float:right'><br>
			$i18n_howto_first_time<br><br><br>$i18n_howto_modify_questions</q_howto>
		";
	}
	echo "$display";

}/*}}}*/
function display_configured_quiz_preview($preview) { /*{{{*/
	extract($_SESSION);
	$l_images=list_images();
	$r=$krr->query("SELECT quiz_name FROM quizes WHERE id=$1" , array($_GET['quiz_configure']))[0];
	$quiz_name=$r['quiz_name'];
	$number_of_questions=$krr->query("SELECT count(*) FROM questions WHERE quiz_id=$1 AND deleted = FALSE" , array($_GET['quiz_configure']))[0]['count'];
	echo "
	<tr><td>
		<table>
		<tr><th>$i18n_quiz<td> $quiz_name
		<tr><th>$i18n_number_of_questions<td> $number_of_questions
		</table>
		<br>
		<h1>$i18n_preview</h1>
		<table style='width:330px'>$preview</table>
	$l_images
	</table>
	";

}/*}}}*/
function validate_quiz_question($q, $q_id) {/*{{{*/
	$line=$q_id*6;
	$near_line=$_SESSION['i18n_err_near_line'];
	if(count($q) < 5) { 
		$_SESSION['krr']->cannot($_SESSION['i18n_in_question']." ".($q_id+1)." ($near_line $line):<br>".$q[0]."<br>".$_SESSION['i18n_input_too_short']);
		return 'err';
	}
	if(!empty($q[5])) { 
		$_SESSION['krr']->cannot($_SESSION['i18n_near_question']." ".($q_id+1)." ($near_line $line):<br>".$q[0]."<br>".$_SESSION['i18n_missing_blank']);
		return 'err';
	}
	if($q[4]=='000') { 
		$_SESSION['krr']->cannot($_SESSION['i18n_in_question']." ".($q_id+1)." ($near_line $line):<br>".$q[0]."<br>".$_SESSION['i18n_answers_cannot_be_zeroes']);
		return 'err';
	}
	if(strlen($q[4])!=3) { 
		$_SESSION['krr']->cannot($_SESSION['i18n_in_question']." ".($q_id+1)." ($near_line $line):<br>".$q[0]."<br>".$_SESSION['i18n_answers_not_binary_triplet']);
		return 'err';
	}
	foreach(str_split($q[4]) as $binary) { 
		if(!in_array($binary, array(0,1))) { 
			$_SESSION['krr']->cannot($_SESSION['i18n_in_question']." ".($q_id+1)." ($near_line $line):<br>".$q[0]."<br>".$_SESSION['i18n_answers_not_binary_triplet']);
			return 'err';
		}
	}
	if(count($q)<5) { 
		$_SESSION['krr']->cannot($_SESSION['i18n_near_question']." ".($q_id+1)." ($near_line: $line):<br>".$q[0]."<br>".$_SESSION['i18n_question_error']);
		return 'err';
	}
	return 'ok';
}
/*}}}*/
function validate_quiz_configuration() {/*{{{*/
	extract($_SESSION);
	$r=$krr->query("SELECT * FROM quizes WHERE id=$1", array($_GET['quiz_configure']));

	// how_many cannot exceed the total number of questions in database
	$how_many=$krr->query("SELECT count(*) AS defined FROM questions WHERE quiz_id=$1 AND deleted = FALSE", array($_GET['quiz_configure']));
	if($how_many[0]['defined'] < $r[0]['how_many']) {
		$krr->fatal("$i18n_too_few_questions_for_quiz: ".$r[0]['quiz_name']);
	}

	if(round($r[0]['how_many'] / $r[0]['sections']) < 2) {
		$krr->fatal("The ratio:<br><br><br><red>$i18n_how_many_questions_long</red><br>----------------------------------------------------<br><red>$i18n_how_many_sections</red><br><br><br>must be > 2<br><br><a class=blink href=?$_SERVER[QUERY_STRING]>Fix your quiz</a>");
		
	}
}
/*}}}*/
function htmlspecialchars_minus_img_src($item) {/*{{{*/
	// Any php or <? injections we transform via htmlspecialchars()
	//
	// karramba transforms 
	//		|| img.png 
	// into 
	//		<br><img src=img.png>
	// Besides we may allow for some html formatting like sup


	if(stripos($item,'php') !== false) { return htmlspecialchars($item); }
	if(stripos($item,'<?') !== false)  { return htmlspecialchars($item); }

	$preserve=array(
		'<br><img src=',
		'<h1>',
		'<h2>',
		'<sup>',
		'<sub>',
	);
	
    foreach($preserve as $pp) {
        if(stripos($item,$pp) !== false) { 
			return $item;
		}
    }

	return htmlspecialchars($item);
}
/*}}}*/
function quiz_update() {/*{{{*/
	# psql karramba -c "select * from quizes"
	# psql karramba -c "alter table quizes add column sections int"
	extract($_SESSION);
	$_SESSION['krr']->process_grades_thresholds($_POST['grades_thresholds']);
	$textarea=$_SESSION['textarea_save']=rtrim($_POST['questions_textarea']);
	$collect=[];
	$arr=array_chunk(explode("\n", $textarea),6);
	foreach($arr as $q_id=>$q) { 
		$q=array_map('trim', $q);
		$q=pipes2imgs($q);
		if(validate_quiz_question($q,$q_id) == 'err') { $err=1; break; }
		$collect[]=array_map('htmlspecialchars_minus_img_src', array($q[0], $q[1], $q[2], $q[3], $q[4]));
	}

	if(!isset($err)) {
		$krr->query("UPDATE questions SET deleted = TRUE WHERE quiz_id=$1", array($_GET['quiz_configure']));
		remove_unused_questions(); 
		prevent_duplicate_questions($collect);
		$krr->query("UPDATE quizes SET how_many=$1, timeout=$2, grades_thresholds=$3, sections=$4 WHERE id=$5", array($_POST['how_many'], $_POST['timeout'], $_POST['grades_thresholds'], $_POST['sections'], $_GET['quiz_configure']));
		unset($_SESSION['textarea_save']);
	}
	validate_quiz_configuration();
}
/*}}}*/
function prevent_duplicate_questions($collect) { #{{{
	# psql karramba -c "select * from questions where quiz_id=3"
	# psql karramba -c "select * from randomized_quizes"
	// Say Professor has 1000 questions and he changes a single character in Quiz Config
	// Normally we would mark 1000 questions as deleted (history needs them) and add another 1000 questions.
	// Instead we iterate and if the new question matches a record in DB, then we un-delete the old one and don't create a new one.
	// Only if there is a brand new record in $_POST questions we are inserting.

	$krr=$_SESSION['krr'];
	$r=$krr->query("SELECT id, question || answer0 || answer1 || answer2 || correct_vector AS record FROM questions WHERE quiz_id=$1", array($_GET['quiz_configure']));
	$existing=[];
	foreach($r as $v) {
		$existing[$v['id']]=$v['record'];
	}

	$unmark_delete=[];
	foreach($collect as $k=>$v) { 
		$search=implode("", $v); 
		$key=array_search($search, $existing);
		if($key) { 
			$unmark_delete[]=$key;
			unset($collect[$k]);
		}
	}
	$krr->query("UPDATE questions SET deleted = FALSE WHERE id=ANY($1)", array("{".implode(",", $unmark_delete)."}"));
	foreach($collect as $v) {
		array_unshift($v, $_GET['quiz_configure']);
		$_SESSION['krr']->query("INSERT INTO questions(quiz_id , question , answer0 , answer1 , answer2 , correct_vector) VALUES($1, $2, $3, $4, $5, $6)", $v, 1);
	}
}
/*}}}*/
function remove_unused_questions() { #{{{
	// At this points we have deleted=True for all questions. 
	// Professor just marked 1000 questions as deleted and we will be soon processing $_POST questions.
	// On occassion that professor provides only 800 $_POST questions (200 are obsolete) we are hunting for 
	// the questions that are not need for history and we are removing them. 
	#psql karramba -c "SELECT * FROM used_questions"
	$_SESSION['krr']->query("DELETE FROM questions WHERE quiz_id=$1 AND id NOT IN (SELECT question_id FROM used_questions WHERE quiz_id=$1)", array($_GET['quiz_configure']));
}
/*}}}*/

function quiz_add(){/*{{{*/
	# psql karramba -c "select * from quizes"
	# psql karramba -c "select * from quizes_owners order by quiz_id"
	extract($_SESSION);
	$id=$krr->query("INSERT INTO quizes (quiz_name) VALUES ($1) RETURNING id" , array($_POST['quiz_name_add']))[0]['id'];
	$krr->query("INSERT INTO quizes_owners (quiz_id, teacher_id) VALUES ($1,$2)" , array($id,$_SESSION['teacher_id']), 1);
}/*}}}*/
function quiz_remove(){/*{{{*/
	extract($_SESSION);
	$r=$krr->query("SELECT * FROM quizes_owners WHERE quiz_id=$1", array($_POST['id']));
	if(count($r)>1) { 
		$krr->fatal($i18n_owners_hanging);
	}
	$krr->query("DELETE FROM quizes WHERE id=$1", array($_POST['id']));
	$krr->query("DELETE FROM quizes_owners WHERE quiz_id=$1", array($_POST['id']), 1);
}/*}}}*/
function monitor_logins() {/*{{{*/
	extract($_SESSION);
	echo "Students logged: <total_logins>&nbsp;</total_logins>";
	echo "<table id='table_monitor_solving'>";
	echo "</table>";
}
/*}}}*/
function quiz_results_by_name() {/*{{{*/
	// All results for a single quiz, i.e. all groups' results in the quiz "Linear Algebra". 
	// We should filter out the results older than 12 months or so.
	extract($_SESSION);

	quizes_summary();
	$query="SELECT s.last_name, s.first_name, s.index, g.id as group_id, g.group_name, r.student_started, r.points, r.grade, q.quiz_name, r.id AS debug_student_quiz 
	FROM randomized_quizes r
	LEFT JOIN students s ON r.student_id=s.id
	LEFT JOIN groups g ON s.group_id=g.id
	LEFT JOIN quizes q ON r.quiz_id=q.id
	WHERE quiz_id=$1 AND last_name IS NOT NULL ORDER BY s.last_name
	";
	$r=$krr->query($query, array($_GET['quiz_results_by_name'])); 
	if(empty($r)) { echo "<orange style='margin-left:20px'>$i18n_empty_results</orange>"; return; }
		
	$collect='';
	$csv=[];
	$csv[]=array("$i18n_last_name $i18n_first_name", "$i18n_grade", "$i18n_points", "$i18n_group","Index");
	$i=1;
	foreach($r as $row) {
		extract($row);
		$gname=format_group($group_id);
		$started=$krr->extractDate($student_started);
		#$collect.="<tr><td>$i<td>$last_name $first_name<td style='opacity:0.1'>$index<td>$points<td>$gname";
		if(empty($points)) { 
			$collect.="<tr><td>$i<td>$last_name $first_name<td>-<td>-<td>$started<td>$gname<td><black>$i18n_didnt_complete</black>";
		} else {
			$collect.="<tr><td>$i<td>$last_name $first_name<td><a href=?debug_student_quiz=$debug_student_quiz class=blink>$grade</a><td>$points<td>$started<td>$gname<td>";
		}
		$i++;
		$csv[]=array("$last_name $first_name", "$grade" , "$points", "$group_name","$index");
	}

	if(!empty($collect)) { 
		$quiz_name=$r[0]['quiz_name'];
		echo "<orange style='margin-left:20px'>$quiz_name</orange>";
		echo "<a href=?quiz_results_by_name_max=$_GET[quiz_results_by_name] class=blink>$quiz_name MAX</a> ";
		echo "<table><thead><th>Id<th>Student<th>$i18n_grade<th>$i18n_points<th>Start<th>$i18n_group<th>Comment";
		echo "$collect";
		echo '</table><br><br><br>';
	} 
	echo "Download: <a class=blink href=?as_xlsx>Excel</a> <a class=blink href=?as_csv>CSV</a> <a class=blink href=?as_screen>Screen</a> <br><br>";
	$_SESSION['spreadsheet_data']=$csv;
	$_SESSION['spreadsheet_name']="${group_name}__${quiz_name}";
} 
/*}}}*/
function xls_formulas() {/*{{{*/
	// * Since grades are frozen in database for historic reasons, the teacher is free
	// to use xls formulas to recalculate grades with lookup()
	// * Points must be the third column
	// * If there are too few records in xls then the array wraps badly, therefore this function
	// is only useful for > 7 rows.
	extract($_SESSION);
	$with_formulas=[];
	if(count($_SESSION['spreadsheet_data']) < 7) { return; }
	foreach($_SESSION['spreadsheet_data'] as $k=>$record) {
		$record[]='';
		$record[]='';
		$record[]='';
		$record[]="=LOOKUP(C".($k+1).",K3:K8,L3:L8)";
		$record[]='';
		$record[]='';
		$with_formulas[]=$record;
	}
	$with_formulas[0][7]='Recalculate?';
	$with_formulas[0][8]="$i18n_grade";
	$with_formulas[0][10]='H column recalculates according to the below criteria:';
	$with_formulas[1][10]='from pts';
	$with_formulas[1][11]='grade';

	$with_formulas[2][10]='0' ; $with_formulas[2][11]=2.0 ;
	$with_formulas[3][10]=10  ; $with_formulas[3][11]=3.0 ;
	$with_formulas[4][10]=12  ; $with_formulas[4][11]=3.5 ;
	$with_formulas[5][10]=14  ; $with_formulas[5][11]=4.0 ;
	$with_formulas[6][10]=16  ; $with_formulas[6][11]=4.5 ;
	$with_formulas[7][10]=18  ; $with_formulas[7][11]=5.0 ;

	$_SESSION['spreadsheet_data']=$with_formulas ;
}
/*}}}*/
function spreadsheet() { #{{{
	$spreadsheet = new Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	
	if(isset($_GET['as_xlsx'])) { 
		xls_formulas();
		$sheet->fromArray($_SESSION['spreadsheet_data'], NULL, 'A1');
		$writer = new Xlsx($spreadsheet);
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="'. $_SESSION['spreadsheet_name'] .'.xls"'); 
		header('Cache-Control: max-age=0');
		$writer->save('php://output');
	} else {
		$sheet->fromArray($_SESSION['spreadsheet_data'], NULL, 'A1');
		header('Content-Type: application/vnd.ms-excel');
		header('Content-Disposition: attachment;filename="'. $_SESSION['spreadsheet_name'] .'.csv"'); 
		header('Cache-Control: max-age=0');
		$csv='';
		foreach($_SESSION['spreadsheet_data'] as $v) { 
			$csv.=implode(";", $v)."\n";
		}
		file_put_contents('php://output', $csv);
	}
	exit();
}
/*}}}*/
function as_screen() { #{{{
	foreach($_SESSION['spreadsheet_data'] as $v) {
		echo implode(";", $v)."<br>";
	}
	exit();
}
/*}}}*/
function quiz_results_by_name_max() {/*{{{*/
	extract($_SESSION);
	quizes_summary();

	$query="SELECT s.last_name, s.first_name, s.index, g.group_name, g.id as group_id, q.quiz_name, max(r.points) as points 
	FROM randomized_quizes r
	LEFT JOIN students s ON r.student_id=s.id
	LEFT JOIN groups g ON g.id=s.group_id
	LEFT JOIN quizes q ON r.quiz_id=q.id
	WHERE quiz_id=$1 AND s.last_name IS NOT NULL
	GROUP BY s.last_name, s.first_name, g.group_name, g.id, q.quiz_name, s.index
	ORDER BY g.group_name desc, s.last_name ASC";
		
	$r=$krr->query($query, array($_GET['quiz_results_by_name_max'])); 
	if(empty($r)) { $krr->msg("$i18n_empty_results"); return; }
		
	$collect='';
	$csv=[];
	$csv[]=array("$i18n_last_name $i18n_first_name", "index", "$i18n_points", "$i18n_group");
	$i=1;
	foreach($r as $row) {
		extract($row);
		$gname=format_group($group_id);
		$collect.="<tr><td>$i<td>$last_name $first_name<td style='opacity:0.1'>$index<td>$points<td>$gname";
		$csv[]=array("$last_name $first_name", "$index", "$points" , "$group_name");
		$i++;
	}
	if(!empty($collect)) { 
		$quiz_name=$r[0]['quiz_name'];
		echo "<a style='margin-left:20px' href=?quiz_results_by_name=$_GET[quiz_results_by_name_max] class=blink>$quiz_name</a>";
		echo "<orange>$quiz_name MAX</orange><br>";
		echo "<table><thead><th>Id<th>Student<th>index<th>$i18n_points<th>$i18n_group";
		echo "$collect";
		echo '</table><br><br><br>';
	} 
	echo "Download: <a class=blink href=?as_xlsx>Excel</a> <a class=blink href=?as_csv>CSV</a> <a class=blink href=?as_screen>Screen</a> <br><br>";
	$_SESSION['spreadsheet_data']=$csv;
	$_SESSION['spreadsheet_name']="${group_name}__${quiz_name}";
} 
/*}}}*/
function quizes_summary() {/*{{{*/
	$r=$_SESSION['krr']->query("SELECT quiz_name, id FROM quizes WHERE id in (SELECT quiz_id FROM quizes_owners WHERE teacher_id=$1) ORDER BY 1" , array($_SESSION['teacher_id']));
	if(!empty($r)) { 
		foreach($r as $q){
			echo "<a href=?quiz_results_by_name=$q[id] class=blink>$q[quiz_name]</a> ";
		}
	}
	echo "<br><br>";
}
/*}}}*/
function quizes_results_by_date() {/*{{{*/
	extract($_SESSION);
	# psql karramba -c "select * from quizes_owners"
	# psql karramba -c "select * from quizes"
	# psql karramba -c "select quiz_instance_id,teacher_id  from randomized_quizes group by quiz_instance_id, teacher_id"
	# psql karramba -c "select * from randomized_quizes order by quiz_instance_id"
	# psql karramba -c "select * from quizes"

	$r=$krr->query("
	SELECT rq.quiz_instance_id, count(rq.id) AS count, rq.teacher_id, qi.group_id, to_char(quiz_activation, 'DD-MM (DY) / HH24:MM') as quiz_activation, t.last_name, q.quiz_name FROM randomized_quizes rq 
	LEFT JOIN quizes_instances qi ON qi.id=rq.quiz_instance_id
	LEFT JOIN teachers t ON t.id=rq.teacher_id
	LEFT JOIN quizes q ON rq.quiz_id=q.id 
	WHERE q.id!=1
	AND
	rq.quiz_id IN (SELECT quiz_id FROM quizes_owners WHERE teacher_id=$1)
	GROUP BY rq.quiz_instance_id, rq.teacher_id, t.last_name, q.quiz_name, qi.group_id, qi.quiz_activation
	ORDER BY rq.quiz_instance_id DESC
	", array($_SESSION['teacher_id']));

	echo "<table><thead><th>Id<th>$i18n_time<th>$i18n_quiz<th>$i18n_teacher<th>$i18n_group<th>$i18n_quizes_results";
	$i=1;
	foreach($r as $row) {
		extract($row);
		$quiz_name=mb_substr($quiz_name,0,18, "utf8");
		$teacher=mb_substr($last_name,0,10, "utf8");
		$group=format_group($group_id);
		echo "<tr><td>$i<td>$quiz_activation<td> <orange>$quiz_name</orange><td>$last_name<td>$group<td> 
		<FORM method=POST action=admin.php?quiz_results_by_date=$quiz_instance_id>
		<input type=submit value='($count) $i18n_show'>
		<input type=hidden name=quiz_activation value=$quiz_activation>
		</FORM>
		";
		$i++;
	}
	echo "</table>";
	
}
/*}}}*/
function quiz_results_by_date() {/*{{{*/
	extract($_SESSION);
	$r=$krr->query("
		SELECT g.group_name, q.quiz_name, r.student_started, r.points, r.grade, r.id AS debug_student_quiz, s.last_name, s.first_name, s.index, i.group_id, i.quiz_activation FROM randomized_quizes r
		LEFT JOIN students s ON s.id=r.student_id
		LEFT JOIN groups g ON g.id=s.group_id
		LEFT JOIN quizes_instances i ON i.id=r.quiz_instance_id
		LEFT JOIN quizes q ON q.id=r.quiz_id
		WHERE 
		i.id = $1							
		ORDER BY s.last_name, s.first_name
		", 
		array($_GET['quiz_results_by_date']));

	$collect='';
	$csv=[];
	$csv[]=array("$i18n_last_name $i18n_first_name", "$i18n_grade", "$i18n_points", "$i18n_group", "index");
	$i=1;
	foreach($r as $row) {
		extract($row);
		$started=$krr->extractTime($student_started);
		$group=format_group($r[0]['group_id']);
		if(empty($points)) { 
			$collect.="<tr><td>$i<td>$last_name $first_name<td>-<td>-<td>$started<td>$group<td><black>$i18n_didnt_complete</black>";
		} else {
			$collect.="<tr><td>$i<td>$last_name $first_name<td><a href=?debug_student_quiz=$debug_student_quiz class=blink>$grade</a><td>$points<td>$started<td>$group<td>";
		}
		$csv[]=array("$last_name $first_name", "$grade" , "$points", "$group_name", "$index");
		$i++;
	}
	if(!empty($collect)) { 
		$quiz_name=$r[0]['quiz_name'];
		$date=$krr->extractDate($student_started);
		echo "<br><orange>$quiz_name</orange> $date<br><br>";
		echo "<table><thead><th>Id<th>Student<th>$i18n_grade<th>$i18n_points<th>Start<th>Group<th>Comment";
		echo "$collect";
		echo '</table><br><br><br>';
		echo "Download: <a class=blink href=?as_xlsx>Excel</a> <a class=blink href=?as_csv>CSV</a> <a class=blink href=?as_screen>Screen</a> <br><br>";
		$_SESSION['spreadsheet_data']=$csv;
		$_SESSION['spreadsheet_name']="${group_name}__${quiz_name}";
	} else {
		echo $_SESSION['i18n_empty_results']; 
	}
	
}
/*}}}*/
function format_group($id) {/*{{{*/
	$r=$_SESSION['krr']->query("SELECT group_name FROM groups WHERE id=$1", array($id));
	if(empty($r[0])) { return; } 
	$name=$r[0]['group_name'];
	return "<a class=nocolor href=?manage_students=$id>$name</a>";
}
/*}}}*/
function do_modify_owners(){/*{{{*/
	extract($_SESSION);
	if(empty($_GET['quiz_configure']) or empty($_POST['active_teachers_ids'])) { 
		return;
	}

	$teachers_ids=explode(",", $_POST['active_teachers_ids']);
	if(!in_array($_SESSION['teacher_id'], $teachers_ids)) { 
		$teachers_ids[]=$_SESSION['teacher_id'];
	}
	$krr->query("DELETE FROM quizes_owners WHERE quiz_id=$1", array($_GET['quiz_configure']), 1);
	foreach($teachers_ids as $teacher_id) { 
		$krr->query("INSERT INTO quizes_owners(quiz_id,teacher_id) VALUES($1,$2)", array($_GET['quiz_configure'], $teacher_id));
	}
	owners_images($teachers_ids);
}/*}}}*/
function owners_images($teachers_ids) { #{{{
	// The images for shared quizes are links to the master. We need to find the master first.
	if(!is_numeric($_GET['quiz_configure'])) { 
		die("err53: quiz_configure not numeric");
	}

	$friends=[];
	foreach($teachers_ids as $kk=>$vv) {
		$t=$_SESSION['krr']->query("SELECT first_name, last_name FROM teachers WHERE id=$1", array($vv));
		extract($t[0]);
		$friends[]="img/$vv/$_GET[quiz_configure] ($first_name $last_name)";
	}

	foreach($teachers_ids as $k=>$v) {
		if(!is_numeric($v)) { 
			die("err54: $v should be numeric");
		}
		if (file_exists("img/$v/$_GET[quiz_configure]") && !is_link("img/$v/$_GET[quiz_configure]")) { 
			$master="img/$v/$_GET[quiz_configure]"; 
			unset($teachers_ids[$k]); 
			break; 
		}
	}

	// Delete and create symlinks to the master
	if(empty($master)) { return; }
	foreach($teachers_ids as $v) {
		if(!is_numeric($v)) { 
			die("err55: $v should be numeric");
		}
		if(!is_dir("img/$v")) {
			mkdir("img/$v");
		}
		if(is_link("img/$v/$_GET[quiz_configure]")) {
			system("rm -rf 'img/$v/$_GET[quiz_configure]'");
		}

		if (!file_exists("img/$v/$_GET[quiz_configure]")) { 
			symlink("../../$master", "img/$v/$_GET[quiz_configure]");
		} else {
			if(!is_link("img/$v/$_GET[quiz_configure]")) {
				$_SESSION['krr']->fatal("err56: Possibly some backup/restore operations introduced a folder in place of a symlink. For the shared quizes, the slave must be a symlink to the master.<br><br>You need to inform the karramba admin who is slave/master here:<br><br>".implode("<br>", $friends));

			}
		}
	}
	exit();
}
/*}}}*/
function manage_owners() {/*{{{*/
	// There may be 3 teachers in one departament and they all use the same quiz for their students.
	echo "<sliding_div id=owners_list>";
	echo "
	<FORM method=POST>
		<div style='width:1px'><help title='".$_SESSION['i18n_help_select_owners']."'></help></div>
		<div style='position: fixed; left:0px; top:0px;'>
			<input input type=submit value='".$_SESSION['i18n_activate_quiz']."' id='finished_selecting_owners'>
			<input input type=submit value='".$_SESSION['i18n_cancel']."'>
		</div>
		<input type=hidden name=do_modify_owners>
		<input id=owners_collector_ids type=hidden name=active_teachers_ids value=''>
	</FORM>";
	foreach($_SESSION['krr']->query("SELECT * FROM teachers WHERE id IN (SELECT teacher_id FROM quizes_owners WHERE quiz_id=$1) ORDER BY last_name", array($_GET['quiz_configure'])) as $arr) { 
		extract($arr);
		$varClass=isChecked(1);
		echo "<div class='$varClass owners'><input type=hidden value=$id>$last_name $first_name</div><br>";
	}
	foreach($_SESSION['krr']->query("SELECT * FROM teachers WHERE id NOT IN (SELECT teacher_id FROM quizes_owners WHERE quiz_id=$1) ORDER BY last_name", array($_GET['quiz_configure'])) as $arr) { 
		extract($arr);
		$varClass=isChecked(0);
		echo "<div class='$varClass owners'><input type=hidden value=$id>$last_name $first_name</div><br>";
	}
	echo "<hr>";
	echo "<br><br><br>";
	echo "</sliding_div>";	
}
/*}}}*/
function menu(){/*{{{*/
	extract($_SESSION);
	if(isset($_SESSION['KARRAMBA_EXTRA_MENUS']['admin_menu'])) { $extra_menus=$_SESSION['KARRAMBA_EXTRA_MENUS']['admin_menu']; }  else { $extra_menus=''; }

	echo "<teacher_menu> 
		  <a title='$i18n_run_quizes' href=?run_quizes class=rlink><i class='fas fa-cog fa-1x'></i></a>
		  <a title='$i18n_quizes_results' href=?quizes_results_by_date class=rlink><i class='fas fa-trophy fa-1x'></i></a>
		  <a title='Students' href=?students_list class=rlink><i class='fas fa-user-cog fa-1x'></i></a>
		  <a title='$i18n_configure' href=?quizes_configure class=rlink><i class='fas fa-tools fa-1x'></i></a>
		  <div style='float:right; margin-right:10px'>
		  $extra_menus
		  <a title='$i18n_logout' href=?q class=rlink><i class='fas fa-sign-out-alt fa-1x'></i></a>
		  </div>
		  </teacher_menu>
		  ";
}/*}}}*/
function main() {/*{{{*/
	if(isset($_SESSION['teacher_in'])) {
		if(isset($_GET['as_xlsx'])) { spreadsheet(); }
		if(isset($_GET['as_csv']))  { spreadsheet(); }
	}
	$_SESSION['krr']->htmlHead();
	echo "<link type='text/css' rel='stylesheet' href='css/admin.css' />";

	if(isset($_GET['q']))  { do_logout(); }
	if(isset($_SESSION['user_id']) and empty($_SESSION['teacher_in'])) { 
		logged_outside();	
	}

	if(!isset($_SESSION['teacher_in'])) {
		if(isset($_POST['logMeIn']))        { teacher_do_login(); }
		#if(!isset($_SESSION['teacher_in'])) { teacher_login_form(); } // to use login form
		if(!isset($_SESSION['teacher_in'])) { // to use Oauth, eg login with Microsoft page
			header('Location:/sgsp/pracownik/index.php?wroc_do=karramba');
		}
	} 
	if(isset($_SESSION['teacher_in'])) {
		menu();
		echo "<teacher_body>";
		if(isset($_GET['debug_student_quiz']))       { $_SESSION['krr']->db_serve_interrupted_quiz($_GET['debug_student_quiz'], 1); }
		if(isset($_POST['update_student']))          { update_student(); }
		if(isset($_GET['manage_students']))          { manage_students(); }
		if(isset($_POST['do_modify_owners']))        { do_modify_owners(); }
		if(isset($_POST['quiz_add']))                { quiz_add(); }
		if(isset($_POST['quiz_remove']))             { quiz_remove(); }
		if(isset($_POST['quiz_update']))             { quiz_update(); }
		if(isset($_GET['quiz_configure']))           { quiz_configure(); }
		if(isset($_POST['run_quiz']))                { run_quiz(); }
		if(isset($_POST['stop_quiz']))               { stop_quiz(); }
		if(isset($_GET['run_quizes']))               { run_quizes(); monitor_logins(); }
		if(isset($_GET['quizes_results_by_date']))   { quizes_summary(); quizes_results_by_date(); }
		if(isset($_GET['quiz_results_by_date']))     { quiz_results_by_date(); }
		if(isset($_GET['quiz_results_by_name']))     { quiz_results_by_name(); }
		if(isset($_GET['quiz_results_by_name_max'])) { quiz_results_by_name_max(); }
		if(isset($_GET['quizes_configure']))         { quizes_configure(); }
		if(isset($_GET['students_list']))            { students_list(); }
		if(isset($_GET['as_screen']))                { as_screen(); }

		echo "</teacher_body>";
	}
	#$_SESSION['krr']->debugKarramba(); 
}
/*}}}*/
main();

?>
