<?php
/**
 * Class DbWrapper
 *
 * A wrapper for database operations using PDO.
 * Supports custom placeholders:
 *  ?s - string
 *  ?i - integer
 *  ?a - array of values (will be inserted as a comma-separated list)
 *  ?u - associative array for update (creates a string of key=value pairs)
 *
 * Methods:
 *  - query()   : Executes an SQL query with value substitution and returns a PDOStatement object or false on error.
 *  - getRow()  : Executes a query and returns the first row of results as an associative array.
 *  - getAll()  : Executes a query and returns all result rows as an array of associative arrays.
 */
class DbWrapper {
    /**
     * @var PDO PDO object used for database operations.
     */
    private $pdo;

    /**
     * Constructor. Retrieves the connection from the Database class (Singleton).
     */
    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /**
     * Private method for substituting custom placeholders.
     * It searches for placeholders ?s, ?i, ?a, ?u in the query string and replaces them
     * with properly quoted values from the provided arguments array.
     *
     * @param string $query The original SQL query with placeholders.
     * @param array $args Array of values to substitute.
     * @return string The final SQL query with substituted values.
     * @throws Exception If there are not enough arguments provided or if the data is invalid.
     */
    private function buildQuery($query, $args) {
        $index = 0;
        $pdo = $this->pdo; // for use inside the callback
        $result = preg_replace_callback('/\?([siua])/', function($matches) use (&$index, $args, $pdo) {
            if (!array_key_exists($index, $args)) {
                throw new Exception("Not enough arguments provided for query substitution.");
            }
            $param = $args[$index];
            $index++;
            switch($matches[1]) {
                case 's':
                    // String type: escape using PDO::quote.
                    return $pdo->quote((string)$param);
                case 'i':
                    // Integer type.
                    return (int)$param;
                case 'a':
                    // Array of values (e.g., for IN clause).
                    if (!is_array($param) || empty($param)) {
                        throw new Exception("Expected a non-empty array for placeholder ?a.");
                    }
                    $list = array();
                    foreach ($param as $value) {
                        if (is_int($value) || is_float($value) || is_numeric($value)) {
                            // If number — cast to integer.
                            $list[] = (int)$value;
                        } else {
                            $list[] = $pdo->quote((string)$value);
                        }
                    }
                    return implode(',', $list);
                case 'u':
                    // Associative array for forming the update fragment.
                    if (!is_array($param) || empty($param)) {
                        throw new Exception("Expected a non-empty associative array for placeholder ?u.");
                    }
                    $pairs = array();
                    foreach ($param as $column => $value) {
                        // Simple sanitization for column names: allow only letters, numbers, and underscores.
                        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
                        if ($column === '') {
                            throw new Exception("Invalid column name for update.");
                        }
                        if (is_int($value) || is_float($value) || is_numeric($value)) {
                            $pairs[] = "$column=" . (int)$value;
                        } else {
                            $pairs[] = "$column=" . $pdo->quote((string)$value);
                        }
                    }
                    return implode(', ', $pairs);
                default:
                    return '';
            }
        }, $query);
        return $result;
    }

    /**
     * Executes an SQL query with value substitution.
     * Example usage:
     *   $db->query("SELECT * FROM table WHERE name = ?s AND id = ?i", "Vasya", 10);
     *
     * @param string $query The SQL query with placeholders.
     * @return PDOStatement|false Returns a PDOStatement object or false on error.
     */
    public function query($query /*, ...$params */) {
        $args = func_get_args();
        array_shift($args);
        try {
            $finalQuery = $this->buildQuery($query, $args);
            return $this->pdo->query($finalQuery);
        } catch (PDOException $e) {
            if (DEBUG) {
                error_log("Query execution error: " . $e->getMessage());
            }
            return false;
        } catch (Exception $e) {
            if (DEBUG) {
                error_log("Query building error: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Executes a query and returns the first row of the result set.
     *
     * @param string $query The SQL query with placeholders.
     * @return array|false An associative array of data or false if no row is found or on error.
     */
    public function getRow($query /*, ...$params */) {
        $stmt = call_user_func_array([$this, 'query'], func_get_args());
        if ($stmt !== false) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        return false;
    }

    /**
     * Executes a query and returns all rows of the result set.
     *
     * @param string $query The SQL query with placeholders.
     * @return array|false An array of associative arrays of data or false on error.
     */
    public function getAll($query /*, ...$params */) {
        $stmt = call_user_func_array([$this, 'query'], func_get_args());
        if ($stmt !== false) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return false;
    }
}
