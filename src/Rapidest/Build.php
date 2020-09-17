<?php

namespace Rapidest;

class Build
{
    const COLUMNS = 'columns';
    const DEFAULT = 'Default';
    const EXISTING = 'existing';
    const EXTRA = 'Extra';
    const FIELD = 'Field';
    const KEY = 'Key';
    const NULL = 'Null';
    const REGEX_FK = '/_id$/i';
    const TABLE = 'table';
    const TYPE = 'Type';
    
    public function __construct(App $app)
    {
        $dir = dirname(dirname(__DIR__)) . '/db';
        $options = [Scanner::PATTERN => '/\.schema$/i'];
        $schema = (new Scanner)->scan($dir, $options);

        foreach ($schema as $k0 => $v0) {
            unset($schema[$k0]);
            $basename = basename($v0);
            $arr = explode('.', $basename);
            $table = $arr[0];
            $rows = $app->db->query("show tables like '{$table}'")->fetch_all();

            if (empty($rows)) {
                $app->query("create table {$table}(id bigint unsigned not null auto_increment primary key)engine=innodb");
            }

            $schema[$table] = [
                self::COLUMNS  => $this->columns($v0),
                self::EXISTING => $this->existingColumns($app, $table),
                self::TABLE    => $table,
            ];
        }

        usort($schema, [$this, 'sortTables']);

        foreach ($schema as $k0 => $v0) {
            $table = $v0[self::TABLE];
            foreach ($v0[self::COLUMNS] as $k1 => $v1) {
                if (!isset($v0[self::EXISTING][$k1])) {
                    $type = $v1[self::TYPE];
                    $notNull = ($v1[self::NULL] == 'No') ? 'not null' : '';
                    $app->query("alter table `{$table}` add column {$k1} {$type} {$notNull}");
                }
                # @todo alter columns - type, default, etc.
                if (preg_match($pattern = self::REGEX_FK, $k1)) {
                    $referencedTable = preg_replace($pattern, '', $k1);
                    # @todo search for column first
                    try {
                        $app->query("alter table {$table} add foreign key fk_{$table}_{$k1} ({$k1}) references {$referencedTable} (id)");
                    } catch (\Error $e) {
                    }
                }
                # @todo account for other foreign keys
                # @todo add unique keys
            }
            foreach ($v0[self::EXISTING] as $k1 => $v1) {
                if ($k1 != 'id' && !isset($v0[self::COLUMNS][$k1])) {
                    $app->query("alter table {$table} drop column {$k1}");
                }
            }
        }

        # @todo drop obsolete tables
    }

    public function dataType(array $arr)
    {
        # @todo error-proof
        if (!isset($arr[1])) {
            if (preg_match(self::REGEX_FK, $arr[0])) {
                return 'bigint unsigned not null';
            } else {
                die('Error in schema: ' . implode(' ', $arr) . '"' . "\n");
            }
        }
        return $arr[1] . (preg_match('/signed/i', $arr[2]) ? ' ' . $arr[2] : '');
    }

    public function columns(string $filename)
    {
        $columns = [];
        $lines = explode("\n", file_get_contents($filename));

        # @todo indexes and unique keys

        foreach ($lines as $k => $v) {
            $arr = explode(' ', trim($v));
            $columns[$arr[0]] = [
                self::FIELD   => $arr[0],
                self::TYPE    => $this->dataType($arr),
                self::NULL    => preg_match('/not  *null/i', $v) ? 'No' : 'Yes',
                # @todo
                #self::KEY     => 
                self::DEFAULT => $this->defaultValue($arr),
                self::EXTRA   => ($arr[0] == 'id') ? 'auto_increment' : '',
            ];
        }

        return $columns;
    }

    public function defaultValue(array $arr)
    {
        foreach ($arr as $k => $v) {
            if (trim($v) == 'default' && isset($arr[$k + 1])) {
                return $arr[$k + 1];
            }
        }
        return null;
    }

    public function existingColumns(App $app, string $table)
    {
        $existingColumns = [];
        $rows = $app->db->query('show columns from ' . $table)
            ->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as $k => $v) {
            $existingColumns[$v[self::FIELD]] = $v;
        }
        return $existingColumns;
    }

    public function sortTables($a, $b)
    {
        foreach ($a[self::COLUMNS] as $k => $v) {
            if ($k == $b[self::TABLE] . '_id') {
                echo $k . ' ' . $b[self::TABLE] . "\n";
                return 1;
            }
        }
        return -1;
    }
}