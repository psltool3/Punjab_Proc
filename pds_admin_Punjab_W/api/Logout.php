<?php

session_start();
$_SESSION['name'] = null;
$_SESSION['user'] = null;
session_unset(); 
session_destroy(); 
header("Location:../AdminLogin.html");

?>