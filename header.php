<?php
require_once("db.php");
if(isset($_POST["log"])){
	$u = $_POST["uname"];
	$p = $_POST["pass"];
	$sql = "SELECT * FROM users ".
			"WHERE username='$u' AND password='$p'";
	$result = mysqli_query($con,$sql);
	$match = mysqli_num_rows($result);
	if($match > 0){
		header("Location:admin.php");
	}else{
		echo "not matched";
	}
}

?>
<!DOCTYPE html>
<html>
	<head>
		<title>Login</title>
	</head>
	
	<body>
		<form method="post" action="login.php">
			<label>USERNAME</label>
			<input type="text" name="uname" >
			<label>PASSWORD</label>
			<input type="password" name="pass" >
			<input type="submit" value="login" name="log">
		</form>
	</body>
</html>