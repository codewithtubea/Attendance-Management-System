<?php
$host = "localhost";
$user = "princess.agyemfra"; 
$pass = "Tracytubea2565";     
$dbname = "webtech_2025A_princess_agyemfra";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
