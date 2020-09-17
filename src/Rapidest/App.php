<?php

namespace Rapidest;

// @todo extend Workerman

// @todo make static?

class App
{
    public $db;
    public $model;
    public $user;
    
    public function __construct()
    {
        session_start();

        # @todo autoload otherwise
        $this->loadConfiguration()
            ->handleErrors()
            ->connectToDb();
    }

    public function run()
    {
        return $this->loadUser()
            ->route();
    }

    // @todo trigger automatically upon deployment
    public function build()
    {
        return new Build($this);
    }

    public function loadConfiguration()
    {
        /** @todo improve upon this kludge */
        require_once dirname(dirname(__DIR__)) . '/config.php';
        return $this;
    }

    public function handleErrors()
    {
        $isDevEnv = RAPIDEST_DEV_ENV ?? 0;
        error_reporting($isDevEnv ? E_ALL : 0);
        ini_set('display_errors', $isDevEnv);
        ini_set('display_startup_errors', $isDevEnv);
        $error = new Error;
        set_error_handler([$error, 'handleError']);
        set_exception_handler([$error, 'handleException']);

        return $this;
    }

    public function connectToDb()
    {
        # @todo change user, password by default
        $this->db = new \mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
        
        if ($this->db->connect_error) {
            $this->db = new \mysqli(DB_HOST, DB_USER, DB_PASSWORD);
            if ($this->db->connect_error) {
                # @todo throw Exception - should never happen
                die('Connect Error (' . $this->db->connect_errno . ') ' . $this->db->connect_error);
            }
            if (!$this->db->query('create database ' . DB_NAME)) {
                die('Error: ' . $this->db->error);
            }
        }

        return $this;
    }

    public function loadUser()
    {
        $this->user = (new User)->loadBySessionOrCookie();

        # @todo dispense with chaining?
        return $this;
    }

    /** @todo batch action */
    public function route()
    {
        // @todo URI convention: [:resource]/[:id|:method]

        // @todo move to function
        $uri = str_replace(REWRITE_BASE, '', trim($_SERVER['REQUEST_URI'], '/'));
        $uri = current(explode('?', $uri));
        $arr = explode('/', $uri);
        $id = array_pop($arr);

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        # @todo move to function
        # @todo account for PATCH, PUT
        if ($method == 'OPTIONS') {
            header('HTTP/1.1 200 OK');
            exit;
        } elseif ($method == 'GET') {
            $vars = $_GET;
        } elseif ($method == 'POST') {
            $vars = $_POST;
        } else {
            parse_str(file_get_contents('php://input'), $vars);
        }
        
        /** @todo how to denote new record? */
        if ($id > 0 || $id == '0') {
            $vars['id'] = (int) $id;
            $uri = implode('/', $arr);
        } else {
            $arr[] = $id;
        }

        if (!strlen($uri)) {
            die(json_encode(['error'=>'Missing URI']));
            throw new Error('Missing URI');
            //return $this;
        }

        # @todo improve
        if (!$this->user->may($method, $uri)) {
            throw new Error('Forbidden');
        }

        // @todo cache results for GET requests, esp. [:resource]/[:id]
        
        die(json_encode(['data'=>$vars]));

        # @todo load static content or cached data

        $arr = array_map('ucfirst', $arr);
        $class = implode('\\', $arr);
        $this->model = $this->model($class);
        $method = $vars['method'] ?? $method;

        try {
            $response = $this->model->$method($vars);
        /** @todo remove for production environment */
        } catch (Exception $e) {
            $response = ['error' => $e->getMessage() . $e->getTraceAsString()];
        }

        /** @todo provide for fetch method */
        /** @todo shouldn't every request be AJAX after the initial page load? */
        //if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            //&& preg_match('/xmlhttprequest/i', $_SERVER['HTTP_X_REQUESTED_WITH'])
        //) {
            die(json_encode($response));
        //}
        
        //die('<pre>' . print_r($response, true));
        //$this->deliverResponse($response);
    }

    public function model(string $class)
    {
        /** @todo better dependency injection needed; use trait? */
        # @todo constants
        $options = ['db' => $this->db];

        # @todo readability
        return (class_exists($fullName = '\\' . __NAMESPACE__ . $class))
            ? new $fullName($options)
            : new Model($options + ['tableName' => str_replace('\\', '_', $class)]);
    }

    # @todo move to model
    public function tableExists($tableName)
    {
        $tableExists = false;
        /** @todo centralize escape and select */
        if (strpos($tableName, '.') !== false) {
            $arr = explode('.', $tableName);
            if (count($arr) > 2) {
                throw new Exception('Invalid table name: ' . $tableName);
            }
            $tableSchema = $this->db->real_escape_string($arr[0]);
            $tableName = $this->db->real_escape_string($arr[1]);
            $sql = "select * from information_schema.tables where table_schema = '{$tableSchema}' and table_name = '{$tableName}'";
        } else {
            $tableName = $this->db->real_escape_string($tableName);
            $sql = "show tables like '{$tableName}'";
        }
        if ($result = $this->db->query($sql)) {
            $tableExists = ($result && $result->num_rows > 0);
            $result->close();
        }
        return $tableExists;
    }

    public function query(string $sql)
    {
        if (!$this->db->query($sql)) {
            throw new Error("Error: {$this->db->error};\nSQL: {$sql}");
        }
        return $this;
    }
}