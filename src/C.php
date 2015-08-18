<?php

namespace crudgen;

use ReflectionClass;
use Exception;

//! common helper & registry
class C
{
    const
        E_Method = 'No method %s on C class';

    static $registry = array();
    static $progress = array();
    static $config   = array();

    static function get($var = null) {
        return isset(self::$registry[$var])?self::$registry[$var]:
            (isset($var)?null:self::$registry);
    }

    static function set($var, $val) {
        self::$registry[$var] = $val;
    }

    static function concat($var, $val) {
        isset(self::$registry[$var]) || self::$registry[$var] = '';
        self::$registry[$var] .= $val;
    }

    static function exists($var) {
        return isset(self::$registry[$var]);
    }

    static function config($var = null, $val = null)
    {
        if (is_array($var)) {
            self::$config = array_merge_recursive(self::$config,
                self::tokenizeConfig($var));

            return;
        }
        elseif (!isset($var))
            return self::$config;
        elseif (isset($val) || !isset(self::$config[$var]))
            self::$config[$var] = $val;

        return self::$config[$var];
    }

    static function tokenizeConfig($var) {
        $newVar = array();
        $tokena = '/@(\w+)/';
        $tokenr = '{#\1#}';
        foreach ($var as $key => $val) {
            $key = preg_replace($tokena, $tokenr, $key);
            $val = is_array($val)?self::tokenizeConfig($val):
                preg_replace($tokena, $tokenr, $val);
            if (strpos($key, 'file_')===0) {
                $key = substr($key, 5);
                isset($newVar['files']) || $newVar['files'] = array();
                $newVar['files'][$key] = $val;
            } else
                $newVar[$key] = $val;
        }

        return $newVar;
    }

    //! progress start
    static function start($str, $tab = 0, $line = 0) {
        array_push(self::$progress, array('str'=>$str, 'line'=>$line,
            'tab'=>$tab, 'time'=>microtime(true)));
        echo self::tab($tab).$str.self::eol($line);
    }

    //! progress end
    static function finish($str = 'done') {
        $line = '';
        if ($args = array_pop(self::$progress))
            $line = self::tab($args['tab']).$str.
                (($args['line'] && $str === 'done')?' '.lcfirst(substr($args['str'], 0,
                    strpos($args['str'], '...')?:strlen($args['str']))):'');
        echo $line?substr($line, 0, 45).' ['.self::howlong($args['time']).
            ' s]'.self::eol($args['line']?:1):'';
    }

    static function readByte($byte, $precision = 7) {
        $unit  = array('Byte','KiB','MiB','GiB','TiB','PiB','EiB','ZiB','YiB');

        if (is_numeric($byte)) {
            $w = floor((strlen($byte) - 1) / 3);
            return sprintf("%.{$precision}f %s", $byte/pow(1024, $w), $unit[$w]);
        }

        $str   = array_values(array_filter(explode(' ', $byte)));
        return array_shift($str)*pow(1024,
            (int) array_search(array_shift($str), $unit)).' '.$unit[0];
    }

    static function howlong($start) {
        return number_format(microtime(true)-$start, 2);
    }

    static function msg($str, $line = 1, $tab = 0) {
        echo self::tab($tab).$str.self::eol($line);
    }

    static function newline($n = 1) {
        echo self::eol($n);
    }

    static function shift($n = 1, $space = 2) {
        echo self::tab($n, $space);
    }

    static function hypen($nb = 1, $na = 1, $n = 80) {
        echo self::eol($nb).
             str_repeat('-', $n).
             self::eol($na);
    }

    static function shiftAll($str, $tab = 0) {
        $tab = self::tab($tab);
        $eol = self::eol();
        echo $tab.str_replace($eol, $eol.$tab, rtrim($str)).$eol;
    }

    static function header($h, $nb = 0, $na = 2, $empty_line = false, $n = 80) {
        $lines = explode(self::eol(), $h);
        $ksg   = !$empty_line?'':'|'.str_repeat(' ', $n-2).'|'.self::eol();
        $pagar = '+'.str_repeat('-', $n-2).'+'.self::eol();

        echo self::eol($nb).$pagar.$ksg;
        foreach ($lines as $line) {
            $line  = trim($line);
            $len   = strlen($line);
            $space = $n-$len-2;
            $left  = floor(($space)/2);
            $right = $space-$left;

            echo '|'.str_repeat(' ', $left).$line.str_repeat(' ', $right).'|'.self::eol();
        }
        echo $ksg.$pagar.self::eol($na-1);
    }

    static function footprint() {
        self::header('Time usage: '.self::howlong(TIME_BEGIN).' sec'.
            ' Memory usage: '.self::readByte(memory_get_usage()), 1, 1);
    }

    static function error($message) {
        echo $message.self::eol(2).'exiting...'.self::eol();
        self::footprint();
        exit(500);
    }

    static function eol($n = 1) {
        return str_repeat("\n", $n);
    }

    static function tab($n = 1, $space = 2) {
        return str_repeat(' ', $n*$space);
    }

    static function camelcase($str) {
        return lcfirst(str_replace(' ', '', ucwords(strtr($str, '_', ' '))));
    }

    static function snakecase($str) {
        return strtolower(preg_replace('/[[:upper:]]/','_\0',$str));
    }

    static function camelhead($str) {
        return ucfirst(self::camelcase($str));
    }

    static function human($str) {
        return ucwords(str_replace('_', ' ', $str));
    }

    static function fixslashes($str) {
        return $str?rtrim(strtr($str, '\\', '/'), '/').'/':'';
    }

    static function ext($file) {
        return ltrim(strrchr($file, '.'), '.');
    }

    static function name($file) {
        return substr($file, 0, strrpos($file, '.'));
    }

    static function call($cm, array $args) {
        if (is_callable($cm))
            return call_user_func_array($cm, $args);

        if (strpos($cm, '->')!==false) {
            $x = explode('->', $cm);
            $class  = array_shift($x);
            $method  = array_shift($x);

            $ref = new ReflectionClass($class);
            $ref = method_exists($class, '__construct')?
                    $ref->newinstanceargs($args):$ref->newinstance();
            if ($ref->hasMethod($method))
                return call_user_func_array($ref, $args);
            else
                throw new Exception('Invalid method '.$cm , 1);
        }

        return null;
    }

    static function perm($s) {
        $s = (string) intval($s);

        switch ($s) {
            case '757': $o = 0757; break;
            case '777': $o = 0777; break;
            default: $o = 0700; break;
        }

        return $o;
    }
}
