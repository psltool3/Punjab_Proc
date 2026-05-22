<?php

require('../util/Connection.php');
require('../structures/Weighbridge.php');
require('../util/SessionFunction.php');
require('../util/Security.php');
require ('../util/Encryption.php');
require('../util/Logger.php');
$nonceValue = 'nonce_value';

require('../structures/Login.php');

if(!SessionCheck()){
	return;
}

require('Header.php');


$person = new Login;
$person->setUsername($_POST["username"]);

$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

if($_SESSION['user']!=$person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);
$numrows = mysqli_num_rows($result);

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
	$Weighbridge = new Weighbridge;
	$Weighbridge->setUniqueid($_POST['uid']);

	$query = $Weighbridge->delete($Weighbridge);

	if($_POST['uid']=="all"){
		$query = $Weighbridge->deleteall($Weighbridge);
	}
	
	$log_query = $Weighbridge->logname($Weighbridge);
	$log_name= "all";
	$log_result = mysqli_query($con,$log_query);
	if ($log_result && $row = $log_result->fetch_assoc()) {
		$log_name =  $row['Name'];
	}

	mysqli_query($con,$query);
	mysqli_close($con);
	$filteredPost = $_POST;
	unset($filteredPost['username'], $filteredPost['password']);
	writeLog("User ->" ." Weighbridge deleted -> ". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost) . " | " . $log_name);
	echo "<script>window.location.href = '../Weighbridge.php';</script>";  
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>