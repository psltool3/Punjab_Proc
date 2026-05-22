<?php
require('../util/Connection.php');
require('../structures/PC.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
ini_set('max_execution_time', 3000);
require('../util/Logger.php');
session_start();
require('../util/Security.php');
require ('../util/Encryption.php');

$nonceValue = 'nonce_value';

require('Header.php');

$mapData = [
    "PC_District"       => "district",
    "Mill_Centre"       => "milling_center",
    "PC_Name"           => "name",
    "PC_ID"             => "PC_ID",
    "PC_Latitude"       => "latitude",
    "PC_Longitude"      => "longitude",
    "Paddy_Procurement" => "Paddy_Procurement",
    "Active/Not-Active" => "active"
];

// Reverse mapping
$reverseMapData = array_flip($mapData);

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

$dbHashedPassword = $row['password'];

if(password_verify($person->getPassword(), $dbHashedPassword)){
$districts = [];
$query = "SELECT name FROM districts WHERE 1";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows>0){
    while($row=mysqli_fetch_assoc($result)){
        array_push($districts,$row["name"]);
    }
}

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

// Filter the excel data
function filterData(&$str){
    $str = str_replace("\t", "", $str);
}

$redirect = 1;

try{
        $fileName = $_FILES["file"]["tmp_name"];
        if ($_FILES["file"]["size"] > 0) {
            $file = fopen($fileName, "r");
            $i = 0;
            $district = -1;
            $milling_center = -1;
            $name = -1;
            $PC_ID = -1;
            $latitude = -1;
            $longitude = -1;
            $Paddy_Procurement = -1;
            $active = -1;

            while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                if($i>0){
                    if($district<0 or $milling_center<0 or $name<0 or $PC_ID<0 or $latitude<0 or $longitude<0 or $Paddy_Procurement<0 or $active<0){
                        echo "Error : You have modified Template Header, please check";
                        exit();
                    }
                    if(!isValidCoordinate($column[$latitude],'latitude') or !isValidCoordinate($column[$longitude],'longitude')){
                        echo "Error : Check Latitude and Longitude Value Latitude: ".$column[$latitude]." Longitude: ".$column[$longitude];
                        echo "</br>";
                        $redirect = 0;
                    }

                    if(!isStringNumber($column[$Paddy_Procurement])){
                        echo "Error : Check Paddy Procurement Value: ".$column[$Paddy_Procurement];
                        echo "</br>";
                        $redirect = 0;
                    }
                    
                    if (!is_numeric($column[$latitude]) || $column[$latitude] >= 40) {
						echo "Error : Latitude must be less than 40. Given: " . $column[$latitude];
						echo "</br>";
						$redirect = 0;
					}

					if (!is_numeric($column[$longitude]) || $column[$longitude] <= 65) {
						echo "Error : Longitude must be more than 65. Given: " . $column[$longitude];
						echo "</br>";
						$redirect = 0;
					}
					if (
						!isset($column[$PC_ID]) ||
						!preg_match('/^[A-Za-z0-9]+$/', $column[$PC_ID])
					) {
						echo "Error: PC_ID should not contain spaces or any special characters: " . ($column[$PC_ID] ?? 'Missing');
						echo "<br>";
						$redirect = 0;
					}	
                    if(!in_array($column[$district], $districts)){
                        echo "Error : Check District Name: ".$column[$district];
                        echo "</br>";
                        $redirect = 0;
                    }
                    if(!($column[$active]==0 || $column[$active]==1)){
                        echo "Error : Check value of active/inactive column: ".$column[$active];
                        echo "</br>";
                        $redirect = 0;
                    }
                    $PC = new PC;
                    filterData($column[$latitude]);
                    filterData($column[$longitude]);
                    filterData($column[$name]);
                    filterData($column[$PC_ID]);
                    filterData($column[$Paddy_Procurement]);
                    filterData($column[$active]);

                    $PC->setDistrict(ucwords(strtolower($column[$district])));
                    $PC->setMillingCenter($column[$milling_center]);
                    $PC->setLatitude($column[$latitude]);
                    $PC->setLongitude($column[$longitude]);
                    $PC->setName($column[$name]);
                    $PC->setPCID($column[$PC_ID]);
                    $PC->setPaddyProcurement($column[$Paddy_Procurement]);
                    $PC->setActive($column[$active]);
                    $query_check = $PC->checkEdit($PC);
                    $query_result = mysqli_query($con, $query_check);
                    $numrows = mysqli_num_rows($query_result);
                    if($numrows==0){
                        echo "Error : in loading data as PC_ID doesn't exist : ".$column[$PC_ID];
                        echo "</br>";
                        $redirect = 0;
                    }
                }
                else{
                    $column[0] = preg_replace('/^\xEF\xBB\xBF/', '', $column[0]);
                    for($j=0;$j<count($column);$j++){
                        switch(trim($column[$j])){
                            case $reverseMapData["district"]:
                                $district = $j;
                                break;
                            case $reverseMapData["milling_center"]:
                                $milling_center = $j;
                                break;
                            case $reverseMapData["latitude"]:
                                $latitude = $j;
                                break;
                            case $reverseMapData["longitude"]:
                                $longitude = $j;
                                break;
                            case $reverseMapData["name"]:
                                $name = $j;
                                break;
                            case $reverseMapData["PC_ID"]:
                                $PC_ID = $j;
                                break;
                            case $reverseMapData["Paddy_Procurement"]:
                                $Paddy_Procurement = $j;
                                break;
                            case $reverseMapData["active"]:
                                $active = $j;
                                break;
                        }
                    }
                }
                $i = $i+1;
            }
        }
}
catch(Exception $e){
    echo "Error : Please check data in .csv file";
    exit();
}

