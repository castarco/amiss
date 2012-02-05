<?php

namespace Amiss\Test\Acceptance;

class SelectTest extends \SqliteDataTestCase
{
	public function testSingleObjectPositionalParametersShorthand()
	{
		$a = $this->manager->get('Artist', 'slug=?', 'limozeen');
		$this->assertTrue($a instanceof \Amiss\Demo\Artist);
		$this->assertEquals('Limozeen', $a->name);
	}
	
	public function testSingleObjectNamedParametersShorthand()
	{
		$a = $this->manager->get('Artist', 'slug=:slug', array(':slug'=>'limozeen'));
		$this->assertTrue($a instanceof \Amiss\Demo\Artist);
		$this->assertEquals('Limozeen', $a->name);
	}
	
	public function testSingleObjectNamedParametersLongForm()
	{
		$a = $this->manager->get(
			'Artist', 
			array(
				'where'=>'slug=:slug', 
				'params'=>array(':slug'=>'limozeen')
			)
		);
		$this->assertTrue($a instanceof \Amiss\Demo\Artist);
		$this->assertEquals('Limozeen', $a->name);
	}
	
	public function testSingleObjectUsingCriteria()
	{
		$criteria = new \Amiss\Criteria\Select;
		$criteria->where = 'slug=:slug';
		$criteria->params[':slug'] = 'limozeen';
		
		$a = $this->manager->get('Artist', $criteria);
		
		$this->assertTrue($a instanceof \Amiss\Demo\Artist);
		$this->assertEquals('Limozeen', $a->name);
	}
	
	public function testSingleObjectUsingRowBuilder()
	{
		$a = $this->manager->get('Venue', 'venueId=?', 1);
		
		$this->assertTrue($a instanceof \Amiss\Demo\Venue);
		$this->assertEquals('Strong Badia', $a->venueName);
		$this->assertSame(1, $a->venueId);
		$this->assertEquals('strong-badia', $a->venueSlug);
	}
	
	public function testList()
	{
		$artists = $this->manager->getList('Artist');
		$this->assertTrue(is_array($artists));
		$this->assertTrue(current($artists) instanceof \Amiss\Demo\Artist);
		$this->assertEquals('limozeen', current($artists)->slug);
		next($artists);
		$this->assertEquals('taranchula', current($artists)->slug);
	}
	
	public function testPagedListFirstPage()
	{
		$artists = $this->manager->getList('Artist', array('page'=>array(1, 3)));
		$this->assertEquals(3, count($artists));
		
		$this->assertTrue(current($artists) instanceof \Amiss\Demo\Artist);
		$this->assertEquals('limozeen', current($artists)->slug);
		next($artists);
		$this->assertEquals('taranchula', current($artists)->slug);
	}

	public function testPagedListSecondPage()
	{
		$artists = $this->manager->getList('Artist', array('page'=>array(2, 3)));
		$this->assertEquals(3, count($artists));
		
		$this->assertTrue(current($artists) instanceof \Amiss\Demo\Artist);
		$this->assertEquals('george-carlin', current($artists)->slug);
		next($artists);
		$this->assertEquals('david-cross', current($artists)->slug);
	}

	public function testListLimit()
	{
		$artists = $this->manager->getList('Artist', array('limit'=>3));
		$this->assertEquals(3, count($artists));
		
		$this->assertTrue(current($artists) instanceof \Amiss\Demo\Artist);
		$this->assertEquals('limozeen', current($artists)->slug);
		next($artists);
		$this->assertEquals('taranchula', current($artists)->slug);
	}
	
	public function testListOffset()
	{
		$artists = $this->manager->getList('Artist', array('limit'=>3, 'offset'=>3));
		$this->assertEquals(3, count($artists));
		
		$this->assertTrue(current($artists) instanceof \Amiss\Demo\Artist);
		$this->assertEquals('george-carlin', current($artists)->slug);
		next($artists);
		$this->assertEquals('david-cross', current($artists)->slug);
	}
	
