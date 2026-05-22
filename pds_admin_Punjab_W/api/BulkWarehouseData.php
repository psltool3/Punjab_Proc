<?php
require('../util/Connection.php');
require('../structures/Warehouse.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Logger.php');
require('../util/Security.php');
require ('../util/Encryption.php');
$nonceValue = 'nonce_value';
ini_set('max_execution_time', 3000);
session_start();


require('Header.php');

$mapData = [
    "District" => "district",
    "Name of Warehouse" => "name",
    "Warehouse ID" => "id",
    "Warehouse Type" => "warehousetype",
    "Storage Point" => "Storage_Point",
    "Latitude" => "latitude",
    "Longitude" => "longitude",
    "Capacity Available" => "Capacity_Available",
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

$redirect = 1;

try{
	$fileName = $_FILES["file"]["tmp_name"];
	if ($_FILES["file"]["size"] > 0) {
		$file = fopen($fileName, "r");
		$i = 0;
		$district = -1;
		$name = -1;
		$id = -2;
		$warehousetype = -3;
		$Storage_Point = -4;
		$latitude = -5;
		$longitude = -6;
		$Capacity_Available = -7;
		$active = -10;
		while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
			if($i>0){
				if($district<0 or $name<0 or $id<0 or $Storage_Point<0 or $Capacity_Available<0 or $latitude<0 or $longitude<0 or $warehousetype<0 or $active<0){
					echo "Error : You have modified Template Header, please check";
					exit();
				}
				if(!isValidCoordinate($column[$latitude],'latitude') or !isValidCoordinate($column[$longitude],'longitude')){
					echo "Error : Check Latitude and Longitude Value Latitude: ".$column[$latitude]." Longitude: ".$column[$longitude];
					echo "</br>";
					$redirect = 0;
				}

				if(!isStringNumber($column[$Capacity_Available])){
					echo "Error : Check Capacity Available Value: ".$column[$Capacity_Available];
					echo "</br>";
					$redirect = 0;
				}

				if(!in_array($column[$district], $districts)){
					echo "Error : Check District Name: ".$column[$district];
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
					!isset($column[$id]) ||
					!preg_match('/^[A-Za-z0-9]+$/', $column[$id])
				) {
					echo "Error: Warehouse ID should not contain spaces or any special characters: " . ($column[$id] ?? 'Missing');
					echo "<br>";
					$redirect = 0;
				}	
				
				if(!($column[$active]==0 || $column[$active]==1)){
					echo "Error : Check value of active/inactive column: ".$column[$active];
					echo "</br>";
					$redirect = 0;
				}
			}
			else{
				for($j=0;$j<count($column);$j++){
					switch($column[$j]){
						case $reverseMapData["district"]:
							$district = $j;
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
						case $reverseMapData["id"]:
							$id = $j;
							break;
				case $reverseMapData["Storage_Point"]:
						$Storage_Point = $j;
						break;
					case $reverseMapData["Capacity_Available"]:
						$Capacity_Available = $j;
							break;
						case $reverseMapData["warehousetype"]:
							$warehousetype = $j;
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
	echo "Error : Error Please check data in  .csv file";
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
			$name = -1;
			$id = -2;
			$warehousetype = -3;
			$Storage_Point = -4;
			$latitude = -5;
			$longitude = -6;
			$Capacity_Available = -7;
			$active = -10;
			while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
				if($i>0){
					if($district<0 or $name<0 or $id<0 or $Storage_Point<0 or $Capacity_Available<0 or $latitude<0 or $longitude<0 or $warehousetype<0 or $active<0){
						echo "Error : You have modified Template Header, please check";
						exit();
					}
					$Warehouse = new Warehouse;
					$uniqueid = uniqid("WH_",);
					$Warehouse->setUniqueid(substr($uniqueid,0,15));
					$Warehouse->setDistrict(ucwords(strtolower($column[$district])));
					$Warehouse->setLatitude($column[$latitude]);
					$Warehouse->setLongitude($column[$longitude]);
					$Warehouse->setName($column[$name]);
					$Warehouse->setId($column[$id]);
					$Warehouse->setStoragePoint($column[$Storage_Point]);
					$Warehouse->setCapacityAvailable($column[$Capacity_Available]);
					$Warehouse->setWarehousetype($column[$warehousetype]);
					$Warehouse->setActive($column[$active]);
					while(true){
						$query_check = $Warehouse->check($Warehouse);
						$query_result = mysqli_query($con, $query_check);
						$numrows = mysqli_num_rows($query_result);
						if($numrows==0){
							break;
						}
						else{
							$uniqueid = uniqid("WH_",);
							$Warehouse->setUniqueid(substr($uniqueid,0,15));
						}
					}
					$query_insert_check = $Warehouse->checkInsert($Warehouse);
					$query_insert_result = mysqli_query($con, $query_insert_check);
					$numrows_insert = mysqli_num_rows($query_insert_result);
					if($numrows_insert==0){
						writeLog("User ->" ." Warehouse Added -> ". $_SESSION['user'] . "| " . $Warehouse->getName());
						$query_add = $Warehouse->insert($Warehouse);
						mysqli_query($con, $query_add);
					}
					else{
						echo "Error : Warehouse with id ".$Warehouse->getId()." Already Exist</br>";
						$redirect = 2;
					}
				}
				else{
					for($j=0;$j<count($column);$j++){
						switch($column[$j]){
							case $reverseMapData["district"]:
								$district = $j;
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
							case $reverseMapData["id"]:
								$id = $j;
								break;
							case $reverseMapData["Storage_Point"]:
								$Storage_Point = $j;
								break;
							case $reverseMapData["Capacity_Available"]:
								$Capacity_Available = $j;
								break;
							case $reverseMapData["warehousetype"]:
								$warehousetype = $j;
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
				echo "<script>window.location.href = '../Warehouse.php';</script>";
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