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
    "Mill_District"   => "district",
    "Milling_Centre"  => "milling_center",
    "Mill_Name"       => "name",
    "Mill_ID"         => "mill_id",
    "Mill_Latitude"        => "latitude",
    "Mill_Longitude"       => "longitude",
    "Milling_Process" => "milling_process",
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

$milling_centers = [];
$mc_result = mysqli_query($con, "SELECT name FROM milling_center");
if(mysqli_num_rows($mc_result) > 0){
	while($mc_row = mysqli_fetch_assoc($mc_result)){
		array_push($milling_centers, $mc_row["name"]);
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

$redirect = 1;

try{
	$fileName = $_FILES["file"]["tmp_name"];
	if ($_FILES["file"]["size"] > 0) {
		$file = fopen($fileName, "r");
		$i = 0;
		$district = -1;
		$milling_center = -1;
		$name = -1;
		$mill_id = -1;
		$latitude = -1;
		$longitude = -1;
		$milling_process = -1;
		$active = -1;

		while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
			if($i>0){
				if($district<0 or $milling_center<0 or $name<0 or $mill_id<0 or $latitude<0 or $longitude<0 or $milling_process<0 or $active<0){
					echo "Error : You have modified Template Header, please check";
					exit();
				}
				if(!isValidCoordinate($column[$latitude],'latitude') or !isValidCoordinate($column[$longitude],'longitude')){
					echo "Error : Check Latitude and Longitude Value Latitude: ".$column[$latitude]." Longitude: ".$column[$longitude];
					echo "</br>";
					$redirect = 0;
				}

				if(!in_array(strtoupper(trim($column[$district])), array_map('strtoupper', $districts))){
					echo "Error : Check District Name: ".$column[$district];
					echo "</br>";
					$redirect = 0;
				}
				if(!in_array(strtoupper(trim($column[$milling_center])), array_map('strtoupper', $milling_centers))){
					echo "Error : Invalid Milling Center: ".$column[$milling_center];
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

				if(!($column[$active]==0 || $column[$active]==1)){
					echo "Error : Check value of active/inactive column: ".$column[$active];
					echo "</br>";
					$redirect = 0;
				}

				$Mill = new Mill;
				$Mill->setDistrict(ucwords(strtolower($column[$district])));
				$Mill->setMillingCenter($column[$milling_center]);
				$Mill->setLatitude($column[$latitude]);
				$Mill->setLongitude($column[$longitude]);
				$Mill->setName($column[$name]);
				$Mill->setMillId($column[$mill_id]);
				$Mill->setMillingProcess($column[$milling_process]);
				$Mill->setActive($column[$active]);
				
				$query_check = $Mill->checkEdit($Mill);
				$query_result = mysqli_query($con, $query_check);
				$numrows = mysqli_num_rows($query_result);
				if($numrows==0){
					echo "Error : in loading data as Mill ID doesn't exist : ".$column[$mill_id];
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
						case $reverseMapData["mill_id"]:
							$mill_id = $j;
							break;
						case $reverseMapData["milling_process"]:
							$milling_process = $j;
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
			$milling_center = -1;
			$name = -1;
			$mill_id = -1;
			$latitude = -1;
			$longitude = -1;
			$milling_process = -1;
			$active = -1;

			while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
				if($i>0){
					if($district<0 or $milling_center<0 or $name<0 or $mill_id<0 or $latitude<0 or $longitude<0 or $milling_process<0 or $active<0){
						echo "Error : You have modified Template Header, please check";
						exit();
					}
					$Mill = new Mill;
					$Mill->setDistrict(ucwords(strtolower($column[$district])));
					$Mill->setMillingCenter($column[$milling_center]);
					$Mill->setLatitude($column[$latitude]);
					$Mill->setLongitude($column[$longitude]);
					$Mill->setName($column[$name]);
					$Mill->setMillId($column[$mill_id]);
					$Mill->setMillingProcess($column[$milling_process]);
					$Mill->setActive($column[$active]);
					
					$query_insert_check = $Mill->checkEdit($Mill);
					$query_insert_result = mysqli_query($con, $query_insert_check);
					$numrows_insert = mysqli_num_rows($query_insert_result);
					if($numrows_insert>0){
						writeLog("User ->" ." Mill Updated -> ". $_SESSION['user'] . "| " . $Mill->getName());
						$query_add = $Mill->updateEdit($Mill);
						mysqli_query($con, $query_add);
					}
					else{
						echo "Error : Mill with ID ".$Mill->getMillId()." Does Not Exist</br>";
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
							case $reverseMapData["mill_id"]:
								$mill_id = $j;
								break;
							case $reverseMapData["milling_process"]:
								$milling_process = $j;
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
