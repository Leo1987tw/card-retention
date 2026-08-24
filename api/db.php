<?php

session_start();
date_default_timezone_set("Asia/Taipei");

$config = require __DIR__ . "/../../db_config/vocabulary/db_config.php";

class DB {
    protected $dsn;
    protected $pdo;
    protected $table;

    public function __construct($table){
        global $config;

        $this->dsn = "{$config['driver']}:host={$config['host']}; dbname={$config['database']}";

        if($config['driver'] == "mysql"){
            $this->dsn .= "; charset=utf8";
        }

        $this->pdo = new PDO($this->dsn, $config['username'], $config['password'], []);
        $this->table = $table;
    }

    protected function a2s($array){
        $tmp = [];
        foreach($array as $key => $value){
            $tmp[] = "`$key`='$value'";
        }

        return $tmp;
    }

    public function all($where = null, $next = null){
        $sql = "SELECT * FROM `$this->table`";
        if(!empty($where)){
            if(is_array($where)){
                $sql .= " WHERE " . join(" AND ", $this->a2s($where));
            }else {
                $sql .= " " . trim($where);
            }
        }

        if(!empty($next)){
            $sql .= " " . trim($next);
        }

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function count($where = null, $next = null){
        $sql = "SELECT COUNT(*) FROM `$this->table`";
        if(!empty($where)){
            if(is_array($where)){
                $sql .= " WHERE " . join(" AND ", $this->a2s($where));
            }else {
                $sql .= " " . trim($where);
            }
        }

        if(!empty($next)){
            $sql .= " " . trim($next);
        }

        return $this->pdo->query($sql)->fetchColumn();
    }
    public function find($where = null, $next = null){
        $sql = "SELECT * FROM `$this->table`";
        if(!empty($where)){
            if(is_array($where)){
                $sql .= " WHERE " . join(" AND ", $this->a2s($where));
            }else {
                $sql .= " WHERE `id`='$where'";
            }
        }

        if(!empty($next)){
            $sql .= " " . trim($next);
        }

        return $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    public function save($where){
        if(isset($where['id'])){
            $id = $where['id'];
            unset($where['id']);
            $sql = "UPDATE `$this->table` SET " . join(", ", $this->a2s($where)) . " WHERE `id`='$id'";
        }else {
            $sql = "INSERT INTO `$this->table`(`" . join("`, `", array_keys($where)) . "`) VALUES ('" . join("', '", $where) . "')";
        }

        $this->pdo->exec($sql);
    }

    public function delete($where){
        $sql = "DELETE FROM `$this->table`";
        if(is_array($where)){
            $sql .= " WHERE " . join(" AND ", $this->a2s($where));
        }else {
            $sql .= " WHERE `id`='$where'";
        }

        $this->pdo->exec($sql);
    }

    public function q($sql){
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

function to($url){
    header("location: $url");
}

function dd($array){
    echo "<pre>";
    print_r($array);
    echo "</pre>";
}

$Word = new DB('words');
$Learner = new DB('learner');
$LearningRecord = new DB('learning_records');
$HTML = new DB('html_terms');
$CSS = new DB('css_terms');
$Category = new DB('categories');

?>