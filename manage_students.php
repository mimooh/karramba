<?PHP
function group_droplist($group_id) {/*{{{*/
	$r=$_SESSION['krr']->query("SELECT * FROM groups WHERE id=$1 ORDER BY group_name", array($group_id));
	$out="<select name=post[group_id]>";
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
	echo "<tr><th>lp<th>$i18n_last_name<th>$i18n_first_name<th>$i18n_password<th>$i18n_group<th>update<th>index";
	$i=1;
	foreach($r as $k=>$v) { 
		echo "<form method=post><tr>";
		echo "<input type=hidden name=post[id] value=$v[id]>";
		echo "<td>$i";
		echo "<td><input type=text name=post[last_name] value=$v[last_name]>";
		echo "<td><input type=text name=post[first_name] value=$v[first_name]>";
		echo "<td><input  style='background:#f88' type=text name=post[password] value=$v[password]>";
		echo "<td>$group";
		echo "<td><input type=submit name=update_student value='update'>";
		echo "<td style='opacity:0.1; width:10px; color: #fff'><input size=1 type=text name=post[index] value=$v[index]>";
		echo "</form>";
		$i++;
	}
	echo "</table>";
	exit();
}
/*}}}*/
function update_student() {/*{{{*/
	# psql karramba -c "select * from students";
	if(!isset($_POST['update_student'])) { return; }
	$uu=$_SESSION['krr']->prepare_pg_update($_POST['post']);
	$r=$_SESSION['krr']->query("UPDATE students SET $uu WHERE id=$1", fix_nulls($_POST['post']));
	$_SESSION['krr']->msg($_POST['post']['last_name']. " updated");
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
