<?php
namespace Amiss\Test\Unit;

use Amiss\Test;

/**
 * @group unit
 * @group active
 */
class ActiveRecordTest extends \Amiss\Test\Helper\TestCase
{
    public function setUp()
    {
        parent::setUp();
        $this->deps = Test\Factory::managerActiveDemo();
        $this->manager = $this->deps->manager;
    }

    public function tearDown()
    {
        $this->manager = null;
        $this->deps = null;
        parent::tearDown();
    }
    
    /**
     * @covers Amiss\Sql\ActiveRecord::getMeta
     */
    public function testGetMeta()
    {
        \Amiss\Sql\ActiveRecord::setManager($this->manager);
        $meta = TestActiveRecord1::getMeta();
        $this->assertInstanceOf('Amiss\Meta', $meta);
        $this->assertEquals(__NAMESPACE__.'\TestActiveRecord1', $meta->class);
        
        // ensure the instsance is cached
        $this->assertTrue($meta === TestActiveRecord1::getMeta());
    }
        
    /**
     * @covers Amiss\Sql\ActiveRecord::getManager
     * @covers Amiss\Sql\ActiveRecord::setManager
     */
    public function testMultiConnection()
    {
        \Amiss\Sql\ActiveRecord::setManager($this->manager);
        $manager2 = clone $this->manager;
        $this->assertFalse($this->manager === $manager2);
        OtherConnBase::setManager($manager2);
        
        $c1 = TestOtherConnRecord1::getManager();
        $c2 = TestOtherConnRecord2::getManager();
        $this->assertTrue($c1 === $c2);
        
        $c3 = TestActiveRecord1::getManager();
        $this->assertFalse($c1 === $c3); 
    }
    
    /**
     * @covers Amiss\Sql\ActiveRecord::__callStatic
     */
    public function testGetForwarded()
    {
        $manager = $this->getMock('Amiss\Sql\Manager', array('get'), array($this->deps->connector, $this->deps->mapper));
        $manager->expects($this->once())->method('get')->with(
            $this->equalTo(__NAMESPACE__.'\TestActiveRecord1'), 
            $this->equalTo('pants=?'), 
            $this->equalTo(1)
        );
        \Amiss\Sql\ActiveRecord::setManager($manager);
        $tar = new TestActiveRecord1;
        TestActiveRecord1::get('pants=?', 1);
    }
    
    /**
     * @covers Amiss\Sql\ActiveRecord::__callStatic
     */
    public function testGetById()
    {
        $manager = $this->getMock('Amiss\Sql\Manager', array('getById'), array($this->deps->connector, $this->deps->mapper));
        \Amiss\Sql\ActiveRecord::setManager($manager);
        
        $manager->expects($this->once())->method('getById')->with(
            $this->equalTo(__NAMESPACE__.'\TestActiveRecord1'), 
            $this->equalTo(1)
        );
        TestActiveRecord1::getById(1);
    }
    
    /**
     * @covers Amiss\Sql\ActiveRecord::__callStatic
     */
    public function testGetRelated()
    {
        $manager = $this->getMock('Amiss\Sql\Manager', array('getRelated'), array($this->deps->connector, $this->deps->mapper));
        \Amiss\Sql\ActiveRecord::setManager($manager);
        
        $manager->expects($this->once())->method('getRelated')->with(
            $this->isInstanceOf(__NAMESPACE__.'\TestRelatedChild'),
            $this->equalTo('parent')
        )->will($this->returnValue(999));
        
        $child = new TestRelatedChild;
        $child->childId = 6;
        $child->parentId = 1;
        $result = $child->getRelated('parent');
        $this->assertEquals(999, $result);
    }

    /**
     * @covers Amiss\Sql\ActiveRecord::__callStatic
     */
    public function testAssignRelatedStatic()
    {
        $manager = $this->getMock('Amiss\Sql\Manager', array('getRelated'), array($this->deps->connector, $this->deps->mapper));
        $manager->relators = \Amiss\Sql\Factory::createRelators();
        \Amiss\Sql\ActiveRecord::setManager($manager);

        $manager->expects($this->once())->method('getRelated')->with(
            $this->containsOnlyInstancesOf(__NAMESPACE__.'\TestRelatedChild'),
            $this->equalTo('parent'),
            $this->equalTo(['stack'=>[]])
        )->will($this->returnValue([999]));

        $child = new TestRelatedChild;
        $child->childId = 6;
        $child->parentId = 1;
        TestRelatedChild::assignRelated($child, 'parent');
        $this->assertEquals(999, $child->parent);
    }

    /**
     * @covers Amiss\Sql\ActiveRecord::__callStatic
     */
    public function testAssignRelatedStaticArray()
    {
        $manager = $this->getMock('Amiss\Sql\Manager', array('getRelated'), array($this->deps->connector, $this->deps->mapper));
        $manager->relators = \Amiss\Sql\Factory::createRelators();
        \Amiss\Sql\ActiveRecord::setManager($manager);

        $child1 = new TestRelatedChild;
        $child1->childId = 6;
        $child1->parentId = 1;

        $child2 = new TestRelatedChild;
        $child2->childId = 7;
        $child2->parentId = 2;

        $input = [$child1, $child2];
        $manager->expects($this->once())->method('getRelated')->with(
            $this->equalTo($input),
            $this->equalTo('parent')
        )->will($this->returnValue([999, 777]));

        TestRelatedChild::assignRelated($input, 'parent');
        $this->assertEquals(999, $child1->parent);
        $this->assertEquals(777, $child2->parent);
    }
    
