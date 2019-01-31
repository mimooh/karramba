<?PHP
function group_droplist($group) {/*{{{*/
	$r=$_SESSION['krr']->query("SELECT * FROM groups order by group_name");
	$out="<select name='group'>";
	$out.="<option value=$group[group_id]>$group[group_name]</option>";
	$out.="<option value=-1></option>";
	foreach($r as $k=>$v) {
		$out.="<option value=$v[id]>$v[group_name]</option>";
	}
	$out.="</select>";
	return $out;
}
/*}}}*/
function manage_students() {/*{{{*/
	# psql karramba -c "SELECT * FROM students"
	# psql karramba -c "SELECT * FROM groups"
	/*
	psql karramba -c "
	INSERT INTO students(first_name, last_name,group_id) values('Adam', 'Aowski', 1119);
	INSERT INTO students(first_name, last_name,group_id) values('Bdam', 'Bowski', 1119);
	INSERT INTO students(first_name, last_name,group_id) values('Cdam', 'Cowski', 1119);
	INSERT INTO students(first_name, last_name,group_id) values('Ddam', 'Dowski', 1119);
	INSERT INTO students(first_name, last_name,group_id) values('Edam', 'Eowski', 1119);
	"
	*/
	extract($_SESSION);
	$group=group_droplist($_POST['manage_students']);
	$r=$_SESSION['krr']->query("SELECT * FROM students WHERE group_id=$1 ORDER BY last_name, first_name", array($_POST['manage_students']['group_id']));
	echo "<table>";
	echo "<tr><th>lp<th>$i18n_last_name<th>$i18n_first_name<th>$i18n_password<th>$i18n_group<th>update";
	$i=1;
	foreach($r as $k=>$v) { 
		echo "<form method=post><tr>";
		echo "<input type=hidden name=id value=$v[id]>";
		echo "<td>$i";
		echo "<td><input type=text name=last_name value=$v[last_name]>";
		echo "<td><input type=text name=first_name value=$v[first_name]>";
		echo "<td><input type=text name=password value=$v[password]>";
		echo "<td>$group";
		echo "<td><input type=submit name=update_student value='update'></form>";
		$i++;
	}
	echo "</table>";
	exit();
}
/*}}}*/
function update_student() {/*{{{*/
	if(!isset($_POST['update_student'])) { return; }
	unset($_POST['update_student']);
	$r=$_SESSION['krr']->query("UPDATE students SET last_name=$2, first_name=$3, password=$4, group_id=$5 WHERE id=$1", $_POST);
	$_SESSION['krr']->msg($_POST['last_name']. " updated");
}
/*}}}*/
function students_list() {/*{{{*/
	# psql karramba -c "select * from students";
	// KARRAMBA_NEW_STUDENT_SECRET is a token which students need to create a new account
	// You most likely don't need it.

	extract($_SESSION);
	if(!empty(getenv("KARRAMBA_NEW_STUDENT_SECRET"))) { 
		echo "<table><tr><td>$i18n_secret:<td><h1><help title='$i18n_help_what_is_secret'>".getenv("KARRAMBA_NEW_STUDENT_SECRET")."</help></h1></table>";
	}

	$r=$_SESSION['krr']->query("SELECT * FROM groups ORDER BY group_name");
	echo "<table><tr><th>group<th>students";
	foreach($r as $k=>$v) { 
		$s=$_SESSION['krr']->query("SELECT count(*) FROM students WHERE group_id=$1", array($v['id']));
		echo "<tr><td><form style='display:inline' method=post><input type=hidden name=manage_students[group_id] value=$v[id]><input type=submit name=manage_students[group_name] value=$v[group_name]></form><td>".$s[0]['count'];
	}
	echo "</table>";
}
/*}}}*/

?>
