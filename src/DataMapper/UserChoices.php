<?php
namespace Telltale\DataMapper;

use Psr\Log\NullLogger;

use Telltale\DataMapper\ImportantEvents;
use Telltale\DataMapper\Game;
use Telltale\DataMapper\ServicesAccount;
use Telltale\DataMapper\ChoiceDocument;
use Telltale\DataMapper\AWSKinesis;
use Telltale\DataMapper\AWSDynamoDB;

use Telltale\Model\ChoiceDocument as ChoiceDocumentModel;

use PDO;
use PDOException;

use Psr\Cache\CacheItemPoolInterface;

class UserChoices {

  const CACHE_NAMESPACE_CHOICE_DATA = 'CloudSaveChoiceData|';

  // What keys should *not* be returned in the choice document?
  const CHOICE_DOC_EXCLUDE = ['count', 'options', 'expression', 'nodeidintval', 'nodeidfullintval'];

  private $importantEvents;
  private $gameData;
  private $servicesAccount;
  private $cache;
  private $servicesCache;
  private $choiceDocument;
  private $kinesis;
  private $log;

  /**
   * Setup new UserChoices data mapper
   *
   * @param PDO $globalPDO
   * @param PDO $servicesPDO
   * @param CacheItemPoolInterface $cache
   * @param CacheItemPoolInterface $servicesCache
   * @param [type] $logger
   */
  public function __construct(PDO $globalPDO, PDO $servicesPDO, CacheItemPoolInterface $cache = null, CacheItemPoolInterface $servicesCache, $logger = null) {

    $this->log = $logger ?? new NullLogger();

    $this->importantEvents = new ImportantEvents($globalPDO, $cache);
    $this->gameData = new Game($globalPDO, $cache);

    $this->servicesAccount = new ServicesAccount($servicesPDO, $cache);
    $this->cache = $cache;
    $this->servicesCache = $servicesCache;

    $this->choiceDocument = new ChoiceDocument($servicesPDO, $cache);

    $this->kinesis = new AWSKinesis($this->log);
    $this->dynamoDB = new AWSDynamoDB($this->log);
  }

