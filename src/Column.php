<?php

namespace crudgen;

use stdClass;

final class Column
{
    //! column definition
    private $def;
    //! relation to table
    private $rel;
    //! name
    public $name;
    //! label
    public $label;

    public function filter()
    {
        $filter = "'trim'";
        $this->isNullable || $filter .= ",'required'";
        $length = 0;
        if (preg_match('/\((?<len>.*)\)/', $this->Type, $match))
            $length = is_numeric($match['len'])? $match['len']:
                explode(',', str_replace(array('"', "'"), '', $match['len']));
        if (is_array($length))
            $filter .= ",'in_array'=>array('".implode("','", $length)."')";
        elseif ($length)
            if ($length == 1)
                $filter .= ",'in_array'=>array(0,1)";
            else
                $filter .= ",'max_length'=>$length";
        !($this->isUnique || ($this->isPrimaryKey && !$this->isAutoIncrement)) ||
            $filter .= ",'unique".$this->name."'";
        !$this->isReferenced || $filter .= ",'exists'=>'{#model_namespace#}".
                H::camelhead($this->referencedTable)."->exists'";

        return ($this->def[__FUNCTION__] = "array($filter)");
    }

    public function defaultContent()
    {
        $type = $this->Type;
        if (preg_match('/^(?<col>[a-z_]+)/i', $type, $match))
            $type = $match['col'];

        switch ($type) {
            case 'date':
                $result = 'date(\'Y-m-d\')';
            case 'year':
                $result = 'date(\'Y\')';
            case 'time':
                $result = 'date(\'H:i:s\')';
            case 'timestamp':
            case 'datetime':
                $result = 'date(\'Y-m-d H:i:s\')';
            default:
                $result = $this->Default?:($this->isNumber?0:null);
        }

        return ($this->def[__FUNCTION__] = $result);
    }

    public function isNumber()
    {
        return ($this->def[__FUNCTION__] = preg_match('/^(bit|tinyint|smallint|'.
            'mediumint|int|integer|bigint|real|double|float|decimal|numeric)/i',
        $this->Type));
    }

    public function isDate()
    {
        return ($this->def[__FUNCTION__] = preg_match('/^(date|time|timestamp|'.
            'datetime)/i', $this->Type));
    }

    public function isYear()
    {
        return ($this->def[__FUNCTION__] = strtolower($this->Type) === 'year');
    }

    public function isLongtext()
    {
        return ($this->def[__FUNCTION__] = preg_match('/^(tinyblob|blob|'.
            'mediumblob|longblob|tinytext|text|mediumtext|longtext)/i',
        $this->Type));
    }

    public function isAutoIncrement()
    {
        return ($this->def[__FUNCTION__] = strpos($this->Extra,
            'auto_increment')!==false);
    }

    public function isPrimaryKey()
    {
        return ($this->def[__FUNCTION__] = $this->Key === 'PRI');
    }

    public function isUnique()
    {
        return ($this->def[__FUNCTION__] = $this->Key === 'UNI');
    }

    public function isNullable()
    {
        return ($this->def[__FUNCTION__] = $this->Null === 'YES');
    }

    public function isReferenced()
    {
        return ($this->def[__FUNCTION__] = isset($this->rel['table']));
    }

    public function referencedTable()
    {
        return ($this->def[__FUNCTION__] = isset($this->rel['table'])?
            $this->rel['table']:null);
    }

    public function form()
    {
        $isRadio    = false;
        $opt        = array();
        if (preg_match('/\((?<num>\d+)\)/', $this->Type, $match)) {
            if ($match['num']==1) {
                $isRadio = true;
                $opt = array(1=>'Yes',0=>'No');
            }
        }
        elseif (preg_match('/\((?<opt>.*)\)/', $this->Type, $match)) {
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
                    ($this->isNumber?$key:$value),
                    $value,
                    ),
                    'S3<label class="radio-inline">EOL'.
                    'S4<input type="radio" name="FIELD" value="VALUE"'.
                    '{{ @POST.FIELD==VALUE?\' checked\':\'\' }}REQUIRED> LABELEOL'.
                    'S3</label>EOL');
        elseif ($this->isDate)
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
        elseif ($this->isYear)
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
        elseif ($this->isLongtext)
            $form = 'S3<textarea name="FIELD" id="FIELD" class="form-control"REQUIRED>'.
                '{{ @POST.FIELD }}</textarea>EOL';
        elseif ($this->isReferenced) {
            $rel  = H::camelcase($this->referencedTable);
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

        return ($this->def[__FUNCTION__] = str_replace(array(
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
            H::tab(1, 2),
            H::tab(2, 2),
            H::tab(3, 2),
            H::tab(4, 2),
            H::tab(5, 2),
            H::eol(),
            (($isRadio || $this->isDate)?'':' for="'.$this->Field.'"'),
            $this->Field,
            ($this->isNullable?'':' required'),
            ),
            'S1<div class="form-group">EOL'.
            'S2<labelFOR class="col-md-3">{{ @fields.FIELD }}</label>EOL'.
            'S2<div class="col-md-9">EOL'.
            $form.
            'S2</div>EOL'.
            'S1</div>'));
    }

    public function __get($var)
    {
        return isset($this->def[$var])?$this->def[$var]:
            (method_exists($this, $var)? call_user_func(array($this, $var)):null);
    }

    public function __construct(array $def, array $rel = array())
    {
        $this->def   = $def;
        $this->rel   = $rel;
        $this->name  = H::camelhead($def['Field']);
        $this->label = H::human($def['Field']);
    }
}
