<?php
namespace Telltale\DataMapper;

use Telltale\Model\LegacyInboxMessage;
use PDO;
use PDOException;
use DateTime;
use DateInterval;
use Psr\Cache\CacheItemPoolInterface;

class LegacyInbox {

  const CACHE_LENGTH = 'P1M';

  private $pdo, $cache;

  public function __construct(PDO $pdo, CacheItemPoolInterface $cachePool) {
    $this->pdo = $pdo;
    $this->cache = $cachePool;
  }

  /**
   * Get the 10 latest messages from a user's inbox earlier than $since
   * Returns array of LegacyInboxMessages 
   *
   */
  public function getMessages(int $userId, string $since = 'now'): array {

    $cacheItem = $this->cache->getItem('inboxMessages|' . $userId . '|since|' . sha1($since));

    // Return from cache if found
    $messages = $cacheItem->get();
    if ($messages) {
      return $messages;
    }

    $dateTime = new DateTime($since);
    $activityDate = $dateTime->format('Y-m-d H:i:s');

    $statement = $this->pdo->prepare(
      'SELECT
        id,
        origin,
        severity,
        status,
        activity_date,
        created_at,
        updated_at,
        content
      FROM inbox
      WHERE user_fk = :userId
        AND status != "deleted"
        AND icon != "digest"
        AND activity_date < :activityDate
      ORDER BY activity_date DESC
      LIMIT 10
    ');

    $statement->setFetchMode(PDO::FETCH_CLASS, LegacyInboxMessage::class);
    $statement->execute([
      'userId' => $userId,
      'activityDate' => $activityDate,
    ]);
    $messages = $statement->fetchAll();

    // Set cache length
    $exp = new DateInterval(self::CACHE_LENGTH);
    $cacheItem->expiresAfter($exp);

    // Cache the result
    $cacheItem->set($messages);
    $this->cache->save($cacheItem);

    return $messages;
  }

  /**
   * Return the count of unread messages in a user's inbox
   *
   */
  public function getUnreadCount(int $userId): int {
    $cacheItem = $this->cache->getItem('inboxMessages|' . $userId . '|count');

    $statement = $this->pdo->prepare('SELECT count(*) FROM inbox WHERE user_fk = :userId AND status = "new"');

    $statement->setFetchMode(PDO::FETCH_NUM);
    $statement->execute([
      'userId' => $userId,
    ]);
    $count = $statement->fetchColumn();

    // Set cache length
    $exp = new DateInterval(self::CACHE_LENGTH);
    $cacheItem->expiresAfter($exp);

    // Cache the result
    $cacheItem->set($count);
    $this->cache->save($cacheItem);

    return $count;
  }

  /**
   * Mark a user's unread messages as read.
   * set $messages to limit the messages being marked as read
   * $messages array of LegacyInboxMessages is optional. If array is empty all the user's messages are marked as read
   *
   */
  public function markAllRead(int $userId, array $messages = []): bool {

    $inputQuery = 'UPDATE inbox SET status = "read", updated_at = NOW() WHERE user_fk = :userId';

    $statementParams = [
      'userId' => $userId,
    ];

    if (count($messages)) {
      $messageIds = [];

      foreach ($messages as $message) {
        if (!$message instanceof LegacyInboxMessage) {
          throw new PDOException('Invalid Message type');
        }

        if ($message->status == 'new') {
          $messageIds[] = $message->id;
        }

      }

      if (!count($messageIds)) {
        // Nothing to mark read
        return false;
      }

      $inputQuery .= ' AND id IN(:ids)';
      $statementParams['ids'] = implode(',', $messageIds);
    }

    $statement = $this->pdo->prepare($inputQuery);
    $statement->execute($statementParams);

    // Clear inbox cache for user
    $cacheItem = $this->cache->deleteItem('inboxMessages|' . $userId);

    return $statement->rowCount() > 0;
  }

}