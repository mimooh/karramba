<?php
session_name(getenv("KARRAMBA_SESSION_NAME"));
session_start();

// The image must have proper name, no spaces, no national characters, no special characters.
// The image must be sanitized since it can be an evil script in disguise. 
// getimagesize() returns FALSE for no-images. We also check mime. 
// You can only catch this $err codes inside firefox developer console (ctrl+shift+c > network > server response)
// SVG support is desired, but needs some separate sanitizing. Later...
// https://blog.online-convert.com/svg-file-and-its-danger/


if (!empty($_FILES)) {
	if(!preg_match("/^[A-Za-z0-9_]+\.(png|PNG|jpg|JPG|jpeg|JPEG)$/", basename($_FILES['file']['name']))) { $err=1; }
	extract($_SESSION);
	$input= $_FILES['file']['tmp_name'];          
	$info = getimagesize($input);
	if ($info === FALSE)                                                { $err=2; }
	if (($info[2] !== IMAGETYPE_PNG) && ($info[2] !== IMAGETYPE_JPEG))  { $err=3; }

	$dest="img/".$_SESSION['teacher_id']."/".$_REQUEST['id'];
	if (!file_exists("$dest")) {
		mkdir($dest, 0777, true);
	}

	$to=$dest."/".$_FILES['file']['name'];  

	if(empty($err)) { 
		echo "OK: $to\n";
	} else {
		echo "ERR: ".$_FILES['file']['name'].", $err\n"; 
		return;
	}
	move_uploaded_file($input,$to); 
} 
?>
