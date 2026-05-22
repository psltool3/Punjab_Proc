<?php

require('../util/Connection.php');
require('../structures/PC.php');
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
	$PC = new PC;
	$PC->setUniqueid($_POST['uid']);

	$query = $PC->delete($PC);

	if($_POST['uid']=="all"){
		$query = $PC->deleteall($PC);
	}
	
	$log_query = $PC->logname($PC);
	$log_name= "all";
	$log_result = mysqli_query($con,$log_query);
	if ($log_result && $row = $log_result->fetch_assoc()) {
		$log_name =  $row['name'];
	}

	mysqli_query($con,$query);
	mysqli_close($con);
	$filteredPost = $_POST;
	unset($filteredPost['username'], $filteredPost['password']);
	writeLog("User ->" ." PC deleted -> ". $_SESSION['district_user'] . "| Requested JSON -> " . json_encode($filteredPost) . " | " . $log_name);
	echo "<script>window.location.href = '../PC.php';</script>";  
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>
