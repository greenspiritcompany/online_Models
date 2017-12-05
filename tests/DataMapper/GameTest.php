<?php
namespace Telltale\Tests;

use Telltale\Model\Game as GameModel;

class GameTest extends AbstractDBTest {

  private $game;

  public function __construct() {

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
          'game' => 'game1',
          'display_name' => 'First Game',
          'active' => 1,
          'cloudsync' => 1
        ],
        [
          'id' => 2,
          'game' => 'game2',
          'display_name' => 'Second Game',
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

  public function testFetchAll() {

    $gameQuery = new GameModel();
    $gameQuery->setGame('game1');
    $result = $this->game->fetchAll($gameQuery);

    print_r($result);

    $this->assertNotFalse($result);
    $this->assertContainsOnlyInstancesOf(GameModel::class, $result);
    $this->assertCount(1, $result); // only one result
  }


}