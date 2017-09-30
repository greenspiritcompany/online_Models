<?php
namespace Telltale\DataMapper;

use Telltale\DataMapper\ImportantEvents;
use Telltale\DataMapper\Game;
use Telltale\DataMapper\ServicesAccount;
use Telltale\DataMapper\ChoiceDocument;
use Telltale\DataMapper\AWSKinesis;

use Telltale\Model\ChoiceDocument as ChoiceDocumentModel;

use PDO;
use PDOException;

use Psr\Cache\CacheItemPoolInterface;

class UserChoices {

  const CACHE_NAMESPACE_FETCH_CHOICES = 'CloudSaveFetchChoices|';
  const CACHE_NAMESPACE_CHOICE_DATA = 'CloudSaveChoiceData|';

  private $globalDB;
  private $servicesDB;

  public function __construct(PDO $globalPDO, PDO $servicesPDO, CacheItemPoolInterface $cache = null, CacheItemPoolInterface $servicesCache, $logger = null) {

    $this->importantEvents = new ImportantEvents($globalPDO, $cache);
    $this->gameData = new Game($globalPDO, $cache);

    $this->servicesAccount = new ServicesAccount($servicesPDO, $cache);
    $this->cache = $cache;
    $this->servicesCache = $servicesCache;

    $this->choiceDocument = new ChoiceDocument($servicesPDO, $cache);

    $this->kinesis = new AWSKinesis($logger);

    $this->environment = getenv('ENV');
  }

  public function fetch(int $userID, string $game) {

    // Has the whole user choice doc been cached?
    if ($this->cache) {
      $cacheItem = $this->cache->getItem(self::CACHE_NAMESPACE_FETCH_CHOICES . $userID . '|' . $game);
      $playerResults = $cacheItem->get();

      if ($cacheItem->isHit()) {
        return $player_results;
      }
    }

    // Get the services account ID for this user
    $servicesAccountId = $this->servicesAccount->getIdFromAuthId($userID);

    if (!$servicesAccountId) {
      return false;
    }

    // Check if the important events have been placed in cache by Kinesis
    $servicesCacheKey = '|' . $this->environment . '|' . self::CACHE_NAMESPACE_CHOICE_DATA . $servicesAccountId . '|' . $game;
    $servicesCacheItem = $this->servicesCache->getItem($servicesCacheKey);

    if (!$servicesCacheItem->isHit()) {

      // Writing a pending message into memcache so we can wait for the kinesis process to return data
      $pendingCacheMessage = json_encode([
        'status' => 'pending',
        'datetime' => date('Y-m-d H:i:s')
      ]);
      $servicesCacheItem->set($pendingCacheMessage);
      $servicesCacheItem->expiresAt(3600);

      $this->servicesCache->save($servicesCacheItem);

      // Trigger the kinesis process
      $this->kinesis->sendCloudSaveProcessRequest($servicesAccountId, $game, $this->environment);

      // Return a pending message so that the frontend knows to keep calling back for results
      return $this->statusResponse("pending", "Searching for player data.");
    }

    $userChoiceData = json_decode($servicesCacheItem->get(), true);

    if (array_key_exists('status', $userChoiceData) && 'pending' == $userChoiceData['status']) {
      return $this->statusResponse("pending", "Searching for player data.");
    }

    // Check if this user's data is empty (they have no save data)
    if (empty($userChoiceData['matches'])) {
      return $this->statusFailResponse();
    }

    // Extract the base64 event values that we will use to determine the players choices
    $playerEventList = array_map(function($match) {
      return $match['base64_event_value'];
    }, $userChoiceData['matches']);

    $choiceDocQuery = new ChoiceDocumentModel();
    $choiceDocQuery->setChoiceDocType('stats_extended');
    $choiceDocQuery->setGameName($game);
    $choiceDocs = $this->choiceDocument->find($choiceDocQuery);

    if (!$choiceDocs) {
      throw new \Exception('No choices are available yet for this game.', 404);
    }

    // Determine what the current live episode is for this game
    $currentLiveEpisodeId = '201'; // TODO: Not enforcing this yet

    $importantEventsSymbolDictionary = $this->importantEvents->fetchSymbolDictionary($game);

    if (empty($importantEventsSymbolDictionary)) {
      return $this->statusFailResponse();
    }

    $playerChoiceDocument = $this->createPlayerChoiceDocument($choiceDocs, $playerEventList, $currentLiveEpisodeId, $importantEventsSymbolDictionary);

    if ($this->cache) {
      // Store these results in the cache
      $exp = new \DateInterval('PT1H'); // 1 hour
      $cacheItem->expiresAfter($exp);
      $cacheItem->set($playerChoiceDocument);
      $this->cache->save($cacheItem);
    }

    return $playerChoiceDocument;
  }

