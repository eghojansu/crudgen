<?php

namespace crudgen;

class Column
{
    //! name
    public $name;
    //! label
    public $label;
    //! column definition
    private $def;
    //! relation to table
    private $rel;

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

    public function referencedTable()
    {
        return ($this->def[__FUNCTION__] = isset($this->rel['table'])?
            $this->rel['table']:null);
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

    public function __get($var)
    {
        return isset($this->def[$var])?$this->def[$var]:
            (method_exists($this, $var)?call_user_func(array($this,$var)):null);
    }

    public function __construct(array $def, array $rel = array())
    {
        $this->def   = $def;
        $this->rel   = $rel;
        $this->name  = C::camelhead($def['Field']);
        $this->label = C::human($def['Field']);
    }
}
