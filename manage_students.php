<?PHP
function group_droplist($group_id) {/*{{{*/
	$r=$_SESSION['krr']->query("SELECT * FROM groups WHERE id=$1 ORDER BY group_name", array($group_id));
	$out="<select name='group'>";
	$out.="<option value=".$r[0]['id'].">".$r[0]['group_name']."</option>";
	$out.="<option value=-1></option>";

	$r=$_SESSION['krr']->query("SELECT * FROM groups order by group_name");
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
	extract($_SESSION);
	$group=group_droplist($_GET['manage_students']);
	$r=$_SESSION['krr']->query("SELECT * FROM students WHERE group_id=$1 ORDER BY last_name, first_name", array($_GET['manage_students']));
	echo "<table>";
	echo "<tr><th>lp<th>$i18n_last_name<th>$i18n_first_name<th>index<th>$i18n_password<th>$i18n_group<th>update";
	$i=1;
	foreach($r as $k=>$v) { 
		echo "<form method=post><tr>";
		echo "<input type=hidden name=id value=$v[id]>";
		echo "<td>$i";
		echo "<td><input type=text name=last_name value=$v[last_name]>";
		echo "<td><input type=text name=first_name value=$v[first_name]>";
		echo "<td><input type=text name=index value=$v[index]>";
		echo "<td><input  style='background:wheat' type=text name=password value=$v[password]>";
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
	$r=$_SESSION['krr']->query("UPDATE students SET last_name=$2, first_name=$3, index=$4, password=$5, group_id=$6 WHERE id=$1", fix_nulls($_POST));
	$_SESSION['krr']->msg($_POST['last_name']. " updated");
}
/*}}}*/
function students_list() {/*{{{*/
	# psql karramba -c "select * from students where group_id=1126";
	# psql karramba -c "delete from students where group_id=1126";
	# psql karramba -c "select * from groups order by id";
	# psql karramba -c "delete from groups where id=1"
	# psql karramba -c "INSERT INTO groups(id,group_name) values(1,'0.GR.TESTOWA')";
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
		if($s[0]['count'] > 0 ) { 
			$count=$s[0]['count']; 
		} else {
			$count='';
		}
		echo "<tr><td><a class=blink href=?manage_students=$v[id]>$v[group_name]</a><td>$count";
	}
	echo "</table>";
}
/*}}}*/

?>
