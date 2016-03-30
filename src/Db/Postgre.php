<?php

namespace Rsf\Db;

class Postgre {

    use \Rsf\Base\Singleton;

    //dsn information
    private $_dsn = null;
    private $_dsnkey = null;
    private $_link = null;
    private $_schema = null;
    private $_prefix = '';
    //return boolen variable
    private $_true_val = 1;
    private $_false_val = 0;
    private $_null_val = 'NULL';
    private $_run_dev = true;

    public function __destruct() {
        $this->close();
    }

    public function connect($dsn, $dsnkey, $type = '') {
        static $linkpool = array();
        if ('' === $type && isset($linkpool[$dsnkey])) {
            //如果已经尝试连接过
            if ($dsn['database'] === $linkpool[$dsnkey]) {
                return;
            }
        }
        $linkpool[$dsnkey] = $dsn['database'];

        if (is_null($this->_dsn)) {
            $this->_dsn = $dsn;
            $this->_dsnkey = $dsnkey;
            $this->_schema = $dsn['database'];
            $this->_prefix = $dsn['prefix'];
            $this->_run_dev = $dsn['rundev'];
        }
        $this->_link = pg_connect('host=' . $dsn['host'] . ' port=' . $dsn['port'] . ' user=' . $dsn['login'] . ' password=' . $dsn['password'] . ' dbname=' . $dsn['database']);
        if (is_resource($this->_link)) {
            pg_set_client_encoding($this->_link, $dsn['charset']);
        } else {
            if ('RETRY' != $type) {
                $this->connect($dsn, $dsnkey, 'RETRY');
            } else {
                unset($linkpool[$dsnkey]);
                $this->_link = null;
                $this->_halt($this->error(), 'CONNECT ERROR');
            }
        }
    }

    public function close() {
        $this->_link && pg_close($this->_link);
        $this->_link = null;
    }

    public function query($sql) {
        if (is_null($this->_link)) {
            return $this->_false_val;
        }
        $query = pg_query($this->_link, $sql);
        if ($query) {
            return $query;
        }
        if ($this->_run_dev) {
            $this->_halt($this->error(), 'QUERY ERROR');
        }
        return $this->_false_val;
    }

    public function qstr($value) {
        static $exist_escape_string = false;
        if (!$exist_escape_string && function_exists('pg_escape_string')) {
            $exist_escape_string = true;
        }
        if (is_numeric($value)) {
            return $value;
        } elseif (is_bool($value)) {
            return $value ? $this->_true_val : $this->_false_val;
        } elseif (is_null($value)) {
            return $this->_null_val;
        }
        if ($exist_escape_string && $this->_link) {
            $return = pg_escape_string($this->_link, $value);
        } else {
            $return = $value;
        }
        return "'" . $return . "'";
    }

    public function qfield($fieldName) {
        $_fieldName = trim($fieldName);
        $ret = ('*' == $_fieldName) ? '*' : "`{$_fieldName}`";
        return $ret;
    }

    public function qtable($tableName, $alias = '') {
        if (strpos($tableName, '.')) {
            $parts = explode('.', $tableName);
            $tableName = trim($parts[1]);
            $schema = trim($parts[0]);
        } else {
            $tableName = $this->_prefix . trim($tableName);
            $schema = $this->_schema;
        }
        $_alias = $alias ? " AS {$alias}" : '';
        $ret = "`{$schema}`.`{$tableName}`" . $_alias;
        return $ret;
    }

    public function field_value($fields = array(), $glue = ',') {
        $sql = $comma = '';
        foreach ($fields as $field => $value) {
            $sql .= $comma . $this->qfield($field) . '=' . $this->qstr($value);
            $comma = $glue;
        }
        return $sql;
    }

    /* up action is solid */

    public function create($tableName, $data, $retid = false) {
        if (empty($data)) {
            return $this->_false_val;
        }
        $fields = $values = $comma = '';
        foreach ($data as $field => $value) {
            $fields .= $comma . $this->qfield($field);
            $values .= $comma . $this->qstr($value);
            $comma = ',';
        }
        //RETURNING id pg8.2+
        if ($retid) {
            $sql = 'INSERT INTO ' . $this->qtable($tableName) . '(' . $fields . ') VALUES (' . $values . ') RETURNING ' . $retid;
        } else {
            $sql = 'INSERT INTO ' . $this->qtable($tableName) . '(' . $fields . ') VALUES (' . $values . ')';
        }
        $ret = $this->query($sql);
        return $ret;
    }

    public function replace($tableName, $data) {
        if (empty($data)) {
            return $this->_false_val;
        }
        $fields = $values = $comma = '';
        foreach ($data as $field => $value) {
            $fields .= $comma . $this->qfield($field);
            $values .= $comma . $this->qstr($value);
            $comma = ',';
        }
        $ret = $this->query('REPLACE INTO ' . $this->qtable($tableName) . '(' . $fields . ') VALUES (' . $values . ')');
        return $ret;
    }

