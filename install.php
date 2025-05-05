<?php
// Check if the application is already installed
if (file_exists('config.php')) {
    die('The application is already installed.');
}

// Handle form submission for setup
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get user input
    $dbHost = $_POST['db_host'];
    $dbUser = $_POST['db_user'];
    $dbPass = $_POST['db_pass'];
    $dbName = $_POST['db_name'];

    try {
        // Test database connection
        $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        // Create database if it doesn't exist
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
        $pdo->exec("USE `$dbName`");

        // Import setup.sql
        $sql = file_get_contents('setup.sql');
        $pdo->exec($sql);

        // Write configuration file
        $configContent = "<?php\nreturn [\n    'db_host' => '$dbHost',\n    'db_user' => '$dbUser',\n    'db_pass' => '$dbPass',\n    'db_name' => '$dbName',\n];";
        file_put_contents('config.php', $configContent);

        echo "Installation completed successfully!";
        exit;
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Application</title>
</head>
<body>
    <h1>Install Application</h1>
    <form method="POST">
        <label for="db_host">Database Host:</label>
        <input type="text" id="db_host" name="db_host" required><br><br>

        <label for="db_user">Database User:</label>
        <input type="text" id="db_user" name="db_user" required><br><br>

        <label for="db_pass">Database Password:</label>
        <input type="password" id="db_pass" name="db_pass"><br><br>

        <label for="db_name">Database Name:</label>
        <input type="text" id="db_name" name="db_name" required><br><br>

        <button type="submit">Install</button>
    </form>
</body>
</html>