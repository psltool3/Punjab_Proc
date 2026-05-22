<?php

require('../util/Connection.php');
require('../structures/Mill.php');
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

if($_SESSION['district_user']!=$person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);
$numrows = mysqli_num_rows($result);

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
	$Mill = new Mill;
	$Mill->setUniqueid($_POST['uid']);

	$query = $Mill->delete($Mill);

	if($_POST['uid']=="all"){
		$query = $Mill->deleteall($Mill);
	}
	
	$log_query = $Mill->logname($Mill);
	$log_name= "all";
	$log_result = mysqli_query($con,$log_query);
	if ($log_result && $row = $log_result->fetch_assoc()) {
		$log_name =  $row['name'];
	}

	mysqli_query($con,$query);
	mysqli_close($con);
	$filteredPost = $_POST;
	unset($filteredPost['username'], $filteredPost['password']);
	writeLog("User ->" ." Mill deleted -> ". $_SESSION['district_user'] . "| Requested JSON -> " . json_encode($filteredPost) . " | " . $log_name);
	echo "<script>window.location.href = '../Mill.php';</script>";  
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>