    public function update($tableName, $data, $condition, $retnum = false) {
        if (empty($data)) {
            return $this->_false_val;
        }
        if (empty($condition)) {
            return $this->_false_val;
        }
        if (is_array($data)) {
            $data = $this->field_value($data, ',');
        }
        if (is_array($condition)) {
            $where = $this->field_value($condition, ' AND ');
        } else {
            $where = $condition;
        }
        $ret = $this->query("UPDATE " . $this->qtable($tableName) . " SET $data WHERE $where");
        return $ret;
    }

    public function remove($tableName, $condition, $muti = false) {
        if (empty($condition)) {
            return $this->_false_val;
        }
        if (is_array($condition)) {
            $condition = $this->field_value($condition, ' AND ');
        }
        $addsql = $muti ? '' : ' LIMIT 1';
        $ret = $this->query('DELETE FROM ' . $this->qtable($tableName) . ' WHERE ' . $condition . $addsql);
        return $ret;
    }

    public function findOne($tableName, $query, $xoption = null) {
        if (!is_resource($query)) {
            if (!strpos($query, 'LIMIT') && !strpos($query, 'limit')) {
                $query .= ' LIMIT 0,1';
            }
            if ($tableName) {
                $query = sprintf($query, $this->qtable($tableName));
            }
            $query = $this->query($query);
        }
        if (!$query) {
            return $this->_false_val;
        }
        $row = pg_fetch_array($query, null, PGSQL_ASSO);
        pg_free_result($query);
        return $row;
    }

    public function findAll($tableName, $query, $xoption = null, $yield = false) {
        if (!is_resource($query)) {
            if ($tableName) {
                if ($query) {
                    $query = str_replace('%s', $this->qtable($tableName), $query);
                } else {
                    $query = 'SELECT * FROM ' . $this->qtable($tableName);
                }
            }
            $query = $this->query($query);
        }
        if (!$query) {
            return $this->_false_val;
        }
        $rowsets = pg_fetch_all($query);
        pg_free_result($query);
        return $rowsets;
    }

    public function page($tableName, $query, $offset = 0, $length = 20, $yield = false) {
        if (!is_resource($query)) {
            if ($tableName) {
                $query = str_replace('%s', $this->qtable($tableName), $query);
            }
            if ($length) {
                $query = $query . " LIMIT {$offset}, {$length}";
            } else {
                $query = $query . " LIMIT {$offset}, 4294967294";  //4294967294 索引的最大值
            }
            $query = $this->query($query);
        }
        if (!$query) {
            return $this->_false_val;
        }
        $rowsets = pg_fetch_all($query);
        /*
        $rowsets = array();
        while ($row = pg_fetch_array($query, null, PGSQL_ASSO)) {
            $rowsets[] = $row;
        }*/
        pg_free_result($query);
        return $rowsets;
    }

    public function result_first($tableName, $field, $condition) {
        if (is_array($condition)) {
            $where = 'WHERE ' . $this->field_value($condition, ' AND ');
        } else {
            $where = $condition ? "WHERE {$condition}" : '';
        }
        $query = $this->query("SELECT {$field} FROM " . $this->qtable($tableName) . " {$where} LIMIT 0,1");
        if (!$query) {
            return $this->_false_val;
        }
        $ret = pg_fetch_row($query);
        pg_free_result($query);
        if ($ret) {
            return $ret[0];
        }
        return $this->_false_val;
    }

    public function count($tableName, $condition = '', $field = '*') {
        return $this->result_first($tableName, "COUNT({$field})", $condition);
    }

    public function version() {
        return '';
    }

    public function ping() {
        if (PHP_VERSION >= '4.3') {
            return pg_ping($this->_link);
        } else {
            return false;
        }
    }

    public function error() {
        $error = $this->_link ? pg_last_error($this->_link) : pg_last_error();
        return $error;
    }

    public function errno() {
        return '';
    }

    public function start_trans() {
        $this->_link->begintransaction();
    }

    public function end_trans($commit_no_errors = true) {
        if ($commit_no_errors) {
            $this->_link->commit();
        } else {
            $this->_link->rollback();
        }
    }

    private function _halt($message = '', $data = '', $halt = 0) {
        if ($halt) {
            $this->close();
            throw new \Rsf\Exception\Exception($message, $data);
        } else {
            return false;
        }
    }

    public function optimize($tableName) {
        $this->query('OPTIMIZE TABLE ' . $this->qtable($tableName));
    }

    public function fields($tableName) {
        $query = $this->query('SHOW FULL FIELDS FROM ' . $this->qtable($tableName));
        if (!$query) {
            return $this->_false_val;
        }
        $rowsets = array();
        while ($row = pg_fetch_array($query, null, PGSQL_ASSO)) {
            $rowsets[] = $row;
        }
        pg_free_result($query);
        return $rowsets;
    }

