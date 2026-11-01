<?php

namespace Core;

use Core\Exceptions\External;

/**
 * Core\ActiveRecord
 *
 * Lightweight Active Record base providing simple CRUD helpers and automatic mapping
 * between database rows and PHP objects. Subclasses should define:
 * - protected static $_table: string — database table name.
 * - protected static $_typeMap: array<string,string> — optional field=>type hints used for IO conversion.
 *
 * Supported types in $_typeMap when reading (createFromArray) and writing (save/bindParams):
 * - dt | datetime    => Carbon/DateTime instance in UTC (stored as 'Y-m-d H:i:s').
 * - json             => stdClass decoded from JSON (and encoded on write).
 * - json-object      => same as json (object form).
 * - json-assoc       => associative array decoded from JSON.
 * - bit              => integer 0/1 when writing; useful for tinyint/bit columns.
 * - default/null     => no conversion.
 *
 * Change tracking:
 * - Call $this->set($field, $value) to assign and mark a field as modified.
 * - save() performs INSERT for new records (no id) or UPDATE for existing ones (has id),
 *   only including modified fields.
 */
#[\AllowDynamicProperties]
class ActiveRecord
{
    /** @var string Database table name */
    protected static $_table;

    /** @var array<string, string> Field type mappings */
    protected static $_typeMap = [];

    /** @var array<string, bool> Map of modified field names */
    protected $_modified = [];

    /** @var mixed Primary key ID */
    protected $id;

    /**
     * Obtain the PDO database connection.
     *
     * @return \PDO
     */
    protected static function getPdo()
    {
        return DB::get();
    }

    /**
     * Fetch a single record by primary key id.
     *
     * Uses the subclass-defined table (static::$_table) and maps the row to an
     * instance of the subclass using createFromArray().
     *
     * @param mixed $id Primary key value.
     * @return static|false The hydrated object or false if not found.
     * @throws \Exception On DB errors or mapping errors.
     */
    protected static function getById($id)
    {
        if ($id === null || $id === '') {
            return false;
        }

        $sql = "SELECT * FROM `" . static::$_table . "` WHERE id = :id";
        $stmt = static::getPdo()->prepare($sql);

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if ($row) {
            return static::createFromArray($row);
        }

        return false;
    }

