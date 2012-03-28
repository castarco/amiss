<?php

namespace Amiss\Criteria;

class Query
{
	public $where;
	public $params=array();
	
	public function __construct(array $criteria=null)
	{
		if ($criteria) {
			$this->populate($criteria);
		}
	}
	
	public function populate(array $criteria)
	{ 
		foreach ($criteria as $k=>$v) {
			$this->$k = $v;
		}
	}
	
	public function buildClause($meta)
	{
		$where = $this->where;
		$params = array();
		$namedParams = $this->paramsAreNamed(); 
		
		$fields = null;
		if ($meta) $fields = $meta->getFields();
		
		if (is_array($where)) {
			$wh = array();
			foreach ($where as $k=>$v) {
				if (isset($fields[$k])) $k = $fields[$k]['name'];
				$wh[] = '`'.str_replace('`', '', $k).'`=:'.$k;
				$params[':'.$k] = $v;
			}
			$where = implode(' AND ', $wh);
			$namedParams = true;
		}
		else {
			if ($fields && strpos($where, '{')!==false) {
				$tokens = array();
				foreach ($fields as $k=>$v)
					$tokens['{'.$k.'}'] = '`'.$v['name'].'`';
				
				$where = strtr($where, $tokens);
			}
		}
		
		if ($namedParams) {
			foreach ($this->params as $k=>$v) {
				if (!is_numeric($k) && strpos($k, ':')!==0) $k = ':'.$k;
				if (is_array($v)) {
					$inparms = array();
					$cnt = 0;
					$v = array_unique($v);
					foreach ($v as $val) {
						$inparms[$k.'_'.$cnt++] = $val;
					}
					$params = array_merge($params, $inparms);
					$where = preg_replace("@IN\s*\($k\)@i", "IN(".implode(',', array_keys($inparms)).")", $where);
				}
				else {
					$params[$k] = $v;
				}
			}
		}
		else {
			$params = $this->params;
		}
		return array($where, $params);
	}
	
	public function paramsAreNamed()
	{
		return !ctype_digit(implode('', array_keys($this->params)));
	}
}
