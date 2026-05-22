<?php

require('../util/Connection.php');
require('../structures/Weighbridge.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Logger.php');

require('../util/Security.php');
require ('../util/Encryption.php');
$nonceValue = 'nonce_value';

if(!SessionCheck()){
    die("Unauthorized access. Admin login required.");
	return;
}

require('Header.php');


function formatName($name) {
	$name = preg_replace('/[^a-zA-Z0-9_ ]/', '', $name);
    $name = ucwords(strtolower($name));
    return trim($name);
}

function isValidCoordinate($value, $coordinateType) {
    if (!is_numeric($value)) {
        return false;
    }
    $coordinate = floatval($value);
    switch ($coordinateType) {
        case 'latitude':
            return ($coordinate >= -90 && $coordinate <= 90);
        case 'longitude':
            return ($coordinate >= -180 && $coordinate <= 180);
        default:
            return false;
    }
}

function isStringNumber($stringValue) {
    return is_numeric($stringValue);
}

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

if(!isValidCoordinate($_POST["Latitude"],'latitude') or !isValidCoordinate($_POST["Longitude"],'longitude')){
	echo "Error : Check Latitude and Longitude Value";
	exit();
}

if(!isStringNumber($_POST["Capacity"])){
	echo "Error : Check Capacity Value";
	exit();
}

if (
    !isValidCoordinate($_POST["Latitude"], 'latitude') ||
    !isValidCoordinate($_POST["Longitude"], 'longitude') ||
    $_POST["Latitude"] >= 40 ||
    $_POST["Longitude"] <= 65
) {
    echo "Error : Latitude must be less than 40 and Longitude must be greater than 65";
    exit();
}

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){

    $district = formatName($_POST["district"]);
    $Latitude = $_POST["Latitude"];
    $Longitude = $_POST["Longitude"];
    $Name = $_POST["Name"];
    $ID = $_POST["ID"];
    $Storage_Point = $_POST["Storage_Point"];
    $Capacity = $_POST["Capacity"];
    $uniqueid = uniqid("WB_",);

    $Weighbridge = new Weighbridge;
    $Weighbridge->setUniqueid(substr($uniqueid,0,15));
    $Weighbridge->setDistrict($district);
    $Weighbridge->setLatitude($Latitude);
    $Weighbridge->setLongitude($Longitude);
    $Weighbridge->setName($Name);
    $Weighbridge->setId($ID);
    $Weighbridge->setStoragePoint($Storage_Point);
    $Weighbridge->setCapacity($Capacity);
    $Weighbridge->setActive("1");

    $query_insert_check = $Weighbridge->checkInsert($Weighbridge);
    $query_insert_result = mysqli_query($con, $query_insert_check);
    $numrows_insert = mysqli_num_rows($query_insert_result);
    if($numrows_insert==0){
        $query = $Weighbridge->insert($Weighbridge);
        mysqli_query($con, $query);
        mysqli_close($con);
		$filteredPost = $_POST;
		unset($filteredPost['username'], $filteredPost['password']);
		writeLog("User ->" ." Weighbridge added ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost));
        echo "<script>window.location.href = '../Weighbridge.php';</script>";
    }
    else{
        echo "Error : in Insertion as Weighbridge ID already exists";
    }
} 
else{
    echo "Error : Password or Username is incorrect";
}


?>
<?php require('Fullui.php');  ?>
