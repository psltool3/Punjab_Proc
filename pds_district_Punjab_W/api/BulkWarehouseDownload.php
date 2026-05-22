<?php
require('../util/Connection.php');
require('../structures/Warehouse.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
ini_set('max_execution_time', 3000);
require('../util/Logger.php');
require('../util/Security.php');
require ('../util/Encryption.php');
$nonceValue = 'nonce_value';

if(!SessionCheck()){
    return;
}

$mapData = [
    "District" => "district",
    "Name of Warehouse" => "name",
    "Warehouse ID" => "id",
    "Warehouse Type" => "warehousetype",
    "Latitude" => "latitude",
    "Longitude" => "longitude",
    "Storage Point" => "Storage_Point",
    "Capacity Available" => "Capacity_Available",
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
$fileName = "WarehouseTemplate_" . date('d-m-Y') . ".csv"; 

$columns = array_keys($mapData);

// Display column names as first row 
$excelDataColumns = implode(",", array_values($columns)) . "\n";

 
// Headers for download 
header("Content-Type: application/vnd.ms-excel"); 
header("Content-Disposition: attachment; filename=\"$fileName\""); 
 
// Render excel data 
echo $excelDataColumns; 
 
exit();

?>