<?php
namespace Amiss\Sql\Type;

class Autoinc implements \Amiss\Type\Handler, \Amiss\Type\Identity
{
    public $type = 'INTEGER';
    
    function handleDbGeneratedValue($value)
    {
        return (int)$value;
    }

    function prepareValueForDb($value, array $fieldInfo)
    {
        return $value;
    }
    
    function handleValueFromDb($value, array $fieldInfo, $row)
    {
        return (int)$value;
    }
    
    function createColumnType($engine, array $fieldInfo)
    {
        if ($engine == 'sqlite') {
            return "INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT";
        } else {
            return $this->type." NOT NULL AUTO_INCREMENT";
        }
    }
}
