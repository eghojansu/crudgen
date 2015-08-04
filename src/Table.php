<?php

namespace crudgen;

final class Table
{
    public $name;
    public $model;
    public $label;
    public $primary_key;
    public $primary_keys = array();
    public $isReferenced;
    public $hasAutoIncrement;
    public $relation = array();
    private $meta = array(
        'schema'=>'',
        'pk'=>'',
        'rel'=>'',
        'column_select'=>'',
        'column_header'=>'',
        'fields_form'=>'',
        'option_list'=>'',
        'namespace_list'=>'',
        'date_field'=>'',
        );

    public function schema()
    {
        $self = __FUNCTION__;
        return <<<FUNC
    public function $self()
    {
        return array(
            {$this->schema}
            );
    }
FUNC;
    }

    public function primaryKey()
    {
        $self = __FUNCTION__;
        return !$this->pk?'':<<<FUNC
    public function $self()
    {
        return array({$this->pk});
    }
FUNC;
    }

    public function relation()
    {
        $self = __FUNCTION__;
        return !$this->rel?'':<<<FUNC
    public function $self()
    {
        return array({$this->rel});
    }
FUNC;
    }

    public function optionList()
    {
        $self = __FUNCTION__;
        return !$this->isReferenced?'':<<<FUNC
    public function $self(\$limit = 0)
    {
        \$list = array();
        foreach (\$this->all(\$limit) as \$value)
            \$list[array_shift(\$value)] = array_shift(\$value);

        return \$list;
    }
FUNC;
    }

    public function data()
    {
        $self = __FUNCTION__;
        return <<<FUNC
    public function $self(\$url)
    {
        return array('data'=>\$this
            ->select(<<<SEL
concat('<a href="{\$url}/input?id=', {table}.{#primary_key#}, '" class="text-green" title="Edit"><i class="fa fa-edit"></i></a>',
'<a href="{\$url}/delete?id=', {table}.{#primary_key#}, '" class="text-red" title="Delete" data-bootbox="confirm"><i class="fa fa-remove"></i></a>') as actions,
{$this->column_select}
SEL
)->fetchMode('num')->all());
    }
FUNC;
    }

    public function __set($var, $val)
    {
        !isset($this->meta[$var]) || $this->meta[$var] = $val;
    }

    public function __get($var)
    {
        return isset($this->meta[$var])?$this->meta[$var]:null;
    }

    public function __construct($name, array $columns, array $relation, $isReferenced)
    {
        $eol = H::eol();
        $t   = array(
                2=>H::tab(2, 4),
                H::tab(3, 4),
                H::tab(4, 4),
            );

        foreach ($columns as $key => $column) {
            $column = new Column($column,
                isset($relation[$column['Field']])?
                    $relation[$column['Field']]:array());

            $this->column_header .= $t[3].'<th>{{ @fields.'.$column->Field.' }}</th>'.$eol;
            $this->column_select .= $column->Field.','.$eol;
            $this->fields_form   .= $column->form.$eol;

            $this->schema .= str_replace(
                array('field', 'label', 'EOL', 'T4', 'T3'),
                array($column->Field, $column->name, $eol, $t[4], $t[3]),
                "'field'=>array(EOLT4'label',EOLT4".
                    $column->filter.",".(is_null($column->defaultContent)?
                        '':'EOLT4'.$column->defaultContent)."EOLT4),EOLT3");

            if ($column->isReferenced) {
                $this->namespace_list .= 'use {#model_namespace#}\\'.H::camelhead($column->referencedTable).';'.$eol;
                $this->option_list .= $eol.$t[2].'$moe->set(\''.H::camelcase($column->referencedTable).'\', '.
                    H::camelhead($column->referencedTable).'::instance()->optionList());';
            }

            !$column->isDate || $this->date_field .= $t[3].'$'.$column->Field.
                ' = $moe->get(\'POST.'.$column->Field.'\');'.$eol.
                $t[3].'krsort($'.$column->Field.');'.$eol.
                $t[3].'$moe->set(\'POST.'.$column->Field.'\', implode(\'-\', $'.$column->Field.'));'.$eol;

            if ($column->isPrimaryKey()) {
                $this->pk .= "'{$column->Field}', ";
                array_push($this->primary_keys, $column->Field);
            }

            $key>0 || $this->hasAutoIncrement = $column->isAutoIncrement;
        }
        $this->column_header = trim($this->column_header);
        $this->column_select = trim($this->column_select,','.$eol);
        $this->fields_form   = ltrim($this->fields_form);
        $this->date_field    = ltrim($this->date_field);
        $this->schema        = rtrim($this->schema);
        $this->pk            = trim($this->pk, ' ,');
        $this->primary_key   = reset($this->primary_keys);

        foreach ($relation as $fil1=>$tab)
            $this->rel .= str_replace(
                array('{tab}', '{fil1}', '{fil2}', '{EOL}', '{S3}'),
                array($tab['table'], $fil1, $tab['field'], $eol, $t[3]),
                "'{tab}'=>'join {join} on {join}.{fil2} = {table}.{fil1}',{EOL}{S3}");
        $this->rel = trim($this->rel);

        $this->model        = H::camelhead($name);
        $this->label        = H::human($name);
        $this->name         = $name;
        $this->relation     = $relation;
        $this->isReferenced = $isReferenced;
    }
}
