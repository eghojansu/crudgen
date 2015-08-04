<?php

namespace crudgen;

class H
{
    static function println($str, $line = 1, $tab = 0) {
        echo self::tab($tab).$str.self::eol($line);
    }

    static function line($n = 1) {
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

    static function shiftAll($content, $tab = 0) {
        $tab = self::tab($tab);
        $eol = self::eol();
        echo $tab.str_replace($eol, $eol.$tab, rtrim($content)).$eol;
    }

    static function header($h, $nb = 0, $na = 2, $n = 80) {
        $lines = explode(self::eol(), $h);
        $ksg   = count($lines)>1?'':'|'.str_repeat(' ', $n-2).'|'.self::eol();
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
        echo $ksg.$pagar.self::eol($na);
    }

    static function error($message) {
        echo $message.self::eol(2).'exiting...'.self::eol();
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
}
