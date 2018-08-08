<?php
namespace Telltale\Tests;

use Telltale\DataMapper\GameConfigurator;
use Telltale\Model\GameConfigurator as GameConfiguratorModel;

use Aws\Result as AwsResult;

class GameConfiguratorTest extends AbstractDBTest {

  const USER_ID = 510;
  const GAME_NAME = 'testgame';
  const NODE_ID = 'phpunit_node_guid';

  private $gameConfigurator;

  public function __construct() {
    parent::__construct();

    $this->setupTableSchema('game_configurator', "
      `user_id` int(11) NOT NULL,
      `game` varchar(34) NOT NULL DEFAULT '',
      `node_guid` varchar(40) NOT NULL DEFAULT '',
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY `user_choice` (`user_id`,`game`,`node_guid`),
      KEY `user_id` (`user_id`),
      KEY `game` (`game`)
    ");

    $this->gameConfigurator = new GameConfigurator($this->getPDO(), $this->getCache());
  }

  protected function getDataSet() {
    return $this->createArrayDataSet([
      'game_configurator' => [
        [
          'user_id' => self::USER_ID,
          'game' => self::GAME_NAME,
          'node_guid' => self::NODE_ID
        ],
        [
          'user_id' => self::USER_ID,
          'game' => self::GAME_NAME,
          'node_guid' => self::NODE_ID . '2'
        ],
        [
          'user_id' => self::USER_ID,
          'game' => self::GAME_NAME . '2',
          'node_guid' => self::NODE_ID
        ],
        [
          'user_id' => self::USER_ID + 1,
          'game' => self::GAME_NAME,
          'node_guid' => self::NODE_ID
        ],
      ]
    ]);
  }

  private function getFullyPopulatedModel() {
    $model = new GameConfiguratorModel();
    $model->setUserId(self::USER_ID);
    $model->setGame(self::GAME_NAME);
    $model->setNodeGuid(self::NODE_ID);
    return $model;
  }

  public function testFindExact() {
    $model = $this->getFullyPopulatedModel();

    // Find a single GUID for user's specific game
    $result = $this->gameConfigurator->find($model);

    $this->assertContainsOnlyInstancesOf(GameConfiguratorModel::class, $result);
    $this->assertCount(1, $result); // out of 4 in dataset

    $this->assertEquals(self::USER_ID, $result[0]->getUserId());
    $this->assertEquals(self::GAME_NAME, $result[0]->getGame());
    $this->assertEquals(self::NODE_ID, $result[0]->getNodeGuid());
    $this->assertEquals(self::NODE_ID, $result[0]);
    $this->assertNotEmpty($result[0]->getCreatedAt());
    $this->assertEmpty($result[0]->getUpdatedAt());

    $this->assertEquals([
      'game' => self::GAME_NAME,
      'node_guid' => self::NODE_ID,
      'created_at' => $result[0]->getCreatedAt(),
      'updated_at' => '',
    ], $result[0]->jsonSerialize());
  }

  public function testFindUserNodes() {

    // This user has multiple GUIDS stored across different games
    $model = new GameConfiguratorModel();
    $model->setUserId(self::USER_ID);

    $result = $this->gameConfigurator->find($model);

    $this->assertContainsOnlyInstancesOf(GameConfiguratorModel::class, $result);
    $this->assertCount(3, $result); // out of 4 in dataset

    $this->assertEquals(self::USER_ID, $result[0]->getUserId());
    $this->assertEquals(self::USER_ID, $result[1]->getUserId());
    $this->assertEquals(self::USER_ID, $result[2]->getUserId());

    $this->assertEquals(self::USER_ID, $result[0]->getUserId());
    $this->assertEquals(self::GAME_NAME, $result[0]->getGame());
    $this->assertEquals(self::NODE_ID, $result[0]->getNodeGuid());
    $this->assertEquals(self::NODE_ID, $result[0]);

    $this->assertNotEmpty($result[1]);
    $this->assertNotEmpty($result[2]);
  }

  public function testFindUserGameNodes() {

    // This user has multiple GUIDs stored for this game
    $model = new GameConfiguratorModel();
    $model->setUserId(self::USER_ID);
    $model->setGame(self::GAME_NAME);

    $result = $this->gameConfigurator->find($model);

    $this->assertContainsOnlyInstancesOf(GameConfiguratorModel::class, $result);
    $this->assertCount(2, $result); // out of 4 in dataset

    $this->assertEquals(self::USER_ID, $result[0]->getUserId());
    $this->assertEquals(self::USER_ID, $result[1]->getUserId());

    $this->assertEquals(self::USER_ID, $result[0]->getUserId());
    $this->assertEquals(self::GAME_NAME, $result[0]->getGame());
    $this->assertEquals(self::NODE_ID, $result[0]->getNodeGuid());
    $this->assertEquals(self::NODE_ID, $result[0]);

    $this->assertNotEquals($result[0], $result[1]);
    $this->assertNotEmpty($result[1]);
  }

  public function testExists() {
    $model = $this->getFullyPopulatedModel();

    $result = $this->gameConfigurator->exists($model);
    $this->assertTrue($result);
  }

  public function testExists2() {
    $model = new GameConfiguratorModel();
    $model->setUserId(self::USER_ID);
    $result = $this->gameConfigurator->exists($model);
    $this->assertTrue($result);
  }

  public function testExists3() {
    $model = new GameConfiguratorModel();
    $model->setUserId(self::USER_ID - 1);
    $result = $this->gameConfigurator->exists($model);
    $this->assertFalse($result);
  }

  public function testAdd() {
    $model = $this->getFullyPopulatedModel();
    $model->setNodeGuid('{' . uniqid() . '}');

    // Not already in the database
    $result1 = $this->gameConfigurator->exists($model);
    $this->assertFalse($result1);

    $this->gameConfigurator->add($model);

    // Now exists in the database
    $result2 = $this->gameConfigurator->exists($model);
    $this->assertTrue($result2);

    // Found result matches what was added
    $result3 = $this->gameConfigurator->find($model);
    $this->assertContainsOnlyInstancesOf(GameConfiguratorModel::class, $result3);
    $this->assertCount(1, $result3);
    $this->assertEquals(self::USER_ID, $result3[0]->getUserId());
    $this->assertEquals(self::GAME_NAME, $result3[0]->getGame());
    $this->assertEquals($model->getNodeGuid(), $result3[0]->getNodeGuid());
  }

  public function testAddDuplicate() {
    $model = $this->getFullyPopulatedModel();

    $result1 = $this->gameConfigurator->find($model);
    $this->assertCount(1, $result1);
    $this->assertEmpty($result1[0]->getUpdatedAt());

    $this->gameConfigurator->add($model);

    $result2 = $this->gameConfigurator->find($model);
    $this->assertCount(1, $result2);
    $this->assertNotEmpty($result2[0]->getUpdatedAt());

    $this->assertEquals($result1[0]->getNodeGuid(), $result2[0]->getNodeGuid());
  }

  /**
    * Expect Exception for missing fields
    *
    * @expectedException        UnexpectedValueException
    * @expectedExceptionMessage Node GUID is required
    */
  public function testAddMissingGUID() {
    $model = new GameConfiguratorModel();
    $model->setUserId(self::USER_ID);
    $model->setGame(self::GAME_NAME);
    $this->gameConfigurator->add($model);
  }

  /**
    * Expect Exception for missing fields
    *
    * @expectedException        UnexpectedValueException
    * @expectedExceptionMessage Game is required
    */
  public function testAddMissingGame() {
    $model = new GameConfiguratorModel();
    $model->setUserId(self::USER_ID);
    $this->gameConfigurator->add($model);
  }

  /**
    * Expect Exception for missing fields
    *
    * @expectedException        UnexpectedValueException
    * @expectedExceptionMessage UserID is required
    */
  public function testAddEmpty() {
    $model = new GameConfiguratorModel();
    $this->gameConfigurator->add($model);
  }

  public function testRemove() {
    $model = new GameConfiguratorModel();
    $model->setUserId(self::USER_ID);
    $model->setGame(self::GAME_NAME);

    // Record exists
    $result1 = $this->gameConfigurator->exists($model);
    $this->assertTrue($result1);

    $this->gameConfigurator->remove($model);

    // No longer exists
    $result2 = $this->gameConfigurator->exists($model);
    $this->assertFalse($result2);

    // And can't be found
    $result3 = $this->gameConfigurator->find($model);
    $this->assertEmpty($result3);

    // User still has records not for this game
    $model2 = new GameConfiguratorModel();
    $model2->setUserId(self::USER_ID);
    $result4 = $this->gameConfigurator->exists($model2);
    $this->assertTrue($result4);

    // And now they don't
    $this->gameConfigurator->remove($model2);
    $result5 = $this->gameConfigurator->exists($model2);
    $this->assertFalse($result5);
  }

  public function testAddMultiple() {
    $model = $this->getFullyPopulatedModel();
    $model->setUserID(self::USER_ID + 100);

    $result1 = $this->gameConfigurator->exists($model);
    $this->assertFalse($result1);

    // Duplicates should be ignored
    $itemsToInsert = [$model, $model];
    for ($x = 0; $x <= 100; $x++) {
      $itemToInsert = clone $model;
      $itemToInsert->setNodeGuid('{' . uniqid() . '}');
      $itemsToInsert[] = $itemToInsert;
    }

    $this->gameConfigurator->addMultiple($itemsToInsert);

    // Model now exists
    $result2 = $this->gameConfigurator->exists($model);
    $this->assertTrue($result2);

    // Find a random item
    $toFind = $itemsToInsert[array_rand($itemsToInsert, 1)];
    $result3 = $this->gameConfigurator->find($toFind);
    $this->assertContainsOnlyInstancesOf(GameConfiguratorModel::class, $result3);
    $this->assertCount(1, $result3);
    $this->assertEquals($model->getUserId(), $result3[0]->getUserId());
    $this->assertEquals($model->getGame(), $result3[0]->getGame());
    $this->assertEquals($toFind->getNodeGuid(), $result3[0]->getNodeGuid());
  }

   /**
    * Expect Exception for missing fields
    *
    * @expectedException        UnexpectedValueException
    * @expectedExceptionMessage UserID is required
    */
  public function testAddMultipleInvalids() {
    $model = $this->getFullyPopulatedModel();
    $model2 = new GameConfiguratorModel();
    $this->gameConfigurator->addMultiple([$model, $model2]);
  }

  /**
    * Expect Exception for empty
    *
    * @expectedException        PDOException
    * @expectedExceptionMessage No items to add
    */
  public function testAddMultipleEmpty() {
    $this->gameConfigurator->addMultiple([]);
  }



}