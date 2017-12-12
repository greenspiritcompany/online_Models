<?php
namespace Telltale\DataMapper;
use Telltale\Model\VanillaUser as VanillaUserModel;
use PDO;
use PDOException;

class VanillaUser {

  const FIELDS = '
    UserID,
    AuthID,
    Name,
    Photo,
    About,
    Email,
    DateOfBirth,
    InsertIPAddress,
    LastIPAddress,
    Banned,
    Points
  ';

  private $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function get(int $userId) {

    $statement = $this->pdo->prepare(
      'SELECT ' . self::FIELDS . '
      FROM GDN_User
      WHERE UserID = :UserID'
    );

    $statement->setFetchMode(PDO::FETCH_CLASS, VanillaUserModel::class);
    $statement->execute(['UserID' => $userId]);
    return $statement->fetch();
  }

  public function find(VanillaUserModel $user) {
    return false;
  }

  /**
    *
    * - BanType is an ENUM and can be 'IPAddress','Name', or 'Email'
    *
    * @throws PDOException If record could not be created
    * @param VanillaUser $user
    * @return bool - if VanillaUser is banned
    */
  public function findIfBanned(VanillaUserModel $user) {

    $banned = $user->getBanned();

    // Check if individually banned
    if ($banned) {
      return true;
    }

    // Get all global bans
    $globalBans = $this->pdo->query('SELECT BanID, BanType, BanValue FROM GDN_Ban')->fetchAll();

    foreach ($globalBans as $ban) {
      if (!isset($ban['BanType'])) {
        continue;
      }

      // Convert ban to regex.
      $parts = explode('*', str_replace('%', '*', $ban['BanValue']));
      $parts = array_map('preg_quote', $parts);
      $regex = '`^'.implode('.*', $parts).'$`i';

      // Get BanType value from user model
      $value = $user->{'get' . $ban['BanType']}();

      // Check the user value against the ban regex
      if (preg_match($regex, $value)) {
        $banned = true;

        // Update blocks count
        $statement = $this->pdo->prepare('UPDATE GDN_Ban SET CountBlockedRegistrations = CountBlockedRegistrations + 1 WHERE BanID = :BanID');
        $statement->execute([
          'BanID' => $ban['BanID']
        ]);
      }
    }

    return $banned;
  }

  /**
    *
    *
    * @throws PDOException If record could not be created
    * @param VanillaUser $user
    * @return VanillaUser|bool - returns VanillaUser if insert statement successful
    */
  public function add(VanillaUserModel $user) {

    $statement = $this->pdo->prepare(
    'INSERT INTO GDN_User (Name, Password, Email, DateOfBirth, AuthID, InsertIPAddress, DateFirstVisit, DateInserted)
      VALUES (:Name, :Password, :Email, :DateOfBirth, :AuthID, :InsertIPAddress, NOW(), NOW())
    ');

    $statement->execute([
      'Name' => $user->getName(),
      'Password' => '',
      'Email' => $user->getEmail(),
      'DateOfBirth' => $user->getDateOfBirth(),
      'AuthID' => $user->getAuthId(),
      'InsertIPAddress' => $user->getInsertIPAddress(),
    ]);

    if ($statement->rowCount() !== 1) {
      throw new PDOException('Vanilla user could not be created');
    }

    // Get the newly created Vanilla user ID
    $userId = $this->pdo->lastInsertId();

    // Update user model with new user ID
    $user->setUserId($userId);

    // Assign user to the default role
    $statement = $this->pdo->prepare('INSERT INTO GDN_UserRole (userID, RoleID) VALUES (:UserID, 2)');
    $statement->execute(['UserID' => $userId]);

    return $user;
  }

  /**
    * Update an existing Vanilla User record using the userId (PK)
    *
    * @throws PDOException If user could not be updated
    * @param VanillaUserModel $user
    * @return bool
    */
  public function update(VanillaUserModel $user) {

    $userId = $user->getUserId();
    $queryHelper = $user->queryUpdateHelper();

    if (!($queryHelper['keys'] && $userId)) {
      throw new PDOException('No Vanilla user fields to update');
    }

    // Add user id a the end of our values array for prepared statement
    $queryHelper['values'][] = $userId;

    $placeholders = implode(',', array_map(function($key){return "$key=?";}, $queryHelper['keys']));

    $placeholders .= ',DateUpdated=NOW()';

    $statement = $this->pdo->prepare("UPDATE GDN_User SET {$placeholders} WHERE userID = ?");
    return $statement->execute($queryHelper['values']);
  }

}