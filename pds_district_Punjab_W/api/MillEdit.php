<?php

require('../util/Connection.php');
require('../structures/Mill.php');
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

if($_SESSION['district_user']!=$person->getUsername()){
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

// Milling Capacity check
if (!is_numeric($_POST["milling_capacity"])) {
    $errors[] = "Error : Milling Capacity must be numeric";
}

// Inventory Raw Rice check
if (!is_numeric($_POST["Inventory_Raw_Rice"])) {
    $errors[] = "Error : Inventory Raw Rice must be numeric";
}

// Inventory Para Rice check
if (!is_numeric($_POST["Inventory_Para_Rice"])) {
    $errors[] = "Error : Inventory Para Rice must be numeric";
}

// Performance Factor check
if (!is_numeric($_POST["performance_factor"])) {
    $errors[] = "Error : Performance Factor must be numeric";
}

// If any error exists, print all and stop
if (!empty($errors)) {
    foreach ($errors as $error) {
        echo $error . "<br>";
    }
    exit();
}


$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
    
    $district = formatName($_POST["district"]);
    $latitude = $_POST["latitude"];
    $longitude = $_POST["longitude"];
    $name = formatName($_POST["name"]);
    $id = $_POST["id"];
    $type = $_POST["type"];
    $milling_capacity = $_POST["milling_capacity"];
    $Inventory_Raw_Rice = $_POST["Inventory_Raw_Rice"];
    $Inventory_Para_Rice = $_POST["Inventory_Para_Rice"];
    $performance_factor = $_POST["performance_factor"];
    
    $uniqueid = $_POST["uniqueid"];
    $active = $_POST["active"];

    $Mill = new Mill;
    $Mill->setUniqueid($uniqueid);
    $Mill->setDistrict($district);
    $Mill->setLatitude($latitude);
    $Mill->setLongitude($longitude);
    $Mill->setName($name);
    $Mill->setId($id);
    $Mill->setType($type);
    $Mill->setMillingCapacity($milling_capacity);
    $Mill->setInventoryRawRice($Inventory_Raw_Rice);
    $Mill->setInventoryParaRice($Inventory_Para_Rice);
    $Mill->setPerformanceFactor($performance_factor);
    $Mill->setActive($active);

    $query_check = $Mill->checkEdit($Mill);
    $query_result = mysqli_query($con, $query_check);
    $numrows = mysqli_num_rows($query_result);
    if($numrows!=0){
        $row = mysqli_fetch_assoc($query_result);
        $uniqueid_check = $row["uniqueid"];
        if($uniqueid!=$uniqueid_check){
            echo "Error : in updating data as Mill id already exist ID: ".$id;
            echo "</br>";
            exit();
        }
    }

    $query = $Mill->update($Mill);
    mysqli_query($con, $query);

    mysqli_close($con);
	
	$filteredPost = $_POST;
	unset($filteredPost['username'], $filteredPost['password']);
	writeLog("User ->" ." Mill Edit ->". $_SESSION['district_user'] . "| Requested JSON -> " . json_encode($filteredPost));

    echo "<script>window.location.href = '../Mill.php';</script>";
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>
