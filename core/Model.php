<?php
class Model
{
    protected static $db;
    protected $_table;

    public static function factory($model)
    {
        $model = 'Model_'.ucfirst($model);
        return new $model;
    }

    public function __construct()
    {
        if (self::$db === null) {
            self::$db = new PDO('mysql:host='.Config::DB_HOST.';dbname='.Config::DB_NAME.';charset=utf8', Config::DB_USR, Config::DB_PWD, array(PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_LOCAL_INFILE => true));
        }
        return $this;
    }

    /* @TODO */
    /*public function all($sort, $order, $limit, $offset, $filters)
    {
        $result = self::$db->query("SELECT * FROM {$this->table()}");
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }

    public function get($id, $column = "id")
    {
        if ($column !== "id") {
            $t = $id;
            $id = $column;
            $column = $t;
        }
        $result = self::$db->query("SELECT * FROM {$this->table()} WHERE {$column} = {$id}");
        return $result->fetchAll(PDO::FETCH_ASSOC);
    }*/

    public function table($name = null)
    {
        if (!$name) {
            return $this->_table;
        }
        $this->_table = $name;
    }
}
