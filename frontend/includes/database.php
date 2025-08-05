<?php
/**
 * Database connection and configuration for RadioGrab PHP frontend
 */

class Database {
    private $host;
    private $port;
    private $username;
    private $password;
    private $database;
    private $connection;
    private static $instance;
    
    public function __construct() {
        // Load configuration from environment or config file
        $this->host = $_SERVER['DB_HOST'] ?? 'localhost';
        $this->port = $_SERVER['DB_PORT'] ?? '3306';
        $this->username = $_SERVER['DB_USER'] ?? 'radiograb';
        $this->password = $_SERVER['DB_PASSWORD'] ?? 'radiograb_pass_2024';
        $this->database = $_SERVER['DB_NAME'] ?? 'radiograb';
    }
    
    public function connect() {
        if ($this->connection === null) {
            try {
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
                $this->connection = new PDO($dsn, $this->username, $this->password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                throw new Exception("Database connection failed");
            }
        }
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connect()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query failed: " . $e->getMessage());
            throw new Exception("Database query failed");
        }
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function fetchOne($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
        
        $this->query($sql, $data);
        return $this->connect()->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $fields = [];
        foreach (array_keys($data) as $field) {
            $fields[] = "{$field} = :{$field}";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $fields) . " WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        return $this->query($sql, $params);
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $params);
    }
    
    public function execute($sql, $params = []) {
        return $this->query($sql, $params);
    }
    
    /**
     * Singleton pattern for getInstance() compatibility
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

// Global database instance
$db = new Database();

// Also create a global PDO connection for legacy compatibility
try {
    $pdo = $db->connect();
} catch (Exception $e) {
    error_log("Failed to create global PDO connection: " . $e->getMessage());
    $pdo = null;
}
?>