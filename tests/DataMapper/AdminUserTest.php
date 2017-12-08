<?php
namespace Telltale\Tests;

use Telltale\DataMapper\AdminUser;
use Telltale\Model\AdminUser as AdminUserModel;

class AdminUserTest extends AbstractDBTest {

  const EXISTING_USERNAME = 'unittester';
  const EXISTING_NAME = 'Unit Testing User';
  const EXISTING_SESSION = 'existing-session-value';

  const ADMIN_USERNAME = 'admin_user';

  private $AdminUser;

  public function __construct() {
    $this->setupTableSchema('admin_user', '
      `username` varchar(20) NOT NULL,
      `fullname` varchar(50) DEFAULT NULL,
      `jira_session` varchar(32) DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` datetime DEFAULT NULL,
      PRIMARY KEY (`username`)
    ');

    $this->setupTableSchema('admin_entitlement_types', '
      `id` smallint(6) NOT NULL,
      `entitlement` varchar(20) DEFAULT NULL,
      `comment` varchar(80) DEFAULT NULL,
      `updated_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`)
    ');

    $this->setupTableSchema('admin_user_entitlement', '
      `id` smallint(6) NOT NULL,
      `username` varchar(20) DEFAULT NULL,
      `entitlement_type` smallint(6) DEFAULT NULL,
      `updated_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`)
    ');

    $this->AdminUser = new AdminUser($this->getPDO(), $this->getCache());
  }

  protected function getDataSet() {
    putenv('AUTH_TOKEN_KEY=AdminUserTestAuthTokenKey');

    return $this->createArrayDataSet([
      'admin_user' => [
        [
          'username' => self::EXISTING_USERNAME,
          'fullname' => self::EXISTING_NAME,
          'jira_session' => self::EXISTING_SESSION,
          'created_at' => '2017-08-28 21:38:24',
          'updated_at' => NULL
        ],
        [
          'username' => self::ADMIN_USERNAME,
          'fullname' => 'Super Admin',
          'jira_session' => 'super-duper-admin',
          'created_at' => '2017-08-28 21:38:24',
          'updated_at' => NULL
        ],
      ],
      'admin_entitlement_types' => [
        [
          'id' => 1,
          'entitlement' => 'UnitTester',
          'comment' => 'Can unit test',
          'updated_at' => '2017-08-28 21:38:24',
        ],
        [
          'id' => 2,
          'entitlement' => 'Admin',
          'comment' => 'Special Admin Type',
          'updated_at' => '2017-08-28 21:38:24',
        ],
      ],
      'admin_user_entitlement' => [
        [
          'id' => 1,
          'username' => self::EXISTING_USERNAME,
          'entitlement_type' => 1,
          'updated_at' => '2017-08-28 21:38:24',
        ],
        [
          'id' => 2,
          'username' => self::ADMIN_USERNAME,
          'entitlement_type' => 2,
          'updated_at' => '2017-08-28 21:38:24',
        ]
      ]
    ]);
  }

  public function testGetExistingUsername() {
    $result = $this->AdminUser->get(self::EXISTING_USERNAME);
    $resultAdmin = $this->AdminUser->get(self::ADMIN_USERNAME);

    $this->assertNotFalse($result);
    $this->assertInstanceOf(AdminUserModel::class, $result);

    $this->assertEquals(self::EXISTING_USERNAME, $result->getUsername());
    $this->assertEquals(self::EXISTING_NAME, $result->getName());
    $this->assertEquals(self::EXISTING_SESSION, $result->getJiraSession());

    $this->assertEquals(['unittester'], $result->getPermissions());

    $this->assertInternalType('string', $result->authToken());

    $this->assertTrue($result->hasPermission('UnitTester'));
    $this->assertFalse($result->hasPermission('Admin'));

    $this->assertEquals([
      'username' => self::EXISTING_USERNAME,
      'name' => self::EXISTING_NAME,
      'permissions' => ['unittester'],
    ], $result->jsonSerialize());

  }

  public function testAdminPermissions() {
    $result = $this->AdminUser->get(self::ADMIN_USERNAME);

    $this->assertNotFalse($result);
    $this->assertEquals(self::ADMIN_USERNAME, $result->getUsername());

    $this->assertEquals(['admin'], $result->getPermissions());

    $this->assertTrue($result->hasPermission('UnitTester'));
    $this->assertTrue($result->hasPermission('Foo'));
    $this->assertTrue($result->hasPermission('Bar'));
    $this->assertTrue($result->hasPermission('Admin'));
  }

  /**
   * Test valid auth token
   * Relies on AUTH_TOKEN_KEY and admin_user jira-session renaming unchanged
   *
   */
  public function testFromToken() {
    $token = "unittester,1512496054,c7619e0ed426e4ba7040e81dc55db3385076a03d6c692c64c21cc12a13e5c2c9";

    $result = $this->AdminUser->fromToken($token);

    $this->assertNotFalse($result);
    $this->assertInstanceOf(AdminUserModel::class, $result);
    $this->assertEquals(self::EXISTING_USERNAME, $result->getUsername());
  }

  /**
   * Test invalid token
   *
   * @expectedException        Exception
   * @expectedExceptionMessage Token Validation Failed
   */
  public function testInvalidToken() {
    $token = "unittester,1512496054,c7619e0ed426e4ba7040e81dc55db3385076a03d6c692c64c21cc12a13e5c2c8";
    $result = $this->AdminUser->fromToken($token);
  }

  public function testGetPermissions() {

    $userQuery = new AdminUserModel();
    $userQuery->setUserName(self::EXISTING_USERNAME);

    // Has no permissions
    $this->assertEquals([], $userQuery->getPermissions());

    $result = $this->AdminUser->getPermissions($userQuery);
    $this->assertInstanceOf(AdminUserModel::class, $result);

    // Now has permissions
    $this->assertEquals(['unittester'], $result->getPermissions());
  }

  public function testStoreNewUser() {

    $newUser = new AdminUserModel();
    $newUser->setUserName('NewUser');
    $newUser->setName('New User');
    $newUser->setJiraSession('new-user-session');

    // User doesn't already exist
    $noUserResult = $this->AdminUser->get('NewUser');
    $this->assertFalse($noUserResult);

    $saveResult = $this->AdminUser->store($newUser);

    $this->assertNotFalse($saveResult);

    // Get user back
    $result = $this->AdminUser->get('NewUser');
    $this->assertNotFalse($result);
    $this->assertEquals('New User', $result->getName());

  }

  public function testStoreExistingUser() {
    $user = $this->AdminUser->get(self::EXISTING_USERNAME);
    $this->assertInstanceOf(AdminUserModel::class, $user);

    $this->assertEquals(self::EXISTING_SESSION, $user->getJiraSession());

    $user->setJiraSession('updated-jira-session');

    $saveResult = $this->AdminUser->store($user);
    $this->assertNotFalse($saveResult);

    // Get user back
    $result = $this->AdminUser->get(self::EXISTING_USERNAME);
    $this->assertEquals('updated-jira-session', $result->getJiraSession());
  }

}