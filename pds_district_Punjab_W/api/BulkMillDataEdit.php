<?php
require('../util/Connection.php');
require('../structures/Mill.php');
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
    "Name of Mill" => "name",
    "Mill ID" => "id",
    "Mill Type" => "type",
    "Latitude" => "latitude",
    "Longitude" => "longitude",
    "Milling Capacity" => "milling_capacity",
    "Inventory Raw Rice" => "Inventory_Raw_Rice",
    "Inventory Para Rice" => "Inventory_Para_Rice",
    "Performance Factor" => "performance_factor",
	"Active/Not-Active" => "active"
];

// Reverse mapping
$reverseMapData = array_flip($mapData);

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
   return true;
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
		$type = -3;
		$latitude = -5;
		$longitude = -6;
        $milling_capacity = -7;
        $Inventory_Raw_Rice = -8;
        $Inventory_Para_Rice = -9;
        $performance_factor = -10;
		$active = -11;
		while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
			if($i>0){
				if($district<0 or $name<0 or $id<0 or $type<0 or $latitude<0 or $longitude<0 or $milling_capacity<0 or $Inventory_Raw_Rice<0 or $Inventory_Para_Rice<0 or $performance_factor<0 or $active<0){
					echo "Error : You have modified Template Header, please check";
					exit();
				}
				if(!isValidCoordinate($column[$latitude],'latitude') or !isValidCoordinate($column[$longitude],'longitude')){
					echo "Error : Check Latitude and Longitude Value Latitude: ".$column[$latitude]." Longitude: ".$column[$longitude];
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
					echo "Error: Mill ID should not contain spaces or any special characters: " . ($column[$id] ?? 'Missing');
					echo "<br>";
					$redirect = 0;
				}	

				// Check Milling Capacity is numeric
				if (!is_numeric($column[$milling_capacity])) {
					echo "Error : Milling Capacity must be numeric. Given: " . $column[$milling_capacity];
					echo "</br>";
					$redirect = 0;
				}

				// Check Inventory Raw Rice is numeric
				if (!is_numeric($column[$Inventory_Raw_Rice])) {
					echo "Error : Inventory Raw Rice must be numeric. Given: " . $column[$Inventory_Raw_Rice];
					echo "</br>";
					$redirect = 0;
				}

				// Check Inventory Para Rice is numeric
				if (!is_numeric($column[$Inventory_Para_Rice])) {
					echo "Error : Inventory Para Rice must be numeric. Given: " . $column[$Inventory_Para_Rice];
					echo "</br>";
					$redirect = 0;
				}

				// Check Performance Factor is numeric
				if (!is_numeric($column[$performance_factor])) {
					echo "Error : Performance Factor must be numeric. Given: " . $column[$performance_factor];
					echo "</br>";
					$redirect = 0;
				}
				
				if(!($column[$active]==0 || $column[$active]==1)){
					echo "Error : Check value of active/inactive column: ".$column[$active];
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
                        case $reverseMapData["milling_capacity"]:
                            $milling_capacity = $j;
                            break;
                        case $reverseMapData["Inventory_Raw_Rice"]:
                            $Inventory_Raw_Rice = $j;
                            break;
                        case $reverseMapData["Inventory_Para_Rice"]:
                            $Inventory_Para_Rice = $j;
                            break;
                        case $reverseMapData["performance_factor"]:
                            $performance_factor = $j;
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
			$type = -3;
			$latitude = -5;
			$longitude = -6;
            $milling_capacity = -7;
            $Inventory_Raw_Rice = -8;
            $Inventory_Para_Rice = -9;
            $performance_factor = -10;
			$active = -11;
			while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
				if($i>0){
					if($district<0 or $name<0 or $id<0 or $type<0 or $latitude<0 or $longitude<0 or $milling_capacity<0 or $Inventory_Raw_Rice<0 or $Inventory_Para_Rice<0 or $performance_factor<0 or $active<0){
						echo "Error : You have modified Template Header, please check";
						exit();
					}
					$Mill = new Mill;
					$Mill->setDistrict(ucwords(strtolower($column[$district])));
					$Mill->setLatitude($column[$latitude]);
					$Mill->setLongitude($column[$longitude]);
					$Mill->setName($column[$name]);
					$Mill->setId($column[$id]);
					$Mill->setType($column[$type]);
                    $Mill->setMillingCapacity($column[$milling_capacity]);
                    $Mill->setInventoryRawRice($column[$Inventory_Raw_Rice]);
                    $Mill->setInventoryParaRice($column[$Inventory_Para_Rice]);
                    $Mill->setPerformanceFactor($column[$performance_factor]);
					$Mill->setActive($column[$active]);
					
					$query_insert_check = $Mill->checkEdit($Mill);
					$query_insert_result = mysqli_query($con, $query_insert_check);
					$numrows_insert = mysqli_num_rows($query_insert_result);
					if($numrows_insert>0){
						writeLog("User ->" ." Mill Updated -> ". $_SESSION['district_user'] . "| " . $Mill->getName());
						$query_add = $Mill->updateEdit($Mill);
						mysqli_query($con, $query_add);
					}
					else{
						echo "Error : Mill with id ".$Mill->getId()." Does Not Exist</br>";
						$redirect = 2;
					}
				}
				else{
					$column[0] = preg_replace('/^\xEF\xBB\xBF/', '', $column[0]);
					for($j=0;$j<count($column);$j++){
						switch(trim($column[$j])){
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
                            case $reverseMapData["milling_capacity"]:
                                $milling_capacity = $j;
                                break;
                            case $reverseMapData["Inventory_Raw_Rice"]:
                                $Inventory_Raw_Rice = $j;
                                break;
                            case $reverseMapData["Inventory_Para_Rice"]:
                                $Inventory_Para_Rice = $j;
                                break;
                            case $reverseMapData["performance_factor"]:
                                $performance_factor = $j;
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
				echo "<script>window.location.href = '../Mill.php';</script>";
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
