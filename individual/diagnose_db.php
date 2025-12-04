<?php
echo "<h2>Database Connection Diagnostic</h2>";

// Test 1: Check if MySQL is running at all
echo "<h3>Test 1: Basic MySQL Connectivity</h3>";

$test_connections = [
    ['host' => 'localhost', 'port' => 3306, 'desc' => 'Standard MySQL port'],
    ['host' => '127.0.0.1', 'port' => 3306, 'desc' => 'Local IP'],
    ['host' => 'mysql', 'port' => 3306, 'desc' => 'Docker/Container'],
];

foreach ($test_connections as $conn) {
    echo "Testing {$conn['host']}:{$conn['port']} ({$conn['desc']})... ";
    
    $socket = @fsockopen($conn['host'], $conn['port'], $errno, $errstr, 5);
    
    if ($socket) {
        echo "✅ Port is open (MySQL might be running)<br>";
        fclose($socket);
    } else {
        echo "❌ Port is closed or blocked ($errstr)<br>";
    }
}

// Test 2: Try different authentication methods
echo "<h3>Test 2: Authentication Methods</h3>";

$auth_tests = [
    ['user' => 'princess.agyemfra', 'pass' => '', 'desc' => 'Empty password'],
    ['user' => 'princess.agyemfra', 'pass' => 'princess.agyemfra', 'desc' => 'Same as username'],
    ['user' => 'princess.agyemfra', 'pass' => 'princessagyemfra', 'desc' => 'Username without dots'],
    ['user' => 'root', 'pass' => '', 'desc' => 'Root with empty password'],
    ['user' => 'root', 'pass' => 'root', 'desc' => 'Root with "root" password'],
];

foreach ($auth_tests as $test) {
    echo "Trying {$test['user']} with password '{$test['pass']}'... ";
    
    try {
        $pdo = new PDO("mysql:host=localhost", $test['user'], $test['pass']);
        echo "✅ SUCCESS!<br>";
        
        // Show privileges
        $stmt = $pdo->query("SHOW GRANTS FOR CURRENT_USER()");
        $grants = $stmt->fetchAll();
        echo "Grants: ";
        foreach ($grants as $grant) {
            echo "<br>&nbsp;&nbsp;- " . $grant[0];
        }
        echo "<br>";
        
        break;
    } catch(PDOException $e) {
        echo "❌ Failed: " . $e->getMessage() . "<br>";
    }
}

// Test 3: Check PHP configuration
echo "<h3>Test 3: PHP Configuration</h3>";

echo "PHP Version: " . phpversion() . "<br>";
echo "PDO MySQL available: " . (extension_loaded('pdo_mysql') ? '✅ Yes' : '❌ No') . "<br>";
echo "MySQLi available: " . (extension_loaded('mysqli') ? '✅ Yes' : '❌ No') . "<br>";

// Test 4: Try MySQLi as alternative
echo "<h3>Test 4: MySQLi Test</h3>";

try {
    $mysqli = @new mysqli('localhost', 'princess.agyemfra', '');
    
    if ($mysqli->connect_error) {
        echo "MySQLi Error: " . $mysqli->connect_error . "<br>";
    } else {
        echo "✅ MySQLi Connected!<br>";
        echo "Server version: " . $mysqli->server_info . "<br>";
        $mysqli->close();
    }
} catch(Exception $e) {
    echo "MySQLi Exception: " . $e->getMessage() . "<br>";
}

// Test 5: Check if user exists in MySQL
echo "<h3>Test 5: Checking MySQL User Accounts</h3>";

// Try to connect as root first (common default)
try {
    $pdo_root = new PDO("mysql:host=localhost", 'root', '');
    echo "✅ Connected as root<br>";
    
    // Check if your user exists
    $stmt = $pdo_root->query("SELECT User, Host FROM mysql.user WHERE User LIKE '%princess%' OR User LIKE '%agyemfra%'");
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "Found users:<br>";
        foreach ($users as $user) {
            echo "- " . $user['User'] . "@" . $user['Host'] . "<br>";
        }
    } else {
        echo "No user found with 'princess' or 'agyemfra' in name<br>";
    }
    
} catch(PDOException $e) {
    echo "Cannot connect as root: " . $e->getMessage() . "<br>";
}

// Test 6: Check file permissions
echo "<h3>Test 6: File Permissions</h3>";

$files_to_check = [
    'includes/database.php',
    'includes/auth.php',
    'index.php',
    'login.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        echo "$file: Permissions $perms, Size: " . filesize($file) . " bytes<br>";
    } else {
        echo "$file: ❌ File not found<br>";
    }
}

// Recommendations
echo "<h3>Possible Solutions:</h3>";
echo "<ol>";
echo "<li><strong>Contact your instructor</strong> for correct database credentials</li>";
echo "<li>Check if database access is through <strong>phpMyAdmin</strong> (usually at http://localhost/phpmyadmin)</li>";
echo "<li>Your user might need to be created in MySQL: <code>CREATE USER 'princess.agyemfra'@'localhost' IDENTIFIED BY 'password';</code></li>";
echo "<li>Check if you need to use a <strong>different host</strong> (not localhost)</li>";
echo "</ol>";

// Quick connection form for testing
echo "<h3>Test Connection Form:</h3>";
?>
<form method="post" style="border: 1px solid #ccc; padding: 15px; border-radius: 5px;">
    <label>Host: <input type="text" name="host" value="localhost"></label><br>
    <label>Username: <input type="text" name="user" value="princess.agyemfra"></label><br>
    <label>Password: <input type="password" name="pass" value=""></label><br>
    <label>Database: <input type="text" name="db" value="princess_agyemfra_db"></label><br>
    <button type="submit">Test Connection</button>
</form>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $test_pdo = new PDO(
            "mysql:host={$_POST['host']};dbname={$_POST['db']}",
            $_POST['user'],
            $_POST['pass']
        );
        echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
        echo "✅ Connection successful!<br>";
        echo "Server: " . $test_pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "<br>";
        echo "</div>";
    } catch(PDOException $e) {
        echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
        echo "❌ Connection failed: " . $e->getMessage() . "<br>";
        echo "</div>";
    }
}
?>