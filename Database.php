<?php
class Database {
    /* database data */
    public static $driver = 'mysql';
    public static $name = 'dbname=';
    public static $host = 'host=';
    public static $username = 'username';
    public static $password = 'password';

    /* Othser database settings */
    public static $limit = 10;

    protected $db;

    public static function getDsn() {
        return self::$driver.':'.self::$name.';'.self::$host;
    }

    public static function getPDO() {
        return new PDO(self::getDsn(), self::$username, self::$password);
    }

    public function __construct() {
        $this->db = self::getPDO();
        $this->db->exec('SET NAMES "utf8"');
    }

    public function getTableColumns($table_name) {
        $statement = $this->db->prepare('SHOW COLUMNS FROM '.$table_name);
        $statement->execute();
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    }

    public function singleSelect($query, $values = []) {
        $statement = $this->db->prepare($query);

        foreach ($values as $val) {
            $statement->bindValue($val['name'], $val['value'], $val['type']);
        }

        $statement->execute();
        $results = $statement->fetchAll(PDO::FETCH_ASSOC);
        return $results;
    }

    public function singleExecute($query, $values = []) {
        $statement = $this->db->prepare($query);

        foreach ($values as $val) {
            $statement->bindValue($val['name'], $val['value'], $val['type']);
        }

        $results = $statement->execute();
        echo $statement->errorInfo()[2];
        return $results;
    }

    public function singleInsert($query, $values = []) {
        $statement = $this->db->prepare($query);

        foreach ($values as $val) {
            $statement->bindValue($val['name'], $val['value'], $val['type']);
        }

        $results = $statement->execute();
        return isset($results) ? $this->pdo->lasdInsertId() : -1;
    }

    public function beginTransaction() {
        if ($this->db->inTransaction()) {
            return false;
        }
        $this->db->beginTransaction();
        return true;
    }

    public function commit() {
        $this->db->commit();
    }

    public function rollBack() {
        $this->db->rollBack();
    }

    public function inTransaction() {
        return $this->db->inTransaction();
    }

    public function lastInsertId() {
        return $this->db->lastInsertId();
    }
}

$database = new Database();
