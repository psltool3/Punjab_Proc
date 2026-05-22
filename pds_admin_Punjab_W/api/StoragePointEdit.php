<?php

require('../util/Connection.php');
require('../structures/StoragePoint.php');
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
    // Check if the value is a number and not a string
    if (!is_numeric($value)) {
        return false;
    }
	
    // Convert the value to a float
    $coordinate = floatval($value);

    // Check if it's latitude or longitude and validate within the range
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
$numrows = mysqli_num_rows($result);


if(!isValidCoordinate($_POST["latitude"],'latitude') or !isValidCoordinate($_POST["longitude"],'longitude')){
	echo "Error : Check Latitude and Longitude Value";
	exit();
}

if(!isStringNumber($_POST["capacity"])){
	echo "Error : Check Capacity Value";
	exit();
}
if (!is_numeric($column[$latitude]) || $column[$latitude] >= 40) {
	echo "Error : Latitude must be less than 40. Given: " . $column[$latitude];
	echo "</br>";
	$redirect = 0;
}

// Longitude check (must be more than 65)
if (!is_numeric($column[$longitude]) || $column[$longitude] <= 65) {
	echo "Error : Longitude must be more than 65. Given: " . $column[$longitude];
	echo "</br>";
	$redirect = 0;
}

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
    
    $district = formatName($_POST["district"]);
    $latitude = $_POST["latitude"];
    $longitude = $_POST["longitude"];
    $name = formatName($_POST["name"]);
    $id = $_POST["id"];
    $type = $_POST["type"];
    $capacity = $_POST["capacity"];
    $warehousetype = $_POST["warehousetype"];
    $uniqueid = $_POST["uniqueid"];
    $active = $_POST["active"];

    $StoragePoint = new StoragePoint;
    $StoragePoint->setUniqueid($uniqueid);
    $StoragePoint->setDistrict($district);
    $StoragePoint->setLatitude($latitude);
    $StoragePoint->setLongitude($longitude);
    $StoragePoint->setName($name);
    $StoragePoint->setId($id);
    $StoragePoint->setType($type);
    $StoragePoint->setCapacity($capacity);
    $StoragePoint->setWarehousetype($warehousetype);
    $StoragePoint->setActive($active);

    $query_check = $StoragePoint->checkInsert($StoragePoint);
    $query_result = mysqli_query($con, $query_check);
    $numrows = mysqli_num_rows($query_result);
    if($numrows!=0){
        $row = mysqli_fetch_assoc($query_result);
        $uniqueid_check = $row["uniqueid"];
        if($uniqueid!=$uniqueid_check){
            echo "Error : in updating data as Storage Point id already exist ID: ".$id;
            echo "</br>";
            exit();
        }
    }

    $query = $StoragePoint->update($StoragePoint);
    mysqli_query($con, $query);

    mysqli_close($con);
	
	$filteredPost = $_POST;
	unset($filteredPost['username'], $filteredPost['password']);
	writeLog("User ->" ." Storage Point Edit ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost));

    echo "<script>window.location.href = '../StoragePoint.php';</script>";
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>
