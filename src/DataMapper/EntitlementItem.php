<?php
namespace Telltale\DataMapper;

use Telltale\Model\EntitlementItem as ItemModel;
use PDO;
use PDOException;

class EntitlementItem {

  private $pdo;

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  public function get() {
    $statement = $this->pdo->prepare('SELECT * FROM entitlement_items');
    $statement->setFetchMode(PDO::FETCH_CLASS, ItemModel::class);
    $statement->execute();

    return $statement->fetchAll();
  }
}
