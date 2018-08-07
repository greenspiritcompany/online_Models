<?php
namespace Telltale\Tests;

use Telltale\DataMapper\ImportantEvents;
use Telltale\Model\ImportantEvents as ImportantEventsModel;

class ImportantEventsTest extends AbstractDBTest {

  const EVENTS_DATASET = [
    [
      'purpose' => 'tracker',
      'event_type' => 'Begin Episode',
      'int_event_type' => '2500895281066692154',
      'event_value' => '1',
      'b64_event_value' => '1',
      'cohort_name' => 'phpunit_Start_All',
      'created_at' => '2016-07-12 17:39:53',
    ],
    [
      'purpose' => 'achievement',
      'event_type' => 'Achievement',
      'int_event_type' => '11184180007009063027',
      'event_value' => NULL,
      'b64_event_value' => NULL,
      'cohort_name' => NULL,
      'created_at' => '2017-07-13 19:59:24',
    ],
    [
      'purpose' => 'choice',
      'event_type' => 'Executing Dialog Node',
      'int_event_type' => '7086542257609849777',
      'event_value' => '{FDB4B670-34B4-48DA-BFFA-B388BC96DF8F}',
      'b64_event_value' => 'KDuaLKJPMdU=',
      'cohort_name' => NULL,
      'created_at' => '2017-07-18 21:13:40',
    ],
    [
      'purpose' => 'choice',
      'event_type' => 'Executing Dialog Node',
      'int_event_type' => '7086542257609849777',
      'event_value' => '{E6C30935-513B-43D4-87B3-1B68A14634E5}',
      'b64_event_value' => 'wCX9xt+bTQQ=',
      'cohort_name' => NULL,
      'created_at' => '2017-08-10 09:33:42',
    ],
    [
      'purpose' => 'choice',
      'event_type' => 'Executing Dialog Node',
      'int_event_type' => '7086542257609849777',
      'event_value' => '{4F69EA1C-B0C6-46A7-8EA3-7A9D1F84B312}',
      'b64_event_value' => 'tW1YGtUvNaw=',
      'cohort_name' => NULL,
      'created_at' => '2017-08-10 09:33:42',
    ],
    [ // Missing event value
      'purpose' => 'choice',
      'event_type' => 'Executing Dialog Node',
      'int_event_type' => '7086542257609849777',
      'event_value' => NULL,
      'b64_event_value' => 'tW1YGtUvNaw=',
      'cohort_name' => NULL,
      'created_at' => '2017-08-10 09:33:42',
    ],
    [ // Missing b64_event_value
      'purpose' => 'choice',
      'event_type' => 'Executing Dialog Node',
      'int_event_type' => '7086542257609849777',
      'event_value' => '{4F69EA1C-B0C6-46A7-8EA3-7A9D1F84B312}',
      'b64_event_value' => NULL,
      'cohort_name' => NULL,
      'created_at' => '2017-08-10 09:33:42',
    ],
  ];

  private $importantEvents;

