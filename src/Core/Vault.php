<?php


namespace Core;

/**
 * Core\Vault
 *
 * Minimal in-memory key-value store scoped to the lifetime of a single PHP request.
 *
 * Purpose:
 * - Provide a convenient place for Core components and your application code to stash
 *   ephemeral per-request data without using globals.
 * - Exposed as a simple singleton accessed via Vault::getInstance().
 *
 * Notes:
 * - This is NOT a cross-request cache or session store. All data is lost at the end of the request.
 * - Not thread/process safe across requests (by design). Use external caches if you need persistence.
 */
class Vault
{
    /** @var Vault|null */
    static private $instance;

    /**
     * @var array<string,mixed> Internal storage for values
     */
    private $data = [];

    /**
     * Get the per-request singleton instance.
     *
     * @return Vault
     */
    static public function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Retrieve a value by name.
     *
     * @param string $name Key name
     * @return mixed|null Stored value or null if not set
     */
    public function get($name)
    {
        return $this->data[$name]?? null;
    }

    /**
     * Store a value by name.
     *
     * @param string $name Key name
     * @param mixed $value Any value
     * @return void
     */
    public function set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * Remove a single key from the vault.
     *
     * @param string $name Key name to remove
     * @return void
     */
    public function unset($name)
    {
        unset($this->data[$name]);
    }
    
    /**
     * Clear all stored values for this request.
     *
     * @return void
     */
    public function clear()
    {
        $this->data = [];
    }
}
