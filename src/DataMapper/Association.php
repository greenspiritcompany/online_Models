<?php
namespace Telltale\DataMapper;

use Telltale\Model\Association as AssociationModel;
use League\OAuth2\Client\Token\AccessToken;
use PDO;
use PDOException;

class Association {

  private $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function find(AssociationModel $association) {
    $queryHelper = $association->queryHelper();
    if (!$queryHelper['keys']) {
      return [];
    }

    $statement = $this->pdo->prepare(
      'SELECT user_id, provider, identifier, access_token, created_at, updated_at
      FROM association
      WHERE ' . implode(' = ? AND ', $queryHelper['keys']) . ' = ? AND identifier != "0"
      ORDER BY created_at DESC'
    );

    $statement->setFetchMode(PDO::FETCH_CLASS, AssociationModel::class);
    $statement->execute($queryHelper['values']);

    return $statement->fetchAll();
  }

  public function add(AssociationModel $association) {
    $statement = $this->pdo->prepare(
      'INSERT INTO association (user_id, provider, identifier, access_token)
       VALUES (:user_id, :provider, :identifier, :access_token)
       ON DUPLICATE KEY UPDATE updated_at = NOW(), identifier = :identifier_update, access_token = :access_token_update'
    );

    $identifier = $association->getIdentifier();
    $accessToken = $association->getAccessToken();

    if ($accessToken instanceof AccessToken) {
      $accessToken = json_encode($accessToken);
    }

    $statement->execute([
      'user_id' => $association->getUserId(),
      'provider' => $association->getProvider(),
      'identifier' => $identifier,
      'identifier_update' => $identifier,
      'access_token' => $accessToken,
      'access_token_update' => $accessToken,
    ]);
  }

  public function delete(AssociationModel $association) {

    // We can't delete the record because Services may need to also un-associate the account
    // If we just deleted the record Services will just add it back next time a game is launched.
    $statement = 
    'UPDATE association
      SET identifier = "0", access_token = NULL, updated_at = NOW()
      WHERE user_id = :user_id';
    $statementParams = ['user_id' => $association->getUserId()];

    $provider = $association->getProvider();

    if ($provider) {
      $statement .= ' AND provider = :provider';
      $statementParams['provider'] = $provider; 
    }

    $statement = $this->pdo->prepare($statement);
    $statement->execute($statementParams);

    return $statement->rowCount() > 0;
  }

}