  /**
   * Return a player choice document along with meta information
   *
   * @param int $userID
   * @param string $game
   * @param string $docType
   * @return array
   */
  public function fetch(int $userID, string $game, string $docType = 'stats_extended', string $namespace = null): array {

    if (!$namespace) {
      $namespace = getenv('ENV');
    }

    // Has the whole user choice doc been cached?
    if ($this->cache) {

      $cacheKey = implode('|', [
        $namespace,
        'FetchUserChoices',
        $userID,
        $game,
        $docType
      ]);

      $cacheItem = $this->cache->getItem($cacheKey);
      $playerResults = $cacheItem->get();

      if ($cacheItem->isHit()) {
        return $playerResults;
      }
    }

    // Get the full choice docs for this game
    $choiceDocQuery = new ChoiceDocumentModel();
    $choiceDocQuery->setChoiceDocType($docType);
    $choiceDocQuery->setGameName($game);
    $choiceDocs = $this->choiceDocument->find($choiceDocQuery);

    if (!$choiceDocs) {
      $this->log->error('No Choice Documents were found.', ['game' => $game, 'type' => $docType]);
      return $this->statusFailResponse('choice_doc');
    }

    // Get the services account ID for this user
    $servicesAccountId = $this->servicesAccount->getIdFromAuthId($userID);

    if (!$servicesAccountId) {
      $this->log->warning('User has no services account.', ['userID' => $userID, 'game' => $game]);
      return $this->statusFailResponse($docType);
    }

    // Check if the important events have been placed in cache by Kinesis
    $servicesCacheKey = implode('|', [
      '',
      $namespace,
      'CloudSaveChoiceData',
      $servicesAccountId,
      $game
    ]);

    $servicesCacheItem = $this->servicesCache->getItem($servicesCacheKey);

    // If there is no cache hit make the Kinesis request
    if (!$servicesCacheItem->isHit()) {

      // Write a pending message into memcache so we can wait for the kinesis process to return data
      $pendingCacheMessage = json_encode([
        'status' => 'pending',
        'datetime' => date('Y-m-d H:i:s')
      ]);
      $servicesCacheItem->set($pendingCacheMessage);
      $servicesCacheItem->expiresAt(3600);

      $this->servicesCache->save($servicesCacheItem);

      // Trigger the kinesis process
      $this->kinesis->sendCloudSaveProcessRequest($servicesAccountId, $game, $namespace);

      // Choices are not ready yet. Try again soon.
      return $this->statusResponse("pending", "Searching for player data.", $docType);
    }

    // Get user choice data from cache
    $userChoiceData = json_decode($servicesCacheItem->get(), true) ?? [];

    $playerEventList = $this->getPlayerEventList($userChoiceData);

    // If we don't have any matches yet, return a pending message
    if (empty($userChoiceData)) {
      return $this->statusResponse($userChoiceData['status'] ?: "pending", "Waiting for player data...", $docType);
    }

    // Determine what the current live episode is for this game
    $currentLiveEpisodeId = '201'; // TODO: Not enforcing this yet

    $importantEventsSymbolDictionary = $this->importantEvents->fetchSymbolDictionary($game);

    if (empty($importantEventsSymbolDictionary)) {
      $this->log->error('No important events were found.', ['game' => $game]);
      return $this->statusFailResponse($docType);
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
   * Return a player choice document by access point id
   * Returns the same format as $this->fetch but without a userID
   *
   * @param int $accessPointID
   * @param string $game
   * @param string $docType
   * @param string $namespace
   * @return array
   */
  public function fetchByAccessPoint(int $accessPointID, string $game, string $docType = 'stats_extended', string $namespace = null): array {

    if (!$namespace) {
      $namespace = getenv('ENV');
    }

    // Has the whole user choice doc been cached?
    if ($this->cache) {

      $cacheKey = implode('|', [
        $namespace,
        'fetchByAccessPoint',
        $accessPointID,
        $game,
        $docType
      ]);

      $cacheItem = $this->cache->getItem($cacheKey);
      $playerResults = $cacheItem->get();

      if ($cacheItem->isHit()) {
        return $playerResults;
      }
    }

    // Get the full choice docs for this game
    $choiceDocQuery = new ChoiceDocumentModel();
    $choiceDocQuery->setChoiceDocType($docType);
    $choiceDocQuery->setGameName($game);
    $choiceDocs = $this->choiceDocument->find($choiceDocQuery);

    if (!$choiceDocs) {
      $this->log->error('No Choice Documents were found.', ['game' => $game, 'type' => $docType]);
      return $this->statusFailResponse('choice_doc');
    }

    // Check if the important events have been placed in cache
    $servicesCacheKey = implode('|', [
      '',
      $namespace,
      'accessPointChoiceData',
      $accessPointID,
      $game
    ]);

    $servicesCacheItem = $this->servicesCache->getItem($servicesCacheKey);

     // If there is no cache hit get game events from dynamoDB
    if (!$servicesCacheItem->isHit()) {
      // Get user choice data from access point
      $userChoiceData = $this->dynamoDB->accessPointGameEvents($accessPointID, $game, $namespace);
      // Cache in services
      $servicesCacheItem->set($userChoiceData);
      $servicesCacheItem->expiresAt(3600);

      $this->servicesCache->save($servicesCacheItem);
    }

    $playerEventList = $this->getPlayerEventList($servicesCacheItem->get());

    if (empty($playerEventList)) {
      // If user has no events it is safe to exit out early with "no results" response.
      return $this->statusFailResponse($docType);
    }

    $importantEventsSymbolDictionary = $this->importantEvents->fetchSymbolDictionary($game);

    if (empty($importantEventsSymbolDictionary)) {
      $this->log->error('No important events were found.', ['game' => $game]);
      return $this->statusFailResponse($docType);
    }

    // Determine what the current live episode is for this game
    $currentLiveEpisodeId = '201'; // TODO: Not enforcing this yet

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
  private function createPlayerChoiceDocument(ChoiceDocumentModel $choiceDocument, array $playerEventList, int $currentLiveEpisodeId, array $importantEventsSymbolDictionary): array {

    if (empty($playerEventList)) {
      return $this->statusFailResponse($choiceDocument->getChoiceDocType());
    }

    $masterChoiceDoc = $choiceDocument->getData();

    if (empty($masterChoiceDoc['choices'])) {
      $this->log->warning('Choice document has no valid choices ' . $choiceDocument->getChoiceDocType());
      return $this->statusResponse('failed', 'No choice data was found.', $choiceDocument->getChoiceDocType());
    }

    $choiceDocExclude = array_fill_keys(self::CHOICE_DOC_EXCLUDE, null);

    $playerChoiceDoc = [];
    foreach($masterChoiceDoc['choices'] as $choice) {

      if (empty($choice['options'])) {
        $this->log->warning('No choice options were in the choice document for ' . $choiceDocument->getGameName());
        continue;
      }

      foreach ($choice['options'] as $option) {
        $expr = $option['expression'];

        if (empty($expr)) {
          $this->log->warning('Choice option has no expression for ' . $choiceDocument->getGameName());
          continue;
        }
        $is_match = $this->evalExpressionString($expr, $playerEventList, $importantEventsSymbolDictionary);
        if ($is_match) {

          // Create a new player choice doc from the choice and matched option
          $playerChoice = array_merge(
            $choice,
            $option,
            [
              'question' => $choice['description'],
              'description' => $this->putPercentageInDescription($option['description'], $option['percent']),
              'description2' => !empty($option['description2']) ? $this->putPercentageInDescription($option['description2'], $option['percent']) : null,
            ],
            $choiceDocExclude
          );

          // Remove empty keys
          $playerChoice = array_filter($playerChoice);

          $playerChoiceDoc[] = $playerChoice;
        }
      }
    }

    if (empty($playerChoiceDoc)) {
      $this->log->warning('Player had no matching expressions for ' . $choiceDocument->getGameName());
      return $this->statusResponse('failed', 'No matching choice data was found.', $choiceDocument->getChoiceDocType());
    }

    $response = $this->statusResponse('success', 'Events were found for this player.', $choiceDocument->getChoiceDocType());
    $response['choice_document'] = $playerChoiceDoc;

    return $response;
  }

  /**
   * Return the base64 event values that we will use to determine the players choices
   *
   * @param array $userChoiceData
   * @return array
   */
  private function getPlayerEventList(array $userChoiceData): array {

    // If we don't have any matches return empty result
    if (!array_key_exists('matches', $userChoiceData)) {
      return [];
    }

    // For every match, return the base64_event_value
    return array_map(function($match) {
      return $match['base64_event_value'] ?? '';
    }, $userChoiceData['matches']);
  }

  /**
   * To detect if the expression we are looking is a compound query (multiple dialog nodes)
   *
   * @param string $expression_string
   * @param array $player_events
   * @param array $symbol_dictionary
   * @return bool
   */
  private function evalExpressionString(string $expression_string, array $player_events, array $symbol_dictionary): bool {

    $expressions = explode(" ", $expression_string);

    if (empty($expressions)) {
      $this->log->warning('evalExpressionString has no expressions');
      return false;
    }

    $translated_evaluation_string = "";
    foreach ($expressions as $expression) {
      if (strpos($expression,'(') !== false | strpos($expression,')') !== false | strpos($expression,'|') !== false | strpos($expression,'&') !== false) {
        $translated_evaluation_string = $translated_evaluation_string . $expression . " ";
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

    if (empty($translated_evaluation_string)) {
      $this->log->warning('Failed to make a translated evaluation string');
      return false;
    }

    $result = 0;
    try {
      eval(sprintf('$result = %s;', $translated_evaluation_string));
    } catch (\ParseError $e) {
      $this->log->error('Failed to eval translated string: "' . $translated_evaluation_string . '". ' . $e->getMessage());
    }

    return ($result == 1);
  }

  /**
   * To detect if the expression we are looking is a compound query (multiple dialog nodes)
   *
   * @param string $dlg_node
   * @param array $player_events
   * @param array $symbol_dictionary
   * @return bool
   */
  private function doesThisEventExistInPlayersEvents(string $dlg_node, array $player_events, array $symbol_dictionary): bool {
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
  private function putPercentageInDescription(array $description_array, string $percentage): array {
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
  private function startsWith(string $haystack, string $needle): bool {
    $length = strlen($needle);
    return (substr($haystack, 0, $length) === $needle);
  }

  /**
   * Undocumented function
   *
   * @return array
   */
  private function statusFailResponse(string $type): array {
    return $this->statusResponse('failed', 'No events were found for this player.', $type);
  }

  /**
   * Undocumented function
   *
   * @param string $status
   * @param string $message
   * @return array
   */
  private function statusResponse(string $status, string $message, string $type): array {
    return [
      'meta' => [
        'status' => $status,
        'message' => $message,
        'type' => $type,
      ]
    ];
  }

}