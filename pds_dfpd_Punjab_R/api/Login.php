<?php

require('../util/Connection.php');
require('../util/Security.php');
require('../structures/Login.php');
require ('../util/Encryption.php');
require ('../util/SessionFunction.php');
session_start();
$nonceValue = 'nonce_value';

if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Something went wrong. Request denied.");
}

if (empty($_POST['captchainput'])||$_SESSION['captcha'] !=  $_POST['captchainput']){
	die("Please Check Captcha");
}

checkLoginRateLimit($con);
$person = new Login;
$person->setUsername($_POST["username"]);
$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);

if(empty($row)){
    recordLoginAttempt($con);
	die("Error : Password or Username is incorrect");
}

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
 if($row['role']=="admin"){
		$count = 1 + $row['count'];
		$uniqueId = uniqid();
		$authToken = md5($uniqueId);
		$currentLoginTime = date("Y-m-d H:i:s");
		$queryUpdate = "UPDATE login SET token='$authToken',lastlogin='$currentLoginTime',count='$count' WHERE username='".$person->getUsername()."'";
		mysqli_query($con,$queryUpdate);
		
		$_SESSION['user'] = $person->getUsername();
		$_SESSION['token'] = $authToken;
		
		mysqli_close($con);
		echo "<script>window.location.href = '../OptimisedDataAll.php';</script>";
    }
} 
else{
    recordLoginAttempt($con);
    echo "Error : Password or Username is incorrect";
}

?>