  /**
   * Combine the list of events the player has hit with our choice document to create a player choice document.
   *
   * @param ChoiceDocumentModel $choiceDocument
   * @param array $playerEventList
   * @param int $currentLiveEpisodeId
   * @param array $importantEventsSymbolDictionary
   * @return array
   */
  public function createPlayerChoiceDocument(ChoiceDocumentModel $choiceDocument, array $playerEventList, int $currentLiveEpisodeId, array $importantEventsSymbolDictionary): array {
    $masterChoiceDoc = $choiceDocument->getData();

    $playerChoiceDoc = [];

    foreach($masterChoiceDoc['choices'] as $choice) {
      foreach ($choice['options'] as $option) {
        $expr = $option['expression'];

        if (empty($expr)) {
          continue;
        }
        $is_match = $this->evalExpressionString($expr, $playerEventList, $importantEventsSymbolDictionary);
        if ($is_match) {
          $playerChoiceDoc[] = [
            'question' => $choice['description'],
            'description' => $this->putPercentageInDescription($option['description'], $option['percent']),
            'percent' => $option['percent'],
            'sequence' => $choice['seq'],
          ];
        }
      }
    }

    return [
      'meta' => [
        'status' => 'success',
        'message' => 'Data found for this user.'
      ],
      'choice_document' => $playerChoiceDoc
    ];

  }

  /**
   * To detect if the expression we are looking is a compound query (multiple dialog nodes)
   *
   * @param string $expression_string
   * @param array $player_events
   * @param array $symbol_dictionary
   * @return bool
   */
  private function evalExpressionString(string $expression_string, array $player_events, array $symbol_dictionary) {

    if (strlen($expression_string) < 1) {
      return false;
    }

    $translated_evaluation_string = "";
    $expressions = explode(" ", $expression_string);
    if (isset($expressions)) {
      foreach ($expressions as $expression) {
        if (strpos($expression,'(') !== false | strpos($expression,')') !== false | strpos($expression,'|') !== false | strpos($expression,'&') !== false) {
          $translated_evaluation_string =  $translated_evaluation_string . $expression . " ";
        }
        if ($this->startsWith($expression,'{')) {
          if ($this->doesThisEventExistInPlayersEvents($expression, $player_events, $symbol_dictionary)) {
              $translated_evaluation_string = $translated_evaluation_string . 'true' . " ";
          } else {
              $translated_evaluation_string = $translated_evaluation_string . 'false' . " ";
          }
        }
        if ($this->startsWith($expression,'~{')) {
          if (!$this->doesThisEventExistInPlayersEvents($expression, $player_events, $symbol_dictionary)) {
              $translated_evaluation_string = $translated_evaluation_string . 'true' . " ";
          } else {
              $translated_evaluation_string = $translated_evaluation_string . 'false' . " ";
          }
        }
      }
    }


    if (!isset($translated_evaluation_string)) return false;
    if (strlen($translated_evaluation_string) < 1) return false;

    $result = null;
    eval(sprintf('$result = %s;', $translated_evaluation_string));
    if (isset($result) && 1 == $result) {
      return true;
    }

    return false;
  }

  /**
   * To detect if the expression we are looking is a compound query (multiple dialog nodes)
   *
   * @param string $dlg_node
   * @param array $player_events
   * @param array $symbol_dictionary
   * @return bool
   */
  private function doesThisEventExistInPlayersEvents(string $dlg_node, array $player_events, array $symbol_dictionary) {
    $translated_value = null;
    if (isset($symbol_dictionary[$dlg_node])) {
      $translated_value = $symbol_dictionary[$dlg_node];
      foreach ($player_events as $event) {
        if ($event == $translated_value) {
          return true;
        }
      }
    }
    return false;
  }

  /**
   * Insert a percentage value into a choice description string
   *
   * @param array $description_array
   * @param string $percentage
   * @return array
   */
  private function putPercentageInDescription(array $description_array, string $percentage) {
    $result_array = [];

    foreach ($description_array as $language => $text) {
      $percentage_string = str_replace('{percent}', $percentage, $text);
      $result_array[$language] = $percentage_string;
    }

    return $result_array;
  }

  /**
   * See if a given string begins with something.
   *
   * @param string $haystack
   * @param string $needle
   * @return bool
   */
  private function startsWith(string $haystack, string $needle) {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
  }

  /**
   * Undocumented function
   *
   * @return array
   */
  private function statusFailResponse() {
    return $this->statusResponse("failed", "No save data for this player.");
  }

  /**
   * Undocumented function
   *
   * @param string $status
   * @param string $message
   * @return array
   */
  private function statusResponse(string $status, string $message) {
    return [
      'meta' => [
        'status' => $stats,
        'message' => $message,
      ]
    ];
  }

}