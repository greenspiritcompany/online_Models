<?php
namespace Telltale\DataMapper;

use Psr\Log\NullLogger;
use Aws\Kinesis\KinesisClient;
use Aws\Kinesis\Exception\KinesisException;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;

use Telltale\DataMapper\ImportantEvents;
use Telltale\DataMapper\ServicesAccount;
use Telltale\DataMapper\ChoiceDocument;

use Telltale\Model\ChoiceDocument as ChoiceDocumentModel;

use PDO;
use PDOException;

use Psr\Cache\CacheItemPoolInterface;

class UserChoices {

  // Sets what region the user choices are coming from
  const AWS_CLIENT_CONFIG = [
    'region' => 'us-west-2',
    'version' => 'latest'
  ];

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

    $this->kinesis = new KinesisClient(self::AWS_CLIENT_CONFIG);
    $this->dynamoDB = new DynamoDbClient(self::AWS_CLIENT_CONFIG);
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
    $choiceDocs = $this->getChoiceDocument($docType, $game);

    if (!$choiceDocs) {
      $this->log->info('No Choice Documents were found.', ['game' => $game, 'type' => $docType]);
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

      // Craft the record being sent to Kinesis
      $kinesisRecord = [
        'StreamName' => $namespace . '-cloud-saves', // The name of the stream to put the data record into.
        'PartitionKey' => uniqid(), // Determines which shard in the stream the data record is assigned to
        'Data' => json_encode([
          'account_id' => $servicesAccountId,
          'game' => $game
        ]),
      ];

      // Write a record into Kinesis to trigger cloud save process
      try {
        $kinesisResult = $this->kinesis->putRecord($kinesisRecord);
      } catch (KinesisException $e) {
        $this->log->error('KinesisException: Failed to submit UserChoices record.', [
          'AWSClientResponse' => $e->getMessage(),
          'kinesisRecord' => $kinesisRecord
        ]);
        return $this->statusFailResponse($docType);
      }

      $this->log->debug('Sent CloudSave player event request via Kinesis', $kinesisResult->toArray());

      // Choices are not ready yet. Try again soon.
      return $this->statusResponse("pending", "Searching for player data.", $docType);
    }

    // Get user choice data from cache
    $userChoiceData = json_decode($servicesCacheItem->get(), true) ?? [];

    // If cache result still has our pendingCacheMessage, continue to return pending response
    if (array_key_exists('status', $userChoiceData) && $userChoiceData['status'] == 'pending') {
      return $this->statusResponse("pending", "Waiting for player data...", $docType);
    }

    $playerEventList = $this->getPlayerEventList($userChoiceData);

    // The resulting player event matches array is empty
    if (empty($playerEventList)) {
      $this->log->info('No player event matches were returned from kinesis.', ['userID' => $userID, 'game' => $game, 'type' => $docType]);
      return $this->statusFailResponse($docType);
    }

    $importantEventsSymbolDictionary = $this->importantEvents->fetchSymbolDictionary($game);

    if (empty($importantEventsSymbolDictionary)) {
      $this->log->warning('No important events were found.', ['game' => $game]);
      return $this->statusFailResponse($docType);
    }

    $playerChoiceDocument = $this->createPlayerChoiceDocument($choiceDocs, $playerEventList, $importantEventsSymbolDictionary);

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

    $logDetails = [
      'game' => $game,
      'type' => $docType,
      'accessPoint' => $accessPointID,
    ];

    // Get the full choice docs for this game
    $choiceDocs = $this->getChoiceDocument($docType, $game);

    if (!$choiceDocs) {
      $this->log->info('No Choice Documents were found.', $logDetails);
      return $this->statusFailResponse('choice_doc');
    }

    // Get user choice data from access point
    $dynamoDBQuery = [
      'TableName' => ucfirst($namespace) . ($namespace != 'staging' ? 'Important' : '') . ucfirst($game) . 'Events',
      'KeyConditions' => [
        'AccessPointId' => [
          'AttributeValueList' => [
            ['S' => (string)$accessPointID]
          ],
          'ComparisonOperator' => 'EQ'
        ]
      ],
    ];

    $dynamoDBResult = $this->dynamoDB->query($dynamoDBQuery);

    if (empty($dynamoDBResult['Items'])) {
      $this->log->info('AccessPoint dynamoDBQuery has no items.', $logDetails);
      return $this->statusFailResponse($docType);
    }

    $importantEventsSymbolDictionary = $this->importantEvents->fetchSymbolDictionary($game);

    if (empty($importantEventsSymbolDictionary)) {
      $this->log->warning('No important events were found.', $logDetails);
      return $this->statusFailResponse($docType);
    }

    // Iterate over the dynamoDBResult AttributeValues, building the playerEventList from the dynamoDBResult items
    $log = $this->log;
    $playerEventList = array_map(function($item) use ($log, $logDetails) {

      // Can't trust results from DynamoDB now?
      if (empty($item['Value'])) {
        $logDetails['item'] = $item;
        $log->warning('dynamoDBResult Choice Item has empty Value', $logDetails);
        return '';
      }

      if (empty($item['Value']['S'])) {
        $logDetails['item'] = $item;
        $log->warning('dynamoDBResult Choice Item has empty Value[S]', $logDetails);
        return '';
      }

      return $item['Value']['S']; // expect this to be the base64_event_value
    }, $dynamoDBResult['Items']);

    $playerChoiceDocument = $this->createPlayerChoiceDocument($choiceDocs, $playerEventList, $importantEventsSymbolDictionary);

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
   * @param array $importantEventsSymbolDictionary
   * @return array
   */
  private function createPlayerChoiceDocument(ChoiceDocumentModel $choiceDocument, array $playerEventList, array $importantEventsSymbolDictionary): array {

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
   * Return a choice document for a specific game
   *
   * @param string $docType
   * @param string $game
   * @return ChoiceDocumentModel|bool
   */
  private function getChoiceDocument(string $docType, string $game) {

    // In cache?
    if ($this->cache) {
      $cacheKey = implode('|', [
        getenv('ENV'),
        'ChoiceDocument',
        $docType,
        $game
      ]);

      $cacheItem = $this->cache->getItem($cacheKey);

      if ($cacheItem->isHit()) {
        return $cacheItem->get();
      }
    }

    // Get the full choice docs for this game
    $choiceDocQuery = new ChoiceDocumentModel();
    $choiceDocQuery->setChoiceDocType($docType);
    $choiceDocQuery->setGameName($game);
    $choiceDocs = $this->choiceDocument->find($choiceDocQuery);

    // Cache the result
    if ($this->cache && $choiceDocs) {
      // Store these results in the cache
      $exp = new \DateInterval('PT1H'); // 1 hour
      $cacheItem->expiresAfter($exp);
      $cacheItem->set($choiceDocs);
      $this->cache->save($cacheItem);
    }

    return $choiceDocs;
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