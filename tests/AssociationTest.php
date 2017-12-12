<?php
namespace Telltale\Tests;

use Telltale\DataMapper\Association;
use Telltale\Model\Association as AssociationModel;
use League\OAuth2\Client\Token\AccessToken;

class AssociationTest extends AbstractDBTest {

  private $association;

  public function __construct() {

    $this->setupTableSchema('association', '
      `user_id` int(11) NOT NULL,
      `provider` varchar(34) NOT NULL,
      `identifier` varchar(60) NOT NULL,
      `access_token` text,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL,
      UNIQUE KEY `user_association` (`user_id`,`provider`)
    ');

    $this->association = new Association($this->getPDO(), $this->getCache());
  }

  protected function getDataSet() {
    return $this->createArrayDataSet([
      'association' => [
        [
          'user_id' => 1,
          'provider' => 'foo',
          'identifier' => 'UnitTesterHandle',
          'access_token' => '{"token_type":"Bearer","access_token":"foo-token","refresh_token":"foo-refresh-token","expires":1500595916}', // new style token
          'created_at' => '2017-12-05 23:54:24',
          'updated_at' => '2017-12-05 22:54:24',
        ],
        [
          'user_id' => 1,
          'provider' => 'bar',
          'identifier' => '123456',
          'access_token' => 'bar-token', // old style token
          'created_at' => '2017-12-05 22:54:24',
          'updated_at' => '2017-12-05 22:54:24',
        ],
        [
          'user_id' => 2,
          'provider' => 'baz',
          'identifier' => 'baz-123456',
          'access_token' => 'baz-token', // old style token
          'created_at' => '2017-12-05 22:54:24',
          'updated_at' => '2017-12-05 22:54:24',
        ]
      ]
    ]);
  }

  public function testFindUserAssociations() {

    $association = new AssociationModel();
    $association->setUserId(1);

    $result = $this->association->find($association);

    $this->assertContainsOnlyInstancesOf(AssociationModel::class, $result);
    $this->assertCount(2, $result);
  }

  public function testFindUserAssociation() {

    $association = new AssociationModel();
    $association->setUserId(1);
    $association->setProvider('foo');

    $result = $this->association->find($association);

    $this->assertContainsOnlyInstancesOf(AssociationModel::class, $result);
    $this->assertCount(1, $result);

    $this->assertEquals('foo', $result[0]->getProvider());
    $this->assertEquals('UnitTesterHandle', $result[0]->getIdentifier());
    $this->assertEquals('2017-12-05 23:54:24', $result[0]->getCreatedAt());
    $this->assertEquals('2017-12-05 22:54:24', $result[0]->getUpdatedAt());

    $accessToken = $result[0]->getAccessToken();
    $this->assertInstanceOf(AccessToken::class, $accessToken);
    $this->assertEquals('foo-token', $accessToken->getToken());
    $this->assertEquals('foo-refresh-token', $accessToken->getRefreshToken());
    $this->assertEquals('1500595916', $accessToken->getExpires());
    $this->assertTrue($accessToken->hasExpired());

    $this->assertEquals([
      'provider' => 'foo',
      'associated' => true,
      'accessToken' => $accessToken
    ], $result[0]->jsonSerialize());
  }

  /**
   * Expect an array of AssociationModel with a string access token matching dataset
   *
   */
  public function testFindAssociationWithOldStyleToken() {
    $association = new AssociationModel();
    $association->setProvider('bar');

    $result = $this->association->find($association);

    $this->assertContainsOnlyInstancesOf(AssociationModel::class, $result);
    $this->assertCount(1, $result);
    $this->assertInternalType('string', $result[0]->getAccessToken());
    $this->assertEquals('bar-token', $result[0]->getAccessToken());
  }

  /**
   * Expect an empty array
   *
   */
  public function testFindMissingAssociation() {
    $association = new AssociationModel();
    $association->setUserId('123');
    $association->setProvider('phpunit');
    $association->setIdentifier('phpunitIdent');
    $token = new AccessToken(['access_token' => 'none']);
    $association->setAccessToken($token);

    $result = $this->association->find($association);

    $this->assertContainsOnlyInstancesOf(AssociationModel::class, $result);
    $this->assertCount(0, $result);
  }

  public function testAddNewAssociation() {
    $association = new AssociationModel();
    $association->setUserId('123');
    $association->setProvider('phpunit');
    $association->setIdentifier('phpunitIdent');

    $result = $this->association->add($association);
    // Returns nothing
    $this->assertNull($result);

    $result = $this->association->find($association);

    $this->assertCount(1, $result);
    $this->assertEquals($association->jsonSerialize(), $result[0]->jsonSerialize());
  }

  public function testModifyExistingAssociation() {
    $association = new AssociationModel();
    $association->setUserId(1); // existing
    $association->setProvider('foo'); // existing
    $association->setIdentifier('UpdatedIdent'); // changed
    $token = new AccessToken(['access_token' => 'updated-foo']);
    $association->setAccessToken($token); // changed

    $this->association->add($association);

    $associationQuery = new AssociationModel();
    $associationQuery->setUserId(1); // existing
    $associationQuery->setProvider('foo'); // existing
    $result = $this->association->find($associationQuery);
    $this->assertCount(1, $result);

    $this->assertEquals('foo', $result[0]->getProvider());
    $this->assertEquals('UpdatedIdent', $result[0]->getIdentifier());
    $this->assertEquals($token, $result[0]->getAccessToken());
    $this->assertNotEquals('2017-12-05 22:54:24', $result[0]->getUpdatedAt());
  }

  public function testDeleteAssociation() {
    $association = new AssociationModel();
    $association->setUserId(1); // existing
    $association->setProvider('bar'); // existing

    $result = $this->association->delete($association);
    $this->assertTrue($result);
  }

  public function testDeleteNonExistentAssociation() {
    $association = new AssociationModel();
    $association->setUserId(99);
    $result = $this->association->delete($association);
    $this->assertFalse($result);
  }

}