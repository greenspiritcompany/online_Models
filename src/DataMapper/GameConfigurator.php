<?php
namespace Telltale\DataMapper;

use Telltale\Model\GameConfigurator as GameConfiguratorModel;
use PDO;
use PDOException;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;

class GameConfigurator {

  private $pdo;
  private $cache;

  public function __construct(PDO $pdo, CacheItemPoolInterface $cachePool = null) {
    $this->pdo = $pdo;
    $this->cache = $cachePool;
  }

  /**
   * Find game configuration items from the game_configurator database
   *
   * @param GameConfiguratorModel $gameConfig
   * @return array
   */
  public function find(GameConfiguratorModel $gameConfig): array {

    $cacheable = $this->cache && empty($gameConfig->getNodeGuid());
    if ($cacheable) {
      $cacheItem = $this->getCacheItem($gameConfig);
      if ($cacheItem->isHit()) {
        return $cacheItem->get();
      }
    }

    $queryHelper = $gameConfig->queryHelper();
    if (!$queryHelper['keys']) {
      return [];
    }

    $statement = $this->pdo->prepare(
      'SELECT user_id, game, node_guid, created_at, updated_at
      FROM game_configurator
      WHERE ' . implode(' = ? AND ', $queryHelper['keys']) . ' = ?
      ORDER BY created_at DESC'
    );

    $statement->setFetchMode(PDO::FETCH_CLASS, GameConfiguratorModel::class);
    $statement->execute($queryHelper['values']);
    $results = $statement->fetchAll();

    if ($cacheable && !empty($results)) {
      $cacheItem->set($results);

      // Cache for 1 hour
      $exp = new \DateInterval('PT1H');
      $cacheItem->expiresAfter($exp);
      $this->cache->save($cacheItem);
    }

    return $results;
  }

  /**
   * Check if a user's configuration(s) exist
   *
   * @param GameConfiguratorModel $gameConfig
   * @return boolean
   */
  public function exists(GameConfiguratorModel $gameConfig): bool {

    $checkCache = $this->cache && empty($gameConfig->getNodeGuid());
    // Check in cache first
    if ($checkCache) {
      $cacheItem = $this->getCacheItem($gameConfig);
      if ($cacheItem->isHit()) {
        return true;
      }
    }

    $queryHelper = $gameConfig->queryHelper();
    if (!$queryHelper['keys']) {
      return false;
    }

    $statement = $this->pdo->prepare(
      'SELECT 1
      FROM game_configurator
      WHERE ' . implode(' = ? AND ', $queryHelper['keys']) . ' = ?
      LIMIT 1'
    );
    $statement->execute($queryHelper['values']);
    return $statement->fetchColumn() > 0;
  }

  /**
   * Add a node GUID to a user's game configuration
   *
   * @param GameConfiguratorModel $gameConfig
   * @return void
   */
  public function add(GameConfiguratorModel $gameConfig) {

    $this->validateModel($gameConfig);

    $statement = $this->pdo->prepare(
      'INSERT INTO game_configurator (user_id, game, node_guid)
      VALUES (:user_id, :game, :node_guid)
      ON DUPLICATE KEY UPDATE updated_at=NOW()
      '
    );
    $statement->execute([
      'user_id' => $gameConfig->getUserId(),
      'game' => $gameConfig->getGame(),
      'node_guid' => $gameConfig->getNodeGuid(),
    ]);

    if ($this->cache) {
      $cacheKey = [
        'GameConfigurator',
        'UserID',
        $gameConfig->getUserId(),
        'Game',
        $gameConfig->getGame()
      ];

      // Delete cache for this user's game configurations
      $this->cache->deleteItem(implode('|', $cacheKey));
    }
  }

  /**
   * Remove a user's game configuration(s)
   *
   * @param GameConfiguratorModel $gameConfig
   * @return void
   */
  public function remove(GameConfiguratorModel $gameConfig): int {

    if (!$gameConfig->getUserId()) {
      throw new \PDOException('Cannot delete record(s) without a userID.', 400);
    }

    $queryHelper = $gameConfig->queryHelper();
    $statement = $this->pdo->prepare(
      'DELETE FROM game_configurator
      WHERE ' . implode(' = ? AND ', $queryHelper['keys']) . ' = ?'
    );

    $statement->execute($queryHelper['values']);
    $count = $statement->rowCount();

    if ($this->cache && $count > 0) {

      $cacheKey = [
        'GameConfigurator',
        'UserID',
        $gameConfig->getUserId()
      ];

      // Delete cache for this user's configurations
      $this->cache->deleteItem(implode('|', $cacheKey));
    }

    return $count;
  }

  /**
   * Add multiple GameConfiguratorModels to the database
   *
   * @param array $items
   * @return void
   */
  public function addMultiple(array $items) {

    if (empty($items)) {
      throw new \PDOException('No items to add', 400);
    }

    $userIDs = [];

    $statement = $this->pdo->prepare(
      'INSERT INTO game_configurator (user_id, game, node_guid)
      VALUES (:user_id, :game, :node_guid)
      ON DUPLICATE KEY UPDATE updated_at=NOW()
      '
    );

    $this->pdo->beginTransaction();
    foreach($items as $item) {
      $this->validateModel($item);
      $statement->execute([
        'user_id' => $item->getUserId(),
        'game' => $item->getGame(),
        'node_guid' => $item->getNodeGuid(),
      ]);
      $userIDs[$item->getUserId()] = true;
    }
    $this->pdo->commit();

    if ($this->cache) {
      $userIDs = array_keys($userIDs);
      foreach ($userIDs as $userID) {
        $cacheKey = [
          'GameConfigurator',
          'UserID',
          $userID
        ];
        $this->cache->deleteItem(implode('|', $cacheKey));
      }
    }
  }

  /**
   * Get the hierarchical cache item from a GameConfiguratorModel
   *
   * @param GameConfiguratorModel $gameConfig
   * @return CacheItemInterface
   */
  private function getCacheItem(GameConfiguratorModel $gameConfig): CacheItemInterface {

    $cacheKey = [
      'GameConfigurator',
    ];

    if ($userID = $gameConfig->getUserId()) {
      $cacheKey[] = 'UserID';
      $cacheKey[] = $userID;
    }

    if ($game = $gameConfig->getGame()) {
      $cacheKey[] = 'Game';
      $cacheKey[] = $game;
    }

    return $this->cache->getItem(implode('|', $cacheKey));
  }

  /**
   * Check that a GameConfiguratorModel has everything needed to be inserted into the db
   *
   * @param GameConfiguratorModel $gameConfig
   * @throws UnexpectedValueException
   * @return void
   */
  private function validateModel(GameConfiguratorModel $gameConfig) {
    if ($gameConfig->getUserId() < 1) {
      throw new \UnexpectedValueException('UserID is required', 400);
    }

    if (empty($gameConfig->getGame())) {
      throw new \UnexpectedValueException('Game is required', 400);
    }

    if (empty($gameConfig->getNodeGuid())) {
      throw new \UnexpectedValueException('Node GUID is required', 400);
    }
  }

}