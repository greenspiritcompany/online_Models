<?php
namespace Telltale\DataMapper;

use Telltale\Model\User as UserModel;
use PDO;
use PDOException;
use Psr\Cache\CacheItemPoolInterface;

class User {

  const FIELDS = '
    id,
    customer_id,
    forum_id,
    username,
    email,
    password,
    pw_salt,
    change_pw,
    birthdate,
    email_salt,
    email_verify,
    created_at,
    updated_at,
    last_login_at,
    disabled
  ';

  private $pdo;
  private $cache;

  public function __construct(PDO $pdo, CacheItemPoolInterface $cachePool) {
    $this->pdo = $pdo;
    $this->cache = $cachePool;
  }

  /**
    * Get a single user by their PK
    * Returns false if no record is found
    *
    * @param int $userId
    * @return UserModel|bool
    */
  public function get(int $userId) {
    $cacheItem = $this->cache->getItem('users|' . $userId);

    // Return user from cache if found
    $user = $cacheItem->get();
    if ($user) {
      return $user;
    }

    $statement = $this->pdo->prepare(
      'SELECT ' . self::FIELDS . '
      FROM users
      WHERE id = :id'
    );

    $statement->setFetchMode(PDO::FETCH_CLASS, UserModel::class);
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    // Set user cache length
    $exp = new \DateInterval('P1D');
    $cacheItem->expiresAfter($exp);

    // Save user into cache
    $cacheItem->set($user);
    $this->cache->save($cacheItem);

    return $user;
  }

  /**
    * Get a single user by userModel
    * Returns false if no record is found
    *
    * @throws PDOException
    * @param UserModel $user
    * @return UserModel|bool
    */
  public function find(UserModel $user) {

    $queryHelper = $user->queryHelper();
    if (!$queryHelper['keys']) {
      throw new PDOException('No user fields to find');
    }

    $wheres = implode(' = ? AND ', $queryHelper['keys']) . ' = ?';

    $statement = $this->pdo->prepare(
      'SELECT ' . self::FIELDS . '
      FROM users WHERE ' . $wheres
    );

    $statement->setFetchMode(PDO::FETCH_CLASS, UserModel::class);
    $statement->execute($queryHelper['values']);

    return $statement->fetch();
  }

  /**
    * Get count of users matching UserModel
    *
    * @param UserModel $user
    * @return int
    */
  public function count(UserModel $user): int {
    $queryHelper = $user->queryHelper();
    if (!$queryHelper['keys']) {
      return 0;
    }

    $statement = $this->pdo->prepare(
      'SELECT count(*) FROM users
      WHERE ' . implode(' = ? AND ', $queryHelper['keys']) . ' = ?'
    );
    $statement->setFetchMode(PDO::FETCH_NUM);
    $statement->execute($queryHelper['values']);
    return $statement->fetchColumn();
  }

  /**
    * Add a new user to the database
    * Returns the updated userModel with new userID (PK)
    *
    * @throws PDOException If user could not be created
    * @param UserModel $user
    * @return UserModel
    */
  public function add(UserModel $user) {

    $statement =  $this->pdo->prepare(
      'INSERT INTO users (email, email_salt, password, birthdate, created_at, updated_at, last_login_at)
      VALUES (:email, :email_salt, :password, :birthdate, NOW(), NOW(), NOW())'
    );

    // Email & Password can be null in the auth users table so we must check here
    // @TODO: Update users schema so these fields cannot be null
    $email = $user->getEmail();
    $password = $user->getPassword();
    if (!($email && $password)) {
      throw new PDOException('Missing required user fields');
    }

    $statement->execute([
      'email' => $email,
      'password' => $password,
      'email_salt' => $user->getEmailVerificationKey(),
      'birthdate' => $user->getBirthdate(),
    ]);

    if ($statement->rowCount() !== 1) {
      throw new PDOException('User could not be created');
    }

    // Update user model with values that are set by DB insert.
    $user->setId($this->pdo->lastInsertId());
    $user->setLastLogin('now');
    $user->setCreatedAt('now');

    // Cache the user
    $this->cacheUser($user);

    return $user;
  }

  /**
    * Update an existing user record using the userId (PK)
    *
    * @throws PDOException If user could not be updated
    * @param UserModel $user
    * @return bool
    */
  public function update(UserModel $user) {
    $userId = $user->getId();

    if (!$userId) {
      throw new PDOException('Unable to update user record', 400);
    }

    if (!$user->hasUpdates()) {
      throw new PDOException('No user updates were made', 409);
    }

    $queryHelper = $user->queryUpdateHelper();

    // Add user id a the end of our values array for prepared statement
    $queryHelper['values'][] = $userId;

    $placeholders = implode(',', array_map(function($key){return "$key=?";}, $queryHelper['keys']));

    // Always update updated_at unless only the update is last_login_at
    if (false === (in_array('last_login_at', $queryHelper['keys']) && count($queryHelper['keys']) == 1)) {
      $placeholders .= ',updated_at=NOW()';
      $user->setLastLogin('now');
    }

    // Update the cached user record
    $this->cacheUser($user);

    $statement = $this->pdo->prepare("UPDATE users SET {$placeholders} WHERE id = ?");
    return $statement->execute($queryHelper['values']);
  }

  /**
    * Get a user by their auth token
    *
    * @throws Exception If token mismatch
    * @param string $token
    * @return UserModel
    */
  public function fromToken(string $token): UserModel {
    // $userId: TTG user id from the auth/users table
    // $time: When the token was issued
    // $hash: Used to sign the token
    list($userId, $time, $hash) = array_pad(explode(',', $token), 3, 0);

    if (!((int)$userId > 0 && $time && $hash)) {
      throw new \Exception('Invalid User Token', 400);
    }

    $user = $this->get($userId);

    if (!$user) {
      throw new \Exception('User Not Found', 404);
    }

    // User must be enabled
    if ($user->getDisabled()) {
      throw new \Exception('Account Disabled', 403);
    }

    // Token must still match the user's
    $expectedToken = $user->authToken($time);
    if (!hash_equals($token,$expectedToken)) {
      throw new \Exception('Token Validation Failed', 401);
    }

    return $user;
  }

  /**
    * Cache the user record
    *
    * @param UserModel $user
    * @return CacheItemPoolInterface
    */
  private function cacheUser(UserModel $user) {
    $cacheItem = $this->cache->getItem('users|' . $user->getId());

    // Set user cache length
    $exp = new \DateInterval('P1D');
    $cacheItem->expiresAfter($exp);

    // Save user into cache
    $cacheItem->set($user);
    $this->cache->save($cacheItem);
    return $cacheItem;
  }

}