	public function testOrderByManualImpliedAsc()
	{
		$artists = $this->manager->getList('Artist', array('order'=>'name'));
		$this->assertTrue(is_array($artists));
		$this->assertEquals('bad-news', current($artists)->slug);
		foreach ($artists as $a); // get the last element regardless of if the array is keyed or indexed
		$this->assertEquals('the-sonic-manipulator', $a->slug);
	}
	
	public function testOrderByManualDesc()
	{
		$artists = $this->manager->getList('Artist', array('order'=>'name desc'));
		$this->assertTrue(is_array($artists));
		$this->assertEquals('the-sonic-manipulator', current($artists)->slug);
		foreach ($artists as $a); // get the last element regardless of if the array is keyed or indexed
		$this->assertEquals('bad-news', $a->slug);
	}
	
	public function testOrderByManualMulti()
	{
		$eventArtists = $this->manager->getList('EventArtist', array(
			'limit'=>3, 
			'where'=>'eventId=1',
			'order'=>'priority, sequence desc',
		));
		
		$this->assertTrue(is_array($eventArtists));
		
		$result = array();
		foreach ($eventArtists as $ea) {
			$result[] = array($ea->priority, $ea->sequence);
		}
		
		$this->assertEquals(array(
			array(1, 2),
			array(1, 1),
			array(2, 1),
		), $result);
	}
	
	public function testOrderBySingleLongForm()
	{
		$artists = $this->manager->getList('Artist', array('order'=>array('name')));
		$this->assertEquals('bad-news', current($artists)->slug);
		$this->assertTrue(is_array($artists));
		foreach ($artists as $a); // get the last element regardless of if the array is keyed or indexed
		$this->assertEquals('the-sonic-manipulator', $a->slug);
	}

	public function testOrderBySingleLongFormDescending()
	{
		$artists = $this->manager->getList('Artist', array('order'=>array('name'=>'desc')));
		$this->assertTrue(is_array($artists));
		
		$this->assertEquals('the-sonic-manipulator', current($artists)->slug);
		foreach ($artists as $a); // get the last element regardless of if the array is keyed or indexed
		$this->assertEquals('bad-news', $a->slug);
	}
	
	public function testSelectSingleObjectFromMultipleResultWhenLimitIsOne()
	{
		$artist = $this->manager->get('Artist', array('order'=>array('name'=>'desc'), 'limit'=>1));
		$this->assertTrue($artist instanceof \Amiss\Demo\Artist);
		
		$this->assertEquals('the-sonic-manipulator', $artist->slug);
	}
	
	/**
	 * @expectedException Amiss\Exception
	 */
	public function testSelectSingleObjectFailsWhenResultReturnsMany()
	{
		$artist = $this->manager->get('Artist', array('order'=>array('name'=>'desc')));
	}
	
	/**
	 * @expectedException Amiss\Exception
	 */
	public function testSelectSingleObjectFailsWithoutIssuingQueryWhenLimitSetButNotOne()
	{
		$this->manager->connector = $this->getMock('Amiss\Connector', array('prepare'), array(''));
		$this->manager->connector->expects($this->never())->method('prepare');
		$artist = $this->manager->get('Artist', array('limit'=>2));
	}
	
	public function testOrderByMulti()
	{
		$eventArtists = $this->manager->getList('EventArtist', array(
			'limit'=>3, 
			'where'=>'eventId=1',
			'order'=>array('priority', 'sequence'=>'desc')
		));
		
		$this->assertTrue(is_array($eventArtists));
		
		$result = array();
		foreach ($eventArtists as $ea) {
			$result[] = array($ea->priority, $ea->sequence);
		}
		
		$this->assertEquals(array(
			array(1, 2),
			array(1, 1),
			array(2, 1),
		), $result);
	}
	
	/*
	public function testWhereClauseBuiltFromArray()
	{
		// TODO: this won't work at the moment as it can't tell the difference between the 'where' array
		// and a criteria array 
		$artists = $this->manager->getList('Artist', array('artistType'=>2));
		$this->assertEquals(2, count($artists));
	}
	*/
}
