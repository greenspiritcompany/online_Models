<?php
namespace Telltale\DataMapper;

use PDO;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

use Telltale\Model\User as UserModel;
use Telltale\Model\Game as GameModel;
use Telltale\Model\ServicesAccount as ServicesAccountModel;

use Telltale\DataMapper\ServicesAccount;

class CloudSave {

  // AWS region where the Cloud Save data is stored
  const AWS_CLIENT_CONFIG = [
    'region' => 'us-west-2',
    'version' => 'latest'
  ];

  private $log;
  private $cache;
  private $s3;
  private $dynamoDB;
  private $servicesAccount;
  private $dynamoTable;

  public function __construct(PDO $pdo, CacheItemPoolInterface $cache = null, $logger = null) {
    $this->log = $logger ?? new NullLogger();
    $this->cache = $cache;
    $this->s3 = new S3Client(self::AWS_CLIENT_CONFIG);
    $this->servicesAccount = new ServicesAccount($pdo, $cache);
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
        'CloudSync',
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

    $hasCloudSave = !empty($this->getCloudSaveStore($accountID, $game->getGame()));

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

  /**
   * Can the $user's cloud save be transferred to $platform?
   *
   * @param UserModel $user
   * @param GameModel $game
   * @param string $platform Name of the requesting platform that wants to import the save files
   * @return boolean
   */
  public function transferable(UserModel $user, GameModel $game, string $platform): bool {

    if (!$this->exists($user, $game)) {
      return false;
    }

    $accountID = $this->servicesAccount->getIdFromAuthId($user->getID());

    $cloudSaveStore = $this->getCloudSaveStore($accountID, $game->getGame());

    if (empty($cloudSaveStore)) {
      return false;
    }

    // Tool is always transferable
    if ($platform == 'tool') {
      return true;
    }

    // PS4 is not allowed to transfer saves from Xbox, Xbone, win10, or NX
    if ($platform == 'ps4') {
      return array_reduce($cloudSaveStore, function(bool $result, array $item) {
        return $result && str_replace(['/xbox/', '/xbone/', '/win10/', '/nx/'], '', $item['Prefix']) === $item['Prefix'];
      }, true);
    }

    // Only pc (and pc-steam etc) is allowed to transfer saves from ps3 and ps4 cloud saves
    if (!(strpos($platform, 'pc') === 0)) {
      return array_reduce($cloudSaveStore, function(bool $result, array $item) {
        return $result && str_replace(['/ps3/', '/ps4/'], '', $item['Prefix']) === $item['Prefix'];
      }, true);
    }

    return true;
  }

  /**
   * Scan a user's cloud save location on S3 to get the location of `_saveslot1_id.estore`
   * in the `{platform}/{version}/cloud_save_choices` subdirectory.
   *
   * @param ServicesAccountModel $account
   * @param GameModel $game
   * @return array
   */
  private function getCloudSaveStore(int $servicesAccountId, string $gameDirectory): array {

    if ($this->cache) {
      $cacheKey = [
        'CloudSaveStore',
        $servicesAccountId,
        $gameDirectory
      ];

      $cacheItem = $this->cache->getItem(implode('|', $cacheKey));

      if ($cacheItem->isHit()) {
        return $cacheItem->get() ?? [];
      }
    }

    $s3Parameters = [
      'Bucket' => getenv('ENV') == 'production' ? 'cloud.telltalegames.com' : 'cloud-stage.telltalegames.com',
      'MaxKeys' => 100,
      'Prefix' => $servicesAccountId . '/' . $gameDirectory . '/',
      'Delimiter' => '/cloud_save_choices/_saveslot1_id.estore'
    ];

    try {
      $foundSaveObjects = $this->s3->listObjectsV2($s3Parameters);
    } catch (S3Exception $e) {
      $this->log->error('S3Exception: Failed to check if Cloud Save exists.', array_merge([
        'AWSClientResponse' => $e->getMessage()
      ], $s3Parameters));
      return [];
    }

    $cloudSaveStore = $foundSaveObjects->get('CommonPrefixes');

    if ($this->cache) {
      $cacheItem->expiresAfter(180); // 3 minutes
      // Save into cache
      $cacheItem->set($cloudSaveStore);
      $this->cache->save($cacheItem);
    }

    return $cloudSaveStore ?? [];
  }

}