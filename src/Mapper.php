<?php

namespace Amiss;

// @codeCoverageIgnoreStart
interface Mapper
{
	function getMeta($class);
	function createObject($meta, $row, $args);
	function exportRow($meta, $object);
	function determineTypeHandler($type);
}