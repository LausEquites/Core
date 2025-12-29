<?php

namespace Phdantic;

class Tester
{
    public static function isInt($int)
    {
        return is_int($int);
    }

    public static function isFloat($float)
    {
        return is_float($float) || is_int($float);
    }

    public static function isFloatStrict($float)
    {
        return is_float($float);
    }

    public static function isString($string)
    {
        return is_string($string);
    }

    public static function isBool($bool)
    {
        return is_bool($bool);
    }

    public static function isBoolLoose($bool)
    {
        if (is_bool($bool)) {
            return true;
        } elseif (
            is_string($bool)
            && (
                $bool === 'true'
                || $bool === 'false'
                || $bool === '1'
                || $bool === '0'
            )
        ) {
            return true;
        } elseif (is_int($bool) && ($bool === 0 || $bool === 1)) {
            return true;
        }

        return false;
    }

    public static function isArray($array)
    {
        return is_array($array);
    }

    public static function isObject($object)
    {
        return is_object($object);
    }
}
