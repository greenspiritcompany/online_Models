<?php
namespace Telltale\DataMapper;

use Telltale\Model\ChoiceDocument as ChoiceDocumentModel;

use PDO;
use PDOException;
use Psr\Cache\CacheItemPoolInterface;

class ChoiceDocument {

  private $pdo;

  public function __construct(PDO $pdo, CacheItemPoolInterface $cachePool = null) {
    $this->pdo = $pdo;
  }

  /**
   * Returns the newest choice document using the $choiceDocument query
   *
   * @param ChoiceDocumentModel $choiceDocument
   * @return bool|ChoiceDocumentModel
   */
  public function find(ChoiceDocumentModel $choiceDocument) {

    $queryHelper = $choiceDocument->queryHelper();
    if (!$queryHelper['keys']) {
      return false;
    }

    $sourceDocDate = $choiceDocument->getSourceDocDate();
    if (!$sourceDocDate) {

      // Try to get the most recent sourceDocDate for this query
      $statement = $this->pdo->prepare(
        'SELECT MAX(source_doc_date) as source_doc_date
        FROM choice_documents_with_data
        WHERE ' . implode(' = ? AND ', $queryHelper['keys']) . ' = ?
        ORDER BY created_at DESC LIMIT 1'
      );

      $statement->execute($queryHelper['values']);
      $sourceDocDate = $statement->fetchColumn();

      if (!$sourceDocDate) {
        return false;
      }

      // Add sourceDocDate and rebuild the query
      $choiceDocument->setSourceDocDate($sourceDocDate);
      $queryHelper = $choiceDocument->queryHelper();
    }

    // Fetch all data for this choice doc
    $statement = $this->pdo->prepare(
      'SELECT id, source_doc_id, game_name, choice_doc_type, source_doc_date, created_at, updated_at, json_data
      FROM choice_documents_with_data
      WHERE ' . implode(' = ? AND ', $queryHelper['keys']) . ' = ?
      ORDER BY created_at DESC LIMIT 1'
    );

    $statement->setFetchMode(PDO::FETCH_CLASS, ChoiceDocumentModel::class);
    $statement->execute($queryHelper['values']);
    $result = $statement->fetch();

    return $result;
  }


}