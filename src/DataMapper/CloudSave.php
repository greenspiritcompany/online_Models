<?php
namespace Telltale\DataMapper;

use PDO;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;

use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

use Telltale\Model\User as UserModel;
use Telltale\Model\Game as GameModel;

use Telltale\DataMapper\ServicesAccount;

class CloudSave {

  // AWS region where the Cloud Save data is stored
  const AWS_CLIENT_CONFIG = [
    'region' => 'us-west-2',
    'version' => 'latest'
  ];

  private $log;
  private $cache;
  private $dynamoDB;
  private $servicesAccount;
  private $dynamoTable;

  public function __construct(PDO $pdo, CacheItemPoolInterface $cache = null, $logger = null) {
    $this->log = $logger ?? new NullLogger();
    $this->cache = $cache;
    $this->s3 = new S3Client(self::AWS_CLIENT_CONFIG);
    $this->dynamoDB = new DynamoDbClient(self::AWS_CLIENT_CONFIG);
    $this->servicesAccount = new ServicesAccount($pdo, $cache);

    // When in development, query the staging dynamoDB
    $this->dynamoTable = (getenv('ENV') == 'development' ? 'Staging' : ucfirst(getenv('ENV'))) . 'CloudSync';

    // Use the staging bucket for all environments except production
    $this->s3Bucket = getenv('ENV') == 'production' ? 'cloud.telltalegames.com' : 'cloud-stage.telltalegames.com';
  }

  /**
   * Check if a user has a cloud save stored for a game
   *
   * @param UserModel $user
   * @param GameModel $game
   * @return boolean
   */
  public function exists(UserModel $user, GameModel $game): bool {

    if (!$game->getCloudsync()) {
      throw new \UnexpectedValueException('Game does not support Cloud Saves', 400);
    }

    if ($this->cache) {
      $cacheKey = [
        $this->dynamoTable,
        $user->getID(),
        $game->getGame(),
        'Exists'
      ];

      $cacheItem = $this->cache->getItem(implode('|', $cacheKey));

      if ($cacheItem->isHit()) {
        return $cacheItem->get();
      }
    }

    // At this time Cloud Saves are tied to a user's services account instead of user ID
    $accountID = $this->servicesAccount->getIdFromAuthId($user->getID());

    if (!$accountID) {
      $this->log->warning('Cloud Save user has no services account.', [
        'userID' => $user->getID()
      ]);
      return false;
    }

    $userPath = $accountID . '/' . $game->getGame() . '/shared/cloud_save_slot_location';

    $dynamoDBQuery = [
      'TableName' => $this->dynamoTable,
      'Select' => 'COUNT',
      'Limit' => 1,
      'KeyConditions' => [
        'userpath' => [
          'AttributeValueList' => [
            ['S' => $userPath]
          ],
          'ComparisonOperator' => 'EQ'
        ]
      ],
    ];

    try {
      $dynamoDBResult = $this->dynamoDB->query($dynamoDBQuery);
    } catch (DynamoDbException $e) {
      $this->log->error('DynamoDbException: Failed to determine if Cloud Save Slot exists.', [
        'AWSClientResponse' => $e->getMessage(),
        'dynamoDBQuery' => $dynamoDBQuery
      ]);
      return false;
    }

    $hasCloudSave = $dynamoDBResult->get('Count') == 1;

    // Sadly we can't trust the dynamoDB result due to Cloud Save bugs in services.
    // Verify that a save slot estore exists in s3.
    try {
      $hasCloudSaveStore = $this->s3->doesObjectExist($this->s3Bucket, $userPath . '/_saveslot1_id.estore');
    } catch (S3Exception $e) {
      $this->log->error('S3Exception: Failed to check if Cloud Save exists.', [
        'AWSClientResponse' => $e->getMessage(),
        'bucket' => $this->s3Bucket,
        'userPath' => $userPath
      ]);
      return $hasCloudSave;
    }

    if ($hasCloudSave && !$hasCloudSaveStore) {
      $this->log->warning('A Cloud Save was found in DynamoDB but no files exists in S3.', [
        'game' => $game->getGame(),
        'userID' => $user->getID(),
        'servicesAccount' => $accountID
      ]);
      return false;
    }

    if (!$hasCloudSave && $hasCloudSaveStore) {
      $this->log->warning('A Cloud Save was found S3 but no DynamoDB record exists.', [
        'game' => $game->getGame(),
        'userID' => $user->getID(),
        'servicesAccount' => $accountID
      ]);
      return false;
    }

    // Only cache success
    if ($this->cache && $hasCloudSave) {
      $cacheItem->set($hasCloudSave);

      // Cache for 1 hour
      $exp = new \DateInterval('PT1H');
      $cacheItem->expiresAfter($exp);
      $this->cache->save($cacheItem);
    }

    return $hasCloudSave;
  }

}