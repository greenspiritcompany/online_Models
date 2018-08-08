<?php
namespace Telltale\DataMapper;

use Telltale\Model\Game as GameModel;
use PDO;
use PDOException;
use Psr\Cache\CacheItemPoolInterface;

class Game {

  private $pdo;
  private $cache;

  public function __construct(PDO $pdo, CacheItemPoolInterface $cachePool = null) {
    $this->pdo = $pdo;
    $this->cache = $cachePool;
  }

  /**
   * Find games matching the query model
   *
   * @param GameModel $gameQuery
   * @return array
   */
  public function find(GameModel $gameQuery): array {
    $queryHelper = $gameQuery->queryHelper();
    if (!$queryHelper['keys']) {
      return [];
    }

    $statement = $this->pdo->prepare(
      'SELECT id, game, display_name, active, cloudsync
      FROM games
      WHERE ' . implode(' = ? AND ', $queryHelper['keys']) . ' = ? '
    );

    $statement->setFetchMode(PDO::FETCH_CLASS, GameModel::class);
    $statement->execute($queryHelper['values']);

    return $statement->fetchAll();
  }

  /**
   * Check that a game exists
   *
   * @param GameModel $gameQuery
   * @return boolean
   */
  public function exists(GameModel $gameQuery):bool {
    $queryHelper = $gameQuery->queryHelper();
    if (!$queryHelper['keys']) {
      return false;
    }

    $statement = $this->pdo->prepare(
      'SELECT 1
      FROM games
      WHERE ' . implode(' = ? AND ', $queryHelper['keys']) . ' = ?
      LIMIT 1'
    );

    $statement->execute($queryHelper['values']);
    return $statement->fetchColumn() > 0;
  }

}