<?php
namespace Amiss\Test\Acceptance;

/**
 * @group unit
 */
class MetaTest extends \Amiss\Test\Helper\TestCase
{
    public $fieldDefaults = ['type'=>null, 'required'=>false];

    /**
     * @covers Amiss\Meta::__construct
     */
    public function testCreateMeta()
    {
        $parent = new \Amiss\Meta('a', array('table'=>'a'));
        $info = array(
            'table'=>'std_class',
            'primary'=>'pri',
            'fields'=>array('f'=>array()),
            'relations'=>array('r'=>array()),
            'defaultFieldType'=>'def',
        );
        $meta = new \Amiss\Meta('stdClass', $info, $parent);
        
        $this->assertEquals('stdClass',   $meta->class);
        $this->assertEquals('std_class',  $meta->table);
        $this->assertEquals(array('pri'), $meta->primary);
        
        $this->assertEquals(['f'=>['name'=>'f', 'id'=>'f'] + $this->fieldDefaults], $this->getProtected($meta, 'fields'));
        $this->assertEquals(['r'=>['name'=>'r', 'mode'=>'default']], $this->getProtected($meta, 'relations'));
        $this->assertEquals(['id'=>'def'],  $this->getProtected($meta, 'defaultFieldType'));
    }
    
    /**
     * @covers Amiss\Meta::getDefaultFieldType
     */
    public function testGetDefaultFieldTypeInheritsFromDirectParent()
    {
        $parent = new \Amiss\Meta('parent', array(
            'table'=>'parent',
            'defaultFieldType'=>'def',
        ));
        $meta = new \Amiss\Meta('child', array('table'=>'child'), $parent);
        $this->assertEquals(array('id'=>'def'), $meta->getDefaultFieldType());
    }
    
    /**
     * @covers Amiss\Meta::getDefaultFieldType
     */
    public function testGetDefaultFieldTypeInheritsFromGrandparent()
    {
        $grandParent = new \Amiss\Meta('grandparent', array(
            'table'=>'grandparent',
            'defaultFieldType'=>'def',
        ));
        $parent = new \Amiss\Meta('parent', array('table'=>'parent'), $grandParent);
        $meta = new \Amiss\Meta('child', array('table'=>'child'), $parent);
        $this->assertEquals(array('id'=>'def'), $meta->getDefaultFieldType());
    }
    
    /**
     * @covers Amiss\Meta::getDefaultFieldType
     * @dataProvider dataForGetDefaultFieldTypeFromParentOnlyCallsParentOnce
     */
    public function testGetDefaultFieldTypeFromParentOnlyCallsParentOnce($defaultType)
    {
        $parent = $this->getMockBuilder('Amiss\Meta')
            ->disableOriginalConstructor()
            ->setMethods(array('getDefaultFieldType'))
            ->getMock()
        ;
        $parent->expects($this->once())->method('getDefaultFieldType')->will($this->returnValue($defaultType));
        
        $meta = new \Amiss\Meta('child', array('table'=>'child'), $parent);
        $meta->getDefaultFieldType();
        $meta->getDefaultFieldType();
    }
    
    public function dataForGetDefaultFieldTypeFromParentOnlyCallsParentOnce()
    {
        return array(
            array('yep'),
            array(array('id'=>'yep')),
            array(null),
            array(false),
        );
    }
    
    /**
     * @covers Amiss\Meta::getFields
     */
    public function testGetFieldInheritance()
    {
        $grandparent = new \Amiss\Meta('a', array(
            'table'=>'a',
            'fields'=>array(
                'field1'=>array(),
                'field2'=>array(),
            ),
        )); 
        $parent = new \Amiss\Meta(
            'b',
            array(
                'table'=>'b',
                'fields'=>array(
                    'field3'=>array(),
                    'field4'=>array(1),
                ),
            ),
            $grandparent
        );
        $child = new \Amiss\Meta('c', array(
            'table'=>'c',
            'fields'=>array(
                'field4'=>array(2),
                'field5'=>array(),
            ),
        ), $parent);
        
        $expected = [
            'field1'=>['id'=>'field1', 'name'=>'field1'] + $this->fieldDefaults,
            'field2'=>['id'=>'field2', 'name'=>'field2'] + $this->fieldDefaults,
            'field3'=>['id'=>'field3', 'name'=>'field3'] + $this->fieldDefaults,
            'field4'=>[2, 'id'=>'field4', 'name'=>'field4'] + $this->fieldDefaults,
            'field5'=>['id'=>'field5', 'name'=>'field5'] + $this->fieldDefaults,
        ];
        $this->assertEquals($expected, $child->getFields());
    }
    
