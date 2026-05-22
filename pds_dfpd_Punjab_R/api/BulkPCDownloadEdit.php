<?php
require('../util/Connection.php');
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
    "Name of the District"        => "district",
    "Name of the Block"           => "Block",
    "Name of the PPC"             => "name",
    "PPC Code"                    => "PPC_Code",
    "Latitude"                    => "latitude",
    "Longitude"                   => "longitude",
    "Target Quantity in Quintals" => "Quantity",
    "Active/Not-Active"           => "active"
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
$fileName = "PCData_" . date('d-m-Y') . ".csv"; 

$columns = array_keys($mapData);
$dbColumns = array_values($mapData);

// Headers for download 
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Display column names as first row 
$excelDataColumns = implode(",", $columns) . "\n";

// Render excel data 
echo $excelDataColumns;

$query = "SELECT * FROM pc WHERE 1";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
if($numrows>0){
	while($row = mysqli_fetch_array($result)){
		for($i=0;$i<count($dbColumns);$i++){
			$val = $row[$dbColumns[$i]] ?? '';
			filterData($val);
			echo '"' . $val . '",';
        }
		echo "\n";
	}
}

exit();

?>
