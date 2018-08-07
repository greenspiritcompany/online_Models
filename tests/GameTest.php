<?php
namespace Telltale\Tests;

use Telltale\Model\Game as GameModel;

class GameTest extends AbstractDBTest {

  const GAME1 = 'game1';
  const GAME1_NAME = 'First Game';

  const GAME2 = 'game2';
  const GAME2_NAME = 'Second Game';

  private $game;

  public function __construct() {
    parent::__construct();

    $this->setupTableSchema('games', "
      `id` smallint(6) NOT NULL,
      `game` varchar(16) DEFAULT NULL,
      `display_name` varchar(34) NOT NULL DEFAULT '',
      `active` tinyint(1) DEFAULT NULL,
      `cloudsync` tinyint(1) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`)
    ");

    $this->game = new \Telltale\DataMapper\Game($this->getPDO(), $this->getCache());
  }

  protected function getDataSet() {
    return $this->createArrayDataSet([
      'games' => [
        [
          'id' => 1,
          'game' => self::GAME1,
          'display_name' => self::GAME1_NAME,
          'active' => 1,
          'cloudsync' => 1
        ],
        [
          'id' => 2,
          'game' => self::GAME2,
          'display_name' => self::GAME2_NAME,
          'active' => 1,
          'cloudsync' => 1
        ],
        [
          'id' => 3,
          'game' => 'game3',
          'display_name' => 'Inactive Game',
          'active' => 0,
          'cloudsync' => 1
        ]
      ]
    ]);
  }

  public function testExists() {
    $gameQuery = new GameModel();
    $gameQuery->setGame(self::GAME1);
    $result = $this->game->exists($gameQuery);

    $this->assertTrue($result);
  }

  public function testNotExists() {
    $gameQuery = new GameModel();
    $gameQuery->setGame(uniqid());
    $gameQuery->setActive(true);
    $result = $this->game->exists($gameQuery);

    $this->assertFalse($result);
  }

  public function testNotExistsAsInactive() {
    $gameQuery = new GameModel();
    $gameQuery->setGame(self::GAME1);
    $gameQuery->setActive(false);
    $result = $this->game->exists($gameQuery);

    $this->assertFalse($result);
  }

  /**
   * Test should only return game1
   *
   * @return void
   */
  public function testFind() {

    $gameQuery = new GameModel();
    $gameQuery->setGame(self::GAME1);
    $result = $this->game->find($gameQuery);

    $this->assertNotFalse($result);
    $this->assertContainsOnlyInstancesOf(GameModel::class, $result);
    $this->assertCount(1, $result); // only one result

    $this->assertEquals(1, $result[0]->getId());
    $this->assertEquals(self::GAME1, $result[0]->getGame());
    $this->assertEquals(self::GAME1_NAME, $result[0]->getDisplayName());
    $this->assertTrue($result[0]->getActive());
    $this->assertTrue($result[0]->getCloudsync());
    $this->assertEquals([
      'key' => self::GAME1,
      'name' => self::GAME1_NAME,
      'active' => true,
      'cloudsync' => true,
    ], $result[0]->jsonSerialize());

  }

  /**
   * Expect an empty array
   *
   */
  public function testFindNoResult() {
    $gameQuery = new GameModel();
    $gameQuery->setId(4);
    $gameQuery->setGame('unknown');
    $gameQuery->setActive(false);
    $gameQuery->setCloudSync(false);

    $result = $this->game->find($gameQuery);

    $this->assertNotFalse($result);
    $this->assertCount(0, $result);
  }

  /**
   * Expect an empty array
   *
   */
  public function testFindNoResult2() {
    $gameQuery = new GameModel();
    $result = $this->game->find($gameQuery);
    $this->assertNotFalse($result);
    $this->assertCount(0, $result);
  }

}