<?php
require('../util/Connection.php');
require('../structures/District.php');
require('../util/SessionFunction.php');

if(!SessionCheck()){
	return;
}

$month = $_POST['month'];
$district = $_POST['district'];
$parts = explode('_', $month);

$year = $parts[0]; 
$month = $parts[1];
$day = $parts[2]; 
$query = "SELECT * FROM optimised_table WHERE year='$year' AND month='$month' AND day='$day'";
$result = mysqli_query($con,$query);
$numrow = mysqli_num_rows($result);
$id = "";
if($numrow>0){
	$row = mysqli_fetch_assoc($result);
	$id = $row['id'];
}

$tablename = "optimiseddata_".$id;
$result = $con->query("SELECT DISTINCT from_id,from_name from $tablename WHERE to_district='$district'");

if ($result->num_rows > 0) {
    $rows = array();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    echo json_encode($rows);
}
?>