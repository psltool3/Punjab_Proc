<?php
require('../util/Connection.php');
require('../structures/PC.php');
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
    "PC_District"       => "district",
    "Mill_Centre"       => "milling_center",
    "PC_Name"           => "name",
    "PC_ID"             => "PC_ID",
    "PC_Latitude"       => "latitude",
    "PC_Longitude"      => "longitude",
    "Paddy_Procurement" => "Paddy_Procurement",
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
$fileName = "PCTemplate_" . date('d-m-Y') . ".csv"; 

$columns = array_keys($mapData);

// Display column names as first row 
$excelDataColumns = implode(",", $columns) . "\n";

 
// Headers for download 
header("Content-Type: application/vnd.ms-excel"); 
header("Content-Disposition: attachment; filename=\"$fileName\""); 
 
// Render excel data 
echo $excelDataColumns; 
 
exit();

?>
