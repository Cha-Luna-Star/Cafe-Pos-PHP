
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coffee-Up Dashboard</title>
</head>
<body>

<h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>

<p>Role: <?= htmlspecialchars($_SESSION['role']) ?></p>

<a href="logout.php">Logout</a>

</body>
</html>
