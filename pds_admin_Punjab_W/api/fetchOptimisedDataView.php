<?php
require('../util/Connection.php');


$district = $_POST['district'];
$tablename = $_POST['tablename'];
$data = null;


$query = "SELECT * FROM ".$tablename." WHERE to_district='".$district."'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);
while($row = mysqli_fetch_array($result))
{
	
	if($row['new_id_admin']!=null or $row['new_id_admin']!=""){
		$id = $row['new_id_admin'];
		$query_warehouse = "SELECT latitude,longitude,district FROM warehouse WHERE id='$id'";
		$result_warehouse = mysqli_query($con,$query_warehouse);
		$numrows_warehouse = mysqli_num_rows($result_warehouse);
		if($numrows_warehouse!=0){
			$row_warehouse = mysqli_fetch_assoc($result_warehouse);
			$row["from_lat"] = $row_warehouse['latitude'];
			$row["from_long"] = $row_warehouse['longitude'];
			$row["from_district"] = $row_warehouse['district'];
		}
		$row["from_id"] = $row['new_id_admin'];
		$row["from_name"] = $row['new_name_admin'];
		$row["distance"] = $row['new_distance_admin'];
	}
	else if(($row['new_id_district']!=null or $row['new_id_district']!="") and $row['approve_admin']=="yes"){
	
		$id = $row['new_id_district'];
		$query_warehouse = "SELECT latitude,longitude,district FROM warehouse WHERE id='$id'";
		$result_warehouse = mysqli_query($con,$query_warehouse);
		$numrows_warehouse = mysqli_num_rows($result_warehouse);
		if($numrows_warehouse!=0){
			$row_warehouse = mysqli_fetch_assoc($result_warehouse);
			$row["from_lat"] = $row_warehouse['latitude'];
			$row["from_long"] = $row_warehouse['longitude'];
			$row["from_district"] = $row_warehouse['district'];
		}
		$row["from_id"] = $row['new_id_district'];
		$row["from_name"] = $row['new_name_district'];
		$row["distance"] = $row['new_distance_district'];
	}
	$data[] = $row;

}

$resultarray = [];
if($data==null){
	$data = array();
}
$resultarray["data"] = $data;
echo json_encode($resultarray);
?>
