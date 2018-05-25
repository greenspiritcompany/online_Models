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

  /**
   * Return all games matching the game query model
   *
   * @deprecated Replaced with find
   * @param GameModel $gameQuery
   * @return array
   */
  public function fetchAll(GameModel $gameQuery) {
    return $this->find($gameQuery);
  }

  /**
   * Return all active games
   *
   * @return array
   */
  public function getAllActive() {
    $gameQuery = new GameModel();
    $gameQuery->setActive(true);
    return $this->find($gameQuery);
  }

  /**
   * Return an array of game 'keys'
   *
   * @param GameModel $game
   * @return array
   */
  public function fetchValidGameList(GameModel $gameQuery = null): array {
    $games = $gameQuery ? $this->find($gameQuery) : $this->getAllActive();

    if (!$games) {
      return [];
    }

    return array_map(function($game) {
      return $game->getGame();
    }, $games);
  }

  /**
   * Return an associated array with game id as key and display name as the value
   *
   * @param GameModel $game
   * @return array
   */
  public function fetchGameIdToDisplayNameMapping(GameModel $gameQuery): array {
    $games = $this->find($gameQuery);

    if (!$games) {
      return [];
    }

    $result = [];
    foreach ($games as $game) {
      $result[$game->getId()] = $game->getDisplayName();
    }

    return $result;
  }

}