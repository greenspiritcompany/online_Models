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
   * Return all games matching the game query model
   *
   * @param GameModel $gameQuery
   * @return array
   */
  public function fetchAll(GameModel $gameQuery) {
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
   * Return all active games
   *
   * @return array
   */
  public function getAllActive() {
    $statement = $this->pdo->query('SELECT id, game, display_name, active, cloudsync FROM games WHERE active = 1');
    $statement->setFetchMode(PDO::FETCH_CLASS, GameModel::class);
    return $statement->fetchAll();
  }

  /**
   * Return an array of game 'keys'
   *
   * @param GameModel $game
   * @return array
   */
  public function fetchValidGameList(GameModel $gameQuery): array {
    $games = $this->fetchAll($gameQuery);

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
    $games = $this->fetchAll($gameQuery);

    if (!$games) {
      return [];
    }

    $result = [];
    foreach ($games as $game) {
      $result[$game->getId()] = $game->getDisplayName();
    }

    return $result;
  }


  public function add(GameModel $game) {

  }

}