<?php
require('../util/Connection.php');
require('../structures/Weighbridge.php');
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
    "District" => "District",
    "Storage Point" => "Storage_Point",
    "Name" => "Name",
    "Weighbridge ID" => "ID",
    "Latitude" => "Latitude",
    "Longitude" => "Longitude",
    "Capacity" => "Capacity",
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
			$District = -1;
			$Name = -1;
			$ID = -1;
			$Storage_Point = -1;
			$Latitude = -1;
			$Longitude = -1;
			$Capacity = -1;
			$active = -1;
			while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
				if($i>0){
					if($District<0 or $Name<0 or $ID<0 or $Storage_Point<0 or $Capacity<0 or $Latitude<0 or $Longitude<0 or $active<0){
						echo "Error : You have modified Template Header, please check";
						exit();
					}
					if(!isValidCoordinate($column[$Latitude],'latitude') or !isValidCoordinate($column[$Longitude],'longitude')){
						echo "Error : Check Latitude and Longitude Value Latitude: ".$column[$Latitude]." Longitude: ".$column[$Longitude];
						echo "</br>";
						$redirect = 0;
					}

					if(!isStringNumber($column[$Capacity])){
						echo "Error : Check Capacity Value: ".$column[$Capacity];
						echo "</br>";
						$redirect = 0;
					}
                    
					if(!in_array($column[$District], $districts)){
						echo "Error : Check District Name: ".$column[$District];
						echo "</br>";
						$redirect = 0;
					}
					if (!is_numeric($column[$Latitude]) || $column[$Latitude] >= 40) {
						echo "Error : Latitude must be less than 40. Given: " . $column[$Latitude];
						echo "</br>";
						$redirect = 0;
					}

					if (!is_numeric($column[$Longitude]) || $column[$Longitude] <= 65) {
						echo "Error : Longitude must be more than 65. Given: " . $column[$Longitude];
						echo "</br>";
						$redirect = 0;
					}
					if (
						!isset($column[$ID]) ||
						!preg_match('/^[A-Za-z0-9]+$/', $column[$ID])
					) {
						echo "Error: Weighbridge ID should not contain spaces or any special characters: " . ($column[$ID] ?? 'Missing');
						echo "<br>";
						$redirect = 0;
					}	
				
					if(!($column[$active]==0 || $column[$active]==1)){
						echo "Error : Check value of active/inactive column: ".$column[$active];
						echo "</br>";
						$redirect = 0;
					}
					$Weighbridge = new Weighbridge;
					filterData($column[$Latitude]);
					filterData($column[$Longitude]);
					filterData($column[$Name]);
					filterData($column[$ID]);
					filterData($column[$Storage_Point]);
					filterData($column[$Capacity]);
					filterData($column[$active]);
					$Weighbridge->setDistrict(ucwords(strtolower($column[$District])));
					$Weighbridge->setLatitude($column[$Latitude]);
					$Weighbridge->setLongitude($column[$Longitude]);
					$Weighbridge->setName($column[$Name]);
					$Weighbridge->setId($column[$ID]);
					$Weighbridge->setStoragePoint($column[$Storage_Point]);
					$Weighbridge->setCapacity($column[$Capacity]);
					$Weighbridge->setActive($column[$active]);
					$query_check = $Weighbridge->checkEdit($Weighbridge);
					$query_result = mysqli_query($con, $query_check);
					$numrows = mysqli_num_rows($query_result);
					if($numrows==0){
						echo "Error : in loading data as Weighbridge ID doesn't exist : ".$column[$ID];
						echo "</br>";
						$redirect = 0;
					}
				}
				else{
					for($j=0;$j<count($column);$j++){
						switch($column[$j]){
							case $reverseMapData["District"]:
								$District = $j;
								break;
							case $reverseMapData["Storage_Point"]:
								$Storage_Point = $j;
								break;
							case $reverseMapData["Name"]:
								$Name = $j;
								break;
							case $reverseMapData["ID"]:
								$ID = $j;
								break;
							case $reverseMapData["Latitude"]:
								$Latitude = $j;
								break;
							case $reverseMapData["Longitude"]:
								$Longitude = $j;
								break;
							case $reverseMapData["Capacity"]:
								$Capacity = $j;
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
			$District = 0;
			$Storage_Point = 1;
			$Name = 2;
			$ID = 3;
			$Latitude = 4;
			$Longitude = 5;
			$Capacity = 6;
			
			while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
				if($i>0){
					$Weighbridge = new Weighbridge;
					filterData($column[$District]);
					filterData($column[$Latitude]);
					filterData($column[$Longitude]);
					filterData($column[$Name]);
					filterData($column[$ID]);
					filterData($column[$Storage_Point]);
					filterData($column[$Capacity]);
					$Weighbridge->setDistrict($column[$District]);
					$Weighbridge->setLatitude($column[$Latitude]);
					$Weighbridge->setLongitude($column[$Longitude]);
					$Weighbridge->setName($column[$Name]);
					$Weighbridge->setId($column[$ID]);
					$Weighbridge->setStoragePoint($column[$Storage_Point]);
					$Weighbridge->setCapacity($column[$Capacity]);
					$Weighbridge->setActive($column[7]);
					$query_check = $Weighbridge->checkEdit($Weighbridge);
					$query_result = mysqli_query($con, $query_check);
					$numrows = mysqli_num_rows($query_result);
					if($numrows==0){
						echo "Error : in loading data as Weighbridge ID doesn't exist : ".$column[$ID];
						echo "</br>";
						$redirect = 0;
					}
					writeLog("User ->" ." Weighbridge Edit -> ". $_SESSION['user'] . "| " . $Weighbridge->getName());
					$query_update = $Weighbridge->updateEdit($Weighbridge);
					mysqli_query($con, $query_update);
				}
				else{
					for($j=0;$j<count($column);$j++){
						switch($column[$j]){
							case $reverseMapData["District"]:
								$District = $j;
								break;
							case $reverseMapData["Storage_Point"]:
								$Storage_Point = $j;
								break;
							case $reverseMapData["Name"]:
								$Name = $j;
								break;
							case $reverseMapData["ID"]:
								$ID = $j;
								break;
							case $reverseMapData["Latitude"]:
								$Latitude = $j;
								break;
							case $reverseMapData["Longitude"]:
								$Longitude = $j;
								break;
							case $reverseMapData["Capacity"]:
								$Capacity = $j;
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
				echo "<script>window.location.href = '../Weighbridge.php';</script>";
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