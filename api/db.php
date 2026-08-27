<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set("Asia/Taipei");

$config = require __DIR__ . "/../../db_config/card/db_config.php";

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

    // 💡 已修正：安全補上 $next = null，徹底解決未定義變數崩潰的 Bug
    public function find($where, $next = null)
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

    // 💡 已升級：智慧自訂複雜查詢通道，安全通吃 api.php 的多表 JOIN 與 RAND()
    public function q($sql, $bindings = [])
    {
        if (!empty($bindings)) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($bindings);
            return $statement->fetchAll();
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute();
        return $statement->fetchAll();
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

// 實例化全域物件
$Category       = new DB('categories');
$CSS            = new DB('css_terms');
$HTML           = new DB('html_terms');
$Learner        = new DB('learners');
$LearningRecord = new DB('learning_records');
$Word           = new DB('words');

?>