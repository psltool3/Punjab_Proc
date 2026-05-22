<?php

require('../util/Connection.php');
require('../structures/Block.php');
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

if(<?php

require('../util/Connection.php');
require('../structures/Block.php');
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
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows == 0){
	echo "Error : Password or Username is incorrect";
	return;
}

$Block = new Block;
$Block->setId(uniqid());
$Block->setName(formatName($_POST['name']));

$query = $Block->check($Block);
$result = mysqli_query($con, $query);
$numrows = mysqli_num_rows($result);
if($numrows > 0){
	echo "Error : Block name already exists";
	exit();
}
$query = $Block->insert($Block);
mysqli_query($con, $query);
mysqli_close($con);

$filteredPost = $_POST;
unset($filteredPost['username'], $filteredPost['password']);
writeLog("User -> Block added -> ".$_SESSION['user']."| Requested JSON -> ".json_encode($filteredPost));

echo "<script>window.location.href = '../Block.php';</script>";

?>
<?php require('Fullui.php'); ?>
SESSION['district_user'] != $person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows == 0){
	echo "Error : Password or Username is incorrect";
	return;
}

$Block = new Block;
$Block->setId(uniqid());
$Block->setName(formatName($_POST['name']));

$query = $Block->check($Block);
$result = mysqli_query($con, $query);
$numrows = mysqli_num_rows($result);
if($numrows > 0){
	echo "Error : Block name already exists";
	exit();
}
$query = $Block->insert($Block);
mysqli_query($con, $query);
mysqli_close($con);

$filteredPost = $_POST;
unset($filteredPost['username'], $filteredPost['password']);
writeLog("User -> Block added -> ".<?php

require('../util/Connection.php');
require('../structures/Block.php');
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
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows == 0){
	echo "Error : Password or Username is incorrect";
	return;
}

$Block = new Block;
$Block->setId(uniqid());
$Block->setName(formatName($_POST['name']));

$query = $Block->check($Block);
$result = mysqli_query($con, $query);
$numrows = mysqli_num_rows($result);
if($numrows > 0){
	echo "Error : Block name already exists";
	exit();
}
$query = $Block->insert($Block);
mysqli_query($con, $query);
mysqli_close($con);

$filteredPost = $_POST;
unset($filteredPost['username'], $filteredPost['password']);
writeLog("User -> Block added -> ".$_SESSION['user']."| Requested JSON -> ".json_encode($filteredPost));

echo "<script>window.location.href = '../Block.php';</script>";

?>
<?php require('Fullui.php'); ?>
SESSION['district_user']."| Requested JSON -> ".json_encode($filteredPost));

echo "<script>window.location.href = '../Block.php';</script>";

?>
<?php require('Fullui.php'); ?>
