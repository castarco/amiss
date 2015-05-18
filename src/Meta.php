<?php
namespace Amiss;

class Meta
{
    public $class;
    public $table;
    public $primary;
    public $constructor;
    public $constructorArgs = [];

    /**
     * Array of relation arrays, hashed by property name
     * 
     * Relation arrays *must* contain at least a type at index 0. 
     * 
     * The meta also looks for the following keys:
     * - name (will be assigned by this class)
     * - mode (default, auto, class)
     * 
     * All other values in the array are ignored by the meta and passed 
     * directly to the relator.
     * 
     * Example:
     *   $meta->relations = array(
     *     // the 'of' and 'on' keys are required by Amiss\Sql\Relator\OneMany
     *     'foo'=>array('one', 'of'=>'Artist', 'on'=>'artistId'),
     *     
     *     // the blahblah relator has different ideas
     *     'bar'=>array('blahblah', 'fee'=>'fi', 'fo'=>'fum'),
     *   );
     */
    public $relations = [];

    /**
     * Additional metadata found but not explicitly handled by the mapper
     */
    public $ext;

    public $autoRelations = [];

    public $indexes;

    public $canInsert = true;
    public $canUpdate = true;
    public $canDelete = true;

    /**
     * Array of fields, hashed by property name
     */
    protected $fields;
    protected $allFields;
    protected $properties;
    protected $parent;
    protected $columnToPropertyMap;
    
    /**
     * @var array|false|null  Array of type if found, false if checked and none found, 
     *                        null if not yet checked
     */
    protected $defaultFieldType;
    
    function __sleep()
    {
        // precache this stuff before serialization
        $this->getFields();
        $this->getProperties();
        $this->getDefaultFieldType();
        $this->getColumnToPropertyMap();

        return array(
            'class', 'table', 'primary', 'relations', 'fields', 'allFields', 
            'parent', 'defaultFieldType', 'columnToPropertyMap', 'autoRelations',
            'indexes', 'constructor', 'constructorArgs', 'ext', 'properties',
            'canInsert', 'canUpdate', 'canDelete',
        ); 
    }

    public function __construct($class, $table, array $info, Meta $parent=null)
    {
        $this->class = $class;
        $this->parent = $parent;
        $this->table = $table;
        $this->primary = isset($info['primary']) ? $info['primary'] : array();

        if ($this->primary && !is_array($this->primary)) {
            $this->primary = array($this->primary);
        }
        $this->setIndexes(isset($info['indexes']) ? $info['indexes'] : array());

        // set indexes first: setFields is reliant on them 
        $this->setFields(isset($info['fields']) ? $info['fields'] : array());

        // must happen after setFields - setFields can influence the primary
        if ($this->primary) {
            $this->indexes['primary'] = ['fields'=>$this->primary, 'key'=>true];
        }

        if (isset($info['relations'])) {
            $this->setRelations($info['relations']);
        }

        $this->ext = isset($info['ext']) ? $info['ext'] : array();
        
        if (isset($info['constructor']) && $info['constructor']) {
            $this->constructor = $info['constructor'];
        }
        if (isset($info['constructorArgs']) && $info['constructorArgs']) {
            // must come after setFields()
            $this->setConstructorArgs($info['constructorArgs']);
        }
        if (!$this->constructor) {
            $this->constructor = '__construct';
        }

        if (isset($info['readOnly'])) {
            $this->canInsert = false; 
            $this->canUpdate = false; 
            $this->canDelete = false; 
        }
        else {
            if (isset($info['canInsert'])) {
                $this->canInsert = !!$info['canInsert'];
            }
            if (isset($info['canUpdate'])) {
                $this->canUpdate = !!$info['canUpdate'];
            }
            if (isset($info['canDelete'])) {
                $this->canDelete = !!$info['canDelete'];
            }
        }

        $this->defaultFieldType = null;
        if (isset($info['defaultFieldType'])) {
            $ft = $info['defaultFieldType'];
            if (!is_array($ft)) {
                $ft = array('id'=>$ft);
            }
            $this->defaultFieldType = $ft;
        }
    }

    private function setRelations($relations)
    {
        foreach ($relations as $id=>$r) {
            if (isset($r['on'])) {
                throw new Exception("Relation $id used 'on' in class {$this->class}. Please use 'from' and/or 'to'");
            }
            $r['name'] = $id;
            if (isset($r['mode'])) {
                if ($r['mode'] == 'auto') {
                    $this->autoRelations[] = $id;
                }
            }
            else {
                $r['mode'] = 'default';
            }
            $this->relations[$id] = $r;
        }
    }

    private function setConstructorArgs($args)
    {
        $checkProperties = [];
        foreach ($args as $arg) {
            $this->constructorArgs[] = $arg;
            if ($arg[0] == 'property') {
                $checkProperties[] = $arg[1];
            }
        }
        if ($diff = array_diff($checkProperties, array_keys($this->getProperties() ?: []))) {
            throw new Exception("Unknown constructor properties ".implode(', ', $diff));
        }
    }

