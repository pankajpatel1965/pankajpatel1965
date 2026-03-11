```php
<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "sql100.infinityfree.com";
$username = "if0_41365881";
$password = "0A6122001";  // your infinityfree login password
$database = "if0_41365881_zaverat";

$conn = mysqli_connect($host, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

mysqli_set_charset($conn,"utf8mb4");

?>
```
