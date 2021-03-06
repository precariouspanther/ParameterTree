<?php
/**
 *
 * @author Adam Benson <adam@precariouspanther.net>
 * @copyright Arcanum Logic
 */

namespace Arcanum\ParameterTree;

use Arcanum\ParameterTree\Exception\MissingValueException;
use Arcanum\ParameterTree\Exception\ValueExistsException;


class ParameterTree implements \ArrayAccess, \JsonSerializable
{
    /**
     * @var ParameterTree[]|mixed[]
     */
    protected $values = [];

    protected $path = null;
    /**
     * The namespace to use to separate tree branches (defaults to ".")
     * @var string
     */
    protected $namespaceSeparator = ".";

    /**
     * @param array|\Traversable $data
     * @param string $namespaceSeparator The namespace to use to separate tree branches (defaults to ".")
     * @param string $path Optional path to this branch relative to the tree root.
     */
    public function __construct($data = [], string $namespaceSeparator = ".", string $path = null)
    {
        $this->namespaceSeparator = $namespaceSeparator;
        $this->path = $path;
        $this->fromArray($data);
    }

    /**
     * Set the local values in this tree from an array/Traversable object
     * @param array|\Traversable $data
     * @throws \Exception
     */
    public function fromArray($data)
    {
        foreach ($data as $arrayKey => $arrayValue) {
            $this->set($arrayKey, $arrayValue);
        }
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
            if ($remainderKey !== null) {
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
     * @param string $key
     * @return ParameterTree|mixed
     */
    public function getBranch($key)
    {
        list($localKey, $remainderKey) = $this->getKeyParts($key);
        if (isset($this->values[$localKey])) {
            $localValue = $this->values[$localKey];
            if ($remainderKey !== null) {
                //We have a subkey, traverse down the ParameterTree to find the requested branch
                if (!$localValue instanceof ParameterTree) {
                    //Requested subbranch doesn't exist
                    throw new MissingValueException("Invalid key - '$key' does not exist under " . $this->getPath() . ".");
                }

                return $localValue->getBranch($remainderKey);
            } else {
                //We are accessing the value on this branch.
                if (!($localValue instanceof ParameterTree)) {
                    throw new MissingValueException("Invalid key - '$key' is a value, not a branch - under " . $this->getPath() . ".");
                }

                return $localValue;
            }
        }
        if (array_key_exists($localKey, $this->values) && $this->values[$localKey] === null) {
            return null;
        }
        throw new MissingValueException("Invalid key - '$key' does not exist under " . $this->getPath() . ".");
    }

    /**
     * Delete a value(or entire branch) from the ParameterTree
     * @param string $key The key to remove relative to the current branch
     */
    public function delete($key)
    {
        list($localKey, $remainderKey) = $this->getKeyParts($key);
        if ($remainderKey !== null) {
            //The given key points to an element on a subbranch - delegate the delete call to that subbranch.
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
     * @throws ValueExistsException
     */
    public function set($key, $value, $force = false)
    {
        list($localKey, $remainderKey) = $this->getKeyParts($key);

        if ($remainderKey !== null) {
            //The given key points to an element on a subbranch.
            if (!isset($this->values[$localKey]) || !($this->values[$localKey] instanceof ParameterTree)) {
                //As we're pointing to a subbranch, make sure the ParameterTree object exists JIT.
                $this->values[$localKey] = new ParameterTree([], $this->namespaceSeparator,
                    $this->getValuePath($localKey));
            }
            //Delegate the set call to the subbranch object.
            $this->values[$localKey]->set($remainderKey, $value, $force);

            return;
        }
        if (is_array($value) || $value instanceof \Traversable) {
            $this->values[$localKey] = new ParameterTree($value, $this->namespaceSeparator,
                $this->getValuePath($localKey));

            return;
        }
        if (!$force && isset($this->values[$localKey])) {
            throw new ValueExistsException(
                "Tried to override an existing value (" . $this->getPath() . $this->namespaceSeparator . "$localKey) without specifying force=true."
            );
        }
        $this->values[$localKey] = $value;
    }

    /**
     * Slice a namespaced key into the localKey (that is, the key part relevant to the current branch), and the
     * remainderKey (if exists, which may be relevant to branches further down the hierarchy.
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
     * @return string The key path for the found value relative to the current branch.
     */
    public function find($value)
    {
        $key = $this->findLocalKey($value);
        if ($key) {
            return $this->getPath() . $this->namespaceSeparator . $key;
        }

        foreach ($this->values as $branches) {
            if ($branches instanceof ParameterTree) {
                //If a sub-branch returns a 'truthy' response we've found the value and should return it.
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
     * Search for the provided value as one of the immediate leaves of this branch (doesn't traverse sub-branches)
     * @param mixed $value
     * @return string The key of the value if it exists, false otherwise
     */
    protected function findLocalKey($value)
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
        foreach ($this->values as $localKey => $val) {
            if ($val instanceof ParameterTree) {
                $keys = array_merge($keys, $val->getKeys());
            } else {
                $keys[] = $this->getValuePath($localKey);
            }
        }

        return $keys;
    }

    /**
     * Get the absolute local path key for an item with the given localKey on this branch.
     * @param string $localKey
     * @return string
     */
    protected function getValuePath($localKey)
    {
        $paths = [];
        if ($this->path !== null) {
            $paths[] = $this->path;
        }
        if ($localKey !== null) {
            $paths[] = $localKey;
        }

        return implode($this->namespaceSeparator, $paths);
    }

    /**
     * Fetch the full namespaced path to the current branch
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Fetch the number of values stored on or below this branch
     * @return int
     */
    public function count()
    {
        $total = count($this->values);
        foreach ($this->values as $value) {
            if ($value instanceof ParameterTree) {
                $total += $value->count();
            }
        }

        return $total;
    }

    /*
     * Typecasted accessors
     */

    /**
     * Fetch a value as an integer
     * @param string $key
     * @param int $default
     * @return int
     */
    public function getInt($key, $default = 0)
    {
        return (int)$this->get($key, $default);
    }

    /**
     * Fetch a value as a boolean
     * @param string $key
     * @param bool $default
     * @return bool
     */
    public function getBoolean($key, $default = false)
    {
        return (bool)($this->get($key, $default));
    }

    /**
     * Fetch a value as a string
     * @param string $key
     * @param string $default
     * @return string
     * @throws \Exception
     */
    public function getString($key, $default = '')
    {
        $value = $this->get($key, $default);
        if (!is_scalar($value)) {
            throw new \Exception(
                "Requested $key as a string from ParameterTree but the stored value for this key is a " . gettype(
                    $value
                )
            );
        }

        return strval($value);
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

    /**
     * (PHP 5 &gt;= 5.4.0)<br/>
     * Specify data which should be serialized to JSON
     *
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
