<?php

session_start();
$_SESSION['district_name'] = null;
$_SESSION['district_user'] = null;
session_unset(); // Unset all session variables
session_destroy(); // Destroy the session
header("Location:../Login.html");

?>