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
    "Name of the District"                       => "district",
    "Name of the Block"                          => "Block",
    "Name of the Mill"                           => "name",
    "Mill Code"                                  => "mill_code",
    "Mill Type Raw/Boiled"                       => "type",
    "Storage Capacity in Quintals (Paddy)"       => "Storage_Capacity",
    "Milling Capacity in Quintals in 8hr Per Shift" => "milling_capacity",
    "BG Amount"                                  => "BG_Amount",
    "SD Norms"                                   => "SD_Norms",
    "Latitude"                                   => "latitude",
    "Longitude"                                  => "longitude",
    "No of days of Season"                       => "days",
    "Active/Not-Active"                          => "active"
];

// Reverse mapping
$reverseMapData = array_flip($mapData);

// Filter the excel data 
function filterData(&$str){ 
    $str = preg_replace("/\t/", "\\t", $str); 
    $str = preg_replace("/\r?\n/", "\\n", $str); 
    if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"'; 
}

// Excel file name for download 
$fileName = "MillTemplate_" . date('d-m-Y') . ".csv"; 

$columns = array_keys($mapData);

// Headers for download 
header("Content-Type: application/vnd.ms-excel"); 
header("Content-Disposition: attachment; filename=\"$fileName\""); 

// Display column names as first row 
$excelDataColumns = implode(",", $columns) . "\n";

// Render excel data 
echo $excelDataColumns; 

exit();

?>
