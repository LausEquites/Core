<?php


namespace Core;


use Carbon\Carbon;

class ActiveRecord
{
    protected static $_table;
    protected static $_typeMap;
    protected $_modified;
    protected $id;

    /**
     * @param $id
     * @return static|false
     * @throws \Exception
     */
    protected static function getById($id)
    {
        $sql = "SELECT * FROM " . static::$_table . " WHERE id = :id";
        $pdo = DB::get();
        $stmt = $pdo->prepare($sql);

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        $obj = false;
        if ($row) {
            $obj = self::createFromArray($row);
        }

        return $obj;
    }

    /**
     * @param $ids
     * @return static[]
     * @throws \Exception
     */
    protected static function getByIdMulti($ids)
    {
        $idCnt = count($ids);
        $inData = str_repeat('?,',$idCnt);
        $inData = mb_substr($inData,0, -1);

        $sql = "SELECT * FROM " . static::$_table . " WHERE id IN($inData)";
        $stmt = DB::get()->prepare($sql);
        $stmt->execute($ids);

        $objs = [];
        while ($data = $stmt->fetch()) {
            $objs[] = static::createFromArray($data);
        }

        return $objs;
    }

    /**
     * @param $sql
     * @param array $params
     * @return static[]
     * @throws \Exception
     */
    protected static function getBySql($sql, $params = [])
    {
        $objs = [];

        $stmt = DB::get()->prepare($sql);
        $stmt->execute($params);

        while ($data = $stmt->fetch()) {
            $objs[] = self::createFromArray($data);
        }

        return $objs;
    }

    /**
     * @param string $sql
     * @param array $params
     * @return static|false
     * @throws \Exception
     */
    protected static function getBySqlSingle($sql, $params = [])
    {
        $objs = static::getBySql($sql, $params);

        if (!$objs) {
            return false;
        }

        return reset ($objs);
    }

    /**
     * @param $data
     * @return static
     */
    public static function createFromArray($data)
    {
        $obj = new static();
        foreach ($data as $key => $value) {
            if (property_exists($obj, $key)) {
                if (!empty(static::$_typeMap[$key])) {
                    switch (static::$_typeMap[$key]) {
                        case 'dt':
                            if ($value !== null) {
                                $value = Carbon::createFromFormat("Y-m-d H:i:s", $value, 'UTC');
                            }
                            break;
                    }
                }

                $obj->$key = $value;
            }
        }

        return $obj;
    }

    /** Set a field
     * Use this to mark a field as changed
     *
     * @param $field
     * @param $value
     */
    protected function set($field, $value)
    {
        $this->$field = $value;
        $this->_modified[$field] = true;
    }

    /** Save
     * Updates record if id is set else a new row will be inserted
     *
     * @return bool
     * @throws \Exception
     */
    protected function save()
    {
        $pdo = DB::get();
        $updatedFields = array_keys($this->_modified);
        if ($this->id) {
            $sql = "UPDATE " . static::$_table . " SET ";
            $fields = [];
            $values = [];
            foreach ($updatedFields as $field) {
                $fields[] = "$field=:$field";
                $values[$field] = $this->getConvertedValue($field);
            }
            $sql .= implode(',', $fields);
            $sql .= " WHERE id = :id";
            $values['id'] = $this->id;
        } else {
            $sql = "INSERT INTO " . static::$_table;
            $placeholders = [];
            $values = [];
            foreach ($updatedFields as $field) {
                $placeholders[] = ":$field";
                $values[$field] = $this->getConvertedValue($field);
            }
            $sql .= "(" . implode(',', $updatedFields) . ") ";
            $sql .= "VALUES (" . implode(',', $placeholders) . ")";
        }

        $stmt = $pdo->prepare($sql);
        $suc = $stmt->execute($values);

        if (!$this->id) {
            $this->id = $pdo->lastInsertId();
        }

        return $suc;
    }

    private function getConvertedValue($field)
    {
        $value = $this->$field;
        $converted = $value;
        if (!empty(static::$_typeMap[$field])) {
            switch (static::$_typeMap[$field]) {
                case 'dt':
                    if ($value !== null) {
                        $converted = $value->format('Y-m-d H:i:s');
                    }
                    break;
            }
        }

        return $converted;
    }
}