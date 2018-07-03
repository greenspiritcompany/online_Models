<?php
namespace Telltale\Tests;

use Telltale\DataMapper\CloudSave;

use Telltale\Model\User as UserModel;
use Telltale\Model\Game as GameModel;

use Aws\Result as AwsResult;

class CloudSaveTest extends AbstractDBTest {

  const USER_ID = 210;
  const SERVICES_USER_ID = 200;
  const GAME_NAME = 'phpunitCloudsaveTest1';
  const GAME_NAME_MISMATCH = 'phpunitCloudsaveTest2';
  const GAME_NAME_XBONE = 'gamePlayedOnXbone';
  const GAME_NAME_PS3 = 'gamePlayedOnPS3';

  private $cloudSave;

  public function __construct() {
    parent::__construct();

    // Services account
    $this->setupTableSchema('account', "
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NOT NULL,
      `password` varchar(255) NOT NULL,
      `group_id` int(11) NOT NULL DEFAULT '1',
      `email` varchar(255) NOT NULL,
      `last_login` varchar(25) NOT NULL,
      `previous_login` varchar(25) NOT NULL DEFAULT '0',
      `login_hash` varchar(255) NOT NULL,
      `user_id` int(11) NOT NULL DEFAULT '0',
      `created_at` int(11) NOT NULL DEFAULT '0',
      `updated_at` int(11) NOT NULL DEFAULT '0',
      `is_anonymous` tinyint(1) NOT NULL DEFAULT '1',
      `web_customer_fk` int(11) DEFAULT NULL,
      `client_login_username` varchar(50) NOT NULL DEFAULT '',
      `xboxlive` varchar(65) DEFAULT NULL,
      `psn` varchar(65) DEFAULT NULL,
      `ttg_account_id` int(11) DEFAULT NULL,
      `skybound_insider` tinyint(4) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `i_emailusername` (`email`,`username`),
      KEY `i_usernameemail` (`username`,`email`),
      KEY `u_xbox_live` (`xboxlive`),
      KEY `u_psn` (`psn`),
      KEY `web_customer_fk_idx` (`web_customer_fk`),
      KEY `ttg_account_id_idx` (`ttg_account_id`)
    ");

    /**
     * Setup new cloudSave data mapper
     *
     * @param PDO $pdo
     * @param CacheItemPoolInterface $cache
     * @param [type] $logger
     */
    $this->cloudSave = new CloudSave($this->getPDO(), $this->getCache());

    // Use reflection to mock the AwsClient
    $reflection = new \ReflectionClass($this->cloudSave);

    $dynamoDBProperty = $reflection->getProperty('dynamoDB');
    $dynamoDBProperty->setAccessible(true);
    $dynamoDBProperty->setValue($this->cloudSave, new class() {
      // https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-dynamodb-2012-08-10.html#query
      public function query(array $dynamoDBQuery): AwsResult {

        if ($dynamoDBQuery['TableName'] !== 'PhpunitCloudSync') {
          throw new \Exception('dynamoDBQuery does not have expected table. Got: ' . $dynamoDBQuery['TableName']);
        }

        if ($dynamoDBQuery['Select'] == 'COUNT' && $dynamoDBQuery['Limit'] == 1) {

          $userPath = $dynamoDBQuery['KeyConditions']['userpath']['AttributeValueList'][0]['S'];

          $allowedPaths = [
            CloudSaveTest::SERVICES_USER_ID . '/' . CloudSaveTest::GAME_NAME . '/shared/cloud_save_slot_location',
            CloudSaveTest::SERVICES_USER_ID . '/' . CloudSaveTest::GAME_NAME_MISMATCH . '/shared/cloud_save_slot_location',
            CloudSaveTest::SERVICES_USER_ID . '/' . CloudSaveTest::GAME_NAME_XBONE . '/shared/cloud_save_slot_location',
            CloudSaveTest::SERVICES_USER_ID . '/' . CloudSaveTest::GAME_NAME_PS3 . '/shared/cloud_save_slot_location',
          ];

          if (in_array($userPath, $allowedPaths)) {
            return new AwsResult([
              'Count' => 1
            ]);
          }

          return new AwsResult([
            'Count' => 0
          ]);
        }

        return new AwsResult();
      }
    });

    $s3Property = $reflection->getProperty('s3');
    $s3Property->setAccessible(true);
    $s3Property->setValue($this->cloudSave, new class() {
      // https://docs.aws.amazon.com/aws-sdk-php/v3/api/api-s3-2006-03-01.html#listobjectsv2
      public function listObjectsV2(array $parameters): AwsResult {

        if ($parameters['Bucket'] !== 'cloud-stage.telltalegames.com') {
          throw new \Exception('Unexpected bucket name. Got: ' . $parameters['Bucket']);
        }

        if ($parameters['Prefix'] == CloudSaveTest::SERVICES_USER_ID . '/' . CloudSaveTest::GAME_NAME . '/') {
          return new AwsResult([
            'KeyCount' => 1,
            'CommonPrefixes' => [
              'Prefix' => CloudSaveTest::SERVICES_USER_ID . '/' . CloudSaveTest::GAME_NAME . '/php-unit/1.0.0.1' . $parameters['Delimiter']
            ]
          ]);
        }

        if ($parameters['Prefix'] == CloudSaveTest::SERVICES_USER_ID . '/' . CloudSaveTest::GAME_NAME_XBONE . '/') {
          return new AwsResult([
            'KeyCount' => 2,
            'CommonPrefixes' => [
              'Prefix' => CloudSaveTest::SERVICES_USER_ID . '/' . CloudSaveTest::GAME_NAME_XBONE . '/xbone/1.0.0.1' . $parameters['Delimiter'],
              'Prefix' => CloudSaveTest::SERVICES_USER_ID . '/' . CloudSaveTest::GAME_NAME_XBONE . '/xbox/1.0.0.1' . $parameters['Delimiter']
            ]
          ]);
        }

        if ($parameters['Prefix'] == CloudSaveTest::SERVICES_USER_ID . '/' . CloudSaveTest::GAME_NAME_PS3 . '/') {
          return new AwsResult([
            'KeyCount' => 1,
            'CommonPrefixes' => [
              'Prefix' => CloudSaveTest::SERVICES_USER_ID . '/' . CloudSaveTest::GAME_NAME_PS3 . '/ps3/1.0.0.1' . $parameters['Delimiter']
            ]
          ]);
        }

        return new AwsResult([
          'KeyCount' => 0
        ]);

      }
    });
  }

  protected function getDataSet() {
    return $this->createArrayDataSet([
      'account' => [
        [
          'id' => self::SERVICES_USER_ID - 100,
          'username' => 'PHPUnit',
          'password' => 'PHPUnit-Password',
          'email' => 'phpunit@example.com',
          'last_login' => '0000-00-00 00:00:00',
          'login_hash' => 'd4e4043dfb591034d8544ea7b47f295ed7d4792b',
          'created_at' => '1375809432',
          'ttg_account_id' => self::USER_ID - 100
        ],
        [
          'id' => self::SERVICES_USER_ID,
          'username' => 'ServicesAccountTest',
          'password' => 'ServicesAccountTest-Password',
          'email' => 'ServicesAccountTest@example.com',
          'last_login' => '0000-00-00 00:00:00',
          'login_hash' => '01532d3fde911a20fecc7134ad34ec090b671c86',
          'created_at' => '1387501390',
          'ttg_account_id' => self::USER_ID
        ],
      ]
    ]);
  }

  public function testExists() {
    $user = new UserModel();
    $user->setId(self::USER_ID);

    $game = new GameModel();
    $game->setGame(self::GAME_NAME);
    $game->setCloudSync(true);

    $result = $this->cloudSave->exists($user, $game);

    $this->assertTrue($result);
  }

  public function testNotExists() {
    $user = new UserModel();
    $user->setId(self::USER_ID - 100);

    $game = new GameModel();
    $game->setGame(self::GAME_NAME);
    $game->setCloudSync(true);

    $result = $this->cloudSave->exists($user, $game);

    $this->assertFalse($result);
  }

  public function testNotExistsInS3() {
    $user = new UserModel();
    $user->setId(self::USER_ID);

    $game = new GameModel();
    $game->setGame(self::GAME_NAME_MISMATCH);
    $game->setCloudSync(true);

    $result = $this->cloudSave->exists($user, $game);

    $this->assertFalse($result);
  }

  public function testNoServicesAccount() {
    $user = new UserModel();
    $user->setId(1);

    $game = new GameModel();
    $game->setGame(self::GAME_NAME);
    $game->setCloudSync(true);

    $result = $this->cloudSave->exists($user, $game);
    $this->assertFalse($result);
  }

  /**
    * Expect UnexpectedValueException
    *
    * @expectedException        UnexpectedValueException
    * @expectedExceptionMessage Game does not support Cloud Saves
    */
  public function testInvalidGame() {
    $user = new UserModel();

    $game = new GameModel();
    $game->setGame('invalidGame');

    $result = $this->cloudSave->exists($user, $game);
  }

  public function testTransferableFromXbone() {

    $user = new UserModel();
    $user->setId(self::USER_ID);

    $game = new GameModel();
    $game->setGame(self::GAME_NAME_XBONE);
    $game->setCloudSync(true);

    // Ok from Xbox to Nintendo
    $nxResult = $this->cloudSave->transferable($user, $game, 'nx');
    $this->assertTrue($nxResult);

    // Ok from Xbox to PC
    $pcResult = $this->cloudSave->transferable($user, $game, 'pc');
    $this->assertTrue($pcResult);

    // Cannot transfer from Xbox to Sony
    $ps4Result = $this->cloudSave->transferable($user, $game, 'ps4');
    $this->assertFalse($ps4Result);
  }

  public function testTransferableFromPS3() {

    $user = new UserModel();
    $user->setId(self::USER_ID);

    $game = new GameModel();
    $game->setGame(self::GAME_NAME_PS3);
    $game->setCloudSync(true);

    // Cannot transfer from Sony to Nintendo
    $nxResult = $this->cloudSave->transferable($user, $game, 'nx');
    $this->assertFalse($nxResult);

    // Ok from Sony to PC
    $pcResult = $this->cloudSave->transferable($user, $game, 'pc');
    $this->assertTrue($pcResult);

    // Ok from PS3 to PS4
    $ps4Result = $this->cloudSave->transferable($user, $game, 'ps4');
    $this->assertTrue($ps4Result);

    // Cannot transfer from Sony to Xbone
    $xboneResult = $this->cloudSave->transferable($user, $game, 'xbone');
    $this->assertFalse($xboneResult);
  }

}