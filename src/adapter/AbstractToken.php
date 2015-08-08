<?php

namespace crudgen\adapter;

use ReflectionClass;
use ReflectionMethod;
use crudgen\C;
use crudgen\Table;

abstract class AbstractToken
{
    protected $table;
    protected $default = array();
    protected $token   = array();

    //! perform all token method
    private function run()
    {
        $ref = new ReflectionClass(get_called_class());
        foreach ($ref->getMethods(ReflectionMethod::IS_PRIVATE) as $refMet)
            if (strpos($refMet->name, 'token')===0 &&
                !isset($this->default[$this->methodToToken($refMet->name)]))
                $this->{$refMet->name}();
    }

    public function replaceToken($content)
    {
        return str_replace($this->keys(), $this->values(), $content);
    }

    public function keys()
    {
        $keys = array();
        foreach (array_keys($this->default+$this->token) as $key)
            $keys[] = '{#'.$key.'#}';

        return $keys;
    }

    public function values()
    {
        return array_values($this->default+$this->token);
    }

    public function assignTokenMethod($method, $val)
    {
        $token = $this->methodToToken($method);
        $this->$token = $val;
    }

    public function methodToToken($method)
    {
        return C::snakecase(substr($method, 5));
    }

    public function __set($var, $val)
    {
        if (isset($this->default[$var]))
            $this->default[$var] = $val;
        else
            $this->token[$var]   = $val;
    }

    public function __get($var)
    {
        return isset($this->default[$var])?$this->default[$var]:
            (isset($this->token[$var])?$this->token[$var]:null);
    }

    public function __construct(Table $table)
    {
        $this->table = $table;
        foreach ($this->default as $key => $value)
            if (method_exists($this, $method = 'token'.C::camelhead($key)))
                $this->$method();
        foreach (C::config('fixed') as $key => $value)
            $this->default[$key] = $value;
        foreach (C::config('path') as $key => $value) {
            $path = 'path_'.$key;
            $this->$path = C::fixslashes($value);
        }
        $this->run();
    }
}
