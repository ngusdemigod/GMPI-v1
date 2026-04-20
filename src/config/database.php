<?php
/**
 * Database Configuration and Connection Class
 * Church Financial Partnership System
 */

// Load environment variables from .env file
// Try multiple paths to find the .env.php file
$envPaths = [
    __DIR__ . '/../.env.php',           // src/.env.php
    __DIR__ . '/../../.env.php',         // root/.env.php
    '/var/www/html/.env.php',            // Docker root
    '/var/www/html/src/.env.php'         // Docker src
];

$envFileFound = false;
foreach ($envPaths as $envPath) {
    if (file_exists($envPath)) {
        require_once $envPath;
        $envFileFound = true;
        break;
    }
}

if (!$envFileFound) {
    // Set default values if .env file not found
    if (!defined('DB_HOST')) define('DB_HOST', 'mysql');
    if (!defined('DB_NAME')) define('DB_NAME', 'church_partnership');
    if (!defined('DB_USER')) define('DB_USER', 'church_user');
    if (!defined('DB_PASSWORD')) define('DB_PASSWORD', 'church_password123');
}

if (!class_exists('Database', false)) {
class Database {
    private static $instance = null;
    private $connection;
    private $host;
    private $port;
    private $dbname;
    private $username;
    private $password;
    private $charset = 'utf8mb4';

    /**
     * Build an ordered list of database hosts to try.
     *
     * `mysql` works inside Docker networking, while `127.0.0.1` is the common
     * local-development fallback when PHP is running directly on the host.
     */
    private function buildHostCandidates(string $configuredHost): array
    {
        $hosts = [$configuredHost];

        if ($configuredHost === 'mysql') {
            $hosts[] = '127.0.0.1';
            $hosts[] = 'localhost';
        }

        return array_values(array_unique(array_filter($hosts)));
    }
    
    /**
     * Private constructor for singleton pattern
     */
    private function __construct() {
        // Load .env
        $envFile = __DIR__ . '/../.env.php';
        if (file_exists($envFile)) {
            require_once $envFile;
        }

        // Database configuration
        // In Docker, use 'mysql' as hostname; otherwise use 127.0.0.1
        $this->host = $this->getEnvVar('DB_HOST') ?: (getenv('DOCKER_ENV') ? 'mysql' : '127.0.0.1');
        $this->port = $this->getEnvVar('DB_PORT') ?: '3306';
        $this->dbname = $this->getEnvVar('DB_NAME') ?: 'church_partnership';
        $this->username = $this->getEnvVar('DB_USER') ?: 'church_user';
        $this->password = $this->getEnvVar('DB_PASSWORD') ?: 'church_password123';

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];

        $connectionErrors = [];

        foreach ($this->buildHostCandidates($this->host) as $hostCandidate) {
            try {
                $dsn = "mysql:host={$hostCandidate};port={$this->port};dbname={$this->dbname};charset={$this->charset}";
                $this->connection = new PDO($dsn, $this->username, $this->password, $options);
                $this->host = $hostCandidate;
                return;
            } catch (PDOException $e) {
                $connectionErrors[] = sprintf('%s => %s', $hostCandidate, $e->getMessage());
            }
        }

        error_log("Database connection failed: " . implode(' | ', $connectionErrors));
        error_log("Database connection details - Host: {$this->host}, Port: {$this->port}, Database: {$this->dbname}");
        throw new Exception("Database connection failed: " . implode(' | ', $connectionErrors));
    }

    /**
     * Helper to get environment variable with multiple fallbacks
     */
    private function getEnvVar($key) {
        if (isset($_ENV[$key])) return $_ENV[$key];
        if (isset($_SERVER[$key])) return $_SERVER[$key];
        $val = getenv($key);
        return $val !== false ? $val : null;
    }
    
    /**
     * Debug method to check database connection
     */
    public static function testConnection() {
        try {
            $dsn = "mysql:host=" . getenv('DB_HOST') ?: '127.0.0.1';
            $conn = new PDO($dsn, getenv('DB_USER') ?: 'church_user', getenv('DB_PASSWORD') ?: 'church_password123');
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return ['success' => true, 'message' => 'Connection successful'];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get singleton instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Execute a prepared statement with parameters
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement
     */
    public function prepare($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            foreach ($params as $key => $value) {
                $paramKey = is_string($key) ? ":{$key}" : $key;
                $paramType = is_int($value) ? PDO::PARAM_INT : 
                            (is_bool($value) ? PDO::PARAM_BOOL : PDO::PARAM_STR);
                $stmt->bindValue($paramKey, $value, $paramType);
            }
            return $stmt;
        } catch (PDOException $e) {
            error_log("Prepare failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Execute a query and return affected rows
     */
    public function execute($sql, $params = []) {
        $stmt = $this->prepare($sql, $params);
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    /**
     * Fetch a single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->prepare($sql, $params);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->prepare($sql, $params);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
}

/**
 * Helper function to get database connection
 * @return PDO
 */
if (!function_exists('getDbConnection')) {
    function getDbConnection() {
        return Database::getInstance()->getConnection();
    }
}
