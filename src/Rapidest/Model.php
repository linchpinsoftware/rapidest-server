<?php

namespace Rapidest;

/** @todo fold into index.php? */
class Model
{
    const ID = 'id';

    /** @todo store data as properties instead? */
    protected $data = array();
    /** @todo inject using trait */
    protected $db = null;
    protected $describe = null;
    protected $foreignKeys = null;
    /** @todo ACL */
    protected $may = null;
    /** @todo prevent access to information_schema */
    /** @todo use URI instead? */
    protected $tableName = null;
    protected $tableSchema = null;
    protected $uri = null;
    /** @todo add title */
    /** @todo add form definitions */
    /** @todo add grid definitions */

    public function __construct($options = null)
    {
        if (!is_array($options) && !is_object($options)) {
            $options = array('tableName' => $options);
        }
        foreach ($options as $k => $v) {
            $this->$k = $v;
        }
        if (isset($options['tableName'])
            && count($arr = explode('.', $options['tableName'])) > 1
        ) {
            $this->tableSchema = $this->db->real_escape_string($arr[0]);
            $this->tableName = $this->db->real_escape_string($arr[1]);
        } elseif ($this->tableSchema === null) {
            $this->tableSchema = $this->tableSchema();
        }
        $this->uri = $this->tableName;
        if ($this->tableName !== null) {
            foreach (array('describe', 'foreignKeys') as $k => $v) {
                if ($this->$v === null) {
                    $this->$v = $this->$v();
                }
            }
        }
    }

    protected function tableSchema()
    {
        if (($result = $this->db->query($sql = 'select database() as db'))
            && ($row = $result->fetch_assoc())
        ) {
            return $row['db'];
        }
        return null;
    }

    /** @todo separate function for fetch all */
    /** @todo force prepared statements and binding of parameters? */
    protected function query($sql)
    {
        /** @todo error-handling */
        if ($result = $this->db->query($sql)) {
            $arr = array();
            while ($row = $result->fetch_assoc()) {
                $arr[] = $row;
            }
            return $arr;
        }
        /** @todo include columns comment as well */
        die(json_encode(array('error' => 'Error in SQL: ' . $sql)));
    }

    /** @todo include options, i.e., possible values? */
    protected function foreignKeys()
    {
        return $this->query("
            select *
            from information_schema.key_column_usage
            where table_schema = '{$this->tableSchema}' and table_name = '{$this->tableName}' and referenced_table_name is not null
        ");
    }

    /** @todo */
    public function DELETE($params)
    {
        die(json_encode(array('method' => __METHOD__, 'parameters' => $params)));
    }

    /** @todo */
    public function POST($params)
    {
        die(json_encode(array('method' => __METHOD__, 'parameters' => $params)));
    }

    /** @todo */
    public function PUT($params)
    {
        die(json_encode(array('method' => __METHOD__, 'parameters' => $params)));
    }

    public function GET($params)
    {
        /** @todo use static::ID instead? flexible vs. inflexible */
        $idColumn = self::ID;

        /** @todo allow for composite key? */
        /** @todo use create or new or other paramater instead? */
        if (isset($params[self::ID]) && $params[self::ID] == 0) {
            $this->data = array();
        } elseif (isset($params[self::ID])) {
            /** @todo convert to load or find function */
            $id = (int) $params[self::ID];
            $sql = "select * from {$this->tableName} where {$idColumn} = {$id}";
            $result = $this->db->query($sql);
            if ($result->num_rows == 0) {
                throw new Exception('Resource not found');
            } elseif ($result->num_rows > 1) {
                throw new Exception('Resource is not unique');
            }
            $this->data = $result->fetch_assoc();
        } else {
            /** @todo create separate collection? */
            /** @todo generalize SELECT */
            $where = array();
            foreach ($params as $k => $v) {
                /** @todo allow for different operators, e.g., IN */
                /** @todo how to escape $k? check that column exists? */
                $v = $this->db->real_escape_string($v);
                $where .= "{$k} = '{$v}'";
            }
            $where = (empty($where)) ? '' : ' where ' . implode(' and ', $where);
            /** @todo impose limit */
            $this->data = $this->query("select * from {$this->tableName} {$where} limit 10");
        }

        return $this->toArray();
    }

    /** @todo account for EAV and other alternate structures */
    protected function describe()
    {
        return $this->query('describe ' . $this->tableName);
    }

    public function getData($key = null)
    {
        return ($key && isset($this->data[$key])) ? $this->data[$key] : null;
    }

    public function setData($key, $value = null)
    {
        if (!is_array($key)) {
            $this->data[$key] = $value;
        } else {
            foreach ($key as $k => $v) {
                $this->data[$k] = $v;
            }
        }
        return $this;
    }

    public function toArray()
    {
        $arr = array();
        foreach ($this as $k => $v) {
            /** @todo eliminate kludge */
            if ($k != 'db') {
                $arr[$k] = $v;
            }
        }
        return $arr;
    }
}