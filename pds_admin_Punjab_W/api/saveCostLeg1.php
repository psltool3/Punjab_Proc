<?php
require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../util/Logger.php');

if(!SessionCheck()){
	return;
}

require('Header.php');

foreach ($_POST as $key => $value) {
    // Check if the parameter starts with 'cost_' and is not empty
    if (substr($key, 0, 5) === 'cost_' && !empty($value)) {
        // Extract the ID from the parameter name
        $id = substr($key, 5);
		$value_temp = $value;
		$log_query = "select * from optimised_table_leg1 WHERE id='$id'";
		$log_result = mysqli_query($con,$log_query);
		if ($log_result && $row = $log_result->fetch_assoc()) {
			$user_id =  $row['year'];
			$user_id1 =  $row['month'];
		}
		
		$value = filter_var($value, FILTER_VALIDATE_FLOAT);
        if ($value === false) {
            // If $value is not a float, try casting it to int
            $value = filter_var($value, FILTER_VALIDATE_INT);
        }

        // If $value is not a valid float or int, skip this iteration
        if ($value === false) {
            echo "Error : Invalid value: $value_temp<br>";
			return;
        }
		else{
			// Update the optimised table where id equals the extracted ID
			$filteredPost = $_POST;
			unset($filteredPost['username'], $filteredPost['password']);
			writeLog("User ->" ." Cost for leg2 Added ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost). " | " . $user_id." | " . $user_id1);
			
			$sql = "UPDATE optimised_table_leg1 SET cost = '$value' WHERE id = '$id'";

			if ($con->query($sql) === TRUE) {
				
			} else {
				echo "Error : updating record: " . $con->error;
				return;
			}
		}
    }
	echo "<script>window.location.href = '../PerformaLeg1.php';</script>";
}
?>

<?php require('Fullui.php');  ?>