    public function columns($tableName) {
        static $typeMap = array(
            'bit' => 'int1',
            'tinyint' => 'int1',
            'bool' => 'bool',
            'boolean' => 'bool',
            'smallint' => 'int2',
            'mediumint' => 'int3',
            'int' => 'int4',
            'integer' => 'int4',
            'bigint' => 'int8',
            'float' => 'float',
            'double' => 'double',
            'doubleprecision' => 'double',
            'float unsigned' => 'float',
            'decimal' => 'dec',
            'dec' => 'dec',
            'date' => 'date',
            'datetime' => 'datetime',
            'timestamp' => 'timestamp',
            'time' => 'time',
            'year' => 'int2',
            'char' => 'char',
            'nchar' => 'char',
            'varchar' => 'varchar',
            'nvarchar' => 'varchar',
            'binary' => 'binary',
            'varbinary' => 'varbinary',
            'tinyblob' => 'blob',
            'tinytext' => 'text',
            'blob' => 'blob',
            'text' => 'text',
            'mediumblob' => 'blob',
            'mediumtext' => 'text',
            'longblob' => 'blob',
            'longtext' => 'text',
            'enum' => 'enum',
            'set' => 'set'
        );

        $query = $this->query('SHOW FULL COLUMNS FROM ' . $tableName);
        if (!$query) {
            return $this->_false_val;
        }
        $retarr = array();
        while ($rowcur = pg_fetch_array($query, null, PGSQL_ASSO)) {
            $row = array_change_key_case($rowcur, CASE_LOWER);
            $field = array();
            $field['name'] = $row['field'];
            $type = strtolower($row['type']);

            $field['scale'] = null;
            $query_arr = false;
            if (preg_match('/^(.+)\((\d+),(\d+)/', $type, $query_arr)) {
                $field['type'] = $query_arr[1];
                $field['length'] = is_numeric($query_arr[2]) ? $query_arr[2] : -1;
                $field['scale'] = is_numeric($query_arr[3]) ? $query_arr[3] : -1;
            } elseif (preg_match('/^(.+)\((\d+)/', $type, $query_arr)) {
                $field['type'] = $query_arr[1];
                $field['length'] = is_numeric($query_arr[2]) ? $query_arr[2] : -1;
            } elseif (preg_match('/^(enum)\((.*)\)$/i', $type, $query_arr)) {
                $field['type'] = $query_arr[1];
                $arr = explode(",", $query_arr[2]);
                $field['enums'] = $arr;
                $zlen = max(array_map("strlen", $arr)) - 2; // PHP >= 4.0.6
                $field['length'] = ($zlen > 0) ? $zlen : 1;
            } else {
                $field['type'] = $type;
                $field['length'] = -1;
            }

            $field['ptype'] = $typeMap[strtolower($field['type'])];
            $field['not_null'] = ('yes' != strtolower($row['null']));
            $field['pk'] = ('pri' == strtolower($row['key']));
            $field['auto_incr'] = strexists($row['extra'], 'auto_incr');
            if ($field['auto_incr']) {
                $field['ptype'] = 'autoincr';
            }
            $field['binary'] = strexists($type, 'blob');
            $field['unsigned'] = strexists($type, 'unsigned');

            $field['has_default'] = $field['default'] = null;
            if (!$field['binary']) {
                $d = $row['default'];
                if (!is_null($d) && 'null' != strtolower($d)) {
                    $field['has_default'] = true;
                    $field['default'] = $d;
                }
            }

            if ('tinyint' == $field['type'] && 1 == $field['length']) {
                $field['ptype'] = 'bool';
            }

            $field['desc'] = !empty($row['comment']) ? $row['comment'] : '';
            if (!is_null($field['default'])) {
                switch ($field['ptype']) {
                    case 'int1':
                    case 'int2':
                    case 'int3':
                    case 'int4':
                        $field['default'] = intval($field['default']);
                        break;
                    case 'float':
                    case 'double':
                    case 'dec':
                        $field['default'] = doubleval($field['default']);
                        break;
                    case 'bool':
                        $field['default'] = (bool)$field['default'];
                }
            }

            $retarr[strtolower($field['name'])] = $field;
        }
        pg_free_result($query);
        return $retarr;
    }

    public function tables($pattern = null) {
        $sql = 'SHOW TABLES';
        if (!empty($this->_schema)) {
            $sql .= " FROM `{$this->_schema}`";
        }
        if (!empty($pattern)) {
            $sql .= ' LIKE ' . $this->qstr($this->_schema);
        }
        $query = $this->query($sql);
        if (!$query) {
            return $this->_false_val;
        }
        $tables = pg_fetch_all($query);
        pg_free_result($query);
        return $tables;
    }

}