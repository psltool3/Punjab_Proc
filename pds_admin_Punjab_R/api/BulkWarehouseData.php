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
    "Motorable/Non-Motorable" => "type",
    "Warehouse Type" => "warehousetype",
    "Latitude" => "latitude",
    "Longitude" => "longitude",
    "Normal Rice" => "normal_rice",
    "State FRK Rice" => "state_frk_rice",
    "Central FRK Rice" => "central_frk_rice",
    "Storage Rice" => "storage_rice",
    "Storage State FRK Rice" => "storage_state_frk_rice",
    "Storage Central FRK Rice" => "storage_central_frk_rice",
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
		$type = -4;
		$latitude = -5;
		$longitude = -6;
		$normal_rice = -7;
        $state_frk_rice = -8;
        $central_frk_rice = -9;
        $storage_rice = -10;
        $storage_state_frk_rice = -11;
        $storage_central_frk_rice = -12;
		$active = -1;
		while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
			if($i>0){
				if($district<0 or $name<0 or $id<0 or $type<0 or $normal_rice<0 or $state_frk_rice<0 or $central_frk_rice<0 or $latitude<0 or $longitude<0 or $warehousetype<0 or $active<0){
					echo "Error : You have modified Template Header, please check";
					exit();
				}
				if(!isValidCoordinate($column[$latitude],'latitude') or !isValidCoordinate($column[$longitude],'longitude')){
					echo "Error : Check Latitude and Longitude Value Latitude: ".$column[$latitude]." Longitude: ".$column[$longitude];
					echo "</br>";
					$redirect = 0;
				}

				if(!isStringNumber($column[$normal_rice])){
					echo "Error : Check Normal Rice Value: ".$column[$normal_rice];
					echo "</br>";
					$redirect = 0;
				}
                if(!isStringNumber($column[$state_frk_rice])){
					echo "Error : Check State FRK Rice Value: ".$column[$state_frk_rice];
					echo "</br>";
					$redirect = 0;
				}
                if(!isStringNumber($column[$central_frk_rice])){
					echo "Error : Check Central FRK Rice Value: ".$column[$central_frk_rice];
					echo "</br>";
					$redirect = 0;
				}
                
                if($storage_rice >= 0 && isset($column[$storage_rice]) && $column[$storage_rice] != "" && !isStringNumber($column[$storage_rice])){
					echo "Error : Check Storage Rice Value: ".$column[$storage_rice];
					echo "</br>";
					$redirect = 0;
				}
                
                if($storage_state_frk_rice >= 0 && isset($column[$storage_state_frk_rice]) && $column[$storage_state_frk_rice] != "" && !isStringNumber($column[$storage_state_frk_rice])){
					echo "Error : Check Storage State FRK Rice Value: ".$column[$storage_state_frk_rice];
					echo "</br>";
					$redirect = 0;
				}
                
                if($storage_central_frk_rice >= 0 && isset($column[$storage_central_frk_rice]) && $column[$storage_central_frk_rice] != "" && !isStringNumber($column[$storage_central_frk_rice])){
					echo "Error : Check Storage Central FRK Rice Value: ".$column[$storage_central_frk_rice];
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

				// Longitude check (must be more than 65)
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
				// Normal Rice should not exceed Storage Rice
				if($storage_rice >= 0 && isset($column[$storage_rice]) && $column[$storage_rice] != ""){
					$normal = floatval($column[$normal_rice]);
					$storage = floatval($column[$storage_rice]);

					if($normal > $storage){
						echo "Error : Normal Rice cannot be greater than Storage Rice. Normal Rice: ".$normal." Storage Rice: ".$storage;
						echo "</br>";
						$redirect = 0;
					}
				}

				// State FRK Rice should not exceed Storage State FRK Rice
				if($storage_state_frk_rice >= 0 && isset($column[$storage_state_frk_rice]) && $column[$storage_state_frk_rice] != ""){
					$state_frk = floatval($column[$state_frk_rice]);
					$storage_state = floatval($column[$storage_state_frk_rice]);

					if($state_frk > $storage_state){
						echo "Error : State FRK Rice cannot be greater than Storage State FRK Rice. State FRK Rice: ".$state_frk." Storage State FRK Rice: ".$storage_state;
						echo "</br>";
						$redirect = 0;
					}
				}

				// Central FRK Rice should not exceed Storage Central FRK Rice
				if($storage_central_frk_rice >= 0 && isset($column[$storage_central_frk_rice]) && $column[$storage_central_frk_rice] != ""){
					$central_frk = floatval($column[$central_frk_rice]);
					$storage_central = floatval($column[$storage_central_frk_rice]);

					if($central_frk > $storage_central){
						echo "Error : Central FRK Rice cannot be greater than Storage Central FRK Rice. Central FRK Rice: ".$central_frk." Storage Central FRK Rice: ".$storage_central;
						echo "</br>";
						$redirect = 0;
					}
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
						case $reverseMapData["type"]:
							$type = $j;
							break;
						case $reverseMapData["normal_rice"]:
							$normal_rice = $j;
							break;
                        case $reverseMapData["state_frk_rice"]:
							$state_frk_rice = $j;
							break;
                        case $reverseMapData["central_frk_rice"]:
							$central_frk_rice = $j;
							break;
                        case $reverseMapData["storage_rice"]:
							$storage_rice = $j;
							break;
                        case $reverseMapData["storage_state_frk_rice"]:
							$storage_state_frk_rice = $j;
							break;
                        case $reverseMapData["storage_central_frk_rice"]:
							$storage_central_frk_rice = $j;
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
	//if (isset($_POST["submit"])){
		$fileName = $_FILES["file"]["tmp_name"];
		if ($_FILES["file"]["size"] > 0) {
			
			$file = fopen($fileName, "r");
			$i = 0;
			$district = -1;
			$name = -1;
			$id = -2;
			$warehousetype = -3;
			$type = -4;
			$latitude = -5;
			$longitude = -6;
			$normal_rice = -7;
            $state_frk_rice = -8;
            $central_frk_rice = -9;
            $storage_rice = -11;
            $storage_state_frk_rice = -12;
            $storage_central_frk_rice = -13;
			$active = -10;
			while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
				if($i>0){
					if($district<0 or $name<0 or $id<0 or $type<0 or $normal_rice<0 or $state_frk_rice<0 or $central_frk_rice<0 or $latitude<0 or $longitude<0 or $warehousetype<0 or $active<0){
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
					$Warehouse->setType($column[$type]);
					$Warehouse->setNormalRice($column[$normal_rice]);
                    $Warehouse->setStateFrkRice($column[$state_frk_rice]);
                    $Warehouse->setCentralFrkRice($column[$central_frk_rice]);
                    $Warehouse->setStorageRice(isset($column[$storage_rice]) ? $column[$storage_rice] : "0");
                    $Warehouse->setStorageStateFrkRice(isset($column[$storage_state_frk_rice]) ? $column[$storage_state_frk_rice] : "0");
                    $Warehouse->setStorageCentralFrkRice(isset($column[$storage_central_frk_rice]) ? $column[$storage_central_frk_rice] : "0");
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
							case $reverseMapData["type"]:
								$type = $j;
								break;
							case $reverseMapData["normal_rice"]:
								$normal_rice = $j;
								break;
                            case $reverseMapData["state_frk_rice"]:
								$state_frk_rice = $j;
								break;
                            case $reverseMapData["central_frk_rice"]:
								$central_frk_rice = $j;
								break;
                            case $reverseMapData["storage_rice"]:
								$storage_rice = $j;
								break;
                            case $reverseMapData["storage_state_frk_rice"]:
								$storage_state_frk_rice = $j;
								break;
                            case $reverseMapData["storage_central_frk_rice"]:
								$storage_central_frk_rice = $j;
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
	//}
	//else{
		//echo "Error Please Select .csv file";
	//}
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