    /**
     * If a record has not been loaded from the database and the class doesn't
     * define fields, undefined properties should throw
     * 
     * @covers Amiss\Sql\ActiveRecord::__get
     * @expectedException BadMethodCallException
     */
    public function testGetUnknownPropertyWhenFieldsUndefinedOnNewObjectReturnsNull()
    {
        TestActiveRecord1::setManager($this->manager);
        $ar = new TestActiveRecord1();
        $a = $ar->thisPropertyShouldNeverExist;
    }
    
    /**
     * If the class defines its fields, undefined properties should always throw. 
     * 
     * @covers Amiss\Sql\ActiveRecord::__get
     * @expectedException BadMethodCallException
     */
    public function testGetUnknownPropertyWhenFieldsDefinedThrowsException()
    {
        TestActiveRecord1::setManager($this->manager);
        $ar = new TestActiveRecord1();
        $value = $ar->thisPropertyShouldNeverExist;
    }
    
    /**
     * Even if the class doesn't define its fields, undefined properties should throw
     * if the record has been loaded from the database as we can expect it is fully
     * populated.
     * 
     * @expectedException BadMethodCallException
     */
    public function testGetUnknownPropertyWhenFieldsUndefinedAfterRetrievingFromDatabaseThrowsException()
    {
        TestActiveRecord1::setManager($this->manager);
        $this->deps->connector->query("CREATE TABLE table_1(fooBar STRING);");
        $this->deps->connector->query("INSERT INTO table_1(fooBar) VALUES(123)");
        
        $ar = TestActiveRecord1::get('fooBar=123');
        $value = $ar->thisPropertyShouldNeverExist;
    }
    
    public function testUpdateTable()
    {
        $manager = $this->getMock(
            'Amiss\Sql\Manager', 
            array('updateTable'), 
            array($this->deps->connector, $this->deps->mapper), 
            'PHPUnitGotcha_RecordTest_'.__FUNCTION__
        );
        $manager->expects($this->once())->method('updateTable')->with(
            $this->equalTo($this->deps->mapper->getMeta(__NAMESPACE__.'\TestActiveRecord1')),
            $this->equalTo(array('pants'=>1)),
            $this->equalTo(1)
        );
        TestActiveRecord1::setManager($manager);
        TestActiveRecord1::updateTable(array('pants'=>1), '1');
    }

    public function testInsertTable()
    {
        $manager = $this->getMock(
            'Amiss\Sql\Manager', 
            array('insertTable'), 
            array($this->deps->connector, $this->deps->mapper), 
            'PHPUnitGotcha_RecordTest_'.__FUNCTION__
        );
        $manager->expects($this->once())->method('insertTable')->with(
            $this->equalTo($this->deps->mapper->getMeta(__NAMESPACE__.'\TestActiveRecord1')),
            $this->equalTo(array('pants'=>1))
        );
        TestActiveRecord1::setManager($manager);
        TestActiveRecord1::insertTable(array('pants'=>1));
    }
}

/**
 * :amiss = {"table": "table_1"};
 */
class TestActiveRecord1 extends \Amiss\Sql\ActiveRecord
{
    /** :amiss = {"field": {"primary": true}}; */
    public $fooBar;
}

/**
 * :amiss = {"table": "table_2"};
 */
class TestActiveRecord2 extends \Amiss\Sql\ActiveRecord
{
    /** :amiss = {"field": {"primary": true}}; */
    public $testActiveRecord2Id;
}

/** :amiss = true; */
class TestActiveRecord3 extends \Amiss\Sql\ActiveRecord
{
    /** :amiss = {"field": {"primary": true}}; */
    public $testActiveRecord3Id;
}

/** :amiss = true; */
abstract class OtherConnBase extends \Amiss\Sql\ActiveRecord {}

/** :amiss = true; */
class TestOtherConnRecord1 extends OtherConnBase {}

/** :amiss = true; */
class TestOtherConnRecord2 extends OtherConnBase {}

/** :amiss = true; */
class TestRelatedParent extends \Amiss\Sql\ActiveRecord
{
    /** :amiss = {"field": {"primary": true}}; */
    public $parentId;
    
    /**
     * :amiss = {"has": {"type": "many", "of": "Amiss\\Test\\Unit\\TestRelatedChild"}};
     */
    public $children;
}

/** :amiss = true; */
class TestRelatedChild extends \Amiss\Sql\ActiveRecord
{
    /** :amiss = {"field": {"primary": true}}; */
    public $childId;
    
    /** :amiss = {"field": {"index": true}}; */
    public $parentId;
    
    /**
     * :amiss = {"has": {
     *     "type": "one",
     *     "of"  : "Amiss\\Test\\Unit\\TestRelatedParent",
     *     "from": "parentId"
     * }};
     */
    public $parent;
}
