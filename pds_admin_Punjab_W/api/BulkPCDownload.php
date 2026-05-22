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
    "District"           => "district",
    "Name of PC"         => "name",
    "PC ID"              => "id",
    "Storage Point"      => "Storage_Point",
    "Latitude"           => "latitude",
    "Longitude"          => "longitude",
    "Paddy Procurement"  => "Paddy_Procurement",
    "Active/Not-Active"  => "active"
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

$columns = array();

$query = "SHOW COLUMNS FROM pc2";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
if($numrows>0){
	while($row = mysqli_fetch_array($result)){
		if($row['Field']!="uniqueid"){
            if(isset($reverseMapData[$row['Field']])){
			    array_push($columns,$reverseMapData[$row['Field']]);
            }
		}
	}
}

// Display column names as first row 
$excelDataColumns = implode(",", array_values($columns)) . "\n";

 
// Headers for download 
header("Content-Type: application/vnd.ms-excel"); 
header("Content-Disposition: attachment; filename=\"$fileName\""); 
 
// Render excel data 
echo $excelDataColumns; 
 
exit();

?>
