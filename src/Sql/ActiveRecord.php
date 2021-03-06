<?php
namespace Amiss\Sql;

use Amiss\Exception;

abstract class ActiveRecord
{
    private static $services=array();
    private static $meta=array();
    
    /**
     * For testing only
     * @codeCoverageIgnore
     */
    public static function _reset()
    {
        self::$services = array();
        self::$meta = array();
    }
    
    public function save()
    {
        return static::getDependency('manager')->save($this, static::getMeta());
    }
    
    public function insert()
    {
        return static::getDependency('manager')->insert($this, static::getMeta());
    }
    
    public function update()
    {
        return static::getDependency('manager')->update($this, static::getMeta());
    }

    public function delete()
    {
        return static::getDependency('manager')->delete($this, static::getMeta());
    }
    
    public static function getDependency($id, $class=null)
    {
        if (!$class) {
            $class = get_called_class();
        }
        
        if (!isset(self::$services[$id][$class])) {
            $parent = get_parent_class($class);
            if ($parent) {
                self::$services[$id][$class] = static::getDependency($id, $parent);
            }
        }

        if (!isset(self::$services[$id][$class])) {
            throw new Exception("No $id defined against $class or any parent thereof");
        }
        
        return self::$services[$id][$class];
    }
    
    public static function setDependency($id, $service)
    {
        $class = get_called_class();
        self::$services[$id][$class] = $service;
    }
    
    public static function getManager($class=null)
    {
        return static::getDependency('manager', $class);
    }
    
    public static function setManager(Manager $manager=null)
    {
        return static::setDependency('manager', $manager);
    }
    
    public static function getMeta($class=null)
    {
        $class = $class ?: get_called_class();
        if (!isset(self::$meta[$class])) {
            self::$meta[$class] = $meta = static::getDependency('manager')->mapper->getMeta($class);

            foreach (\Amiss\Sql\Manager::$eventNames as $name) {
                if (method_exists($class, $name)) {
                    $meta->on[$name][] = $name;
                }
            }
        }
        return self::$meta[$class];
    }

    public static function insertTable(...$args)
    {
        $manager = static::getDependency('manager');
        $meta = static::getMeta();
        array_unshift($args, $meta);
        return call_user_func_array(array($manager, 'insertTable'), $args);
    }

    public static function updateTable(...$args)
    {
        $manager = static::getDependency('manager');
        $meta = static::getMeta();
        array_unshift($args, $meta);
        return call_user_func_array(array($manager, 'updateTable'), $args);
    }
    
    public function __call($name, $args)
    {
        $manager = static::getDependency('manager');
        
        $exists = null;
        if ($name == 'getRelated' || $name == 'assignRelated') { 
            $exists = true; 
            array_unshift($args, $this);
        }
        
        if ($exists) {
            return call_user_func_array(array($manager, $name), $args);
        } else {
            throw new \BadMethodCallException("Unknown method $name on class ".get_class($this));
        }
    }
    
    public static function __callStatic($name, $args)
    {
        $manager = static::getDependency('manager');
        $called = get_called_class();
        
        $exists = null;
        if ($name == 'get'         || $name == 'getById'     || 
            $name == 'getList'     || $name == 'getByKey'    || 
            $name == 'exists'      || $name == 'count'       || 
            $name == 'deleteById'  ||
            $name == 'deleteTable' || $name == 'updateTable' ||
            $name == 'insertTable' 
        ) {
            $exists = true; 
            array_unshift($args, $called);
        }
        elseif ($name == 'assignRelated') {
            $exists = true;
        }
        elseif ($name == 'indexBy') {
            $args = array_pad($args, 4, null);
            $args = array_merge(array_slice($args, 0, 2), [$called], array_slice($args, 2));
            $exists = true;
        }
        elseif ($name == 'groupBy') {
            $args = array_pad($args, 2, null);
            $args[] = $called;
            $exists = true;
        }
        
        if ($exists) {
            return call_user_func_array(array($manager, $name), $args);
        } else {
            throw new \BadMethodCallException("Unknown method $name");
        }
    }
    
    public function __get($name)
    {
        $meta = static::getMeta();
        
        if (!isset($meta->fields[$name])) {
            throw new \BadMethodCallException("Unknown property $name on class ".get_class($this));
        }
        else {
            // add the property to stop this from being called again
            $this->$name = null;
        }
    }
    
    public function __set($name, $value)
    {
        $meta = static::getMeta();
        
        if (!isset($meta->fields[$name])) {
            throw new \BadMethodCallException("Unknown property $name on class ".get_class($this));
        }
        else {
            // add the property to stop this from being called again
            $this->$name = $value;
        }
    }
}
