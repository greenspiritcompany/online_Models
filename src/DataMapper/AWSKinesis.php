<?php
namespace Telltale\DataMapper;

use Aws\Kinesis\KinesisClient;
use Psr\Log;

class AWSKinesis {
  private static $instance;
  private static $kinesis;
  private $logger;

  public static function getInstance($logger) {
    if (null === static::$instance) {
      static::$instance = new AWSKinesis($logger);
    }

    return static::$instance;
  }

  public function __construct(Log\LoggerInterface $logger = null) {
    $this->logger = $logger ?? new Log\NullLogger();
    $config = [
      'region' => 'us-west-2',
      'version' => 'latest'
    ];
    static::$kinesis = KinesisClient::factory($config);
  }

  /**
   * Trigger the kinesis process to get CloudSave data
   *
   * @param int $account_id
   * @param string $game
   * @param string $environment
   * @return bool
   */
  public function sendCloudSaveProcessRequest(int $account_id, string $game, string $environment = 'production') {
    // The name of the stream includes the environment to keep them separated.
    $kinesis_stream_name = $environment.'-cloud-saves';

    // There is a single record being updated via this mechanism, it's a TSV including the HTTP headers
    // of the request and the POST body in string format.
    $kinesis_records = $this->createRecordList($kinesis_stream_name);

    $document = json_encode([
      'account_id' => $account_id,
      'game' => $game
    ]);

    $this->addDocumentToRecords($kinesis_records, $document);

    // The Kinesis Client is treated as a singleton instance.
    $kinesis = $this->getInstance($this->logger);

    try {
      $kinesis->putRecords($kinesis_records);
    } catch (Aws\Kinesis\Exception\ResourceNotFoundException $e) {
      $this->logger->error('Unable to find the kinesis stream ' . $kinesis_stream_name);
      return false;
    } catch (Aws\Kinesis\Exception\KinesisException $e) {
      $this->logger->error('Unable to send a document to ' . $kinesis_stream_name);
      return false;
    } catch (\Exception $e) {
      $this->logger->error('Unable to send a document to ' . $kinesis_stream_name);
      throw new \Exception('Unable to send CloudSave Process Request. ' . $e->getMessage(), 500);
    }

    return true;
  }

  /**
   * Attempt to put records into Kinesis.
   *
   * @param array $records
   * @return array Record keys that failed to be placed in Kinesis
   */
  private function putRecords(array $records): array {
    $kinesis_response = static::$kinesis->putRecords($records);

    $failed_records = [];
    foreach ($kinesis_response['Records'] as $key => $record) {
      if (isset($record['ErrorCode'])) {
        $this->logger->error($record['ErrorCode'].": ".$record['ErrorMessage']);
        $failed_records[] = $key;
      }
    }

    return $failed_records;
  }

  /**
   * Return a new records stream
   *
   * @param string $stream_name
   * @return array
   */
  private function createRecordList(string $stream_name): array {
    return [
      'Records' => [],
      'StreamName' => $stream_name
    ];
  }

  /**
   * Push a document string into the records array
   *
   * @param array $records
   * @param string $document
   * @return void
   */
  private function addDocumentToRecords(array &$records, string $document) {
    array_push($records['Records'], [
      'Data' => $document,
      'PartitionKey' => uniqid()
    ]);
  }
}