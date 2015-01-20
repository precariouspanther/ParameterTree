<?php
/**
 *
 * @author Adam Benson <adam@precariouspanther.net>
 * @copyright Arcanum Logic
 */

namespace Arcanum\ParameterTree;

use Exception;

class ParameterTree implements \ArrayAccess
{
    /**
     * @var ParameterTree[]|mixed[]
     */
    protected $values = [];
    /**
     * @var ParameterTree
     */
    protected $parentBranch = null;
    /**
     * The namespace to use to separate tree branches (defaults to ".")
     * @var string
     */
    protected $namespaceSeparator = ".";

    /**
     * @param string $namespace The namespace to use to separate tree branches (defaults to ".")
     * @param ParameterTree $parentBranch
     */
    public function __construct($namespace = ".", ParameterTree $parentBranch = null)
    {
        $this->namespaceSeparator = $namespace;
        $this->parentBranch = $parentBranch;
    }

    /**
     * Create a new ParameterTree from a multi-dimensional array
     * @param array $parameterArray
     * @param string $namespace
     * @return ParameterTree
     * @throws Exception
     */
    public static function CreateFromArray(array $parameterArray, $namespace = ".")
    {
        $tree = new ParameterTree($namespace);
        foreach ($parameterArray as $arrayKey => $arrayValue) {
            $tree->set($arrayKey, $arrayValue);
        }
        return $tree;
    }

    /**
     * Convert a parameter tree into a basic PHP array
     * @return array
     */
    public function toArray()
    {
        $values = [];
        foreach ($this->values as $valKey => $value) {
            if ($value instanceof ParameterTree) {
                $values[$valKey] = $value->toArray();
            } else {
                $values[$valKey] = $value;
            }
        }
        return $values;
    }

    /**
     * @param string $key Key to fetch from the ParameterTree relative to the current branch
     * @param mixed $default Default value to return if the value doesn't exist.
     * @return mixed
     */
    public function get($key, $default = null)
    {
        list($localKey, $remainderKey) = $this->getKeyParts($key);
        if (isset($this->values[$localKey])) {
            $localValue = $this->values[$localKey];
            if ($remainderKey) {
                //We have a subkey, traverse down the ParameterTree branch to find the value
                if (!$localValue instanceof ParameterTree) {
                    //Requested subbranch doesn't exist
                    return $default;
                }
                return $localValue->get($remainderKey, $default);
            } else {
                //We are accessing the value on this branch. Convert the entire subbranch to an array if it is a ParameterTree
                return ($localValue instanceof ParameterTree) ? $localValue->toArray() : $localValue;
            }
        }
        return $default;
    }

    /**
     * Delete a value(or entire branch) from the ParameterTree
     * @param string $key The key to remove relative to the current branch
     */
    public function delete($key)
    {
        list($localKey, $remainderKey) = $this->getKeyParts($key);
        if ($remainderKey) {
            if (isset($this->values[$localKey]) && $this->values[$localKey] instanceof ParameterTree) {
                $this->values[$localKey]->delete($remainderKey);
            }
            return;
        }
        if (isset($this->values[$localKey])) {
            unset($this->values[$localKey]);
        }
    }

    /**
     * Set a value on the ParameterTree.
     * @param string $key Key to store the value in, relative to the current branch
     * @param mixed|mixed[] $value Value to store. Will create a subbranch if passed an array
     * @param bool $force Overwrite the existing value even if it is a branch itself (usually not intended)
     * @throws Exception
     */
    public function set($key, $value, $force = false)
    {
        list($localKey, $remainderKey) = $this->getKeyParts($key);

        if ($remainderKey) {
            //Namespaced key
            if (!isset($this->values[$localKey]) || !($this->values[$localKey] instanceof ParameterTree)) {
                $this->values[$localKey] = new ParameterTree($this->namespaceSeparator, $this);
            }
            $this->values[$localKey]->set($remainderKey, $value, $force);
            return;
        }
        if (is_array($value)) {
            foreach ($value as $valKey => $valValue) {
                $this->set($localKey . $this->namespaceSeparator . $valKey, $valValue, $force);
            }
            return;
        }
        if (!$force && isset($this->values[$localKey]) && $this->values[$localKey] instanceof ParameterTree) {
            throw new Exception(
                "Tried to replace a ParameterTree branch (" . $this->getPath() . $this->namespaceSeparator . "$localKey) with a scalar value without specifying force=true."
            );
        }
        $this->values[$localKey] = $value;
    }

    /**
     * Slice a namespaced key taking the first fragment to be used as the key on the current branch, and the remainder
     * to be passed down to subbranches
     * @param string $key
     * @return array
     */
    protected function getKeyParts($key)
    {
        $localKey = $key;
        $remainderKey = null;
        if (strstr($key, $this->namespaceSeparator)) {
            list($localKey, $remainderKey) = explode(".", $key, 2);
        }
        return [$localKey, $remainderKey];
    }

    /**
     * Find a value somewhere relative to the current branch and return its full path
     * @param mixed $value
     * @return string
     */
    public function find($value)
    {
        $key = $this->getKey($value);
        if ($key) {
            return $this->getPath() . $this->namespaceSeparator . $key;
        }

        foreach ($this->values as $branches) {
            if ($branches instanceof ParameterTree) {
                if (true == $key = $branches->find($value)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * Check if we have a value stored for the requested key
     * @param string $key
     * @return bool
     */
    public function hasKey($key)
    {
        list($localKey, $remainderKey) = $this->getKeyParts($key);
        if (!$remainderKey) {
            //Check on local branch
            return (array_key_exists($localKey, $this->values));
        } elseif (isset($this->values[$localKey]) && $this->values[$localKey] instanceof ParameterTree) {
            //Check subbranch
            return $this->values[$localKey]->hasKey($remainderKey);
        }
        return false;
    }

    /**
     * Fetch the key for a value stored on the current branch
     * @param mixed $value
     * @return string The key of the value if it exists, false otherwise
     */
    public function getKey($value)
    {
        return array_search($value, $this->values, true);
    }

    /**
     * Fetch an array of all keys stored beneath this branch
     * @return array
     */
    public function getKeys()
    {
        $keys = [];
        foreach ($this->values as $valueKey => $val) {
            if ($val instanceof ParameterTree) {
                $keys = array_merge($keys, $val->getKeys());
            } else {
                $keys[] = $this->trimKey($this->getPath() . $this->namespaceSeparator . $valueKey);
            }
        }
        return $keys;
    }

    /**
     * Remove leading namespace character from returned key paths
     * @param $key
     * @return string
     */
    protected function trimKey($key)
    {
        return ltrim($key, $this->namespaceSeparator);
    }

    /**
     * Fetch the full namespaced path to the current branch
     * @return string
     */
    public function getPath()
    {
        if (!$this->parentBranch) {
            return "";
        }
        return $this->trimKey(
            $this->parentBranch->getPath() . $this->namespaceSeparator . $this->parentBranch->getKey($this)
        );
    }

    /*
     * ArrayAccess methods
     */

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset
     * @return boolean true on success or false on failure.
     */
    public function offsetExists($offset)
    {
        return $this->hasKey($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return $this->get($offset, null);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->set($offset, $value);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->delete($offset);
    }


}