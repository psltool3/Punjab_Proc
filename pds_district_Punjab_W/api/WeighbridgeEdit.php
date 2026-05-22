<?php

require('../util/Connection.php');
require('../structures/Weighbridge.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Security.php');
require ('../util/Encryption.php');
require('../util/Logger.php');
$nonceValue = 'nonce_value';

if(!SessionCheck()){
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

if($_SESSION['district_user']!=$person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_assoc($result);
$numrows = mysqli_num_rows($result);

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
    $uniqueid = $_POST["uniqueid"];
    $active = $_POST["active"];

    $Weighbridge = new Weighbridge;
    $Weighbridge->setUniqueid($uniqueid);
    $Weighbridge->setDistrict($district);
    $Weighbridge->setLatitude($Latitude);
    $Weighbridge->setLongitude($Longitude);
    $Weighbridge->setName($Name);
    $Weighbridge->setId($ID);
    $Weighbridge->setStoragePoint($Storage_Point);
    $Weighbridge->setCapacity($Capacity);
    $Weighbridge->setActive($active);

    $query_check = $Weighbridge->checkInsert($Weighbridge);
    $query_result = mysqli_query($con, $query_check);
    $numrows = mysqli_num_rows($query_result);
    if($numrows!=0){
        $row = mysqli_fetch_assoc($query_result);
        $uniqueid_check = $row["uniqueid"];
        if($uniqueid!=$uniqueid_check){
            echo "Error : in updating data as Weighbridge ID already exists: ".$ID;
            echo "</br>";
            exit();
        }
    }

    $query = $Weighbridge->update($Weighbridge);
    mysqli_query($con, $query);

    mysqli_close($con);
	
	$filteredPost = $_POST;
	unset($filteredPost['username'], $filteredPost['password']);
	writeLog("User ->" ." Weighbridge Edit ->". $_SESSION['district_user'] . "| Requested JSON -> " . json_encode($filteredPost));

    echo "<script>window.location.href = '../Weighbridge.php';</script>";
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>
