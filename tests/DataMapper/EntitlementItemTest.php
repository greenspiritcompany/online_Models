<?php
namespace Telltale\Tests;

use Telltale\DataMapper\EntitlementItem;
use Telltale\Model\EntitlementItem as EntitlementItemModel;

class EntitlementItemTest extends AbstractDBTest {

  private $entitlementItem;

  public function __construct() {

    $this->setupTableSchema('entitlement_items', "
      `id` int(11) unsigned NOT NULL,
      `item` varchar(34) NOT NULL DEFAULT '',
      `relation` varchar(34) NOT NULL DEFAULT '',
      `game` varchar(34) NOT NULL DEFAULT '',
      `platform` varchar(16) NOT NULL DEFAULT '',
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL,
      `expires_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`)
    ");

    $this->entitlementItem = new EntitlementItem($this->getPDO(), $this->getCache());
  }

  protected function getDataSet() {
    return $this->createArrayDataSet([
      'entitlement_items' => [
        [
          'id' => 1,
          'item' => 'foo',
          'relation' => 'foo-relation',
          'game' => 'foo-game',
          'platform' => 'foo-platform'
        ],
        [
          'id' => 2,
          'item' => 'bar',
          'relation' => 'bar-relation',
          'game' => 'bar-game',
          'platform' => 'bar-platform'
        ],
      ],
    ]);
  }

  public function testGet() {
    $result = $this->entitlementItem->get();

    $this->assertContainsOnlyInstancesOf(EntitlementItemModel::class, $result);
    $this->assertInternalType('array', $result);
    $this->assertCount(2, $result); // out of 2 in dataset

    $this->assertEquals([
      'id' => 1,
      'item' => 'foo',
      'relation' => 'foo-relation',
      'game' => 'foo-game',
      'platform' => 'foo-platform',
      'expires' => null
    ], $result[0]->jsonSerialize());

  }

}