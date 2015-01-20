<?php
/**
 *
 * @author Adam Benson <adam@precariouspanther.net>
 * @copyright Arcanum Logic
 */

namespace Arcanum\ParameterTree;

use Exception;

class Branch
{
    /**
     * @var Branch[]|mixed[]
     */
    protected $values = [];

    protected $parentBranch = null;

    protected $fragmentPath = "";

    public function __construct(Branch $parentBranch = null)
    {
        $this->parentBranch = $parentBranch;
    }

    public function fromArray(array $parameterArray)
    {
        foreach ($parameterArray as $arrayKey => $arrayValue) {
            $this->set($arrayKey, $arrayValue);
        }
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $values = [];
        foreach ($this->values as $valKey => $value) {
            if ($value instanceof Branch) {
                $values[$valKey] = $value->toArray();
            } else {
                $values[$valKey] = $value;
            }
        }
        return $values;
    }

    /**
     * @param $key
     * @throws Exception
     * @return mixed
     */
    public function get($key)
    {
        if (strstr($key, ".")) {
            $keyParts = explode(".", $key);
            $fragmentKey = array_shift($keyParts);
            $subKey = implode(".", $keyParts);
            if (isset($this->values[$fragmentKey])) {
                return $this->values[$fragmentKey]->get($subKey);
            }
        }
        if (isset($this->values[$key])) {
            if ($this->values[$key] instanceof Branch) {
                return $this->values[$key]->toArray();
            }
            return $this->values[$key];
        }
        throw new Exception("Couldn't find config for $key under '" . $this->fragmentPath . "'");
    }

    /**
     * @param $key
     * @param $value
     * @throws Exception
     */
    public function set($key, $value)
    {
        if (strstr($key, ".")) {
            $keyParts = explode(".", $key);
            $fragmentKey = array_shift($keyParts);
            $subKey = implode(".", $keyParts);
            if (!isset($this->values[$fragmentKey]) || !($this->values[$fragmentKey]) instanceof Branch) {
                $this->values[$fragmentKey] = new Branch($this->fragmentPath . "." . $fragmentKey);
            }
            $this->values[$fragmentKey]->set($subKey, $value);
            return;
        }
        if (is_array($value)) {
            foreach ($value as $valKey => $valValue) {
                $this->set($key . "." . $valKey, $valValue);
            }
            return;
        }
        if (isset($this->values[$key]) && $this->values[$key] instanceof Branch) {
            throw new Exception("Tried to override a multidimensional config - " . $this->fragmentPath . ".$key");
        }
        $this->values[$key] = $value;
    }
}