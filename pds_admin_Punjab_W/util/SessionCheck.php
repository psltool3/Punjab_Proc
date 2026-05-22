<?php

session_start();

// -----------------------------
// Session Timeout
// -----------------------------
$timeout_duration = 6000;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: AdminLogin.html?error=session_timeout");
    exit();
}
$_SESSION['last_activity'] = time();

// -----------------------------
// Session Hijack Protection
// -----------------------------
$user_ip = $_SERVER['REMOTE_ADDR'];
$user_agent = $_SERVER['HTTP_USER_AGENT'];

if (!isset($_SESSION['USER_IP']) || !isset($_SESSION['USER_AGENT'])) {
    $_SESSION['USER_IP'] = $user_ip;
    $_SESSION['USER_AGENT'] = $user_agent;
} else {
    if ($_SESSION['USER_IP'] !== $user_ip || $_SESSION['USER_AGENT'] !== $user_agent) {
        session_unset();
        session_destroy();
        header("Location: AdminLogin.html?error=session_hijacked");
        exit();
    }
}

// -----------------------------
// Auth Check
// -----------------------------
require('Connection.php');
if (isset($_SESSION['user']) && isset($_SESSION['token'])) {
    $user = $_SESSION['user'];
    $token = $_SESSION['token'];
    $query = "SELECT * FROM login WHERE username='$user' AND token='$token'";
    $result = mysqli_query($con, $query);

    if (mysqli_num_rows($result) === 0) {
        session_unset();
        session_destroy();
        header("Location: AdminLogin.html?error=invalid_token");
        exit();
    }

    $currentLoginTime = date("Y-m-d H:i:s");
    $queryUpdate = "UPDATE login SET lastlogin='$currentLoginTime' WHERE username='$user'";
    mysqli_query($con, $queryUpdate);
} else {
    header("Location: AdminLogin.html?error=no_session");
    exit();
}
?>
