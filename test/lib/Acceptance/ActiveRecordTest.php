<?php
namespace Amiss\Test\Acceptance;

use Amiss\Demo\Active;

/**
 * @group active
 * @group acceptance
 */
class ActiveRecordTest extends \Amiss\Test\Helper\TestCase
{
    public function setUp()
    {
        $this->deps = \Amiss\Test\Factory::managerActiveDemo();
    }

    public function tearDown()
    {
        $this->deps = null;
        parent::tearDown();
    }

    public function testGetById()
    {
        $obj = Active\ArtistRecord::getById(1);
        $this->assertTrue($obj instanceof Active\ArtistRecord);
        $this->assertEquals(1, $obj->artistId);
    }

    public function testGetByPositionalWhere()
    {
        $obj = Active\ArtistRecord::get('artistId=?', [1]);
        $this->assertTrue($obj instanceof Active\ArtistRecord);
        $this->assertEquals(1, $obj->artistId);
    }

    public function testGetByPositionalWhereMulti()
    {
        $obj = Active\ArtistRecord::get('artistId=? AND artistTypeId=?', [1, 1]);
        $this->assertTrue($obj instanceof Active\ArtistRecord);
        $this->assertEquals(1, $obj->artistId);
    }

    public function testGetByNamedWhere()
    {
        $obj = Active\ArtistRecord::get('artistId=:id', array(':id'=>1));
        $this->assertTrue($obj instanceof Active\ArtistRecord);
        $this->assertEquals(1, $obj->artistId);
    }
    
    public function testGetRelatedSingle()
    {
        $obj = Active\ArtistRecord::getById(1);
        $this->assertTrue($obj==true, "Couldn't retrieve object");

        $related = $obj->getRelated('type');

        $this->assertTrue($related instanceof Active\ArtistType);
        $this->assertEquals(1, $related->artistTypeId);
    }
    
    public function testGetRelatedWithLazyLoad()
    {
        $obj = Active\ArtistRecord::getById(1);
        $this->assertTrue($obj==true, "Couldn't retrieve object");
        
        $this->assertNull($this->getProtected($obj, 'type'));
        $type = $obj->getType();
        $this->assertTrue($this->getProtected($obj, 'type') instanceof Active\ArtistType);
    }

    public function testDeleteByPrimary()
    {
        $obj = Active\ArtistRecord::getById(1);
        $this->assertTrue($obj==true, "Couldn't retrieve object");

        $obj->delete();
        $this->assertEquals(0, $this->deps->manager->count(Active\ArtistRecord::class, 'artistId=1'));

        // sanity check: make sure we didn't delete everything!
        $this->assertGreaterThan(0, $this->deps->manager->count(Active\ArtistRecord::class));
    }

    public function testDeleteById()
    {
        $obj = Active\ArtistRecord::getById(1);
        $this->assertTrue($obj==true, "Couldn't retrieve object");
        Active\ArtistRecord::deleteById(1);

        $this->assertEquals(0, $this->deps->manager->count(Active\ArtistRecord::class, 'artistId=1'));

        // sanity check: make sure we didn't delete everything!
        $this->assertGreaterThan(0, $this->deps->manager->count(Active\ArtistRecord::class));
    }

    public function testDeleteTable()
    {
        $obj = Active\ArtistRecord::getById(1);
        $this->assertTrue($obj==true, "Couldn't retrieve object");
        Active\ArtistRecord::deleteTable('1=1');

        $this->assertEquals(0, $this->deps->manager->count(Active\ArtistRecord::class));
    }

    public function testUpdateTable()
    {
        $cnt = Active\ArtistRecord::count("{name}='flerb'");
        $this->assertEquals(0, $cnt);

        Active\ArtistRecord::updateTable([
            'set'   => ['name' => 'flerb'],
            'where' => '1=1',
        ]);

        $this->assertEquals(
            Active\ArtistRecord::count("{name}='flerb'"),
            Active\ArtistRecord::count()
        );
    }

    public function testInsertTable()
    {
        $this->assertEquals(0, Active\ArtistRecord::count("{name}='flerb'"));
        Active\ArtistRecord::insertTable([
            'name' => 'flerb',
            'slug' => 'flerb',
            'artistTypeId' => 1,
        ]);
        $this->assertEquals(1, Active\ArtistRecord::count("{name}='flerb'"));
    }

    public function testUpdateByPrimary()
    {
        $n = md5(uniqid('', true));
        $obj = Active\ArtistRecord::getById(1);
        $obj->name = $n;
        $obj->update();

        $obj = Active\ArtistRecord::getById(1);
        $this->assertEquals($n, $obj->name);
    }

    public function testInsert()
    {
        $n = md5(uniqid('', true));

        $obj = new Active\ArtistRecord;
        $this->assertNull($obj->artistId);
        $obj->artistTypeId = 1;
        $obj->name = $n;
        $obj->slug = $n;
        $obj->insert();

        $this->assertGreaterThan(0, $obj->artistId);
        $obj = Active\ArtistRecord::getById($obj->artistId);
        $this->assertEquals($obj->name, $n);
    }

    public function testSaveUpdate()
    {
        $n = md5(uniqid('', true));
        $obj = Active\ArtistRecord::getById(1);
        $obj->name = $n;
        $obj->save();

        $obj = Active\ArtistRecord::getById(1);
        $this->assertEquals($n, $obj->name);
    }

    public function testSaveInsert()
    {
        $n = md5(uniqid('', true));

        $obj = new Active\ArtistRecord;
        $this->assertNull($obj->artistId);
        $obj->artistTypeId = 1;
        $obj->name = $n;
        $obj->slug = $n;
        $obj->save();

        $this->assertGreaterThan(0, $obj->artistId);
        $obj = Active\ArtistRecord::getById($obj->artistId);
        $this->assertEquals($obj->name, $n);
    }

    public function testIndexBy()
    {
        $obj1 = new Active\ArtistRecord;
        $obj1->artistTypeId = 1;
        $obj1->name = 'a';
        $obj1->slug = 'a';

        $obj2 = new Active\ArtistRecord;
        $obj2->artistTypeId = 1;
        $obj2->name = 'b';
        $obj2->slug = 'b';

        $indexed = Active\ArtistRecord::indexBy([$obj1, $obj2], 'name');
        $expected = ['a'=>$obj1, 'b'=>$obj2];
        $this->assertEquals($expected, $indexed);
    }
}
