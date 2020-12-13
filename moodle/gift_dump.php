<?php

function dd() { #{{{
	if(isset($_SESSION['console']) || isset($_SERVER['SHELL'])) { 
		foreach(func_get_args() as $v) {
			$out=print_r($v,1);
			echo $out;
		}
		echo "\n";
	} else {
		foreach(func_get_args() as $v) {
			echo "<pre>";
			$out=print_r($v,1);
			echo htmlspecialchars($out);
			echo "</pre>";
		}
		echo "<br><br>";
	}
}
/*}}}*/
# db schema {{{
#echo "select * from vq order by quiz_id limit 5" | psql karramba

# echo "
# DROP VIEW IF EXISTS vq CASCADE;
# CREATE VIEW vq AS select 
# 	q.quiz_name,
# 	o.teacher_id,
# 	t.last_name,
# 	que.*
# 
# 	FROM questions que
# 	left join quizes q on (que.quiz_id=q.id) 
# 	left join quizes_owners o on (o.quiz_id=q.id) 
# 	left join teachers t on (t.id=o.teacher_id) 
# 	where deleted=false
# ;
# select * from vq limit 5;
# " | psql karramba
/*}}}*/

function query($qq,$arr=[],$success=0) { /*{{{*/
	$connect=pg_connect("dbname=karramba");
	$result=pg_query_params($connect, $qq, $arr);
	$k=pg_fetch_all($result);
	if(is_array($k)) { 
		return $k;
	} else {
		return array();
	}

}
/*}}}*/

$r=query("select * from vq where quiz_id in(1,12) order by id");
$collect=[];
foreach($r as $k=>$v) {
	extract($v);
	$key="${last_name}_${quiz_name}_$quiz_id.txt";
	$key=preg_replace("/\s+/", "_", $key);
	if(!isset($collect[$key])) { $collect[$key]=[]; }
	$vect=str_split($correct_vector);
	if($vect[0]==1) { $op0='='; } else { $op0='~'; } 
	if($vect[1]==1) { $op1='='; } else { $op1='~'; } 
	if($vect[2]==1) { $op2='='; } else { $op2='~'; } 

	$collect[$key][]="$question"."{"."$op0$answer0 $op1$answer1 $op2$answer2"."}";
}

foreach($collect as $k=>$v) {
	file_put_contents(mb_strtolower($k), implode("\n\n", $v));
}


?>
