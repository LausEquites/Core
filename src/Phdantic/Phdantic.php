<?php

namespace Phdantic;

class Phdantic {
    static private $lastErrors = [];

    /**
     * Filters an object based on defined required and optional in $rules.
     *
     * @param object $object The input object to be filtered.
     * @param array $rules An array containing filtering rules with 'required' and 'optional' keys.
     * @return object Returns the filtered object containing only the specified keys.
     */
    public static function filterObject($object, $rules)
    {
        $required = $rules['required']?? [];
        $optional = $rules['optional']?? [];
        $keys = array_merge(array_keys($required), array_keys($optional));

        $filtered = new \stdClass();
        foreach ($keys as $key) {
            if (isset($object->$key)) {
                $filtered->$key = $object->$key;
            }
        }

        return $filtered;
    }

    public static function validateObject($object, $rules)
    {
        $required = $rules['required']?? [];
        $optional = $rules['optional']?? [];
        $errors = [];
        $properties = get_object_vars($object);

        foreach ($required as $key => $type) {
            if (!array_key_exists($key,$properties)) {
                $errors['missing'][] = $key;
                continue;
            }

            if (!self::validateFromType($properties[$key], $type)) {
                $errors['invalid'][] = $key;
                continue;
            }
        }

        foreach ($optional as $key => $type) {
            if (!array_key_exists($key,$properties)) {
                continue;
            }
            if (!self::validateFromType($properties[$key], $type)) {
                $errors['invalid'][] = $key;
            }
        }

        if ($errors) {
            self::$lastErrors = $errors;
            return false;
        }

        return true;
    }

    private static function validateFromType($value, $type)
    {
        switch ($type) {
            case 'int': return self::validateInt($value);
            case 'string': return self::validateString($value);
            case 'bool': return self::validateBool($value);
            default: throw new \Exception("Invalid type: $type");
        }
    }

    public static function validateInt($int)
    {
        return is_int($int);
    }

    public static function validateString($string)
    {
        return is_string($string);
    }

    public static function validateBool($bool)
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

    public static function getLastErrors()
    {
        return self::$lastErrors;
    }
}
