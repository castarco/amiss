<?php
namespace Amiss;

class Cache
{
    public $expiration;

    public function __construct($getter, $setter, $class=null, $expiration=null)
    {
        $this->getter = $class ? array($class, $getter) : $getter;
        $this->setter = $class ? array($class, $setter) : $setter;
        $this->class = $class;
        $this->expiration = null;
    }

    function get($key)
    {
        return unserialize(call_user_func($this->getter, $key));
    }

    function set($key, $value, $expiration=null)
    {
        return call_user_func($this->setter, $key, serialize($value), $expiration ?: $this->expiration);
    }
}
