<?php
namespace Telltale\Tests;

use Telltale\DataMapper\Entitlement;
use Telltale\Model\Entitlement as EntitlementModel;

class EntitlementTest extends AbstractDBTest {

  CONST ENT_USER_ID = '1337';

  private $entitlement;

    public function __construct() {
      parent::__construct();

      $this->setupTableSchema('entitlement', "
        `user_id` int(11) NOT NULL,
        `relation` varchar(34) NOT NULL DEFAULT '',
        `item` varchar(34) NOT NULL DEFAULT '',
        `game` varchar(34) NOT NULL DEFAULT '',
        `platform` varchar(16) NOT NULL DEFAULT '',
        `source` varchar(60) DEFAULT NULL,
        `source_id` varchar(60) NOT NULL DEFAULT '',
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        `enabled` tinyint(1) NOT NULL DEFAULT '1',
        `attributes` mediumtext,
        UNIQUE KEY `user_entitlement` (`user_id`,`relation`,`item`,`game`,`platform`),
        KEY `user_id` (`user_id`),
        KEY `source_id` (`source_id`)
      ");

      $this->setupTableSchema('entitlement_codes', "
        `code` varchar(255) NOT NULL DEFAULT '',
        `item_id` varchar(34) NOT NULL DEFAULT '',
        `issuer` varchar(60) NOT NULL DEFAULT '',
        `used` tinyint(1) NOT NULL DEFAULT '0',
        `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` datetime DEFAULT NULL,
        UNIQUE KEY `entitlement` (`code`,`item_id`),
        KEY `code` (`code`)
      ");

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

      $this->entitlement = new Entitlement($this->getPDO(), $this->getCache());
    }

    protected function getDataSet() {
      return $this->createMySQLXMLDataSet(dirname(__FILE__) . '/../datasets/entitlement-data.xml');
    }

    public function testFindEnabledUserEntitlements() {
      $entitlement = new EntitlementModel();
      $entitlement->setUserId(self::ENT_USER_ID);
      $entitlement->setEnabled(true);

      $result = $this->entitlement->find($entitlement);

      $this->assertContainsOnlyInstancesOf(EntitlementModel::class, $result);
      $this->assertInternalType('array', $result);
      $this->assertCount(5, $result); // out of 7 in dataset
    }

    public function testFindEntitlement() {

      $game = 'backtothefuture';
      $relation = 'seasonpass';
      $item = 'game_installer';
      $platform = 'telltale';
      $source = 'ElectronicFulfillmentRequest';
      $createdAt = '2017-06-22 18:05:06';
      $attributes = [
        'orderID' => '10008743320014',
        'userID' => '486320788915',
      ];

      $entitlement = new EntitlementModel();
      $entitlement->setUserId(self::ENT_USER_ID);
      $entitlement->setEnabled(true);
      $entitlement->setGame($game);
      $entitlement->setRelation($relation);
      $entitlement->setItem($item);
      $entitlement->setPlatform($platform);
      $entitlement->setSource($source);

      $result = $this->entitlement->find($entitlement);
      $this->assertContainsOnlyInstancesOf(EntitlementModel::class, $result);
      $this->assertCount(1, $result); // out of 7 in dataset

      $this->assertEquals(self::ENT_USER_ID, $result[0]->getUserId());
      $this->assertEquals($game, $result[0]->getGame());
      $this->assertEquals($relation, $result[0]->getRelation());
      $this->assertEquals($item, $result[0]->getItem());
      $this->assertEquals($platform, $result[0]->getPlatform());
      $this->assertEquals($source, $result[0]->getSource());
      $this->assertEquals('10000367054970014', $result[0]->getSourceID());
      $this->assertEquals('2017-06-22 18:05:06', $result[0]->getCreatedAt());
      $this->assertTrue($result[0]->getEnabled());
      $this->assertEquals($attributes, $result[0]->getAttributes());
      $this->assertEquals([
        'game' => $game,
        'relation' => $relation,
        'item' => $item,
        'platform' => $platform,
        'created_at' => $createdAt,
        'attributes' => $attributes,
      ], $result[0]->jsonSerialize());
    }

    public function testFindEntitlementNoResult() {

      $entitlement = new EntitlementModel();
      $entitlement->setGame('does-not-exist-yet');

      $result = $this->entitlement->find($entitlement);
      $this->assertContainsOnlyInstancesOf(EntitlementModel::class, $result);
      $this->assertCount(0, $result); // out of 7 in dataset
    }

    public function testFindUnusedCode() {
      $result = $this->entitlement->findByCode('TEST-UNUSED');

      $this->assertInstanceOf(EntitlementModel::class, $result);
      $this->assertEquals('entitlement_code', $result->getSource());
      $this->assertEquals('guardians', $result->getGame());
      $this->assertEquals('seasonpass', $result->getRelation());
      $this->assertEquals('game_installer', $result->getItem());
      $this->assertEquals('telltale', $result->getPlatform());
      $this->assertFalse($result->getUsed());
    }

    public function testUnlimitedCode() {
      $result = $this->entitlement->findByCode('TEST-UNLIMITED');
      $this->assertInstanceOf(EntitlementModel::class, $result);
      $this->assertEquals('gameofthrones', $result->getGame());
      $this->assertEquals('trial', $result->getRelation());
      $this->assertTrue($result->getUsed());
    }

    public function testUsedCode() {
      $result = $this->entitlement->findByCode('TEST-USED');
      $this->assertFalse($result);
    }

    public function testExpiredCode() {
      $result = $this->entitlement->findByCode('TEXT-UNUSED-EXPIRED');
      $this->assertFalse($result);
    }

    public function testRandomCode() {
      $code = bin2hex(random_bytes(10));
      $result = $this->entitlement->findByCode($code);
      $this->assertFalse($result);
    }

    public function testAddEntitlement() {

      // TODO: If string is longer than db row allows should it be truncated by EntitlementModel?
      $game = substr(bin2hex(random_bytes(10)), 0, 32);
      $relation = substr(bin2hex(random_bytes(10)), 0, 32);
      $item = substr(bin2hex(random_bytes(10)), 0, 32);
      $platform = substr(bin2hex(random_bytes(10)), 0, 16);
      $source = 'PHPUnit';
      $sourceID = rand();

      $entitlement = new EntitlementModel();
      $entitlement->setUserId(self::ENT_USER_ID);;
      $entitlement->setGame($game);
      $entitlement->setRelation($relation);
      $entitlement->setItem($item);
      $entitlement->setPlatform($platform);
      $entitlement->setSource($source);
      $entitlement->setSourceId($sourceID);

      $this->entitlement->add($entitlement);

      // make sure it exists
      $result = $this->entitlement->find($entitlement);

      $this->assertContainsOnlyInstancesOf(EntitlementModel::class, $result);
      $this->assertCount(1, $result);

      $this->assertEquals($game, $result[0]->getGame());
      $this->assertEquals($relation, $result[0]->getRelation());
      $this->assertEquals($item, $result[0]->getItem());
      $this->assertEquals($platform, $result[0]->getPlatform());
      $this->assertEquals($source, $result[0]->getSource());
      $this->assertEquals($sourceID, $result[0]->getSourceId());
      $this->assertTrue($result[0]->getEnabled());
    }

    /**
     * Expect PDOException on duplicate entitlements
     *
     * @expectedException        PDOException
     * @expectedExceptionMessage SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1337-seasonpass-game_installer-backtothefuture-telltale' for key 'user_entitlement'
     */
    public function testAddExistingEntitlement() {
      $entitlement = new EntitlementModel();
      $entitlement->setUserId(self::ENT_USER_ID);
      $entitlement->setRelation('seasonpass');
      $entitlement->setItem('game_installer');
      $entitlement->setGame('backtothefuture');
      $entitlement->setPlatform('telltale');

      // Make sure it exists
      $result = $this->entitlement->find($entitlement);
      $this->assertContainsOnlyInstancesOf(EntitlementModel::class, $result);
      $this->assertCount(1, $result);

      // Required to add
      $entitlement->setSource('PHPUnit');
      $entitlement->setSourceId(rand());

      $this->entitlement->add($entitlement);
    }

    /**
     * Expect PDOException for missing fields
     *
     * @expectedException        PDOException
     * @expectedExceptionMessage SQLSTATE[23000]: Integrity constraint violation: 1048
     */
    public function testAddEmptyEntitlement() {

      $attributes = ['test' => 'testAddEmptyEntitlement'];

      $entitlement = new EntitlementModel();
      $this->entitlement->add($entitlement);

      $entitlement = new EntitlementModel();
      $entitlement->setUserId(self::ENT_USER_ID);
      $entitlement->setRelation('disabled-test');
      $entitlement->setEnabled(false);

      $result = $this->entitlement->find($entitlement);
      $this->assertCount(1, $result);

      // Now make our changes
      $result[0]->setEnabled(true);
      $result[0]->setAttributes($attributes);

      $this->entitlement->update($found[0]);

      // Now there is no result
      $result2 = $this->entitlement->find($entitlement);
      $this->assertCount(0, $result2);

      // Update our query and search again
      $entitlement->setEnabled(true);
      $result3 = $this->entitlement->find($entitlement);
      $this->assertCount(1, $result3);

      $this->assertEquals($attributes, $result3[0]->getAttributes());
    }

    public function testDeleteEntitlement() {

      // Find a disabled entitlement to delete
      $entitlement = new EntitlementModel();
      $entitlement->setUserId(self::ENT_USER_ID);
      $entitlement->setRelation('disabled-test');
      $entitlement->setEnabled(false);

      // Make sure a result is found
      $result = $this->entitlement->find($entitlement);
      $this->assertCount(1, $result);

      $this->entitlement->delete($result[0]);

      // Now there is no result
      $result2 = $this->entitlement->find($entitlement);
      $this->assertCount(0, $result2);
    }

    /**
     * Expect PDOException when attempting to delete enabled entitlement
     *
     * @expectedException        PDOException
     * @expectedExceptionMessage Entitlement must be disabled before it can be deleted
     */
    public function testCantDeleteEnabledEntitlement() {
      $entitlement = new EntitlementModel();
      $entitlement->setUserId(self::ENT_USER_ID);
      $entitlement->setEnabled(true);

      $result = $this->entitlement->find($entitlement);

      $this->entitlement->delete($result[0]);
    }

    public function testRedeemUnusedCode() {
      $code = 'TEST-UNUSED';

      $redeemResult = $this->entitlement->redeem($code, self::ENT_USER_ID);

      $this->assertContainsOnlyInstancesOf(EntitlementModel::class, $redeemResult);
      $this->assertCount(1, $redeemResult);

      $this->assertEquals(self::ENT_USER_ID, $redeemResult[0]->getUserId()); // assigned to user
      $this->assertEquals($code, $redeemResult[0]->getSourceId());

      // Can be found in the database
      $entitlement = new EntitlementModel();
      $entitlement->setUserId(self::ENT_USER_ID);
      $entitlement->setSource('entitlement_code');
      $entitlement->setSourceId($code);
      $foundResult = $this->entitlement->find($entitlement);
      $this->assertCount(1, $foundResult);
      $this->assertEquals(self::ENT_USER_ID, $foundResult[0]->getUserId()); // assigned to user
      $this->assertEquals($code, $foundResult[0]->getSourceId());

      // Can't be redeemed again
      $secondRedeemResult = $this->entitlement->redeem($code, self::ENT_USER_ID);
      $this->assertCount(0, $secondRedeemResult);
    }

    public function testRedeemUnlimitedCode() {

      $code = 'TEST-UNLIMITED';
      $redeemResult = $this->entitlement->redeem($code, self::ENT_USER_ID);
      $this->assertCount(1, $redeemResult);
      $this->assertEquals(self::ENT_USER_ID, $redeemResult[0]->getUserId()); // assigned to user

      $redeemResult2 = $this->entitlement->redeem($code, self::ENT_USER_ID + 1);
      $this->assertCount(1, $redeemResult2);
      $this->assertEquals(self::ENT_USER_ID + 1, $redeemResult2[0]->getUserId()); // assigned to user
    }

    /**
     * User cannot redeem a code for an entitlement they already have through other means
     *
     */
    public function testRedeemCodeAlreadyOwn() {

      $code = 'TEST-UNUSED-OWNED';
      try {
        $redeemResult = $this->entitlement->redeem($code, self::ENT_USER_ID);
      } catch (\Exception $e) {
        $this->assertTrue(strpos($e->getMessage(), 'Duplicate entry') !== false);
      }

      // still can be found
      $result = $this->entitlement->findByCode($code);
      $this->assertInstanceOf(EntitlementModel::class, $result);
      $this->assertEquals('entitlement_code', $result->getSource());
      $this->assertEquals($code, $result->getSourceId());
    }

    public function testMarkUsed() {

      $code = 'TEST-UNUSED';

      $entitlement = $this->entitlement->findByCode($code);

      // Not used
      $this->assertFalse($entitlement->getUsed());

      $usedEntitlement = $this->entitlement->markAsUsed($entitlement);

      // Now used
      $this->assertTrue($usedEntitlement->getUsed());

      // Can no longer be found
      $result = $this->entitlement->findByCode($code);
      $this->assertFalse($result);
    }

    public function testMarkAlreadyUsed() {

      $entitlement = new EntitlementModel();
      $entitlement->setUserId(self::ENT_USER_ID);
      $entitlement->setSource('entitlement_code');
      $entitlement->setSourceId('random');
      $entitlement->setUsed(true);

      $usedEntitlement = $this->entitlement->markAsUsed($entitlement);

      $this->assertEquals($entitlement, $usedEntitlement);
    }

    /**
     * Prevent entitlements not created by a code from being marked as used
     *
     * @expectedException        PDOException
     * @expectedExceptionMessage markAsUsed only allows entitlements sourced from entitlement codes
     */
    public function testMarkUsedLimitedSource() {
      $entitlement = new EntitlementModel();
      $entitlement->setSource('foobar');

      $this->entitlement->markAsUsed($entitlement);
    }

}