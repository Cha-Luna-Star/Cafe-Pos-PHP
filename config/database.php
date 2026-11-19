
<?php

$server = "Cha\SQLEXPRESS";
$database = "CafePOS";

try {
    $connection = new PDO(
        "sqlsrv:Server=$server;Database=$database;Encrypt=yes;TrustServerCertificate=yes",
        null,
        null
    );

    $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

?>
