<?php

require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../util/Logger.php');

if(!SessionCheck()){
	return;
}

require('Header.php');

$id = $_POST["uid"];

$query = "SELECT * FROM pc WHERE uniqueid='$id'";
$result = mysqli_query($con,$query);
$numrows = mysqli_num_rows($result);

if($numrows>0){
	$row = mysqli_fetch_assoc($result);
	$status = $row['active'];
	$pcname = $row['name'];
	if($status==0){
		$query = "UPDATE pc SET active='1' WHERE uniqueid='$id'";
		writeLog("User ->" ." PC Active -> ". $_SESSION['district_user'] . "| " . $pcname);
		mysqli_query($con,$query);
	}
	else{
		$query = "UPDATE pc SET active='0' WHERE uniqueid='$id'";
		writeLog("User ->" ." PC InActive -> ". $_SESSION['district_user'] . "| " . $pcname);
		mysqli_query($con,$query);
	}
}


mysqli_close($con);
echo "<script>window.location.href = '../PC.php';</script>";

?>
<?php require('Fullui.php');  ?>
