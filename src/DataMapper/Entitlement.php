<?php
namespace Telltale\DataMapper;

use Telltale\Model\Entitlement as EntitlementModel;
use PDO;
use PDOException;

class Entitlement {

  private $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  /**
    * Get full records from the entitlement table
    *
    * @param EntitlementModel $entitlement
    * @return array of EntitlementModel
    */
  public function find(EntitlementModel $entitlement) {

    $queryHelper = $entitlement->queryHelper();
    if (!$queryHelper['keys']) {
      return [];
    }

    $statement = $this->pdo->prepare(
      'SELECT relation, user_id, item, game, platform, source, source_id, created_at, attributes, enabled
      FROM entitlement
      WHERE ' . implode(' = ? AND ', $queryHelper['keys']) . ' = ?
      ORDER BY created_at DESC'
    );

    $statement->setFetchMode(PDO::FETCH_CLASS, EntitlementModel::class);
    $statement->execute($queryHelper['values']);

    return $statement->fetchAll();
  }

  /**
    * Find an entitlement by code
    *
    * @param string $code
    * @return EntitlementModel|bool
    */
  public function findByCode(string $code) {
    $statement = $this->pdo->prepare(
      'SELECT entitlement.relation, entitlement.item, entitlement.game, entitlement.platform, codes.code as source_id, codes.used
      FROM entitlement_codes as codes
      LEFT JOIN entitlement_items as entitlement ON codes.item_id = entitlement.id
      WHERE codes.code=:code AND codes.used != 1 AND (entitlement.expires_at IS NULL OR entitlement.expires_at > NOW())'
      );
    $statement->setFetchMode(PDO::FETCH_CLASS, EntitlementModel::class);
    $statement->execute(['code' => $code]);

    // Is this code available and unused?
    if ($statement->rowCount() !== 1) {
      return false;
    }

    // Return entitlement attached to the code
    $entitlement = $statement->fetch();
    $entitlement->setSource('entitlement_code');

    return $entitlement;
  }

  /**
    * Add new entitlement to the db
    * Throws PDOException on missing fields or existing entitlement
    * 
    * @param EntitlementModel $entitlement
    */
  public function add(EntitlementModel $entitlement) {
    $statement = $this->pdo->prepare(
      'INSERT INTO entitlement (user_id, relation, item, game, platform, source, source_id, attributes)
      VALUES (:user_id, :relation, :item, :game, :platform, :source, :source_id, :attributes)'
    );
    $statement->execute([
      'user_id' => $entitlement->getUserId(),
      'relation' => $entitlement->getRelation(),
      'item' => $entitlement->getItem(),
      'game' => $entitlement->getGame(),
      'platform' => $entitlement->getPlatform(),
      'source' => $entitlement->getSource(),
      'source_id' => $entitlement->getSourceId(),
      'attributes' => json_encode($entitlement->getAttributes()),
    ]);
  }

  /**
    * Update an entitlement record
    * Note: At this time only the enabled and attributes fields can be changed
    * 
    * @param EntitlementModel $entitlement
    */
  public function update(EntitlementModel $entitlement) {

    $statement = $this->pdo->prepare(
      'UPDATE entitlement SET enabled = :enabled, attributes = :attributes, updated_at = NOW()
      WHERE user_id = :user_id AND relation = :relation AND item = :item AND game = :game AND platform = :platform'
    );
    $statement->execute([
      'user_id' => $entitlement->getUserId(),
      'relation' => $entitlement->getRelation(),
      'item' => $entitlement->getItem(),
      'game' => $entitlement->getGame(),
      'platform' => $entitlement->getPlatform(),
      'enabled' => (int)$entitlement->getEnabled(),
      'attributes' => json_encode($entitlement->getAttributes()),
    ]);
  }

  /**
    * Redeem an entitlement by code and assign it to a user
    * 
    * @param string $code
    * @param int $userId
    * @return EntitlementModel $entitlement
    */
  public function redeem(string $code, int $userId) {

    if (strlen($code) > 255) {
      return false;
    }

    // Check whether this code was found and has not been previously redeemed
    $entitlement = $this->findByCode($code);

    if (!$entitlement) {
      return false;
    }

    // Assign this entitlement to the user
    $entitlement->setUserId($userId);

    // Attempt to create the entitlement
    $this->add($entitlement);

    // Mark entitlement as used
    $this->markAsUsed($entitlement);

    return $entitlement;
  }

  /**
    * Mark an entitlement as used if applicable
    *
    * @param EntitlementModel $entitlement
    * @return EntitlementModel
    */
  public function markAsUsed(EntitlementModel $entitlement) {

    if ($entitlement->getSource() != 'entitlement_code') {
      throw new PDOException('markAsUsed only allows entitlements sourced from entitlement codes', 400);
    }

    $code = $entitlement->getSourceId();

    if (!$code || $entitlement->getUsed() == true) {
      return $entitlement;
    }

    $statement = $this->pdo->prepare("UPDATE entitlement_codes SET used = 1, updated_at = NOW() WHERE code = :code");
    $statement->execute(['code' => $code]);

    $entitlement->setUsed(true);

    return $entitlement;
  }

}

