<?php

require('../util/Connection.php');
require('../structures/MillingCenter.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Security.php');
require('../util/Encryption.php');
require('../util/Logger.php');
$nonceValue = 'nonce_value';

if(!SessionCheck()){
	return;
}

require('Header.php');

function formatName($name) {
    $name = ucwords(strtolower(trim($name)));
    return $name;
}

$person = new Login;
$person->setUsername($_POST["username"]);
$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

if($_SESSION['user'] != $person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

$dbHashedPassword = $row['password'];

if(password_verify($person->getPassword(), $dbHashedPassword)){
	$MillingCenter = new MillingCenter;
	$MillingCenter->setName(formatName(str_replace("'", "", $_POST['name'])));
	$MillingCenter->setId(str_replace("'", "", $_POST['uid']));

	$query = $MillingCenter->update($MillingCenter);
	$result = mysqli_query($con, $query);

	mysqli_close($con);

	if($result){
		$filteredPost = $_POST;
		unset($filteredPost['username'], $filteredPost['password']);
		writeLog("User -> Milling Center Edit -> ".$_SESSION['user']."| Requested JSON -> ".json_encode($filteredPost));
		echo "<script>window.location.href = '../MillingCenter.php';</script>";
	} else {
		echo "Error : in update";
	}
} else {
	echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php'); ?>
