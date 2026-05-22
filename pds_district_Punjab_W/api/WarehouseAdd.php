<?php

require('../util/Connection.php');
require('../structures/Warehouse.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Logger.php');

require('../util/Security.php');
require ('../util/Encryption.php');
$nonceValue = 'nonce_value';

if(!SessionCheck()){
	return;
}

require('Header.php');

function formatName($name) {
    $name = preg_replace('/[^a-zA-Z ]/', '', $name);
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

if($numrows == 0){
	echo "Error : Password or Username is incorrect";
	return;
}

if(!isValidCoordinate($_POST["latitude"],'latitude') or !isValidCoordinate($_POST["longitude"],'longitude')){
	echo "Error : Check Latitude and Longitude Value";
	exit();
}

if(isset($_POST["Capacity_Available"]) && $_POST["Capacity_Available"] != "" && !isStringNumber($_POST["Capacity_Available"])){
	echo "Error : Check Capacity Available Value";
	exit();
}

if (
    !isValidCoordinate($_POST["latitude"], 'latitude') ||
    !isValidCoordinate($_POST["longitude"], 'longitude') ||
    $_POST["latitude"] >= 40 ||
    $_POST["longitude"] <= 65
) {
    echo "Error : Latitude must be less than 40 and Longitude must be greater than 65";
    exit();
}
$errors = [];

if (!empty($errors)) {
	echo implode("</br>", $errors);
	exit();
}

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
    
    $district = formatName($_POST["district"]);
    $latitude = $_POST["latitude"];
    $longitude = $_POST["longitude"];
    $name = formatName($_POST["name"]);
    $id = $_POST["id"];
    $Storage_Point = isset($_POST["Storage_Point"]) ? $_POST["Storage_Point"] : "0";
    $Capacity_Available = isset($_POST["Capacity_Available"]) ? $_POST["Capacity_Available"] : "0";
    $warehousetype = $_POST["warehousetype"];
    $uniqueid = uniqid("WH_",);

    $Warehouse = new Warehouse;
    $Warehouse->setUniqueid(substr($uniqueid,0,15));
    $Warehouse->setDistrict($district);
    $Warehouse->setLatitude($latitude);
    $Warehouse->setLongitude($longitude);
    $Warehouse->setName($name);
    $Warehouse->setId($id);
    $Warehouse->setStoragePoint($Storage_Point);
    $Warehouse->setCapacityAvailable($Capacity_Available);
    $Warehouse->setWarehousetype($warehousetype);
    $Warehouse->setActive("1");

    $query_insert_check = $Warehouse->checkInsert($Warehouse);
    $query_insert_result = mysqli_query($con, $query_insert_check);
    $numrows_insert = mysqli_num_rows($query_insert_result);
    if($numrows_insert==0){
        $query = $Warehouse->insert($Warehouse);
        mysqli_query($con, $query);
        mysqli_close($con);
		
		$filteredPost = $_POST;
		unset($filteredPost['username'], $filteredPost['password']);
		writeLog("district_user ->" ." Warehouse added ->". $_SESSION['district_user'] . "| Requested JSON -> " . json_encode($filteredPost));
		
        echo "<script>window.location.href = '../Warehouse.php';</script>";
    }
    else{
        echo "Error : in Insertion as Warehouse id already exist";
    }
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>