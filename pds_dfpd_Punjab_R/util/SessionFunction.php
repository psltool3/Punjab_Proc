<?php
// Max failed login attempts per IP before lockout
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_ATTEMPT_WINDOW', 15); // minutes

function checkLoginRateLimit($con) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $window = date('Y-m-d H:i:s', strtotime('-' . LOGIN_ATTEMPT_WINDOW . ' minutes'));
    $stmt = mysqli_prepare($con, "SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND attempted_at > ?");
    mysqli_stmt_bind_param($stmt, "ss", $ip, $window);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
    if ($count >= MAX_LOGIN_ATTEMPTS) {
        http_response_code(429);
        die("Too many failed login attempts. Please try again after " . LOGIN_ATTEMPT_WINDOW . " minutes.");
    }
}

function recordLoginAttempt($con) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $now = date('Y-m-d H:i:s');
    $stmt = mysqli_prepare($con, "INSERT INTO login_attempts (ip_address, attempted_at) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ss", $ip, $now);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function SessionCheck(){
	require('Connection.php');
	session_start();

	if(isset($_SESSION['user'])){
		$user = $_SESSION['user'];
		$token = $_SESSION['token'];
		$query = "SELECT * FROM login WHERE username='$user' AND token='$token'";
		$result = mysqli_query($con,$query);
		$numrows = mysqli_num_rows($result);
		if($numrows==0){
			return false;
		}
		else{
			$currentLoginTime = date("Y-m-d H:i:s");
			$queryUpdate = "UPDATE login SET lastlogin='$currentLoginTime' WHERE username='$user'";
			mysqli_query($con,$queryUpdate);
			
			return true;
		}
	}
	else{
		return false;
	}
}

?>
