<?php
namespace Telltale\DataMapper;
use Telltale\Model\User as UserModel;
use PDO;
use PDOException;

class EmailQueue {
  private $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  /**
    * Add a user to the email_queue database
    * - Optionally set the emailType
    * - The other side of the email_queue is handled by Services
    * - Returns the updated userModel with new userID (PK)
    *
    * @throws PDOException If record could not be created
    * @param UserModel $userData
    * @param string $emailType
    * @return bool - true if insert statement successful
    */
  public function add(UserModel $userData, string $emailType = 'auth_account_creation') {

    $statement = $this->pdo->prepare(
    'INSERT INTO email_queue (user_id, email_type, platform, created_at, status, newsletter)
      VALUES (:user_id, :type, "website", NOW(), "pending", 1)
    ');

    return $statement->execute([
      'user_id' => $userData->getId(),
      'type' => $emailType,
    ]);
  }

}