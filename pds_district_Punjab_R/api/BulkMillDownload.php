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
