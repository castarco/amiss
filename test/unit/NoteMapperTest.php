<?php
namespace Amiss\Test\Acceptance;

/**
 * @group mapper
 * @group unit
 */ 
class NoteMapperTest extends \CustomTestCase
{   
    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaWithDefinedTable()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', "
            /** @table custom_table */
            class Test {}
        ");
        $meta = $mapper->getMeta($class);
        $this->assertEquals('custom_table', $meta->table);
    }

    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaWithDefaultTable()
    {
        $mapper = $this->getMockBuilder('\Amiss\Mapper\Note')
            ->setMethods(array('getDefaultTable'))
            ->getMock()
        ;
        $mapper->expects($this->once())->method('getDefaultTable');
        $class = $this->createFnScopeClass('Test', "class Test {}");
        $meta = $mapper->getMeta($class);
    }
    
    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaCache()
    {
        $cacheData = array();
        $getCount = $setCount = 0;
        
        $cache = new \Amiss\Cache(
            function($key) use (&$cacheData, &$getCount) {
                ++$getCount;
                return isset($cacheData[$key]) ? $cacheData[$key] : null;
            },
            function($key, $value) use (&$cacheData, &$setCount) {
                ++$setCount;
                $cacheData[$key] = $value;
            }
        );
        $mapper = new \Amiss\Mapper\Note($cache);
        
        $this->assertArrayNotHasKey('stdClass', $cacheData);
        $meta = $mapper->getMeta('stdClass');
        $this->assertArrayHasKey('stdClass', $cacheData);
        $this->assertEquals(1, $getCount);
        $this->assertEquals(1, $setCount);
        
        $mapper = new \Amiss\Mapper\Note($cache);
        $meta = $mapper->getMeta('stdClass');
        $this->assertEquals(2, $getCount);
        $this->assertEquals(1, $setCount);
    }

    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaMultiplePrimaries()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', '
            class Test {
                /** @primary */ public $id1;
                /** @primary */ public $id2;
            }
        ');
        $meta = $mapper->getMeta($class);
        $this->assertEquals(array('id1', 'id2'), $meta->primary);
    }
    
    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaFieldsFound()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', '
            class Test {
                /** @field */ public $foo;
                /** @field */ public $bar;
            }
        ');
        
        $meta = $mapper->getMeta($class);
        $this->assertEquals(array('foo', 'bar'), array_keys($meta->getFields()));
    }
    
    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaSkipsPropertiesWithNoFieldNote()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', '
            class Test {
                public $notAField;
                
                /** @field */ public $yepAField;
            }
        ');
        $meta = $mapper->getMeta($class);
        $this->assertEquals(array('yepAField'), array_keys($meta->getFields()));
    }
    
    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaGetterWithDefaultSetter()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', '
            class Test {
                /** @field */
                public function getFoo(){}
                public function setFoo($value){} 
            }
        ');
        $meta = $mapper->getMeta($class);
        $expected = array('name'=>'foo', 'type'=>null, 'getter'=>'getFoo', 'setter'=>'setFoo');
        $this->assertEquals($expected, $meta->getField('foo'));
    }

    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaWithDefinedConstructor()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', "
            /** @constructor pants */
            class Test {}
        ");
        $meta = $mapper->getMeta($class);
        $this->assertEquals('pants', $meta->constructor);
    }

    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaWithDefaultConstructor()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', "
            /** @table pants */
            class Test {}
        ");
        $meta = $mapper->getMeta($class);
        $this->assertEquals('__construct', $meta->constructor);
    }

    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaPrimaryNoteImpliesFieldNote()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', '
            class Test {
                /** @primary */ public $id;
            }
        ');
        $meta = $mapper->getMeta($class);
        $this->assertEquals(array('id'), array_keys($meta->getFields()));
    }

    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaPrimaryNoteImpliedFieldNoteAllowsType()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', '
            class Test {
                /**
                 * @primary
                 * @type autoinc 
                 */ 
                public $id;
            }
        ');
        $meta = $mapper->getMeta($class);
        $this->assertEquals(array('id'=>array('name'=>'id', 'type'=>array('id'=>'autoinc'))), $meta->getFields());
    }
    
    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaPrimaryNoteFound()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', '
            class Test {
                /** @primary */ public $id;
            }
        ');
        $meta = $mapper->getMeta($class);
        $this->assertEquals(array('id'), $meta->primary);
    }

    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaMultiPrimaryNoteFound()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', '
            class Test {
                /** @primary */ public $idPart1;
                /** @primary */ public $idPart2;
            }
        ');
        $meta = $mapper->getMeta($class);
        $this->assertEquals(array('idPart1', 'idPart2'), $meta->primary);
    }
    
    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaFieldTypeFound()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Test', '
            class Test {
                /** 
                 * @field
                 * @type foobar
                 */
                 public $id;
            }
        ');
        $meta = $mapper->getMeta($class);
        $field = $meta->getField('id');
        $this->assertEquals(array('id'=>'foobar'), $field['type']);
    }

    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testGetMetaWithParentClass()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class1 = $this->createFnScopeClass("Test1", '
            class Test1 {
                /** @field */ public $foo;
            }
        ');
        $class2 = $this->createFnScopeClass("Test2", '
            class Test2 extends Test1 {
                /** @field */ public $bar;
            }
        ');
        
        $meta1 = $mapper->getMeta($class1);
        $meta2 = $mapper->getMeta($class2);
        $this->assertEquals($meta1, $this->getProtected($meta2, 'parent'));
    }

    /**
     * @covers Amiss\Mapper\Note::buildRelations
     * @covers Amiss\Mapper\Note::findGetterSetter
     */
    public function testGetMetaRelationWithInferredGetterAndInferredSetter()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Foo', '
            class Foo {
                /** @primary */ public $id;
                /** @field */   public $barId;

                private $bar;
                
                /** 
                 * @has.one.of Bar
                 * @has.one.from barId
                 */
                public function getBar() { return $this->bar; }
            }
        ');
        $meta = $mapper->getMeta($class);
        $expected = array(
            'bar'=>array('one', 'of'=>"Bar", 'from'=>'barId', 'getter'=>'getBar', 'setter'=>'setBar', 'name'=>'bar', 'mode'=>'default'),
        );
        $this->assertEquals($expected, $meta->relations);
    }

    /**
     * @covers Amiss\Mapper\Note::loadMeta
     */
    public function testPrimaryFieldTranslation()
    {
        $class = $this->createFnScopeClass('Foo', "
            class Foo {
                /** @primary */
                public \$fooBarBaz;

                /** @field */
                public \$bazQuxDing;
            }
        ");

        $mapper = new \Amiss\Mapper\Note;
        $mapper->unnamedPropertyTranslator = new \Amiss\Name\CamelToUnderscore();
        $meta = $mapper->getMeta($class);
        
        $fields = $meta->getFields();
        $this->assertEquals('foo_bar_baz',  $fields['fooBarBaz']['name']);
        $this->assertEquals('baz_qux_ding', $fields['bazQuxDing']['name']);
    }

    /**
     * @covers Amiss\Mapper\Note::buildRelations
     * @covers Amiss\Mapper\Note::findGetterSetter
     */
    public function testGetMetaRelationWithInferredGetterAndExplicitSetter()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class = $this->createFnScopeClass('Foo', '
            class Foo {
                /** @primary */ public $id;
                /** @field */   public $barId;
                
                private $bar;
                
                /** 
                 * @has.one.of Bar
                 * @has.one.from barId
                 * @setter setLaDiDaBar
                 */
                public function getBar()             { return $this->bar; }
                public function setLaDiDaBar($value) { $this->bar = $value; }
            }
        ');
        $meta = $mapper->getMeta($class);
        $expected = array(
            'bar'=>array('one', 'of'=>"Bar", 'from'=>'barId', 'getter'=>'getBar', 'setter'=>'setLaDiDaBar', 'name'=>'bar', 'mode'=>'default'),
        );
        $this->assertEquals($expected, $meta->relations);
    }
    
    /**
     * @covers Amiss\Mapper\Note::loadMeta
     * @covers Amiss\Mapper\Note::buildRelations
     */
    public function testGetMetaOneToManyPropertyRelationWithNoOn()
    {
        $mapper = new \Amiss\Mapper\Note;
        $class1 = $this->createFnScopeClass("Class1", "
            class Class1 {
                /** @primary */ 
                public \$class1id;
                
                /** @field */ 
                public \$class2Id;
                
                /** @has.many.of Class2 */
                public \$class2;
            }
        ");
        $class2 = $this->createClass("Class2", "
            class Class2 {
                /** @primary */ 
                public \$class2Id;
            }
        ");
        $meta = $mapper->getMeta($class1);
        $expected = array(
            'class2'=>array('many', 'of'=>"Class2", 'name'=>'class2', 'mode'=>'default')
        );
        $this->assertEquals($expected, $meta->relations);
    }
    
    /**
     * @covers Amiss\Mapper\Note::loadMeta
     * @covers Amiss\Mapper\Note::buildRelations
     */
    public function testGetMetaWithStringRelation()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Class1", '
            class Class1 {
                /** @has test */ 
                public $test;
            }
        ');
        $meta = $mapper->getMeta($name);
        $expected = array(
            'test'=>array('test', 'name'=>'test', 'mode'=>'default')
        );
        $this->assertEquals($expected, $meta->relations);
    }

    public function testGetMetaWithClassIndex()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            /** @index.foo.fields[] a */
            class Test {
                /** @field */ public $a;
            }
        ');
        $meta = $mapper->getMeta($name);
        $expected = ['foo'=>['fields'=>['a'], 'key'=>false]];
        $this->assertEquals($expected, $meta->indexes);
    }

    public function testGetMetaWithClassKeyIndex()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            /** 
             * @index.foo.fields[] a
             * @index.foo.key
             */
            class Test {
                /** @field */ public $a;
            }
        ');
        $meta = $mapper->getMeta($name);
        $expected = ['foo'=>['fields'=>['a'], 'key'=>true]];
        $this->assertEquals($expected, $meta->indexes);
    }

    public function testGetMetaWithMultiFieldClassIndex()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            /** 
             * @index.foo.fields[] b
             * @index.foo.fields[] a
             */
            class Test {
                /** @field */ public $a;
            }
        ');
        $meta = $mapper->getMeta($name);
        $expected = ['foo'=>['fields'=>['b', 'a'], 'key'=>false]];
        $this->assertEquals($expected, $meta->indexes);
    }

    public function testGetMetaWithDuplicateIndexDefinition()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            /** 
             * @index.foo.key
             */
            class Test {
                /**
                 * @field
                 * @index foo
                 */
                 public $a;
            }
        ');
        $this->setExpectedException(\Amiss\Exception::class, "Index foo already defined");
        $meta = $mapper->getMeta($name);
    }

    public function testGetMetaWithStringFieldIndex()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            class Test {
                /** 
                 * @field 
                 * @index foo
                 */
                public $a;
            }
        ');
        $meta = $mapper->getMeta($name);
        $expected = ['foo'=>['fields'=>['a'], 'key'=>false]];
        $this->assertEquals($expected, $meta->indexes);
    }

    public function testGetMetaWithStringFieldKey()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            class Test {
                /** 
                 * @field 
                 * @key foo
                 */
                public $a;
            }
        ');
        $meta = $mapper->getMeta($name);
        $expected = ['foo'=>['fields'=>['a'], 'key'=>true]];
        $this->assertEquals($expected, $meta->indexes);
    }

    public function testGetMetaWithBadTypeFails()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            class Test {
                /** 
                 * @field 
                 * @index.foo
                 */
                public $a;
            }
        ');
        $this->setExpectedException(\Amiss\Exception::class);
        $meta = $mapper->getMeta($name);
    }

    public function testGetMetaAutoNamedIndexFromGetter()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            class Test {
                private $field;
                
                /**
                 * @field
                 * @index
                 */
                public function getField()   { return $this->field; }
                public function setField($v) { $this->field = $v;   }
            }
        ');
        $meta = $mapper->getMeta($name);

        $expected = [
            'field'=>['fields'=>['field'], 'key'=>false],
        ];
        $this->assertEquals($expected, $meta->indexes);
    }

    public function testGetMetaAutoNamedKeyFromGetter()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            class Test {
                private $field;
                
                /**
                 * @field
                 * @key
                 */
                public function getField()   { return $this->field; }
                public function setField($v) { $this->field = $v;   }
            }
        ');
        $meta = $mapper->getMeta($name);

        $expected = [
            'field'=>['fields'=>['field'], 'key'=>true],
        ];
        $this->assertEquals($expected, $meta->indexes);
    }

    public function testGetMetaAutoNamedIndexFromField()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            class Test {
                /**
                 * @field
                 * @index
                 */
                public $field;
            }
        ');
        $meta = $mapper->getMeta($name);

        $expected = [
            'field'=>['fields'=>['field'], 'key'=>false],
        ];
        $this->assertEquals($expected, $meta->indexes);
    }

    public function testGetMetaAutoNamedKeyFromField()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            class Test {
                /**
                 * @field
                 * @key
                 */
                public $field;
            }
        ');
        $meta = $mapper->getMeta($name);

        $expected = [
            'field'=>['fields'=>['field'], 'key'=>true],
        ];
        $this->assertEquals($expected, $meta->indexes);
    }

    public function testGetMetaPrimaryAutoFieldNameFromMethod()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass("Test", '
            class Test {
                private $field;
                /** @primary */
                public function getField() { return $this->field; }
                public function setField($v) { $this->field = $v; }
            }
        ');
        $meta = $mapper->getMeta($name);

        $expected = ['field'];
        $this->assertEquals($expected, $meta->primary);
    }

    public function testGetMetaConstructor()
    {
        $mapper = new \Amiss\Mapper\Note;

        $name = $this->createFnScopeClass("Test", '
            class Test {
                /** @constructor */
                public static function foo() {}
            }
        ');
        $meta = $mapper->getMeta($name);
        $this->assertEquals('foo', $meta->constructor);
    }

    public function testGetMetaConstructorArg()
    {
        $mapper = new \Amiss\Mapper\Note;

        $name = $this->createFnScopeClass("Test", '
            class Test {
                /**
                 * @constructor.args[] relation:pants
                 */
                public static function foo() {}
            }
        ');
        $meta = $mapper->getMeta($name);
        $this->assertEquals('foo', $meta->constructor);
        $this->assertEquals([['relation', 'pants']], $meta->constructorArgs);
    }

    public function testGetMetaDefaultConstructorArg()
    {
        $mapper = new \Amiss\Mapper\Note;

        $name = $this->createFnScopeClass("Test", '
            class Test {
                /**
                 * @constructor.args[] relation:pants
                 */
                public function __construct()
                {}
            }
        ');
        $meta = $mapper->getMeta($name);
        $this->assertEquals('__construct', $meta->constructor);
        $this->assertEquals([['relation', 'pants']], $meta->constructorArgs);
    }

    public function testGetMetaConstructorArgs()
    {
        $mapper = new \Amiss\Mapper\Note;

        $name = $this->createFnScopeClass("Test", '
            class Test {
                /**
                 * @constructor.args[] relation:pants
                 * @constructor.args[] field:foo
                 */
                public static function foo($a, $b) {}
            }
        ');
        $meta = $mapper->getMeta($name);
        $this->assertEquals('foo', $meta->constructor);
        $this->assertEquals([['relation', 'pants'], ['field', 'foo']], $meta->constructorArgs);
    }

    public function testGetMetaField()
    {
        $mapper = new \Amiss\Mapper\Note;

        $name = $this->createFnScopeClass("Test", '
            class Test {
                /** @field bar */
                public $foo;
            }
        ');
        $meta = $mapper->getMeta($name);
        $this->assertEquals(['name'=>'bar', 'type'=>null], $meta->getField('foo'));
    }

    public function testClassRelations()
    {
        $mapper = new \Amiss\Mapper\Note;
        $name = $this->createFnScopeClass('Test', '
            /**
             * @relation[foo].one.of Pants
             */
            class Test {
            }
        ');
        $meta = $mapper->getMeta($name);
        $expected = ['foo'=>[
            'one', 'of'=>'Pants', 'mode'=>'class', 'name'=>'foo',
        ]];
        $this->assertEquals($expected, $meta->relations);
    }
}
