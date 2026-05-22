<?php

require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../util/Logger.php');

if(!SessionCheck()){
	return;
}

require('Header.php');

$id = $_POST["uid"];

$query = "SELECT * FROM storage_point WHERE uniqueid='$id'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows>0){
	$row = mysqli_fetch_assoc($result);
	$status = $row['active'];
	$name = $row['name'];
	if($status==0){
		$query = "UPDATE storage_point SET active='1' WHERE uniqueid='$id'";
		writeLog("User ->" ." Storage Point Active -> ". $_SESSION['user'] . "| " . $name);
		mysqli_query($con,$query);
	}
	else{
		$query = "UPDATE storage_point SET active='0' WHERE uniqueid='$id'";
		writeLog("User ->" ." Storage Point InActive -> ". $_SESSION['user'] . "| " . $name);
		mysqli_query($con,$query);
	}
}


mysqli_close($con);
echo "<script>window.location.href = '../StoragePoint.php';</script>";

?>
<?php require('Fullui.php');  ?>
