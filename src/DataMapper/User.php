<?php
namespace Telltale\DataMapper;

use Telltale\Model\User as UserModel;
use PDO;
use PDOException;

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
    created_user_id,
    updated_user_id,
    last_login_at,
    disabled
  ';

  private $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  /**
    * Get a single user by their PK
    * Returns false if no record is found
    *
    * @param int $userId
    * @return UserModel|bool
    */
  public function get(int $userId) {

    $statement = $this->pdo->prepare(
      'SELECT ' . self::FIELDS . '
      FROM users
      WHERE id = :id'
    );

    $statement->setFetchMode(PDO::FETCH_CLASS, UserModel::class);
    $statement->execute(['id' => $userId]);
    return $statement->fetch();
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
      'INSERT INTO users (email, email_salt, password, created_at, updated_at, last_login_at)
      VALUES (:email, :email_salt, :password, NOW(), NOW(), NOW())'
    );

    $email = $user->getEmail();
    $password = $user->getPassword();
    $emailKey = $user->getEmailVerificationKey();

    // @TODO: These fields should be required in the users table!
    if (!($email && $password && $emailKey)) {
      throw new PDOException('Missing required user fields');
    }

    $statement->execute([
      'email' => $email,
      'password' => $password,
      'email_salt' => $emailKey,
    ]);

    if ($statement->rowCount() !== 1) {
      throw new PDOException('User could not be created');
    }

    // Update user model with new user ID
    $user->setId($this->pdo->lastInsertId());
    $user->setLastLogin('now');

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
    $queryHelper = $user->queryUpdateHelper();

    if (!($queryHelper['keys'] && $userId)) {
      throw new PDOException('No user fields to update');
    }

    // Add user id a the end of our values array for prepared statement
    $queryHelper['values'][] = $userId;

    $placeholders = implode(',', array_map(function($key){return "$key=?";}, $queryHelper['keys']));

    // Always update updated_at unless only the update is last_login_at
    if (false === (in_array('last_login_at', $queryHelper['keys']) && count($queryHelper['keys']) == 1)) {
      $placeholders .= ',updated_at=NOW()';
    }

    $statement = $this->pdo->prepare("UPDATE users SET {$placeholders} WHERE id = ?");
    return $statement->execute($queryHelper['values']);
  }

  /**
    * Has a user record been updated since $datetime
    *
    * @param int $user ID
    * @param string datetime
    * @return bool
    */
  public function hasUpdated(int $userId, string $datetime): bool {
    $statement = $this->pdo->prepare(
      'SELECT 1
      FROM users
      WHERE id = :id AND updated_at > :last_updated'
    );

    // Validate and format datetime
    $datetime = new DateTime($datetime);
    $datetime = $datetime->format('Y-m-d H:i:s');

    $statement->execute([
      'id' => $userId,
      'last_updated' => $datetime,
    ]);
    return (bool)$statement->fetchColumn();
  }

}