<?php
require('../util/Connection.php');
require('../util/SessionFunction.php');
require('../structures/Login.php');
require('../util/Security.php');
require ('../util/Encryption.php');
require('../util/Logger.php');
$nonceValue = 'nonce_value';

if(!SessionCheck()){
	return;
}

require('Header.php');


$person = new Login;
$person->setUsername($_POST["username"]);
$Encryption = new Encryption();
$person->setPassword($Encryption->decrypt($_POST["password"], $nonceValue));

if($_SESSION['user']!=$person->getUsername()){
	echo "User is logged in with different username and password";
	return;
}

// Query the database to get the stored hash for the username
$query = "SELECT * FROM login WHERE username='".$person->getUsername()."'";
$result = mysqli_query($con, $query);
$row = mysqli_fetch_assoc($result);

    // Use password_verify to check if the entered password matches the hashed password in the database
    if (password_verify($person->getPassword(), $row['password'])) {
        // Password is correct
        $uid = $_POST["uid"];
		
		$log_query = "select username  from login WHERE uid='$uid'";
		$log_result = mysqli_query($con,$log_query);
		if ($log_result && $row = $log_result->fetch_assoc()) {
			$log_name =  $row['username'];
		}


        $query = "DELETE FROM login WHERE uid='$uid'";
        mysqli_query($con, $query);
        mysqli_close($con);
		
		$filteredPost = $_POST;
		unset($filteredPost['username'], $filteredPost['password']);
		writeLog("User ->" ." Delete User ->". $_SESSION['user'] . "| Requested JSON -> " . json_encode($filteredPost). " | " . $log_name);

        echo "<script>window.location.href = '../Userdata.php';</script>";
    } else {
        // Password is incorrect
        echo "Error : Username or Password is incorrect";
        return;
    }

?>
<?php require('Fullui.php');  ?>