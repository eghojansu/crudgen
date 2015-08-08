<?php

namespace moegen;

use crudgen\adapter\AbstractToken;
use crudgen\C;
use crudgen\Column;
use crudgen\Table;

class Moegen extends AbstractToken
{
    public function createModel($template)
    {
        $content = ltrim($this->schema());
        if ($t = $this->primaryKey())
            $content .= C::eol(2).$t;
        if ($t = $this->relation())
            $content .= C::eol(2).$t;
        if ($t = $this->optionList())
            $content .= C::eol(2).$t;
        if ($t = $this->data())
            $content .= C::eol(2).$t;

        return str_replace('{#content#}', $content, $template);
    }

    public function createRoute($content)
    {
        return 'GET @home: / = {#controller_namespace#}\Home->index'.C::eol(2).$content;
    }

    private function schema()
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

    private function primaryKey()
    {
        $self = __FUNCTION__;
        return !$this->pk?'':<<<FUNC
    public function $self()
    {
        return array({$this->pk});
    }
FUNC;
    }

    private function relation()
    {
        $self = __FUNCTION__;
        return !$this->rel?'':<<<FUNC
    public function $self()
    {
        return array({$this->rel});
    }
FUNC;
    }

    private function optionList()
    {
        $self = __FUNCTION__;
        return !$this->table->isRef?'':<<<FUNC
    public function $self(\$limit = 0)
    {
        \$list = array();
        foreach (\$this->all(\$limit) as \$value)
            \$list[array_shift(\$value)] = array_shift(\$value);

        return \$list;
    }
FUNC;
    }

    private function data()
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

    private function form(Column $column)
    {
        $isRadio    = false;
        $opt        = array();
        if (preg_match('/\((?<num>\d+)\)/', $column->Type, $match)) {
            if ($match['num']==1) {
                $isRadio = true;
                $opt = array(1=>'Yes',0=>'No');
            }
        }
        elseif (preg_match('/\((?<opt>.*)\)/', $column->Type, $match)) {
            $opt = explode(',', str_replace(array('"', "'"), '', $match['opt']));
            $isRadio = count($opt)<4;
        }

        $form  = '';
        if ($isRadio)
            foreach ($opt as $key => $value)
                $form .= str_replace(array(
                    'VALUE',
                    'LABEL',
                    ), array(
                    ($column->isNumber?$key:$value),
                    $value,
                    ),
                    'S3<label class="radio-inline">EOL'.
                    'S4<input type="radio" name="FIELD" value="VALUE"'.
                    '{{ @POST.FIELD==VALUE?\' checked\':\'\' }}REQUIRED> LABELEOL'.
                    'S3</label>EOL');
        elseif ($column->isDate)
            $form = 'S3{~ @x = explode(\'-\', @POST.FIELD) ~}EOL'.
                'S3<select style="width: 70px; display: inline" name="FIELD[d]" class="form-control"REQUIRED>EOL'.
                'S4<option value=""> ---</option>EOL'.
                'S4{{ moe\\Helper::optionRange(1, 31, @x[2]) }}EOL'.
                'S3</select>EOL'.
                'S3<select style="width: 100px; display: inline" name="FIELD[m]" class="form-control"REQUIRED>EOL'.
                'S4<option value=""> ---</option>EOL'.
                'S4{{ moe\\Helper::optionMonth(@x[1]) }}EOL'.
                'S3</select>EOL'.
                'S3<select style="width: 80px; display: inline" name="FIELD[y]" class="form-control"REQUIRED>EOL'.
                'S4<option value=""> ---</option>EOL'.
                'S4{{ moe\\Helper::optionRange(date(\'Y\')-5, date(\'Y\')+5, @x[0]) }}EOL'.
                'S3</select>EOL';
        elseif ($column->isYear)
            $form = 'S3<select style="width: 80px" name="FIELD" id="FIELD" class="form-control"REQUIRED>EOL'.
                'S4<option value=""> ---</option>EOL'.
                'S4{{ moe\\Instance::optionRange(date(\'Y\')-5, date(\'Y\')+5, @POST.FIELD) }}EOL'.
                'S3</select>EOL';
        elseif (count($opt)) {
            $form = 'S4<option value=""> ---</option>EOL';
            foreach ($opt as $key => $value)
                $form .= str_replace(array(
                    'VALUE',
                    'LABEL',
                    ), array(
                    $value,
                    ucwords(str_replace('_', ' ', $value)),
                    ),
                    'S4<option value="VALUE"{{ @POST.FIELD==VALUE?'.
                    '\' selected\':\'\' }}> LABEL</option>EOL');
            $form = 'S3<select name="FIELD" id="FIELD" class="form-control"REQUIRED>EOL'.
                $form.
                'S3</select>EOL';
        }
        elseif ($column->isLongtext)
            $form = 'S3<textarea name="FIELD" id="FIELD" class="form-control"REQUIRED>'.
                '{{ @POST.FIELD }}</textarea>EOL';
        elseif ($column->isReferenced) {
            $rel  = C::camelcase($column->referencedTable);
            $form = 'S3<select name="FIELD" id="FIELD" class="form-control"REQUIRED>EOL'.
                'S4<option value=""> ---</option>EOL'.
                'S4<repeat group="{{ @'.$rel.' }}" key="{{ @code }}" value="{{ @label }}">EOL'.
                'S5<option value="{{ @code }}"{{ @code==@POST.FIELD?\' selected\':\'\' }}>'.
                '{{ @label }}</option>EOL'.
                'S4</repeat>EOL'.
                'S3</select>EOL';
        }
        else
            $form = 'S3<input type="text" name="FIELD" id="FIELD" class="form-control"'.
                ' value="{{ @POST.FIELD }}"REQUIRED>EOL';

        return str_replace(array(
            'S1',
            'S2',
            'S3',
            'S4',
            'S5',
            'EOL',
            'FOR',
            'FIELD',
            'REQUIRED',
            ), array(
            C::tab(1, 2),
            C::tab(2, 2),
            C::tab(3, 2),
            C::tab(4, 2),
            C::tab(5, 2),
            C::eol(),
            (($isRadio || $column->isDate)?'':' for="'.$column->Field.'"'),
            $column->Field,
            ($column->isNullable?'':' required'),
            ),
            'S1<div class="form-group">EOL'.
            'S2<labelFOR class="col-md-3">{{ @fields.FIELD }}</label>EOL'.
            'S2<div class="col-md-9">EOL'.
            $form.
            'S2</div>EOL'.
            'S1</div>');
    }

