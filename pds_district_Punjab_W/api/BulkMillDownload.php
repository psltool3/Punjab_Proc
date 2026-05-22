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

// Excel file name for download 
$fileName = "MillTemplate_" . date('d-m-Y') . ".csv"; 

// Headers for download 
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0'); // no cache

// Display column names as first row 
echo implode(",", array_keys($mapData)) . "\n";

exit();
?>
