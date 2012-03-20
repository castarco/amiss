<?php

namespace Amiss\Mapper;

class Statics extends \Amiss\Mapper\Base
{
	protected function createMeta($class)
	{
		$info = array();
		
		$rc = new \ReflectionClass($class);
		
		$statics = $rc->getStaticProperties();
		
		$table = isset($statics['table']) ? $statics['table'] : $this->getDefaultTable($class);
		$info = array(
			'fields'=>array(),
			'relations'=>null,
		);
		
		if ($rc->hasMethod('getRelations')) {
			$relationMethod = $rc->getMethod('getRelations');
			if ($relationMethod && $relationMethod->isStatic()) {
				$info['relations'] = $relationMethod->invoke(null);
			}
		}
		
		if (!$info['relations']) {
			$info['relations'] = isset($statics['relations']) ? $statics['relations'] : array();
		}
		
		foreach ($info['relations'] as $id=>$rel) {
			if (isset($rel['getter']) && !isset($rel['setter'])) {
				$rel['setter'] = 'set'.ucfirst(strpos($rel['getter'], 'get')===0 ? substr($rel['getter'], 3) : $rel['getter']);
			}
		}
		
		if (isset($statics['defaultFieldType']))
			 $info['defaultFieldType'] = $statics['defaultFieldType'];
		if (isset($statics['primary']))
			 $info['primary'] = $statics['primary'];
		
		if (isset($statics['fields'])) {
			foreach ($statics['fields'] as $k=>$v) {
				// this micro-optimisation saves us an is_numeric call. 
				// php converts array key string('0') into int(0) and string('0')==0
				if ($k == 0 && $k !== 0)
					$info['fields'][$k] = array('name'=>$k, 'type'=>$v);
				else
					$info['fields'][$v] = array('name'=>$v, 'type'=>null);
			}
		}
		else {
			foreach ($rc->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
				if ($prop->class == $class && !isset($statics[$prop->name])) {
					if (!isset($info['relations'][$prop->name])) {
						$info['fields'][$prop->name] = array('name'=>$prop->name, 'type'=>null);
					}
				}	
			}
		}
		
		if (!isset($info['primary'])) {
			$pos = strrpos($class, '\\');
			$name = lcfirst($pos ? substr($class, $pos+1) : $class).'Id';
			if (isset($info['fields'][$name]))
				$info['primary'] = $name;
		}
		
		if (isset($info['primary'])) {
			if (!is_array($info['primary']))
				$info['primary'] = array($info['primary']);
			
			foreach ($info['primary'] as $p) {
				if (!isset($info['fields'][$p])) {
					$info['fields'][$p] = array(
						'name'=>$p, 
						'type'=>null,
					);
				}
			}
		}
		
		$parentClass = get_parent_class($class);
		$parent = null;
		if ($parentClass && $parentClass != 'Amiss\Active\Record') {
			$parent = $this->getMeta($parentClass);
		}
		
		$info['fields'] = $this->resolveUnnamedFields($info['fields']);
		
		$meta = new \Amiss\Meta($class, $table, $info, $parent);
		
		return $meta; 
	}
	
	function createObject($meta, $row, $args)
	{
		$object = parent::createObject($meta, $row, $args);
		return $object;
	}
}