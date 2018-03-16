<?php
namespace Telltale\DataMapper;

use PDO;
use PDOException;
use Psr\Cache\CacheItemPoolInterface;

use Telltale\Model\AdminUser as AdminUserModel;

class AdminUser {

  const CACHE_NAMESPACE = 'authUser|';

  private $pdo, $cache;

  public function __construct(PDO $pdo, CacheItemPoolInterface $cachePool) {
    $this->pdo = $pdo;
    $this->cache = $cachePool;
  }

  /**
    * Get a single admin user from cache by their username
    * Returns false if no record is found
    *
    * @param string $username
    * @return AdminUserModel|bool
    */
  public function get(string $username) {
    $cacheItem = $this->cache->getItem(self::CACHE_NAMESPACE . $username);

    // User in cache?
    $user = $cacheItem->get();
    $cacheHit = $cacheItem->isHit();

    // Try the database
    if (!$cacheHit) {
      $statement = $this->pdo->prepare(
        'SELECT username, fullname as name, jira_session as jiraSession
        FROM admin_user WHERE username = :username'
      );

      $statement->setFetchMode(PDO::FETCH_CLASS, AdminUserModel::class);
      $statement->execute(['username' => $username]);
      $user = $statement->fetch();
    }

    // If user has no permissions always check if some were added
    if ($user instanceof AdminUserModel && !$user->getPermissions()) {
      $user = $this->getPermissions($user);
    }

    if (!$cacheHit) {
      // Set user cache length
      $exp = new \DateInterval('P1D');
      $cacheItem->expiresAfter($exp);

      // Save user into cache
      $cacheItem->set($user);
      $this->cache->save($cacheItem);
    }

    return $user;
  }

  /**
    * Store an admin user in cache
    * Returns the updated userModel with new userID (PK)
    *
    * @throws PDOException If user could not be created
    * @param UserModel $user
    * @return UserModel
    */
  public function store(AdminUserModel $user) {
    $username = $user->getUsername();
    $cacheItem = $this->cache->getItem(self::CACHE_NAMESPACE . $username);

    // Store in DB
    $statement = $this->pdo->prepare(
      'INSERT INTO admin_user (username, fullname, jira_session)
      VALUES (:username, :name, :jira_session)
      ON DUPLICATE KEY UPDATE updated_at = NOW(), jira_session = :jira_session_update'
    );

    $jiraSession = $user->getJiraSession();

    $statement->execute([
      'username' => $username,
      'name' => $user->getName(),
      'jira_session' => $jiraSession,
      'jira_session_update' => $jiraSession,
    ]);

    // Set user cache length
    $exp = new \DateInterval('P1D');
    $cacheItem->expiresAfter($exp);

    // Save user into cache
    $cacheItem->set($user);
    $this->cache->save($cacheItem);
    return $user;
  }

  /**
    * Get a single admin user from an admin auth token
    * Throws exception if token is invalid
    *
    * @param string $token
    * @return AdminUserModel
    */
  public function fromToken(string $token): AdminUserModel {

    list($username, $time, $hash) = explode(',', $token);

    if (!($username && !is_numeric($username) && $time && $hash)) {
      throw new \Exception('Invalid User Admin Token', 400);
    }

    $user = $this->get($username);

    if (!$user) {
      throw new \Exception('Session Expired', 401);
    }

    // Token must still match the user's
    $expectedToken = $user->authToken($time);
    if (!hash_equals($expectedToken, $token)) {
      throw new \Exception('Token Validation Failed', 401);
    }

    return $user;
  }

  /**
    * Add in admin user permissions from the shared table
    *
    * @param AdminUserModel $user
    * @return AdminUserModel
    */
  public function getPermissions(AdminUserModel $user): AdminUserModel {

    $statement = $this->pdo->prepare(
      'SELECT et.entitlement as permissions
      FROM admin_user_entitlement as ue LEFT JOIN admin_entitlement_types AS et ON ue.entitlement_type = et.id
      WHERE ue.username = :username'
    );

    $statement->execute(['username' => $user->getUsername()]);
    $permissions = $statement->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!$permissions) {
      return $user;
    }

    return $user->setPermissions($permissions);
  }

}