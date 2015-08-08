<?php

namespace crudgen;

class Table
{
    //! table name
    public $name;
    //! model name, eg: AuthorPost
    public $model;
    //! label name, eg: Author Post
    public $label;
    //! primary key
    public $primary_key = array();
    //! is this table referenced by other
    public $isRef;
    //! is this table has auto increment field
    public $hasAutoIncrement;
    //! table column
    public $column = array();
    //! table relation
    public $relation = array();
    //! hold table meta data
    private $meta = array();

    public function __set($var, $val)
    {
        !isset($this->meta[$var]) || $this->meta[$var] = $val;
    }

    public function __get($var)
    {
        return isset($this->meta[$var])?$this->meta[$var]:null;
    }

    public function __construct($name, array $columns, array $relation, $isRef)
    {
        $this->model    = C::camelhead($name);
        $this->label    = C::human($name);
        $this->name     = $name;
        $this->relation = $relation;
        $this->isRef    = $isRef;

        foreach ($columns as $key => $column) {
            $column = new Column($column,
                isset($relation[$column['Field']])?
                    $relation[$column['Field']]:array());
            !$column->isPrimaryKey || array_push($this->primary_key, $column->Field);
            $this->column[$column->Field] = $column;
        }
    }
}