  public function __construct() {
    parent::__construct();

    $this->setupTableSchema('important_events', "
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `purpose` varchar(11) COLLATE utf8_unicode_ci DEFAULT NULL,
      `event_type` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
      `int_event_type` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
      `event_value` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
      `b64_event_value` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
      `cohort_name` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `unique_rec` (`purpose`,`event_type`,`int_event_type`,`event_value`,`b64_event_value`),
      KEY `event_value` (`event_value`)
    ");

    // Note: game varchar changed from 16 to 19 for this test
    $this->setupTableSchema('games', "
      `id` smallint(6) NOT NULL AUTO_INCREMENT,
      `game` varchar(19) DEFAULT NULL,
      `display_name` varchar(34) NOT NULL DEFAULT '',
      `active` tinyint(1) DEFAULT NULL,
      `cloudsync` tinyint(1) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`)
    ");

    $this->importantEvents = new ImportantEvents($this->getPDO(), $this->getCache());
  }

  protected function getDataSet() {
    return $this->createArrayDataSet([
      'important_events' => self::EVENTS_DATASET,
      'games' => [
        [
          //'id' => 1,
          // Game ID must be the same as the DB due to game.important_events
          'game' => DBUNIT_DATABASE_MYSQL_NAME,
          'display_name' => 'Unit Test Game',
          'active' => 1,
          'cloudsync' => 0
        ],
      ]
    ]);
  }

  public function testFetchAll() {
    $result = $this->importantEvents->fetchAll(DBUNIT_DATABASE_MYSQL_NAME);

    $this->assertContainsOnlyInstancesOf(ImportantEventsModel::class, $result);
    $this->assertInternalType('array', $result);
    $this->assertCount(7, $result); // out of 7 in dataset

    $this->assertEquals(self::EVENTS_DATASET, array_map(function($r) {
      return $r->jsonSerialize();
    }, $result));

    $this->assertEquals(self::EVENTS_DATASET[0]['purpose'], $result[0]->getPurpose());
    $this->assertEquals(self::EVENTS_DATASET[0]['event_type'], $result[0]->getEventType());
    $this->assertEquals(self::EVENTS_DATASET[0]['int_event_type'], $result[0]->getIntEventType());
    $this->assertEquals(self::EVENTS_DATASET[0]['event_value'], $result[0]->getEventValue());
    $this->assertEquals(self::EVENTS_DATASET[0]['b64_event_value'], $result[0]->getB64EventValue());
    $this->assertEquals(self::EVENTS_DATASET[0]['cohort_name'], $result[0]->getCohortName());
    $this->assertEquals(self::EVENTS_DATASET[0]['created_at'], $result[0]->getCreatedAt());
  }

  public function testFetchAllValidGameList() {
    $result = $this->importantEvents->fetchAll(DBUNIT_DATABASE_MYSQL_NAME, [DBUNIT_DATABASE_MYSQL_NAME]);

    $this->assertContainsOnlyInstancesOf(ImportantEventsModel::class, $result);
    $this->assertInternalType('array', $result);
    $this->assertCount(7, $result); // out of 7 in dataset
  }

  /**
   * Expect PDOException on invalid game
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage Invalid game
   */
  public function testFetchInvalidGame() {
    $result = $this->importantEvents->fetchAll('invalid');
    $this->assertCount(0, $result); // out of 7 in dataset
  }

  /**
   * Expect PDOException on invalid game
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage Invalid game
   */
  public function testFetchInvalidGameInList() {
    $result = $this->importantEvents->fetchAll('invalid', ['example']);
    $this->assertCount(0, $result); // out of 7 in dataset
  }

  public function testFetchSymbolDictionary() {
    $result = $this->importantEvents->fetchSymbolDictionary(DBUNIT_DATABASE_MYSQL_NAME);
    $this->assertInternalType('array', $result);
    $this->assertCount(3, $result);

   $this->assertEquals([
    '{FDB4B670-34B4-48DA-BFFA-B388BC96DF8F}' => 'KDuaLKJPMdU=',
    '{E6C30935-513B-43D4-87B3-1B68A14634E5}' => 'wCX9xt+bTQQ=',
    '{4F69EA1C-B0C6-46A7-8EA3-7A9D1F84B312}' => 'tW1YGtUvNaw=',
   ], $result);

  }

  public function testFetchSymbolDictionaryWhitelist() {
    $result = $this->importantEvents->fetchSymbolDictionary(DBUNIT_DATABASE_MYSQL_NAME, [DBUNIT_DATABASE_MYSQL_NAME]);
    $this->assertInternalType('array', $result);
    $this->assertCount(3, $result);

   $this->assertEquals([
    '{FDB4B670-34B4-48DA-BFFA-B388BC96DF8F}' => 'KDuaLKJPMdU=',
    '{E6C30935-513B-43D4-87B3-1B68A14634E5}' => 'wCX9xt+bTQQ=',
    '{4F69EA1C-B0C6-46A7-8EA3-7A9D1F84B312}' => 'tW1YGtUvNaw=',
   ], $result);

  }

  /**
   * Expect PDOException on invalid game
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage Invalid game
   */
  public function testFetchSymbolDictionaryInvalidGame() {
    $result = $this->importantEvents->fetchSymbolDictionary('game');
    $this->assertCount(0, $result);
  }

  /**
   * Expect PDOException on invalid game
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage Invalid game
   */
  public function testFetchSymbolDictionaryGameNotInWhiteList() {
    $result = $this->importantEvents->fetchSymbolDictionary('gameName', ['notGameName']);
    $this->assertCount(0, $result);
  }

}