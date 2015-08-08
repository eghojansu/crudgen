<?php

namespace crudgen;

use PDO;
use PDOException;

class Database
{
    //! table list
    public $table    = array();
    //! table relation
    public $relation = array();
    //! referenced table
    public $refTable = array();

    public function __construct(array $opt)
    {
        C::start('Trying construct PDO...', 0, 1);
        try {
            C::start('Constructing PDO...', 1);
            $dsn = 'mysql:dbname='.$opt['name'].';host='.$opt['host'];
            $pdo = new PDO($dsn, $opt['username'], $opt['password']);
            C::finish();

            C::start('Reading table relation...', 1);
            $query = $pdo->query(<<<SQL
SELECT
`TABLE_NAME`,
`COLUMN_NAME`,
`REFERENCED_TABLE_NAME`,
`REFERENCED_COLUMN_NAME`
FROM `information_schema`.`KEY_COLUMN_USAGE`
WHERE `CONSTRAINT_SCHEMA` = '{$opt['name']}' AND
`REFERENCED_TABLE_SCHEMA` IS NOT NULL AND
`REFERENCED_TABLE_NAME` IS NOT NULL AND
`REFERENCED_COLUMN_NAME` IS NOT NULL
SQL
                );
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                isset($this->relation[$row['TABLE_NAME']]) ||
                    $this->relation[$row['TABLE_NAME']] = array();
                $this->relation[$row['TABLE_NAME']][$row['COLUMN_NAME']] = array(
                    'table'=>$row['REFERENCED_TABLE_NAME'],
                    'field'=>$row['REFERENCED_COLUMN_NAME'],
                    );
                in_array($row['REFERENCED_TABLE_NAME'], $this->refTable) ||
                    array_push($this->refTable, $row['REFERENCED_TABLE_NAME']);
            }
            C::finish();

            C::start('Reading table list...', 1, 1);
            $query = $pdo->query('show tables');
            while ($table = $query->fetchColumn()) {
                C::start('Constructing table: '.$table.'...', 2);
                $this->table[$table] = new Table($table, $pdo
                        ->query('show columns from '.$table)
                        ->fetchAll(PDO::FETCH_ASSOC),
                    isset($this->relation[$table])?$this->relation[$table]:array(),
                    in_array($table, $this->refTable));
                C::finish();
            }
            C::finish();

            $pdo = null;
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            C::error('Connection failed: '.ltrim(substr($msg, strrpos($msg, ']')+1)));
        }

        count($this->table) || C::error('No tables in database '.$opt['name']);

        C::finish();
    }
}
