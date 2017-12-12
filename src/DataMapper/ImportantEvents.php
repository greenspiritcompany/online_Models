<?php
namespace Telltale\DataMapper;

use Telltale\Model\ImportantEvents as ImportantEventsModel;
use Telltale\Model\Game as GameModel;
use Telltale\DataMapper\Game;

use PDO;
use PDOException;
use Psr\Cache\CacheItemPoolInterface;

class ImportantEvents {

  const CACHE_TIME = 'PT2H'; // +2 hours

  private $pdo;
  private $cache;

  public function __construct(PDO $pdo, CacheItemPoolInterface $cachePool = null) {
    $this->pdo = $pdo;
    $this->cache = $cachePool;
  }

  /**
   * Fetch all the important events for a game
   * Note: Database changes name based on game key
   *
   * @param string $game
   * @param array $list_of_valid_games
   * @return void
   */
  public function fetchAll(string $game, array $list_of_valid_games = null) {

    if ($this->cache) {
      $cacheItem = $this->cache->getItem('ImportantEvents|' . $game);

      // Return user from cache if found
      $events = $cacheItem->get();
      if ($events) {
        return $events;
      }
    }

    if (is_null($list_of_valid_games)) {
      $gameData = new Game($this->pdo, $this->cache);
      $gameQuery = new GameModel();
      $gameQuery->setGame($game);
      $list_of_valid_games = $gameData->fetchValidGameList($gameQuery);
    }

    if (!in_array($game, $list_of_valid_games)) {
      throw new PDOException('Invalid game');
    }

    $statement = $this->pdo->query(
      'SELECT purpose, event_type, int_event_type, event_value, b64_event_value, cohort_name, created_at
      FROM ' . $game . '.important_events'
    );
    $statement->setFetchMode(PDO::FETCH_CLASS, ImportantEventsModel::class);
    $events = $statement->fetchAll();

    if ($this->cache) {
      // Cache the result
      $exp = new \DateInterval(self::CACHE_TIME);
      $cacheItem->expiresAfter($exp);

      // Save user into cache
      $cacheItem->set($events);
      $this->cache->save($cacheItem);
    }

    return $events;
  }

  /**
   * Return just the Executing Dialog Nodes - need for translating choice expressions
   *
   * @param string $game
   * @param array $list_of_valid_games
   * @return array
   */
  public function fetchSymbolDictionary(string $game, array $list_of_valid_games = null): array {

    if ($this->cache) {
      $cacheItem = $this->cache->getItem('ImportantEvents|' . $game . '|SymbolDictionary');

      // Return user from cache if found
      $symbolDictionary = $cacheItem->get();
      if ($symbolDictionary) {
        return $symbolDictionary;
      }
    }

    $events = $this->fetchAll($game, $list_of_valid_games);

    if (empty($events)) {
      return [];
    }

    $symbolDictionary = [];
    foreach($events as $event) {
      if ('Executing Dialog Node' == $event->getEventType()) {

        $dialogNode = $event->getEventValue();
        $translatedValue = $event->getB64EventValue();

        if (is_null($dialogNode) || is_null($translatedValue)) {
          continue;
        }

        $symbolDictionary[$dialogNode] = $translatedValue;
      }
    }

    if ($this->cache) {
      // Cache the result
      $exp = new \DateInterval(self::CACHE_TIME);
      $cacheItem->expiresAfter($exp);

      // Save user into cache
      $cacheItem->set($symbolDictionary);
      $this->cache->save($cacheItem);
    }

    return $symbolDictionary;
  }
}