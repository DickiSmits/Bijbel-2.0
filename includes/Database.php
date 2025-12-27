<?php
/**
 * Database Class
 * 
 * Handelt alle database operaties af met PDO
 */

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $this->pdo = new PDO('sqlite:' . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->initializeTables();
        } catch (PDOException $e) {
            die('Database verbinding mislukt: ' . $e->getMessage());
        }
    }
    
    /**
     * Singleton pattern - geef database instantie terug
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Geef PDO connectie terug
     */
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * Initialiseer tabellen die mogelijk nog niet bestaan
     */
    private function initializeTables() {
        // Notities tabel
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS Notities (
            Notitie_ID INTEGER PRIMARY KEY AUTOINCREMENT,
            Titel TEXT,
            Inhoud TEXT,
            Aangemaakt DATETIME DEFAULT CURRENT_TIMESTAMP,
            Gewijzigd DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Migratie: Voeg Tekst_Kleur kolom toe aan Timeline_Events
        try {
            $columns = $this->pdo->query("PRAGMA table_info(Timeline_Events)")->fetchAll();
            $hasTextColor = false;
            foreach ($columns as $col) {
                if ($col['name'] === 'Tekst_Kleur') {
                    $hasTextColor = true;
                    break;
                }
            }
            if (!$hasTextColor) {
                $this->pdo->exec("ALTER TABLE Timeline_Events ADD COLUMN Tekst_Kleur TEXT");
            }
        } catch (Exception $e) {
            // Table might not exist yet, that's okay
        }
    }
    
    /**
     * Voer een query uit en geef resultaten terug
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log('Database query fout: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Voer een query uit en geef eerste resultaat terug
     */
    public function queryOne($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log('Database query fout: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Voer een insert/update/delete uit
     */
    public function execute($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($params);
            return $result;
        } catch (PDOException $e) {
            error_log('Database execute fout: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Geef laatste insert ID terug
     */
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Start een transactie
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Commit een transactie
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * Rollback een transactie
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
    
    /**
     * Prevent cloning of instance
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization of instance
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
