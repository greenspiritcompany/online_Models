<?php
namespace Telltale\Tests;

use Telltale\DataMapper\UserChoices;
use Telltale\Model\User as UserModel;

use Telltale\DataMapper\ServicesCache;

class UserChoicesTest extends AbstractDBTest {

  const USER_ID = 110;
  const SERVICES_USER_ID = 100;
  const VALID_ACCESS_POINT = 1337;
  const ACCESS_POINT_N0_MATCHES = 41453;
  const GAME_NAME = 'phpunit_model_tests'; // determines important_events database name
  const CHOICE_DOC_TYPE = 'stats_extended';

  private $userChoices;

  public function __construct() {
    parent::__construct();

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

    // Note: game varchar had to be changed from 16 to 19 for this test
    $this->setupTableSchema('choice_documents_with_data', '
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `source_doc_id` int(11) DEFAULT NULL,
      `game_name` varchar(19) DEFAULT NULL,
      `choice_doc_type` varchar(20) DEFAULT NULL,
      `source_doc_date` varchar(20) DEFAULT NULL,
      `created_at` datetime DEFAULT NULL,
      `updated_at` datetime DEFAULT NULL,
      `json_data` longtext,
      PRIMARY KEY (`id`)
    ');

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

    // Note: game varchar had to be changed from 16 to 19 for this test
    $this->setupTableSchema('games', "
      `id` smallint(6) NOT NULL AUTO_INCREMENT,
      `game` varchar(19) DEFAULT NULL,
      `display_name` varchar(34) NOT NULL DEFAULT '',
      `active` tinyint(1) DEFAULT NULL,
      `cloudsync` tinyint(1) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`)
    ");


    /**
     * Mock services cache so we can pretend to get results back from kinesis
     */
    $servicesCache = new Class extends ServicesCache {

      public function __construct() {
        // Mock Memcached
        $this->cache = new Class {
          public function __call(string $name, array $arguments) {
            return true;
          }
        };
      }

      public function getItem($key) {

        if ($key == "|PHPUnitNamespace|CloudSaveChoiceData|100|phpunit_model_tests") {
          // This cache result would be populated from kinesis
          return $this->buildItem($key, true, json_encode([
            'matches' => [
              ['base64_event_value' => 'KDuaLKJPMdU='],
              ['base64_event_value' => 'wCX9xt+bTQQ='],
              ['base64_event_value' => 'tW1YGtUvNaw='],
            ],
          ]));
        }

        if ($key == "|PHPUnitNoMatch|CloudSaveChoiceData|100|phpunit_model_tests") {
          return $this->buildItem($key, true, json_encode([
            'matches' => [],
          ]));
        }

        // pendingCacheMessage we put in cache has not been replaced by the above kinesis responses yet
        if ($key == "|PHPUnitWaiting|CloudSaveChoiceData|100|phpunit_model_tests") {
          return $this->buildItem($key, true, json_encode([
            'status' => 'pending',
            'datetime' => date('Y-m-d H:i:s')
          ]));
        }

        return $this->buildItem($key, false);
      }
    };

    /**
     * Setup new UserChoices data mapper
     *
     * @param PDO $globalPDO
     * @param PDO $servicesPDO
     * @param CacheItemPoolInterface $cache
     * @param CacheItemPoolInterface $servicesCache
     * @param [type] $logger
     */
    $this->userChoices = new UserChoices($this->getPDO(), $this->getPDO(), $this->getCache(), $servicesCache);

    // Bonk! We must mock the kenesis and dynamoDB data mappers
    $reflection = new \ReflectionClass($this->userChoices);
    $kinesisProperty = $reflection->getProperty('kinesis');
    $kinesisProperty->setAccessible(true);
    $kinesisProperty->setValue($this->userChoices, new class() {
      public function sendCloudSaveProcessRequest(int $account_id, string $game, string $environment = 'production') {

        if ($account_id !== UserChoicesTest::SERVICES_USER_ID) {
          throw \Exception('sendCloudSaveProcessRequest mock got unexpected account_id');
        }

        if ($game !== UserChoicesTest::GAME_NAME) {
          throw \Exception('sendCloudSaveProcessRequest mock got unexpected game');
        }

        return true;
      }
    });
    $dynamoDBProperty = $reflection->getProperty('dynamoDB');
    $dynamoDBProperty->setAccessible(true);
    $dynamoDBProperty->setValue($this->userChoices, new class() {
      public function accessPointGameEvents(int $accessPointID, string $game, string $environment = 'production'): array {

        if ($game != UserChoicesTest::GAME_NAME) {
          return false;
        }

        if ($accessPointID === UserChoicesTest::ACCESS_POINT_N0_MATCHES) {
          return [
            'matches' => [
              ['base64_event_value' => 'random='],
            ],
          ];
        }

        if ($accessPointID !== UserChoicesTest::VALID_ACCESS_POINT) {
          return [
            'matches' => []
          ];
        }

        return [
          'matches' => [
            ['base64_event_value' => 'KDuaLKJPMdU='],
            ['base64_event_value' => 'wCX9xt+bTQQ='],
            ['base64_event_value' => 'tW1YGtUvNaw='],
          ],
        ];
      }
    });
  }

  protected function getDataSet() {

    return $this->createArrayDataSet([
      'account' => [
        [
          'id' => self::SERVICES_USER_ID,
          'username' => 'PHPUnit',
          'password' => 'PHPUnit-Password',
          'email' => 'phpunit@example.com',
          'last_login' => '0000-00-00 00:00:00',
          'login_hash' => 'd4e4043dfb591034d8544ea7b47f295ed7d4792b',
          'created_at' => '1375809432',
          'ttg_account_id' => self::USER_ID
        ],
      ],
      'important_events' => [
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
          'event_value' => '{2F0B7CD6-48F3-4E92-B29C-2E8189D10535}',
          'b64_event_value' => 'tW1YGtUvNaw=',
          'cohort_name' => NULL,
          'created_at' => '2017-08-10 09:33:42',
        ],
      ],
      'choice_documents_with_data' => [
        [
          'game_name' => self::GAME_NAME,
          'choice_doc_type' => self::CHOICE_DOC_TYPE,
          'source_doc_date' => '2017-12-11',
          'json_data' => file_get_contents(dirname(__FILE__) . '/../datasets/minecraft2-choice-data.json'),
        ],
        [
          'game_name' => 'mock-choice-game',
          'choice_doc_type' => 'stats_mock_data',
          'source_doc_date' => '2017-12-13',
          'json_data' => json_encode([
            'choices' => [
              [
                'foo' => 'bar',
                'bar' => 'foo',
              ]
            ]
          ])
        ]
      ],
      'games' => [
        [
          'game' => self::GAME_NAME,
          'display_name' => 'Unit Test Game',
          'active' => 1,
          'cloudsync' => 0
        ],
      ]
    ]);
  }


  public function testFetch() {

    $result = $this->userChoices->fetch(self::USER_ID, self::GAME_NAME, 'stats_extended', 'PHPUnitNamespace');

    $this->assertCount(2, $result);
    $this->assertArrayHasKey('meta', $result);
    $this->assertArrayHasKey('choice_document', $result);

    $this->assertEquals([
      'status' => 'success',
      'message' => 'Events were found for this player.',
      'type' => 'stats_extended',
    ], $result['meta']);

    $this->assertCount(2, $result['choice_document']);

    $this->assertEquals([
      'game', 'episode', 'seq', 'revision', 'description', 'percent', 'question'
    ], array_keys($result['choice_document'][0]));

    // 50 percent result because percent is invalid in minecraft2-choice-data.json
    $this->assertEquals(50, $result['choice_document'][0]['percent']);
    $this->assertEquals('You and 50% of players told them to watch each other.', $result['choice_document'][0]['description']['english']);
    $this->assertEquals('What orders did you give the Golem Guards?', $result['choice_document'][0]['question']['english']);

    $this->assertCount(10, $result['choice_document'][1]['description']);
    $this->assertCount(10, $result['choice_document'][1]['question']);
    $this->assertEquals(68, $result['choice_document'][1]['percent']);
  }

  public function testFetchPending() {

    $result = $this->userChoices->fetch(self::USER_ID, self::GAME_NAME);

    $this->assertCount(1, $result);
    $this->assertArrayHasKey('meta', $result);

    $this->assertEquals([
      'status' => 'pending',
      'message' => 'Searching for player data.',
      'type' => 'stats_extended',
    ], $result['meta']);
  }

  public function testFetchWaiting() {
    $result = $this->userChoices->fetch(self::USER_ID, self::GAME_NAME, 'stats_extended', 'PHPUnitWaiting');

    $this->assertCount(1, $result);
    $this->assertArrayHasKey('meta', $result);

    $this->assertEquals([
      'status' => 'pending',
      'message' => 'Waiting for player data...',
      'type' => 'stats_extended',
    ], $result['meta']);
  }

  public function testFetchNoMatches() {
    $result = $this->userChoices->fetch(self::USER_ID, self::GAME_NAME, 'stats_extended', 'PHPUnitNoMatch');

    $this->assertCount(1, $result);
    $this->assertArrayHasKey('meta', $result);

    $this->assertEquals([
      'status' => 'failed',
      'message' => 'No events were found for this player.',
      'type' => 'stats_extended',
    ], $result['meta']);
  }

  public function testFetchNoChoiceDocs() {
    $result = $this->userChoices->fetch(self::USER_ID, self::GAME_NAME, 'foo-bar');

    $this->assertCount(1, $result);
    $this->assertArrayHasKey('meta', $result);

    $this->assertEquals([
      'status' => 'failed',
      'message' => 'No events were found for this player.',
      'type' => 'choice_doc', // this part changes from 'foo-bar'
    ], $result['meta']);

  }

  public function testFetchByAccessPoint() {
    $result = $this->userChoices->fetchByAccessPoint(self::VALID_ACCESS_POINT, self::GAME_NAME);

    $this->assertCount(2, $result);
    $this->assertArrayHasKey('meta', $result);
    $this->assertArrayHasKey('choice_document', $result);

    $this->assertEquals([
      'status' => 'success',
      'message' => 'Events were found for this player.',
      'type' => 'stats_extended',
    ], $result['meta']);

    $this->assertCount(2, $result['choice_document']);

    $this->assertEquals([
      'game', 'episode', 'seq', 'revision', 'description', 'percent', 'question'
    ], array_keys($result['choice_document'][0]));

    // 50 percent result because percent is invalid in minecraft2-choice-data.json
    $this->assertEquals(50, $result['choice_document'][0]['percent']);
    $this->assertEquals('You and 50% of players told them to watch each other.', $result['choice_document'][0]['description']['english']);
    $this->assertEquals('What orders did you give the Golem Guards?', $result['choice_document'][0]['question']['english']);

    $this->assertCount(10, $result['choice_document'][1]['description']);
    $this->assertCount(10, $result['choice_document'][1]['question']);
    $this->assertEquals(68, $result['choice_document'][1]['percent']);
  }

  public function testFetchByAccessPointInvalidAP() {
    $result = $this->userChoices->fetchByAccessPoint(987, self::GAME_NAME, 'stats_extended');

    $this->assertCount(1, $result);
    $this->assertArrayHasKey('meta', $result);

    $this->assertEquals([
      'status' => 'failed',
      'message' => 'No events were found for this player.',
      'type' => 'stats_extended',
    ], $result['meta']);
  }

  public function testFetchByAccessPointInvalidGame() {
    $result = $this->userChoices->fetchByAccessPoint(self::VALID_ACCESS_POINT, 'invalid-game');

    $this->assertCount(1, $result);
    $this->assertArrayHasKey('meta', $result);

    $this->assertEquals([
      'status' => 'failed',
      'message' => 'No events were found for this player.',
      'type' => 'choice_doc',
    ], $result['meta']);
  }

  /**
   * Note: One would expect an access point with no valid base64_event_values to get no results but
   * if there is an expression full of 'nots' it will always show up (see line 3196 in minecraft2-choice-data.json)
   *
   */
  public function testFetchByAccessPointNoMatch() {
    $result = $this->userChoices->fetchByAccessPoint(self::ACCESS_POINT_N0_MATCHES, self::GAME_NAME);

    $this->assertCount(2, $result);
    $this->assertArrayHasKey('meta', $result);

    $this->assertEquals([
      'status' => 'success',
      'message' => 'Events were found for this player.',
      'type' => 'stats_extended',
    ], $result['meta']);

    $this->assertCount(1, $result['choice_document']);

    $this->assertEquals([
      'game', 'episode', 'seq', 'revision', 'description', 'percent', 'question'
    ], array_keys($result['choice_document'][0]));

    // 50 percent result because percent is invalid in minecraft2-choice-data.json
    $this->assertEquals(68, $result['choice_document'][0]['percent']);
    $this->assertEquals('You and 68 % of players did not get the Zombie Flesh.', $result['choice_document'][0]['description']['english']);
    $this->assertEquals('Did you take the zombie flesh from Carmine in the Zombie Mine?', $result['choice_document'][0]['question']['english']);

    $this->assertCount(10, $result['choice_document'][0]['description']);
    $this->assertCount(10, $result['choice_document'][0]['question']);
  }

}