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
    echo "Unauthorized access. Login required.";
    exit();
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
$district = $_SESSION['district_district'];


// Filter the excel data 
function filterData(&$str){ 
    $str = preg_replace("/\t/", "\\t", $str); 
    $str = preg_replace("/\r?\n/", "\\n", $str); 
    if(strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"'; 
}

// Excel file name for download 
$fileName = "WarehouseData_" . date('d-m-Y') . ".csv"; 

$columns = array_keys($mapData);


// Headers for download 
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $fileName . '"');
header('Cache-Control: max-age=0');

// Display column names as first row 
$excelDataColumns = implode(",", array_values($columns)) . "\n";

// Render excel data 
echo $excelDataColumns;

$query = "SELECT * FROM warehouse WHERE district='$district'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
if($numrows>0){
	while($row = mysqli_fetch_array($result)){
		for($i=0;$i<count($columns);$i++){
			if ($columns[$i] !== "uniqueid") {
				filterData($row[$mapData[$columns[$i]]]);
				echo '"' . $row[$mapData[$columns[$i]]] . '",';
            }
        }
		echo "\n";
	}
}



exit();

?>