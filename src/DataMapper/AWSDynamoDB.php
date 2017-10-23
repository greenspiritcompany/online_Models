<?php
namespace Telltale\DataMapper;

use Aws\DynamoDb\DynamoDBClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Psr\Log;

class AWSDynamoDB {

  private $client;
  private $logger;

  public function __construct(Log\LoggerInterface $logger = null) {
    $this->logger = $logger ?? new Log\NullLogger();

    $config = [
      'region' => 'us-west-2',
      'version' => 'latest'
    ];
    $this->client = new DynamoDBClient($config);
  }

  /**
   * Query our region's DynamoDB
   *
   * @param string $tableName
   * @param array $keyConditions
   * @return mixed
   */
  public function query(string $tableName, array $keyConditions) {
    return $this->client->query([
      'TableName' => $tableName,
      'KeyConditions' => $keyConditions,
    ]);
  }

   /**
   * Get info for a table in DynamoDB
   *
   * @param string $tableName
   * @return array
   */
  public function describeTable(string $tableName) {
    $response = $this->client->describeTable([
      'TableName' => $tablename,
    ]);

    $result = $response->toArray();

    return $result['@metadata'];
  }

  /**
   * Get a users important events from DynamoDB
   *
   * @param int $accessPointID - User access point id
   * @param string $game - Game name, used to identify the dynamo table
   * @param string $environment - Environment, used to identify the dynamo table
   * @return array
   */
  public function accessPointGameEvents(int $accessPointID, string $game, string $environment = 'production'): array {
    $tableName = ucfirst(strtolower($environment)) . 'Important' . ucfirst(strtolower($game)) . 'Events';

    // Format the query parameters
    $params = [
      'TableName' => $tableName,
      'KeyConditions' => [
        'AccessPointId' => [
          'AttributeValueList' => [
            ['S' => (string)$accessPointID]
          ],
          'ComparisonOperator' => 'EQ'
        ]
      ],
    ];

    // Query DynamoDb
    try {
      $result = $this->client->query($params);
    } catch (DynamoDbException $e) {
      $this->logger->error('Unable fetch ' . $tableName . ' for ' . $accessPointID .  '. ' . $e->getMessage());
      throw new \Exception('Unable fetch game events.', 500);
    }

    if (!isset($result['Items'])) {
      $this->logger->warning('No items from ' . $tableName . ' for ' . $accessPointID .  '.');
      return [];
    }

    // Handle the results
    $matches = [];
    foreach ($result['Items'] as $item) {

      if (!(isset($item['Type']['S']) && isset($item['Value']['S']))) {
        continue;
      }

      $matches[] = [
        'int_event_type' => $item['Type']['S'],
        'base64_event_value' => $item['Value']['S'],
      ];
    }

    return [
      'matches' => $matches
    ];
  }

}