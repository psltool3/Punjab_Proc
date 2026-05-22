<?php
require('../util/Connection.php');
require('../structures/StoragePoint.php');
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
    "District" => "district",
    "Name of Storage Point" => "name",
    "Storage Point ID" => "id",
    "Motorable/Non-Motorable" => "type",
    "Storage Point Type" => "warehousetype",
    "Latitude" => "latitude",
    "Longitude" => "longitude",
    "Capacity" => "capacity",
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


// Filter the excel data 
function filterData(&$str){ 
    $str = str_replace("\t", "", $str);
}

$redirect = 1;

try{
	//if (isset($_POST["submit"])){
		$fileName = $_FILES["file"]["tmp_name"];
		if ($_FILES["file"]["size"] > 0) {
			$file = fopen($fileName, "r");
			$i = 0;
			$district = -1;
			$name = -1;
			$id = -1;
			$warehousetype = -1;
			$type = -1;
			$latitude = -1;
			$longitude = -1;
			$capacity = -1;
			$active = -1;
			while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
				if($i>0){
					if($district<0 or $name<0 or $id<0 or $type<0 or $capacity<0 or $latitude<0 or $longitude<0 or $warehousetype<0 or $active<0){
						echo "Error : You have modified Template Header, please check";
						exit();
					}
					if(!isValidCoordinate($column[$latitude],'latitude') or !isValidCoordinate($column[$longitude],'longitude')){
						echo "Error : Check Latitude and Longitude Value Latitude: ".$column[$latitude]." Longitude: ".$column[$longitude];
						echo "</br>";
						$redirect = 0;
					}

					if(!isStringNumber($column[$capacity])){
						echo "Error : Check Capacity Value: ".$column[$capacity];
						echo "</br>";
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
					$StoragePoint = new StoragePoint;
					filterData($column[$latitude]);
					filterData($column[$longitude]);
					filterData($column[$name]);
					filterData($column[$id]);
					filterData($column[$type]);
					filterData($column[$capacity]);
					filterData($column[$warehousetype]);
					filterData($column[$active]);
					$StoragePoint->setDistrict(ucwords(strtolower($column[$district])));
					$StoragePoint->setLatitude($column[$latitude]);
					$StoragePoint->setLongitude($column[$longitude]);
					$StoragePoint->setName($column[$name]);
					$StoragePoint->setId($column[$id]);
					$StoragePoint->setType($column[$type]);
					$StoragePoint->setCapacity($column[$capacity]);
					$StoragePoint->setWarehousetype($column[$warehousetype]);
					$StoragePoint->setActive($column[$active]);
					$query_check = $StoragePoint->checkEdit($StoragePoint);
					$query_result = mysqli_query($con, $query_check);
					$numrows = mysqli_num_rows($query_result);
					if($numrows==0){
						echo "Error : in loading data as Storage Point id doesn't exist : ".$column[$id];
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
							case $reverseMapData["type"]:
								$type = $j;
								break;
							case $reverseMapData["capacity"]:
								$capacity = $j;
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
	//}
	//else{
	//	echo "Error Please Select .csv file";
	//	exit();
	//}
}
catch(Exception $e){
	echo "Error : Please check data in .csv file";
	exit();
}

if($redirect==0){
	exit();
}

try{
	//if (isset($_POST["submit"])){
		$fileName = $_FILES["file"]["tmp_name"];
		if ($_FILES["file"]["size"] > 0) {
			$file = fopen($fileName, "r");
			$i = 0;
			$district = 0;
			$name = 1;
			$id = 2;
			$warehousetype = 3;
			$type = 4;
			$latitude = 5;
			$longitude = 6;
			$capacity = 7;
			$active = 8;
			while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
				if($i>0){
					$StoragePoint = new StoragePoint;
					filterData($column[$district]);
					filterData($column[$latitude]);
					filterData($column[$longitude]);
					filterData($column[$name]);
					filterData($column[$id]);
					filterData($column[$type]);
					filterData($column[$capacity]);
					filterData($column[$warehousetype]);
					filterData($column[$active]);
					$StoragePoint->setDistrict($column[$district]);
					$StoragePoint->setLatitude($column[$latitude]);
					$StoragePoint->setLongitude($column[$longitude]);
					$StoragePoint->setName($column[$name]);
					$StoragePoint->setId($column[$id]);
					$StoragePoint->setType($column[$type]);
					$StoragePoint->setCapacity($column[$capacity]);
					$StoragePoint->setWarehousetype($column[$warehousetype]);
					$StoragePoint->setActive($column[$active]);
					$query_check = $StoragePoint->checkEdit($StoragePoint);
					$query_result = mysqli_query($con, $query_check);
					$numrows = mysqli_num_rows($query_result);
					if($numrows==0){
						echo "Error : in loading data as Storage Point id doesn't exist : ".$column[$id];
						echo "</br>";
						$redirect = 0;
					}
					writeLog("User ->" ." Storage Point Edit -> ". $_SESSION['user'] . "| " . $StoragePoint->getName());
					$query_update = $StoragePoint->updateEdit($StoragePoint);
					mysqli_query($con, $query_update);
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
							case $reverseMapData["type"]:
								$type = $j;
								break;
							case $reverseMapData["capacity"]:
								$capacity = $j;
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
				echo "<script>window.location.href = '../StoragePoint.php';</script>";
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
