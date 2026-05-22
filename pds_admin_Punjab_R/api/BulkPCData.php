<?php
require('../util/Connection.php');
require('../structures/PC.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Logger.php');
require('../util/Security.php');
require('../util/Encryption.php');

$nonceValue = 'nonce_value';
ini_set('max_execution_time', 3000);
session_start();
require('Header.php');

/* ================= HEADER MAPPING ================= */

$mapData = [
    "PC_District"          => "district",
    "Mill_Centre"          => "milling_center",
    "PC_Name"              => "name",
    "PC_ID"                => "PC_ID",
    "PC_Latitude"          => "latitude",
    "PC_Longitude"         => "longitude",
    "Paddy_Procurement"    => "Paddy_Procurement",
    "Active/Not-Active"    => "active"
];

$reverseMapData = array_flip($mapData);

/* ================= AUTH CHECK ================= */

$person = new Login();
$person->setUsername($_POST["username"]);

$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

if ($_SESSION['user'] !== $person->getUsername()) {
    echo "User is logged in with different credentials";
    exit();
}

$query = "SELECT password FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

if (!password_verify($person->getPassword(), $row['password'])) {
    echo "Error : Password or Username is incorrect";
    exit();
}

/* ================= DISTRICT MASTER ================= */

$districts = [];
$res = mysqli_query($con, "SELECT name FROM districts");
while ($r = mysqli_fetch_assoc($res)) {
    $districts[] = $r['name'];
}

/* ================= HELPER FUNCTIONS ================= */

function isValidCoordinate($value, $type) {
    if (!is_numeric($value)) return false;
    $value = floatval($value);
    return $type === 'latitude'
        ? ($value >= -90 && $value <= 90)
        : ($value >= -180 && $value <= 180);
}

function isNumber($value) {
    return is_numeric($value);
}

/* ================= CSV VALIDATION ================= */

$redirect = 1;

try {
    $fileName = $_FILES["file"]["tmp_name"];
    if ($_FILES["file"]["size"] <= 0) {
        throw new Exception("Empty file");
    }

    $file = fopen($fileName, "r");
    $i = 0;

    $district = $name = $PC_ID = $latitude = $longitude = $Paddy_Procurement = $milling_center = $active = -1;

    while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {

        /* HEADER ROW */
        if ($i == 0) {
            $column[0] = preg_replace('/^\xEF\xBB\xBF/', '', $column[0]);
            for ($j = 0; $j < count($column); $j++) {
                switch (trim($column[$j])) {
                    case $reverseMapData["district"]:         $district = $j; break;
                    case $reverseMapData["milling_center"]:   $milling_center = $j; break;
                    case $reverseMapData["name"]:             $name = $j; break;
                    case $reverseMapData["PC_ID"]:            $PC_ID = $j; break;
                    case $reverseMapData["latitude"]:         $latitude = $j; break;
                    case $reverseMapData["longitude"]:        $longitude = $j; break;
                    case $reverseMapData["Paddy_Procurement"]:$Paddy_Procurement = $j; break;
                    case $reverseMapData["active"]:           $active = $j; break;
                }
            }

            if (
                $district < 0 || $name < 0 || $PC_ID < 0 ||
                $latitude < 0 || $longitude < 0 ||
                $Paddy_Procurement < 0 || $milling_center < 0 || $active < 0
            ) {
                echo "Error : You have modified Template Header, please check";
                exit();
            }
        }
        /* DATA ROW */
        else {
            if (!isValidCoordinate($column[$latitude], 'latitude') ||
                !isValidCoordinate($column[$longitude], 'longitude')) {
                echo "Error : Invalid Latitude/Longitude<br>";
                $redirect = 0;
            }

            if (!isNumber($column[$Paddy_Procurement])) {
                echo "Error : Paddy Procurement must be numeric<br>";
                $redirect = 0;
            }

            if (!in_array($column[$district], $districts)) {
                echo "Error : Invalid District - ".$column[$district]."<br>";
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

            if (!($column[$active] == 0 || $column[$active] == 1)) {
                echo "Error : Active value must be 0 or 1<br>";
                $redirect = 0;
            }
        }
        $i++;
    }
    fclose($file);

    if ($redirect == 0) exit();

} catch (Exception $e) {
    echo "Error : Please check CSV data";
    exit();
}

/* ================= INSERT DATA ================= */

$file = fopen($_FILES["file"]["tmp_name"], "r");
$i = 0;

while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
    if ($i > 0) {
        $PC = new PC();

        do {
            $uniqueid = substr(uniqid("PC_"), 0, 15);
            $PC->setUniqueid($uniqueid);
        } while (mysqli_num_rows(mysqli_query($con, $PC->check($PC))) > 0);

        $PC->setDistrict(ucwords(strtolower($column[$district])));
        $PC->setName($column[$name]);
        $PC->setPCID($column[$PC_ID]);
        $PC->setLatitude($column[$latitude]);
        $PC->setLongitude($column[$longitude]);
        $PC->setPaddyProcurement($column[$Paddy_Procurement]);
        $PC->setMillingCenter($column[$milling_center]);
        $PC->setActive($column[$active]);

        if (mysqli_num_rows(mysqli_query($con, $PC->checkInsert($PC))) == 0) {
            mysqli_query($con, $PC->insert($PC));
            writeLog("PC Added -> ".$PC->getName()." by ".$_SESSION['user']);
        } else {
            echo "Error : PC_ID ".$PC->getPCID()." already exists<br>";
        }
    }
    $i++;
}
fclose($file);

echo "<script>window.location.href='../PC.php';</script>";
require('Fullui.php');
?>
