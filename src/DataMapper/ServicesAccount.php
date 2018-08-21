<?php
namespace Telltale\DataMapper;

use Telltale\Model\ServicesAccount as ServicesAccountModel;
use PDO;
use PDOException;
use Psr\Cache\CacheItemPoolInterface;

class ServicesAccount {

  private $pdo;
  private $cache;

  public function __construct(PDO $pdo, CacheItemPoolInterface $cachePool = null) {
    $this->pdo = $pdo;
    $this->cache = $cachePool;
  }

  /**
   * Get a single user by ServicesAccount query
   * Returns false if no record is found
   *
   * @param ServicesAccountModel $servicesAccount
   * @return ServicesAccount|bool
   */
  public function find(ServicesAccountModel $servicesAccount) {
    $queryHelper = $servicesAccount->queryHelper();
    if (!$queryHelper['keys']) {
      return false;
    }

    $statement = $this->pdo->prepare(
      'SELECT id, username, ttg_account_id, created_at
      FROM account
      WHERE ' . implode(' = ? AND ', $queryHelper['keys']) . ' = ?
      ORDER BY created_at DESC LIMIT 1'
    );

    $statement->setFetchMode(PDO::FETCH_CLASS, ServicesAccountModel::class);
    $statement->execute($queryHelper['values']);

    return $statement->fetch();
  }

  /**
   * Convenience function to get the Services Account ID from the Telltale Auth ID
   *
   * @param int $userId Auth ID
   * @return int Services Account ID
   */
  public function getIdFromAuthId(int $userId): int {

    if ($this->cache) {
      $cacheItem = $this->cache->getItem('servicesAccount|AuthId|' . $userId);

      // Return user from cache if found
      $accountID = $cacheItem->get();
      if ($accountID) {
        return $accountID;
      }
    }

    $statement = $this->pdo->prepare('SELECT id from account WHERE ttg_account_id = :userId AND password != "anonymous" ORDER BY created_at DESC LIMIT 1');
    $statement->execute([
      'userId' => $userId
    ]);

    $result = $statement->fetch(PDO::FETCH_ASSOC);
    $accountID = $result['id'] ?: 0;

    if ($accountID > 0 && $this->cache) {
      // Cache the result
      $exp = new \DateInterval('P1D');
      $cacheItem->expiresAfter($exp);

      // Save user into cache
      $cacheItem->set($accountID);
      $this->cache->save($cacheItem);
    }

    return $accountID;
  }

}