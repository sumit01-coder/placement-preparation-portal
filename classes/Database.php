<?php
// ============================================
// DATABASE CLASS - PDO WRAPPER
// ============================================

class Database {
    private static $instance = null;
    private $connection;
    private $tableExistsCache = [];
    private $columnExistsCache = [];

    private function filterNamedParamsForSql(string $sql, array $params): array {
        $hasStringKey = false;
        foreach (array_keys($params) as $key) {
            if (is_string($key)) {
                $hasStringKey = true;
                break;
            }
        }

        if (!$hasStringKey) {
            return $params;
        }

        if (!preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches)) {
            return [];
        }

        $allowed = array_fill_keys($matches[1], true);
        return array_intersect_key($params, $allowed);
    }
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => true
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Execute a query and return results
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            if (is_array($params) && !empty($params)) {
                $params = $this->filterNamedParamsForSql($sql, $params);
            }
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            throw $e;
        }
    }
    
    // Fetch all rows
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Fetch single row
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    // Get last insert ID
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    // Begin transaction
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    // Commit transaction
    public function commit() {
        return $this->connection->commit();
    }
    
    // Rollback transaction
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    // Count rows
    public function count($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Insert data
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        return $this->query($sql, $data);
    }
    
    // Update data
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :{$column}";
        }
        $setClause = implode(', ', $set);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        
        $params = array_merge($data, $whereParams);
        
        return $this->query($sql, $params);
    }
    
    // Delete data
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return $this->query($sql, $whereParams);
    }

    public function tableExists($table) {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        try {
            $row = $this->fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                 AND table_name = :table_name",
                ['table_name' => $table]
            );
            $this->tableExistsCache[$table] = ((int)($row['cnt'] ?? 0)) > 0;
        } catch (Exception $e) {
            $this->tableExistsCache[$table] = false;
        }

        return $this->tableExistsCache[$table];
    }

    public function columnExists($table, $column) {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        if (!$this->tableExists($table)) {
            $this->columnExistsCache[$cacheKey] = false;
            return false;
        }

        try {
            $row = $this->fetchOne(
                "SELECT COUNT(*) AS cnt
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                 AND table_name = :table_name
                 AND column_name = :column_name",
                ['table_name' => $table, 'column_name' => $column]
            );
            $this->columnExistsCache[$cacheKey] = ((int)($row['cnt'] ?? 0)) > 0;
        } catch (Exception $e) {
            $this->columnExistsCache[$cacheKey] = false;
        }

        return $this->columnExistsCache[$cacheKey];
    }

    public function firstExistingTable(array $candidates) {
        foreach ($candidates as $candidate) {
            if ($this->tableExists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
