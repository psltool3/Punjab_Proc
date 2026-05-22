<?php

require('../util/Connection.php');
require('../structures/PC.php');
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

if(!isStringNumber($_POST["Paddy_Procurement"])){
	echo "Error : Check Paddy Procurement Value";
	exit();
}

$dbHashedPassword = $row['password'];
if(password_verify($person->getPassword(), $dbHashedPassword)){

    $district = formatName($_POST["district"]);
    $name = $_POST["name"];
    $PC_ID = $_POST["PC_ID"];
    $latitude = $_POST["latitude"];
    $longitude = $_POST["longitude"];
    $Paddy_Procurement = $_POST["Paddy_Procurement"];
    $milling_center = $_POST["milling_center"];
   
    $uniqueid = uniqid("PC_",);

    $PC = new PC;
    $PC->setUniqueid(substr($uniqueid,0,15));
    $PC->setDistrict($district);
    $PC->setName($name);
    $PC->setPCID($PC_ID);
    $PC->setLatitude($latitude);
    $PC->setLongitude($longitude);
    $PC->setPaddyProcurement($Paddy_Procurement);
    $PC->setMillingCenter($milling_center);
    
	$PC->setActive("1");

    $query_insert_check = $PC->checkInsert($PC);
    $query_insert_result = mysqli_query($con, $query_insert_check);
    $numrows_insert = mysqli_num_rows($query_insert_result);
    if($numrows_insert==0){
        $query = $PC->insert($PC);
        if (!mysqli_query($con, $query)) {
            echo "Error description: " . mysqli_error($con);
            exit();
        }
        mysqli_close($con);
		$filteredPost = $_POST;
		unset($filteredPost['username'], $filteredPost['password']);
		writeLog("User ->" ." PC added ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost));
        echo "<script>window.location.href = '../PC.php';</script>";
        exit();
    }
    else{
        echo "Error : in Insertion as PC_ID already exists";
        exit();
    }
} 
else{
    echo "Error : Password or Username is incorrect";
    exit();
}


?>
<?php require('Fullui.php');  ?>