    /**
     * Fetch multiple records by primary key ids using a single IN() query.
     *
     * @param array $ids List of primary key values. Empty array returns [].
     * @return static[] An array of hydrated objects (possibly empty).
     * @throws \Exception On DB errors or mapping errors.
     */
    protected static function getByIdMulti($ids)
    {
        if (empty($ids)) {
            return [];
        }

        $normalizedIds = array_values($ids);
        $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));

        $sql = "SELECT * FROM `" . static::$_table . "` WHERE id IN ($placeholders)";
        $stmt = static::getPdo()->prepare($sql);
        $stmt->execute($normalizedIds);

        $objs = [];
        while ($data = $stmt->fetch()) {
            $objs[] = static::createFromArray($data);
        }

        return $objs;
    }

    /**
     * Execute an arbitrary SELECT and map each row to an instance via createFromArray().
     *
     * Use for custom queries. Parameters are prepared and executed safely.
     *
     * @param string $sql SQL SELECT statement with placeholders.
     * @param array $params Bound parameters for the prepared statement.
     * @return static[] A list of hydrated objects (possibly empty).
     * @throws \Exception On DB errors or mapping errors.
     */
    protected static function getBySql($sql, $params = [])
    {
        $objs = [];

        $stmt = static::getPdo()->prepare($sql);
        $stmt->execute($params);

        while ($data = $stmt->fetch()) {
            $objs[] = static::createFromArray($data);
        }

        return $objs;
    }

    /**
     * Execute a SELECT and return a single mapped object (first row) or false if none.
     *
     * Convenience wrapper around getBySql() when at most one row is expected.
     *
     * @param string $sql SQL SELECT statement with placeholders.
     * @param array $params Bound parameters for the prepared statement.
     * @return static|false Hydrated object or false if no rows.
     * @throws \Exception On DB errors or mapping errors.
     */
    protected static function getBySqlSingle($sql, $params = [])
    {
        $objs = static::getBySql($sql, $params);

        if (empty($objs)) {
            return false;
        }

        return reset($objs);
    }

    /**
     * Hydrate an instance of the subclass from an associative array/row.
     *
     * Only properties that exist on the subclass are assigned. If a field name
     * exists in static::$_typeMap, conversions are applied:
     * - 'dt'/'datetime' => Carbon/DateTime instance in UTC
     * - 'json'/'json-object' => json_decode to object (stdClass)
     * - 'json-assoc' => json_decode to associative array
     *
     * @param array|object $data Row data as array or object implementing ArrayAccess.
     * @return static Hydrated object.
     */
    public static function createFromArray($data)
    {
        $obj = new static();
        foreach ($data as $key => $value) {
            if (property_exists($obj, $key)) {
                $type = static::$_typeMap[$key] ?? null;
                if ($value !== null) {
                    $value = match ($type) {
                        'dt', 'datetime' => self::hydrateDateTime($value),
                        'json', 'json-object' => json_decode($value, false, 512, JSON_THROW_ON_ERROR),
                        'json-assoc' => json_decode($value, true, 512, JSON_THROW_ON_ERROR),
                        default => $value
                    };
                }

                $obj->$key = $value;
            }
        }

        return $obj;
    }

    /**
     * Hydrate a datetime value into a Carbon or DateTime instance in UTC.
     *
     * @param mixed $value
     * @return \DateTimeInterface
     */
    private static function hydrateDateTime($value)
    {
        if ($value instanceof \DateTimeInterface) {
            return $value;
        }

        if (class_exists('\Carbon\Carbon')) {
            return \Carbon\Carbon::parse($value, 'UTC');
        }

        return new \DateTime($value, new \DateTimeZone('UTC'));
    }

    /**
     * Set a field value and mark it as modified for persistence.
     *
     * Use this method from your domain logic to ensure save() knows which columns
     * to include in INSERT/UPDATE statements.
     *
     * @param string $field Column/property name.
     * @param mixed $value New value (will be converted on save according to $_typeMap).
     * @return void
     */
    protected function set($field, $value)
    {
        $this->$field = $value;
        $this->_modified[$field] = true;
    }

    /**
     * Persist changes to the database.
     *
     * Behavior:
     * - If no fields were modified, returns true without executing SQL.
     * - If $this->id is set, performs an UPDATE on static::$_table with only modified fields.
     * - If $this->id is not set, performs an INSERT with only modified fields and updates $this->id
     *   with the lastInsertId() from the PDO connection.
     *
     * Type handling is delegated to bindParams() which honors the $_typeMap.
     *
     * @return bool True on success from PDOStatement::execute().
     * @throws \Exception On DB errors or unknown type mapping.
     */
    protected function save()
    {
        if (empty($this->_modified)) {
            return true;
        }

        $pdo = static::getPdo();
        $updatedFields = array_keys($this->_modified);

        if ($this->id) {
            $updatedFields = array_values(array_diff($updatedFields, ['id']));
            if (empty($updatedFields)) {
                $this->_modified = [];
                return true;
            }

            $fields = array_map(fn($f) => "`$f`=:$f", $updatedFields);
            $sql = "UPDATE `" . static::$_table . "` SET " . implode(',', $fields) . " WHERE id = :id";

            $values = [];
            foreach ($updatedFields as $field) {
                $values[$field] = $this->$field;
            }
            $values['id'] = $this->id;
        } else {
            $columns = array_map(fn($f) => "`$f`", $updatedFields);
            $placeholders = array_map(fn($f) => ":$f", $updatedFields);
            $sql = "INSERT INTO `" . static::$_table . "` (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";

            $values = [];
            foreach ($updatedFields as $field) {
                $values[$field] = $this->$field;
            }
        }

        $stmt = $pdo->prepare($sql);
        $this->bindParams($stmt, $values);
        $suc = $stmt->execute();

        if ($suc) {
            if (!$this->id) {
                $this->id = $pdo->lastInsertId();
            }
            $this->_modified = [];
        }

        return $suc;
    }

    /**
     * Bind values to a prepared PDO statement using $_typeMap conversion rules.
     *
     * Rules:
     * - null type: bind as-is (PDO will infer type).
     * - 'dt', 'datetime': expects Carbon/DateTime or null; converts to UTC 'Y-m-d H:i:s'.
     * - 'bit': binds as integer (PDO::PARAM_INT), useful for tinyint/bit.
     * - 'json', 'json-assoc', 'json-object': json_encode before binding. Throws External on failure.
     * - booleans are normalized to ints 0/1 before any further handling.
     *
     * @param \PDOStatement $stmt Prepared statement.
     * @param array $values name=>value pairs to bind (names should not include colons).
     * @return void
     * @throws External If JSON encoding fails.
     * @throws \Exception If an unknown field type is encountered.
     */
    private function bindParams($stmt, $values)
    {
        foreach ($values as $field => $value) {
            if (is_bool($value)) {
                $value = (int) $value;
            }

            $type = static::$_typeMap[$field] ?? null;
            switch ($type) {
                case null:
                    $stmt->bindValue(":$field", $value);
                    break;
                case 'dt':
                case 'datetime':
                    if ($value instanceof \DateTimeInterface) {
                        $dt = clone $value;
                        if (method_exists($dt, 'setTimezone')) {
                            $dt = $dt->setTimezone(new \DateTimeZone('UTC'));
                        }
                        $stmt->bindValue(":$field", $dt->format('Y-m-d H:i:s'));
                    } elseif ($value !== null) {
                        $stmt->bindValue(":$field", (string) $value);
                    } else {
                        $stmt->bindValue(":$field", null, \PDO::PARAM_NULL);
                    }
                    break;
                case 'bit':
                    if ($value === null) {
                        $stmt->bindValue(":$field", null, \PDO::PARAM_NULL);
                    } else {
                        $stmt->bindValue(":$field", (int) $value, \PDO::PARAM_INT);
                    }
                    break;
                case 'json':
                case 'json-assoc':
                case 'json-object':
                    if ($value === null) {
                        $stmt->bindValue(":$field", null, \PDO::PARAM_NULL);
                    } else {
                        try {
                            $encoded = json_encode($value, JSON_THROW_ON_ERROR);
                            $stmt->bindValue(":$field", $encoded);
                        } catch (\JsonException) {
                            throw new External("Error converting $field to json", 500);
                        }
                    }
                    break;
                default:
                    throw new \Exception("Unknown field type " . static::$_typeMap[$field] . " for $field");
            }
        }
    }
}
