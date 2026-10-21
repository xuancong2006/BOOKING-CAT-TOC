<?php
if ($_SERVER['HTTP_HOST'] == 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) {
    $servername = "localhost";
    $username   = "root";
    $password   = "";
    $dbname     = "cmtc_salon";
} else {
    $servername = "sql313.infinityfree.com";
    $username   = "if0_43116533";
    $password   = "0B2ClnIIsyqt0B";
    $dbname     = "if0_43116533_cmtc";
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Kết nối CSDL thất bại: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>