    /**
     * @covers Amiss\Meta::setFields
     */
    public function testPrimaryDefinedInInfoAndFieldsFails()
    {
        $this->setExpectedException(
            \Amiss\Exception::class, 
            "Primary can not be defined at class level and field level simultaneously in class 'stdClass'"
        );
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'primary'=>'a',
            'fields'=>[
                'a'=>['primary'=>true],
            ],
        ));
    }

    /**
     * @covers Amiss\Meta::__construct
     * @covers Amiss\Meta::getIndexValue
     */
    public function testGetIndexValueString()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'primary'=>'a',
        ));
        
        $obj = (object)array('a'=>1, 'b'=>2);
        $this->assertEquals(array('a'=>1), $meta->getIndexValue($obj));
    }

    /**
     * @covers Amiss\Meta::__construct
     * @covers Amiss\Meta::getIndexValue
     */
    public function testGetIndexValueSingleCol()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'primary'=>array('a'),
        ));
        
        $obj = (object)array('a'=>1, 'b'=>2);
        $this->assertEquals(array('a'=>1), $meta->getIndexValue($obj));
    }

    /**
     * @covers Amiss\Meta::__construct
     * @covers Amiss\Meta::getIndexValue
     */
    public function testGetIndexValueMultiCol()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'primary'=>array('a', 'b'),
        ));
        
        $obj = (object)array('a'=>1, 'b'=>2);
        $this->assertEquals(array('a'=>1, 'b'=>2), $meta->getIndexValue($obj));
    }

    /**
     * @covers Amiss\Meta::__construct
     * @covers Amiss\Meta::getIndexValue
     */
    public function testGetIndexValueMultiReturnsNullWhenNoValues()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'primary'=>array('a', 'b'),
        ));
        
        $obj = (object)array('a'=>null, 'b'=>null, 'c'=>3);
        $this->assertEquals(null, $meta->getIndexValue($obj));
    }

    /**
     * @covers Amiss\Meta::getIndexValue
     */
    public function testGetIndexValueMultiWhenOneValueIsNull()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'primary'=>array('a', 'b'),
        ));
        
        $obj = (object)array('a'=>null, 'b'=>2, 'c'=>3);
        $this->assertEquals(array('a'=>null, 'b'=>2), $meta->getIndexValue($obj));
    }

    /**
     * @covers Amiss\Meta::getValue
     */
    public function testGetPropertyValue()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'fields'=>array(
                'a'=>array(),
            ),
        ));
        
        $obj = (object)array('a'=>'foo');
        $result = $meta->getValue($obj, 'a');
        $this->assertEquals('foo', $result);
    }
    
    /**
     * @covers Amiss\Meta::getValue
     */
    public function testGetUnknownPropertyValue()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'fields'=>array(
                'a'=>array(),
            ),
        ));
        
        $obj = (object)array('a'=>'foo');
        
        $this->setExpectedException('PHPUnit_Framework_Error_Notice');
        $result = $meta->getValue($obj, 'b');
    }
    
    /**
     * @covers Amiss\Meta::getValue
     */
    public function testGetGetterValue()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'fields'=>array(
                'a'=>array('getter'=>'getTest'),
            ),
        ));
        
        $mock = $this->getMockBuilder('stdClass')
            ->setMethods(array('getTest'))
            ->getMock()
        ;
        $mock->expects($this->once())->method('getTest');
        
        $result = $meta->getValue($mock, 'a');
    }
    
    /**
     * @covers Amiss\Meta::getValue
     */
    public function testGetUnknownGetterValue()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'fields'=>array(
                'a'=>array('getter'=>'getDoesntExist'),
            ),
        ));
        
        $mock = $this->getMockBuilder('stdClass')
            ->setMethods(array('getTest'))
            ->getMock()
        ;
        $mock->expects($this->never())->method('getTest');
        
        $this->setExpectedException('PHPUnit_Framework_Error_Warning');
        $result = $meta->getValue($mock, 'a');
    }
    
    /**
     * @covers Amiss\Meta::setValue
     */
    public function testSetValue()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'fields'=>array(
                'a'=>array(),
            ),
        ));
        
        $object = (object) array('a'=>null);
        $meta->setValue($object, 'a', 'foo');
        $this->assertEquals($object->a, 'foo');
    }
    
    /**
     * @covers Amiss\Meta::setValue
     */
    public function testSetUnknownValue()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'fields'=>array(
                'a'=>array(),
            ),
        ));
        
        $object = (object) array('a'=>null);
        $meta->setValue($object, 'doesntExist', 'foo');
        $this->assertEquals($object->doesntExist, 'foo');
    }

    /**
     * @covers Amiss\Meta::setValue
     */
    public function testSetValueWithSetter()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'fields'=>array(
                'a'=>array('setter'=>'setValue'),
            ),
        ));
        
        $object = $this->getMockBuilder('stdClass')
            ->setMethods(array('setValue'))
            ->getMock()
        ;
        $object->expects($this->once())->method('setValue');
        $meta->setValue($object, 'a', 'foo');
    }
    
    /**
     * @covers Amiss\Meta::setValue
     */
    public function testSetValueWithUnknownSetter()
    {
        $meta = new \Amiss\Meta('stdClass', array(
            'table'=>'std_class',
            'fields'=>array(
                'a'=>array('setter'=>'setDoesntExist'),
            ),
        ));
        
        $object = $this->getMockBuilder('stdClass')
            ->setMethods(array('setValue'))
            ->getMock()
        ;
        $object->expects($this->never())->method('setValue');
        
        $this->setExpectedException('PHPUnit_Framework_Error_Warning');
        $meta->setValue($object, 'a', 'foo');
    }

    /**
     * @covers Amiss\Meta::__sleep
     */
    public function testSleep()
    {
        $m = new \Amiss\Meta('stdClass', []);
        $props = $m->__sleep();
        $rc = new \ReflectionClass($m);
        foreach ($rc->getProperties() as $p) {
            $this->assertContains($p->name, $props, "You forgot to add '{$p->name}' to Amiss\Meta's __sleep()");
        }
    }
}