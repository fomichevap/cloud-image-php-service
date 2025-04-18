<?php
/**
 * Class Database for connecting to SQLite using PDO.
 */
class Database {
    // Static property for storing the single instance of the class.
    private static $instance = null;
    
    // PDO connection.
    private $pdo;

    /**
     * Private constructor to initialize the connection.
     */
    private function __construct() {
        // Use DB_FILE from config.php to form the DSN.
        $dsn = 'sqlite:' . DB_FILE;
        try {
            $this->pdo = new PDO($dsn);
            // Set error mode to throw exceptions.
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Set default fetch mode to associative arrays.
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Log the connection error and terminate execution.
            error_log("SQLite connection error: " . $e->getMessage());
            die(DEBUG ? "Database connection error: " . $e->getMessage() : "Database connection error.");
        }
    }

    /**
     * Static method to get the single instance of the class.
     *
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Method to retrieve the PDO connection object.
     *
     * @return PDO
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    // Additional methods for database interactions (e.g., executing queries) can be added here.
}
