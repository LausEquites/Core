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
            case 'int': return Tester::isInt($value);
            case 'float': return Tester::isFloat($value);
            case 'string': return Tester::isString($value);
            case 'bool': return Tester::isBool($value);
            case 'array': return Tester::isArray($value);
            case 'object': return Tester::isObject($value);
            default: throw new \Exception("Invalid type: $type");
        }
    }

    public static function getLastErrors()
    {
        return self::$lastErrors;
    }
}
