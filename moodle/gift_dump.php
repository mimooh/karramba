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
function moodle_ctrls_replace($v) {
	foreach(array(':', '~', '=', '#', '{', '}') as $i) {
		$v['question']=str_replace($i, "\\$i", $v['question']);
		$v['answer0']=str_replace($i, "\\$i", $v['answer0']);
		$v['answer1']=str_replace($i, "\\$i", $v['answer1']);
		$v['answer2']=str_replace($i, "\\$i", $v['answer2']);
	}
	return $v;
}

$r=query("select * from vq order by id");
$collect=[];
$current_key="";
foreach($r as $k=>$v) {
	$v=moodle_ctrls_replace($v);
	extract($v);
	$key="${last_name}_${quiz_name}_$quiz_id.txt";
	if($current_key!=$key) { $current_key=$key; $i=1; }
	$key=preg_replace("/\s+/", "_", $key);
	if(!isset($collect[$key])) { $collect[$key]=[]; }
	$vect=str_split($correct_vector);

	$sum=array_sum($vect);
	if($sum==1) { $percent=100; }
	if($sum==2) { $percent=50; }
	if($sum==3) { $percent=33.3333; }

	if($vect[0]==1) { $op0="~%$percent%"; } else { $op0='~%-100%'; } 
	if($vect[1]==1) { $op1="~%$percent%"; } else { $op1='~%-100%'; } 
	if($vect[2]==1) { $op2="~%$percent%"; } else { $op2='~%-100%'; } 

	$collect[$key][]="::Pytanie_".str_pad($i,3,'0', STR_PAD_LEFT)."_$question::$question"."{"."$op0$answer0 $op1$answer1 $op2$answer2"."}";
	
	$i++;
}

foreach($collect as $k=>$v) {
	$file=str_replace("/", "_", $k);
	file_put_contents(mb_strtolower($file), implode("\n\n", $v)."\n");
}

?>
