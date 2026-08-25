<?php

session_start();
date_default_timezone_set("Asia/Taipei");

$config = require __DIR__ . "/../../db_config/vocabulary/db_config.php";

class DB
{
    protected $dsn;
    protected $pdo;
    protected $table;

    public function __construct($table)
    {
        global $config;

        $this->dsn = "{$config['driver']}:host={$config['host']};dbname={$config['database']}";

        if ($config['driver'] == "mysql") {
            $this->dsn .= ";charset=utf8mb4";
        }

        $this->pdo = new PDO($this->dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $this->table = $table;
    }

    protected function a2s($array)
    {
        $tmp = [];
        foreach ($array as $key => $value) {
            $tmp[] = "`$key` = :$key";
        }
        return $tmp;
    }

    public function all($where = null, $next = null)
    {
        $sql = "SELECT * FROM `$this->table`";
        $bindings = [];

        if (!empty($where)) {
            if (is_array($where)) {
                $sql .= " WHERE " . join(" AND ", $this->a2s($where));
                $bindings = $where;
            } else {
                $sql .= " " . trim($where);
            }
        }

        if (!empty($next)) {
            $sql .= " " . trim($next);
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);
        return $statement->fetchAll();
    }

    public function count($where = null, $next = null)
    {
        $sql = "SELECT COUNT(*) FROM `$this->table`";
        $bindings = [];

        if (!empty($where)) {
            if (is_array($where)) {
                $sql .= " WHERE " . join(" AND ", $this->a2s($where));
                $bindings = $where;
            } else {
                $sql .= " " . trim($where);
            }
        }

        if (!empty($next)) {
            $sql .= " " . trim($next);
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);
        return $statement->fetchColumn();
    }

    public function find($where)
    {
        $sql = "SELECT * FROM `$this->table` WHERE ";
        $bindings = [];

        if (is_array($where)) {
            $sql .= join(" AND ", $this->a2s($where));
            $bindings = $where;
        } else {
            $sql .= "`id` = :id";
            $bindings = ['id' => $where];
        }

        if (!empty($next)) {
            $sql .= " " . trim($next);
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);
        return $statement->fetch();
    }

    public function save($where)
    {
        if (isset($where['id'])) {
            $id = $where['id'];
            unset($where['id']);
            $sql = "UPDATE `$this->table` SET " . join(", ", $this->a2s($where)) . " WHERE `id` = :id";
            $where['id'] = $id;
        } else {
            $fields = array_keys($where);
            $placeholders = array_map(function ($field) {
                return ":$field";
            }, $fields);
            $sql = "INSERT INTO `$this->table` (`" . join("`, `", $fields) . "`) VALUES (" . join(", ", $placeholders) . ")";
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($where);
    }

    public function delete($where)
    {
        $sql = "DELETE FROM `$this->table` WHERE ";
        $bindings = [];

        if (is_array($where)) {
            $sql .= join(" AND ", $this->a2s($where));
            $bindings = $where;
        } else {
            $sql .= "`id` = :id";
            $bindings = ['id' => $where];
        }

        $statement = $this->pdo->prepare($sql);
        $statement->execute($bindings);
    }

    public function q($sql)
    {
        return $this->pdo->query($sql)->fetchAll();
    }
}

function to($url)
{
    header("Location: $url");
    exit();
}

function dd($array)
{
    echo "<pre>";
    print_r($array);
    echo "</pre>";
}

$Category = new DB('categories');
$CSS = new DB('css_terms');
$HTML = new DB('html_terms');
$Learner = new DB('learners');
$LearningRecord = new DB('learning_records');
$Word = new DB('words');

?>