if($redirect==0){
    exit();
}

try{
        $fileName = $_FILES["file"]["tmp_name"];
        if ($_FILES["file"]["size"] > 0) {
            $file = fopen($fileName, "r");
            $i = 0;
            $district = -1;
            $milling_center = -1;
            $name = -1;
            $PC_ID = -1;
            $latitude = -1;
            $longitude = -1;
            $Paddy_Procurement = -1;
            $active = -1;

            while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
                if($i>0){
                    $PC = new PC;
                    filterData($column[$district]);
                    filterData($column[$milling_center]);
                    filterData($column[$latitude]);
                    filterData($column[$longitude]);
                    filterData($column[$name]);
                    filterData($column[$PC_ID]);
                    filterData($column[$Paddy_Procurement]);
                    filterData($column[$active]);

                    $PC->setDistrict($column[$district]);
                    $PC->setMillingCenter($column[$milling_center]);
                    $PC->setLatitude($column[$latitude]);
                    $PC->setLongitude($column[$longitude]);
                    $PC->setName($column[$name]);
                    $PC->setPCID($column[$PC_ID]);
                    $PC->setPaddyProcurement($column[$Paddy_Procurement]);
                    $PC->setActive($column[$active]);
                    $query_check = $PC->checkEdit($PC);
                    $query_result = mysqli_query($con, $query_check);
                    $numrows = mysqli_num_rows($query_result);
                    if($numrows==0){
                        echo "Error : in loading data as PC_ID doesn't exist : ".$column[$PC_ID];
                        echo "</br>";
                        $redirect = 0;
                    }
                    writeLog("User ->" ." PC Edit -> ". $_SESSION['user'] . "| " . $PC->getName());
                    $query_update = $PC->updateEdit($PC);
                    mysqli_query($con, $query_update);
                }
                else{
                    $column[0] = preg_replace('/^\xEF\xBB\xBF/', '', $column[0]);
                    for($j=0;$j<count($column);$j++){
                        switch(trim($column[$j])){
                            case $reverseMapData["district"]:
                                $district = $j;
                                break;
                            case $reverseMapData["milling_center"]:
                                $milling_center = $j;
                                break;
                            case $reverseMapData["latitude"]:
                                $latitude = $j;
                                break;
                            case $reverseMapData["longitude"]:
                                $longitude = $j;
                                break;
                            case $reverseMapData["name"]:
                                $name = $j;
                                break;
                            case $reverseMapData["PC_ID"]:
                                $PC_ID = $j;
                                break;
                            case $reverseMapData["Paddy_Procurement"]:
                                $Paddy_Procurement = $j;
                                break;
                            case $reverseMapData["active"]:
                                $active = $j;
                                break;
                        }
                    }
                }
                $i = $i+1;
            }
            if($redirect==1){
                echo "<script>window.location.href = '../PC.php';</script>";
            }
        }
}
catch(Exception $e){
    echo "Error : Please check data in  .csv file";
}
}
else{
    echo "Error : Password or Username is incorrect";
}
?>
<?php require('Fullui.php');  ?>