    private function filter(Column $column)
    {
        $filter = "'trim'";
        $column->isNullable || $filter .= ",'required'";
        $length = 0;
        if (preg_match('/\((?<len>.*)\)/', $column->Type, $match))
            $length = is_numeric($match['len'])? $match['len']:
                explode(',', str_replace(array('"', "'"), '', $match['len']));
        if (is_array($length))
            $filter .= ",'in_array'=>array('".implode("','", $length)."')";
        elseif ($length)
            if ($length == 1)
                $filter .= ",'in_array'=>array(0,1)";
            else
                $filter .= ",'max_length'=>$length";
        !($column->isUnique || ($column->isPrimaryKey && !$column->isAutoIncrement)) ||
            $filter .= ",'unique".$column->name."'";
        !$column->isReferenced || $filter .= ",'exists'=>'{#model_namespace_quoted#}\\\\".
                C::camelhead($column->referencedTable)."->exists'";

        return "array($filter)";
    }

    public function __construct(Table $table)
    {
        parent::__construct($table);
        $eol = C::eol();
        $t   = array(
                2=>C::tab(2, 4),
                3=>C::tab(3, 4),
                4=>C::tab(4, 4),
                22=>C::tab(2, 2),
                32=>C::tab(3, 2),
                42=>C::tab(4, 2),
            );

        $column_header  =
        $column_select  =
        $pk             =
        $schema         =
        $rel            =
        $fields_form    =
        $namespace_list =
        $option_list    =
        $date_field     = '';
        foreach ($table->column as $key => $column) {
            $column_header .= $t[32].'<th>{{ @fields.'.$column->Field.' }}</th>'.$eol;
            $fields_form   .= $this->form($column).$eol;
            $column_select .= $column->Field.','.$eol;
            $schema        .= str_replace(
                array('field', 'label', 'EOL', 'T4', 'T3'),
                array($column->Field, $column->label, $eol, $t[4], $t[3]),
                "'field'=>array(EOLT4'label',EOLT4".
                    $this->filter($column).",".(is_null($column->defaultContent)?
                        '':'EOLT4'.$column->defaultContent)."EOLT4),EOLT3");

            if ($column->isReferenced) {
                $namespace_list .= 'use {#model_namespace#}\\'.C::camelhead($column->referencedTable).';'.$eol;
                $option_list .= $eol.$t[2].'$moe->set(\''.C::camelcase($column->referencedTable).'\', '.
                    C::camelhead($column->referencedTable).'::instance()->optionList());';
            }

            !$column->isDate || $date_field .= $t[3].'$'.$column->Field.
                ' = $moe->get(\'POST.'.$column->Field.'\');'.$eol.
                $t[3].'krsort($'.$column->Field.');'.$eol.
                $t[3].'$moe->set(\'POST.'.$column->Field.'\', implode(\'-\', $'.$column->Field.'));'.$eol;

            !$column->isPrimaryKey() || $pk .= "'{$column->Field}', ";
        }

        $this->controller     = 'Crud'.$table->model;
        $this->model          = $table->model;
        $this->table_name     = $table->name;
        $this->label          = $table->label;
        $this->column_header  = trim($column_header);
        $this->namespace_list = ltrim($namespace_list);
        $this->option_list    = rtrim($option_list);
        $this->fields_form    = ltrim($fields_form);
        $this->date_field     = ltrim($date_field);
        $this->column_select  = trim($column_select,','.$eol);
        $this->schema         = rtrim($schema);
        $this->pk             = trim($pk, ' ,');
        $this->primary_key    = reset($table->primary_key);

        foreach ($table->relation as $fil1=>$tab)
            $this->rel .= str_replace(
                array('{tab}', '{fil1}', '{fil2}', '{EOL}', '{S3}'),
                array($tab['table'], $fil1, $tab['field'], $eol, $t[3]),
                "'{tab}'=>'join {join} on {join}.{fil2} = {table}.{fil1}',{EOL}{S3}");
        $this->rel = trim($this->rel);

        foreach (C::config('namespaces')?:array() as $key => $value) {
            $key2 = $key.'_quoted';
            $this->$key = $this->replaceToken($value);
            $this->$key2 = addslashes($this->$key);
        }

        $cff = array();
        foreach (C::config('files')?:array() as $key => $value)
            C::ext($value['name'])!=='ini' || array_push($cff, C::name($this->replaceToken($value['name'])));
        ksort($cff);
        $this->config_files = "'".implode("',".C::eol().C::tab(1,4)."'", $cff)."'";

        $this->table = $table;
    }
}
