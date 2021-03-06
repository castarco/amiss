<?php
namespace Amiss\Test\Acceptance\AutoRelation;

use Amiss\Sql\TableBuilder;
use Amiss\Test;

class OneManyTest extends \Amiss\Test\Helper\TestCase
{
    private $manager;

    public function setUp()
    {
        $this->db = new \PDOK\Connector('sqlite::memory:');
        $this->mapper = $this->createDefaultMapper();
        $this->manager = new \Amiss\Sql\Manager($this->db, $this->mapper);
        $this->manager->relators = \Amiss\Sql\Factory::createRelators();
        foreach ($this->mapper->mappings as $class=>$meta) {
            TableBuilder::create($this->manager->connector, $this->mapper, $class);
        }
        $this->manager->connector->exec("INSERT INTO test_child(id, parentId) VALUES(1, 1)");
        $this->manager->connector->exec("INSERT INTO test_child(id, parentId) VALUES(2, 1)");
        $this->manager->connector->exec("INSERT INTO test_parent(id, grandParentId) VALUES(1, 1)");
        $this->manager->connector->exec("INSERT INTO test_grand_parent(id) VALUES(1)");

        $this->db->queries = 0;
    }

    public function createDefaultMapper()
    {
        return new \Amiss\Mapper\Arrays([
            TestChild::class=>[
                'primary'=>'id',
                'fields'=>[
                    'id'=>['type'=>'autoinc'],
                    'parentId'=>['type'=>'int'],
                ],
                'indexes'=>['parent'=>['fields'=>'parentId']],
                'relations'=>[
                    'parent'=>['one', 'of'=>TestParent::class, 'from'=>'parent'],
                ],
            ],
            TestParent::class=>[
                'primary'=>'id',
                'fields'=>[
                    'id'=>['type'=>'autoinc'],
                    'grandParentId'=>['type'=>'int'],
                ],
                'indexes'=>['grandParent'=>['fields'=>'grandParentId']],
                'relations'=>[
                    'children'=>['many', 'of'=>TestChild::class, 'to'=>'parent'],
                    'grandParent'=>['one', 'of'=>TestGrandParent::class, 'from'=>'grandParent'],
                ],
            ],
            TestGrandParent::class=>[
                'primary'=>'id',
                'fields'=>['id'=>['type'=>'autoinc']],
                'relations'=>[
                    'parents'=>['many', 'of'=>TestParent::class, 'to'=>'grandParent'],
                ],
            ],
        ]);
    }

    public function setAutoRelation($class, $relation, $inverse=null)
    {
        $meta = $this->mapper->getMeta($class);
        $meta->autoRelations[] = $relation;
        $relatedMeta = $this->mapper->getMeta($meta->relations[$relation]['of']);

        if ($inverse) {
            $relatedMeta->autoRelations[] = $inverse;
        }
    }

    public function testAutoOne()
    {
        $manager = $this->manager;
        $this->setAutoRelation(TestChild::class, 'parent');

        $child = $manager->getById(TestChild::class, 1);
        $this->assertTrue($child->parent instanceof TestParent);
    }

    public function testAutoOneWithInverseMany()
    {
        $manager = $this->manager;
        $this->setAutoRelation(TestChild::class, 'parent', 'children');

        $child = $manager->getById(TestChild::class, 1);
        $this->assertEquals(3, $this->db->queries);

        $this->assertTrue($child->parent instanceof TestParent);
        $this->assertCount(2, $child->parent->children);
    }

    public function testAutoOneDeep()
    {
        $manager = $this->manager;
        $this->setAutoRelation(TestChild::class, 'parent', 'children');
        $this->setAutoRelation(TestGrandParent::class, 'parents', 'grandParent');

        $child = $manager->getById(TestChild::class, 1);
        $this->assertEquals(4, $this->db->queries);

        $this->assertTrue($child->parent instanceof TestParent);
        $this->assertTrue($child->parent->grandParent instanceof TestGrandParent);
        $this->assertCount(2, $child->parent->children);
    }

    public function testAutoMany()
    {
        $manager = $this->manager;
        $this->setAutoRelation(TestChild::class, 'parent', 'children');
        $this->setAutoRelation(TestGrandParent::class, 'parents', 'grandParent');

        $parent = $manager->getById(TestParent::class, 1);
        $this->assertTrue($parent->children[0] instanceof TestChild);
    }

    public function testAutoManyDeep()
    {
        $manager = $this->manager;
        $this->setAutoRelation(TestChild::class, 'parent', 'children');
        $this->setAutoRelation(TestGrandParent::class, 'parents', 'grandParent');

        $parent = $manager->getById(TestParent::class, 1);
        $this->assertTrue($parent->children[0] instanceof TestChild);
        $this->assertTrue($parent->grandParent instanceof TestGrandParent);
        $this->assertTrue($parent->children[0]->parent instanceof TestParent);
    }

    public function testAutoOneQuery()
    {
        $manager = $this->manager;

        $child = $manager->getById(TestChild::class, 1, ['with'=>['parent']]);
        $this->assertTrue($child->parent instanceof TestParent);

        // alternate syntax
        $child = $manager->getById(TestChild::class, 1, ['with'=>'parent']);
        $this->assertTrue($child->parent instanceof TestParent);
    }

    public function testAutoManyQuery()
    {
        $manager = $this->manager;
        $parent = $manager->getById(TestParent::class, 1, ['with'=>'children']);
        $this->assertTrue($parent->children[0] instanceof TestChild);
    }

    public function testAutoQueryGetRelated()
    {
        $manager = $this->manager;
        $child = $manager->getById(TestChild::class, 1);
        $related = $manager->getRelated($child, 'parent', ['with'=>'grandParent']);
        $this->assertInstanceOf(TestGrandParent::class, $related->grandParent);
    }
}

class TestGrandParent extends \Amiss\Test\Helper\DummyClass {}
class TestParent extends \Amiss\Test\Helper\DummyClass {}
class TestChild extends \Amiss\Test\Helper\DummyClass {}

