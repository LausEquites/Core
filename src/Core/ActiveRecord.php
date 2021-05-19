<?php


namespace Core;


class ActiveRecord
{
    protected static $_table;
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
     * @param $data
     * @return static
     */
    public static function createFromArray($data)
    {
        $obj = new static();
        foreach ($data as $key => $value) {
            if (property_exists($obj, $key)) {
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
                $values[$field] = $this->$field;
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
                $values[$field] = $this->$field;
            }
            $sql .= "(" . implode(',', $updatedFields) . ") ";
            $sql .= "VALUES (" . implode(',', $placeholders) . ")";
        }

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($values);
    }
}