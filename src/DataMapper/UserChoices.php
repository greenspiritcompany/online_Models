<?php
namespace Telltale\DataMapper;

use Psr\Log\NullLogger;

use Telltale\DataMapper\ImportantEvents;
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
  private $servicesAccount;
  private $cache;
  private $servicesCache;
  private $choiceDocument;
  private $kinesis;
  private $dynamoDB;
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
      $this->log->warning('No Choice Documents were found.', ['game' => $game, 'type' => $docType]);
      return $this->statusFailResponse('choice_doc');
    }

    // Get the services account ID for this user
    $servicesAccountId = $this->servicesAccount->getIdFromAuthId($userID);

    if (!$servicesAccountId) {
      $this->log->debug('User has no services account.', ['userID' => $userID, 'game' => $game]);
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
    if (empty($playerEventList)) {
      return $this->statusResponse("pending", "Waiting for player data...", $docType);
    }

    // Determine what the current live episode is for this game
    $currentLiveEpisodeId = '201'; // TODO: Not enforcing this yet

    $importantEventsSymbolDictionary = $this->importantEvents->fetchSymbolDictionary($game);

    if (empty($importantEventsSymbolDictionary)) {
      $this->log->warning('No important events were found.', ['game' => $game]);
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
      $this->log->info('No Choice Documents were found.', ['game' => $game, 'type' => $docType]);
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
      $this->log->warning('No important events were found.', ['game' => $game]);
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
      $this->log->warning('Choice document has no valid choices.', ['game' => $choiceDocument->getGameName(), 'type' => $choiceDocument->getChoiceDocType()]);
      return $this->statusResponse('failed', 'No choice data was found.', $choiceDocument->getChoiceDocType());
    }

    $choiceDocExclude = array_fill_keys(self::CHOICE_DOC_EXCLUDE, null);

    $playerChoiceDoc = [];
    foreach($masterChoiceDoc['choices'] as $choice) {

      if (empty($choice['options'])) {
        $this->log->warning('No choice options were in the choice document.', ['game' => $choiceDocument->getGameName(), 'type' => $choiceDocument->getChoiceDocType()]);
        continue;
      }

      foreach ($choice['options'] as $option) {
        $expr = $option['expression'];

        if (empty($expr)) {
          $this->log->warning('Choice document option has no expression.', ['game' => $choiceDocument->getGameName(), 'type' => $choiceDocument->getChoiceDocType(), 'option' => $option]);
          continue;
        }
        $is_match = $this->evalExpressionString($expr, $playerEventList, $importantEventsSymbolDictionary);
        if ($is_match) {

          // If there is no percent or 0 default to 50%
          if (empty($option['percent']) || $option['percent'] == '0') {
            $this->log->notice('Choice option is missing percent. Defaulting to 50%', ['game' => $choiceDocument->getGameName(), 'type' => $choiceDocument->getChoiceDocType(), 'option' => $option]);
            $option['percent'] = '50';
          }

          // Create a new player choice doc from the choice and matched option
          $playerChoice = array_merge(
            $choice,
            $option,
            [
              'question' => $choice['description'],
              'description' => str_replace('{percent}', $option['percent'], $option['description']),
              'description2' => !empty($option['description2']) ? str_replace('{percent}', $option['percent'], $option['description2']) : null,
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
      $this->log->warning('Player had no matching expressions.', ['game' => $choiceDocument->getGameName(), 'type' => $choiceDocument->getChoiceDocType()]);
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
   * Evaluates an expression string against the player's events using the symbol dictionary
   *
   * @param string $expressionString
   * @param array $playerEvents
   * @param array $symbolDictionary
   * @return bool
   */
  private function evalExpressionString(string $expressionString, array $playerEvents, array $symbolDictionary): bool {

    $expressions = array_filter(explode(" ", $expressionString));

    if (empty($expressions)) {
      $this->log->warning('evalExpressionString has no expressions');
      return false;
    }

    // The expression string can contain these operators
    $allowedOperators = ['(',')','|','&'];

    // Convert the expression into a condition that can be evaluated against player events
    $conditional = '(bool) ';
    foreach ($expressions as $expression) {

      // If this expression is one of our allowed operators append it into the conditional string.
      if (in_array($expression, $allowedOperators)) {
        $conditional .= $expression;
        continue;
      }

      // Check if the expression (event value or dialogue node id) is in the symbol dictionary and translated value is in $playerEvents
      $match = array_key_exists($expression, $symbolDictionary) && in_array($symbolDictionary[$expression], $playerEvents);

      // If expression starts with ~ it should not exist in $playerEvents
      if (strncmp($expression, "~", 1) === 0) {
        $match = !$match;
      }

      $conditional .= (int)$match;
    }

    try {
      // eval should be safe here as $conditional will only contain allowed operators and boolean result of $match
      return eval(sprintf('return %s;', $conditional));
    } catch (\ParseError $e) {
      $this->log->error('Failed to evaluate expression.', [
        'Exception' => $e->getMessage(),
        'conditional' => $conditional,
      ]);
    }

    return false;
  }

  /**
   * Return meta response when no choices were found for a player
   *
   * @return array
   */
  private function statusFailResponse(string $type): array {
    return $this->statusResponse('failed', 'No events were found for this player.', $type);
  }

  /**
   * Return meta status response
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