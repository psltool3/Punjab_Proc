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

if (
    !isValidCoordinate($_POST["latitude"], 'latitude') ||
    !isValidCoordinate($_POST["longitude"], 'longitude') ||
    $_POST["latitude"] >= 40 ||
    $_POST["longitude"] <= 65
) {
    echo "Error : Latitude must be less than 40 and Longitude must be greater than 65";
    exit();
}

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){
    
    $district = formatName($_POST["district"]);
    $name = $_POST["name"];
    $mill_id = $_POST["mill_id"];
    $latitude = $_POST["latitude"];
    $longitude = $_POST["longitude"];
    $milling_center = $_POST["milling_center"];
    $milling_process = $_POST["milling_process"];
    $uniqueid = $_POST["uniqueid"];
    $active = $_POST["active"];

    $Mill = new Mill;
    $Mill->setUniqueid($uniqueid);
    $Mill->setDistrict($district);
    $Mill->setName($name);
    $Mill->setMillId($mill_id);
    $Mill->setLatitude($latitude);
    $Mill->setLongitude($longitude);
    $Mill->setMillingCenter($milling_center);
    $Mill->setMillingProcess($milling_process);
    $Mill->setActive($active);

    $query_check = $Mill->checkEdit($Mill);
    $query_result = mysqli_query($con, $query_check);
    $numrows = mysqli_num_rows($query_result);
    if($numrows!=0){
        $row = mysqli_fetch_assoc($query_result);
        $uniqueid_check = $row["uniqueid"];
        if($uniqueid!=$uniqueid_check){
            echo "Error : in updating data as Mill ID already exists: ".$mill_id;
            echo "</br>";
            exit();
        }
    }

    $query = $Mill->update($Mill);
    mysqli_query($con, $query);

    mysqli_close($con);
	
	$filteredPost = $_POST;
	unset($filteredPost['username'], $filteredPost['password']);
	writeLog("User ->" ." Mill Edit ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost));

    echo "<script>window.location.href = '../Mill.php';</script>";
} 
else{
    echo "Error : Password or Username is incorrect";
}

?>
<?php require('Fullui.php');  ?>
