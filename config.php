<?php
$dbname = getenv('DB_NAME') ?: '{database_name}';
$usernamedb = getenv('DB_USER') ?: '{username_db}';
$passworddb = getenv('DB_PASSWORD') ?: '{password_db}';
$dbhost = getenv('DB_HOST') ?: 'localhost';
$dbport = getenv('DB_PORT') ?: '3306';

$connect = mysqli_connect($dbhost, $usernamedb, $passworddb, $dbname, (int) $dbport);
if ($connect->connect_error) { die("error" . $connect->connect_error); }
mysqli_set_charset($connect, "utf8mb4");
$options = [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, ];
$dsn = "mysql:host=$dbhost;port=$dbport;dbname=$dbname;charset=utf8mb4";
try { $pdo = new PDO($dsn, $usernamedb, $passworddb, $options); } catch (\PDOException $e) { error_log("Database connection failed: " . $e->getMessage()); }
$APIKEY = getenv('API_KEY') ?: '{API_KEY}';
$adminnumber = getenv('ADMIN_NUMBER') ?: '{admin_number}';
$domainhosts = getenv('DOMAIN_NAME') ?: '{domain_name}';
$usernamebot = getenv('BOT_USERNAME') ?: '{username_bot}';

$appStorage = getenv('STORAGE_PATH') ?: __DIR__ . '/storage';
if (!defined('APP_STORAGE')) {
    define('APP_STORAGE', $appStorage);
}
if (!defined('APP_TMP')) {
    define('APP_TMP', APP_STORAGE . '/tmp');
}
if (!defined('APP_LOGS')) {
    define('APP_LOGS', APP_STORAGE . '/logs');
}

$new_marzban = getenv('NEW_MARZBAN') !== false ? filter_var(getenv('NEW_MARZBAN'), FILTER_VALIDATE_BOOLEAN) : true;
?>
