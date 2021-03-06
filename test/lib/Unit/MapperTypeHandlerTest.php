<?php
namespace Amiss\Test\Acceptance;

use Amiss\Demo;

/**
 * @group unit
 */
class MapperTypeHandlerTest extends \Amiss\Test\Helper\TestCase
{
    public function setUp()
    {
        parent::setUp();
        
        $this->mapper = $this->getMockBuilder('Amiss\Mapper\Base')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass()
        ;
    }
    
    /**
     * @group mapper
     * @covers Amiss\Mapper\Base::addTypeHandler
     */
    public function testAddTypeHandler()
    {
        $handler = new \Amiss\Test\Helper\TestTypeHandler();
        
        $this->assertFalse(isset($this->mapper->typeHandlers['foo']));
        
        $this->mapper->addTypeHandler(new \Amiss\Test\Helper\TestTypeHandler(), 'foo');
        $handler2 = $this->mapper->typeHandlers['foo'];
        
        $this->assertEquals($handler, $handler2);
    }
    
    /**
     * @group mapper
     * @covers Amiss\Mapper\Base::addTypeHandler
     */
    public function testAddTypeHandlerToManyTypes()
    {
        $handler = new \Amiss\Test\Helper\TestTypeHandler();
        
        $this->assertFalse(isset($this->mapper->typeHandlers['foo']));
        $this->assertFalse(isset($this->mapper->typeHandlers['bar']));
        
        $this->mapper->addTypeHandler(new \Amiss\Test\Helper\TestTypeHandler(), array('foo', 'bar'));
        
        $this->assertEquals($handler, $this->mapper->typeHandlers['foo']);
        $this->assertEquals($handler, $this->mapper->typeHandlers['bar']);
    }
}