    private function setIndexes($indexes)
    {
        foreach ($indexes as $name=>&$index) {
            if (isset($this->indexes[$name])) {
                throw new Exception("Duplicate index name '$name' on {$this->class}");
            }
            if (!isset($index['key'])) {
                $index['key'] = false;
            }
            if (!isset($index['fields']) || !$index['fields']) {
                throw new \UnexpectedValueException("Misconfigured index $name");
            }
            $index['fields'] = (array)$index['fields'];
            $this->indexes[$name] = $index;
        }

        // special sauce
        if (isset($this->indexes['primary'])) {
            throw new \UnexpectedValueException("Cannot manually declare primary in indexes - set 'primary' in meta info instead");
        }
    }

    private function setFields($fields)
    {
        $primary = [];
        $indexes = [];
        foreach ($fields as $name=>&$field) {
            if ($field === true) {
                $field = [];
            } elseif (is_string($field)) {
                $field = ['name'=>$field];
            }
            if (!is_array($field)) {
                throw new \UnexpectedValueException();
            }
            if (!isset($field['name'])) {
                $field['name'] = $name;
            }
            if (isset($field['type'])) {
                if (!is_array($field['type'])) {
                    $field['type'] = array('id'=>$field['type']);
                }
            }
            else {
                $field['type'] = null;
            }
            if (isset($field['primary'])) {
                $primary[] = $name;
            }
            if (isset($field['index'])) {
                $index = $field['index'];
                if ($index === true) {
                    $index = [];
                } elseif (!is_array($index)) {
                    throw new Exception("Invalid index '$name': index must either be boolean or an array of index metadata");
                }
                if (!isset($index['fields'])) {
                    $index['fields'] = [$name];
                }
                $indexes[$name] = $index;
            }
        }
        $this->fields = $fields;
        if ($primary) {
            if ($this->primary) {
                throw new \Exception();
            }
            $this->primary = $primary;
        }
        if ($indexes) {
            $this->setIndexes($indexes);
        }
        return $this;
    }
    
    public function getFields()
    {
        if ($this->allFields===null) {
            $fields = $this->fields;
            
            $current = $this;
            while ($current->parent) {
                $fields = array_merge($current->parent->getFields(), $fields);
                $current = $current->parent;
            }
            
            $this->allFields = $fields ?: array();
        }

        return $this->allFields;
    }

    public function getProperties()
    {
        if ($this->properties === null) {
            foreach ($this->getFields() as $name=>$field) {
                $field['source'] = 'field';
                $this->properties[$name] = $field;
            }
            foreach ($this->relations as $name=>$relation) {
                if ($relation['mode'] != 'class') {
                    $field['source'] = 'relation';
                    $this->properties[$name] = $relation;
                }
            }
        }
        return $this->properties;
    }

    public function getColumnToPropertyMap()
    {
        if ($this->columnToPropertyMap===null) {
            $map = array();
            foreach ($this->getFields() as $prop=>$f) {
                $map[$f['name']] = $prop;
            }
            $this->columnToPropertyMap = $map;
        }
        
        return $this->columnToPropertyMap;
    }
    
    function getField($field)
    {
        if (!$this->allFields) {
            $this->getFields();
        }
        if (isset($this->allFields[$field])) {
            return $this->allFields[$field];
        }
    }
    
    function getDefaultFieldType()
    {
        if ($this->defaultFieldType===null && $this->parent) {
            $this->defaultFieldType = $this->parent->getDefaultFieldType() ?: false;
        }
        return $this->defaultFieldType;
    }

    /**
     * @deprecated Use getIndexValue($object, 'primary')
     */
    function getPrimaryValue($object)
    {
        return $this->getIndexValue($object);
    }
    
    function getIndexValue($object, $indexName='primary')
    {
        $foundValue = false;

        if (!isset($this->indexes[$indexName])) {
            throw new Exception("Class {$this->class} doesn't define index $indexName");
        }
        $indexValue = array();
        foreach ($this->indexes[$indexName]['fields'] as $p) {
            $field = $this->getField($p);
            $value = !isset($field['getter']) 
                ? $object->{$p} 
                : call_user_func(array($object, $field['getter']))
            ;
            if ($value) { $foundValue = true; }
            
            $indexValue[$p] = $value;
        }
        
        if ($foundValue) { return $indexValue; }
    }
    
    function getValue($object, $property)
    {
        $field = $this->getField($property);
        $value = !isset($field['getter']) 
            ? $object->{$property} 
            : call_user_func(array($object, $field['getter'])
        );
        return $value;
    }
    
    function setValue($object, $property, $value)
    {
        $field = $this->getField($property);
        if (!isset($field['setter'])) {
            $object->{$property} = $value;
        } else {
            call_user_func(array($object, $field['setter']), $value);
        }
        return $this;
    }
}
