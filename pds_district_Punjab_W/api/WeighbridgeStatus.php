<?php

require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../util/Logger.php');

if(!SessionCheck()){
	return;
}

require('Header.php');

$id = $_POST["uid"];

$query = "SELECT * FROM weighbridge WHERE uniqueid='$id'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows>0){
	$row = mysqli_fetch_assoc($result);
	$status = $row['active'];
	$weighbridgename = $row['name'];
	if($status==0){
		$query = "UPDATE weighbridge SET active='1' WHERE uniqueid='$id'";
		writeLog("User ->" ." Weighbridge Active -> ". $_SESSION['district_user'] . "| " . $weighbridgename);
		mysqli_query($con,$query);
	}
	else{
		$query = "UPDATE weighbridge SET active='0' WHERE uniqueid='$id'";
		writeLog("User ->" ." Weighbridge InActive -> ". $_SESSION['district_user'] . "| " . $weighbridgename);
		mysqli_query($con,$query);
	}
}


mysqli_close($con);
echo "<script>window.location.href = '../Weighbridge.php';</script>";

?>
<?php require('Fullui.php');  ?>