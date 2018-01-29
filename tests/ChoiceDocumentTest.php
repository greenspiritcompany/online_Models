<?php
namespace Telltale\Tests;

use Telltale\DataMapper\ChoiceDocument;
use Telltale\Model\ChoiceDocument as ChoiceDocumentModel;

class ChoiceDocumentTest extends AbstractDBTest {

  private $choiceDocument;

    public function __construct() {
      parent::__construct();

      $this->setupTableSchema('choice_documents_with_data', '
        `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
        `source_doc_id` int(11) DEFAULT NULL,
        `game_name` varchar(16) DEFAULT NULL,
        `choice_doc_type` varchar(20) DEFAULT NULL,
        `source_doc_date` varchar(20) DEFAULT NULL,
        `created_at` datetime DEFAULT NULL,
        `updated_at` datetime DEFAULT NULL,
        `json_data` longtext,
        PRIMARY KEY (`id`)
      ');

      $this->choiceDocument = new ChoiceDocument($this->getPDO(), $this->getCache());
    }

    protected function getDataSet() {
      return $this->createMySQLXMLDataSet(dirname(__FILE__) . '/../datasets/choice_documents_with_data.xml');
    }

    public function testFindChoices() {

      $choiceQuery = new ChoiceDocumentModel();
      $choiceQuery->setGameName('minecraft2');

      $result = $this->choiceDocument->find($choiceQuery);

      $this->assertInstanceOf(ChoiceDocumentModel::class, $result);

      $this->assertEquals('stats_relationships', $result->getChoiceDocType());
      $this->assertArrayHasKey('choices', $result->getData());
    }

    public function testGetMostRecentlyCreated() {
      $choiceQuery = new ChoiceDocumentModel();
      $choiceQuery->setChoiceDocType('stats_extended');

      $result = $this->choiceDocument->find($choiceQuery);
      $this->assertInstanceOf(ChoiceDocumentModel::class, $result);

      $this->assertEquals('batman2', $result->getGameName());
    }

    public function testGetSpecificDoc() {
      $choiceQuery = new ChoiceDocumentModel();
      $choiceQuery->setChoiceDocType('stats_choices');
      $choiceQuery->setGameName('minecraft2');
      $choiceQuery->setSourceDocDate('2017-12-01-02-01');
      $choiceQuery->setCreatedAt('2017-12-01 10:12:00');
      $choiceQuery->setUpdatedAt('2017-12-06 00:38:15');

      $result = $this->choiceDocument->find($choiceQuery);
      $this->assertInstanceOf(ChoiceDocumentModel::class, $result);
      $this->assertEquals('minecraft2', $result->getGameName());
      $this->assertEquals('stats_choices', $result->getChoiceDocType());
    }

    public function testGetNoResult() {
      $choiceQuery = new ChoiceDocumentModel();
      $choiceQuery->setChoiceDocType('stats_testing');
      $choiceQuery->setSourceDocDate('2017-12-01-02-01');

      $result = $this->choiceDocument->find($choiceQuery);
      $this->assertFalse($result);
    }

}
