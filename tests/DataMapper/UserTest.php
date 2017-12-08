<?php
namespace Telltale\Tests;

use Telltale\DataMapper\User;
use Telltale\Model\User as UserModel;

class UserTest extends AbstractDBTest {

  const NOTFOUND_EMAIL = 'does-not-exist@example.com';
  const VALID_PASSWORD = 'testtest';

  const USERS_DATASET = [
    [
      'id' => 1,
      'customer_id' => 110,
      'forum_id' => 120,
      'username' => 'PHPUnit',
      'email' => 'old@example.com',
      'password' => '$2a$07$nFnnSm3b8URPCfDw5N0/y.yclNit88bZ9inQBuqjtF3lfqexiyqeq',
      'pw_salt' => '$2a$07$nFnnSm3b8URPCfDw5N0/yG$',
      'change_pw' => 'random-hash-1',
      'birthdate' => NULL,
      'email_salt' => 'd4e4043dfb591034d8544e',
      'email_verify' => '0000-00-00 00:00:00',
      'created_at' => '0000-00-00 00:00:00',
      'updated_at' => '0000-00-00 00:00:00',
      'last_login_at' => '0000-00-00 00:00:00',
      'disabled' => 1,
    ],
    [
      'id' => 2,
      'customer_id' => 0,
      'forum_id' => 0,
      'username' => NULL,
      'email' => 'existing.telltale+account@example.com',
      'password' => '$2y$10$RxnHD.rCD0fhSMHWX/hX3.MLFm/3y/Dk6AengV3CrY3N1g0l3qs4.',
      'pw_salt' => NULL,
      'change_pw' => NULL,
      'birthdate' => NULL,
      'email_salt' => 'c19de9cdbe900ac7275bc0',
      'email_verify' => NULL,
      'created_at' => '2017-12-07 21:54:41',
      'updated_at' => '2017-12-07 22:01:50',
      'last_login_at' => '2017-12-07 22:01:50',
      'disabled' => 0,
    ],
    [
      'id' => 3,
      'customer_id' => 0,
      'forum_id' => 220,
      'username' => 'verified',
      'email' => 'verified+account@example.com',
      'password' => '$2y$10$L8KtUn39RUylzauOGu/y9.3fdn6nXWdpiiqaa5UeJp11W1Cp6jS1m',
      'pw_salt' => NULL,
      'change_pw' => NULL,
      'birthdate' => '1980-02-01 00:00:00',
      'email_salt' => '',
      'email_verify' => '2017-12-16 00:28:15',
      'created_at' => '2017-12-07 21:54:41',
      'updated_at' => '2017-12-16 00:28:15',
      'last_login_at' => '2017-12-16 12:28:15',
      'disabled' => 0,
    ],
  ];

  private $user;

  public function __construct() {
    $this->setupTableSchema('users', "
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `customer_id` int(11) NOT NULL DEFAULT '0',
      `forum_id` int(11) NOT NULL DEFAULT '0',
      `couch_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
      `username` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
      `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
      `password` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
      `pw_salt` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
      `change_pw` varchar(22) COLLATE utf8_unicode_ci DEFAULT NULL,
      `birthdate` datetime DEFAULT NULL,
      `email_salt` varchar(22) COLLATE utf8_unicode_ci DEFAULT NULL,
      `email_verify` datetime DEFAULT NULL,
      `created_at` datetime NOT NULL,
      `updated_at` datetime NOT NULL,
      `created_user_id` int(11) NOT NULL DEFAULT '0',
      `updated_user_id` int(11) NOT NULL DEFAULT '0',
      `last_login_at` datetime NOT NULL,
      `score` int(11) NOT NULL DEFAULT '0',
      `disabled` tinyint(4) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `email_idx` (`email`),
      KEY `uname_idx` (`username`),
      KEY `changepw` (`email`,`change_pw`),
      KEY `customer_idx` (`customer_id`),
      KEY `forum_idx` (`forum_id`)
    ");

    $this->user = new User($this->getPDO(), $this->getCache());
  }

  protected function getDataSet() {
    putenv('AUTH_TOKEN_KEY=UserTestAuthTokenKey');

    return $this->createArrayDataSet([
      'users' => self::USERS_DATASET,
    ]);
  }

  public function testGet() {
    $result = $this->user->get(self::USERS_DATASET[0]['id']);

    $this->assertNotFalse($result);
    $this->assertInstanceOf(UserModel::class, $result);

    $this->assertEquals(self::USERS_DATASET[0]['id'], $result->getId());
    $this->assertEquals(self::USERS_DATASET[0]['customer_id'], $result->getCustomerId());
    $this->assertEquals(self::USERS_DATASET[0]['forum_id'], $result->getForumId());
    $this->assertEquals(self::USERS_DATASET[0]['username'], $result->getUsername());
    $this->assertEquals(self::USERS_DATASET[0]['email'], $result->getEmail());
    $this->assertEquals(self::USERS_DATASET[0]['password'], $result->getPassword());
    $this->assertEquals(self::USERS_DATASET[0]['pw_salt'], $result->getPasswordSalt());
    $this->assertEquals(self::USERS_DATASET[0]['change_pw'], $result->getPasswordResetKey());
    $this->assertEquals(self::USERS_DATASET[0]['birthdate'], $result->getBirthdate());
    $this->assertEquals(self::USERS_DATASET[0]['email_salt'], $result->getEmailVerificationKey());
    $this->assertEquals(self::USERS_DATASET[0]['email_verify'], $result->getEmailVerify());
    $this->assertEquals(self::USERS_DATASET[0]['created_at'], $result->getCreatedAt());
    $this->assertEquals(self::USERS_DATASET[0]['updated_at'], $result->getUpdatedAt());
    $this->assertEquals(self::USERS_DATASET[0]['last_login_at'], $result->getLastLogin());

    $this->assertTrue($result->getDisabled());
    $this->assertFalse($result->hasUpdates());
    $this->assertTrue($result->isVerified());
    $this->assertFalse($result->isLegal());
    $this->assertFalse($result->isUnderage());

    $this->assertTrue($result->verifyPassword(self::VALID_PASSWORD));
    $this->assertFalse($result->verifyPassword('asdfasdf'));
    $this->assertTrue($result->hasUpdates()); // Legacy password has been updated
    $this->assertNotEquals(self::USERS_DATASET[0]['password'], $result->getPassword());
    $this->assertNotEquals(self::USERS_DATASET[0]['pw_salt'], $result->getPasswordSalt());

    $this->assertEquals([
      'user_id' => self::USERS_DATASET[0]['id'],
      'forum_id' => self::USERS_DATASET[0]['forum_id'],
      'username' => self::USERS_DATASET[0]['username'],
      'email' => self::USERS_DATASET[0]['email'],
      'birthdate' => self::USERS_DATASET[0]['birthdate'],
      'created_at' => self::USERS_DATASET[0]['created_at'],
      'last_login' => self::USERS_DATASET[0]['last_login_at'],
      'verified' => true,
      'legal' => false,
    ], $result->jsonSerialize());
  }

  public function testGet2() {
    $result = $this->user->get(self::USERS_DATASET[2]['id']);

    $this->assertNotFalse($result);
    $this->assertInstanceOf(UserModel::class, $result);

    $this->assertEquals(self::USERS_DATASET[2]['id'], $result->getId());
    $this->assertEquals(self::USERS_DATASET[2]['customer_id'], $result->getCustomerId());
    $this->assertEquals(self::USERS_DATASET[2]['forum_id'], $result->getForumId());
    $this->assertEquals(self::USERS_DATASET[2]['username'], $result->getUsername());
    $this->assertEquals(self::USERS_DATASET[2]['email'], $result->getEmail());
    $this->assertEquals(self::USERS_DATASET[2]['password'], $result->getPassword());
    $this->assertEquals(self::USERS_DATASET[2]['pw_salt'], $result->getPasswordSalt());
    $this->assertEquals(self::USERS_DATASET[2]['change_pw'], $result->getPasswordResetKey());
    $this->assertEquals(self::USERS_DATASET[2]['birthdate'], $result->getBirthdate());
    $this->assertEquals(self::USERS_DATASET[2]['email_salt'], $result->getEmailVerificationKey());
    $this->assertEquals(self::USERS_DATASET[2]['email_verify'], $result->getEmailVerify());
    $this->assertEquals(self::USERS_DATASET[2]['created_at'], $result->getCreatedAt());
    $this->assertEquals(self::USERS_DATASET[2]['updated_at'], $result->getUpdatedAt());
    $this->assertEquals(self::USERS_DATASET[2]['last_login_at'], $result->getLastLogin());

    $this->assertFalse($result->getDisabled());
    $this->assertFalse($result->hasUpdates());

    $this->assertTrue($result->isVerified());
    $this->assertTrue($result->isLegal());
    $this->assertFalse($result->isUnderage());

    $this->assertTrue($result->verifyPassword(self::VALID_PASSWORD));
    $this->assertFalse($result->verifyPassword('asdfasdf'));
    $this->assertFalse($result->hasUpdates()); // No updates to password

    $this->assertEquals([
      'user_id' => self::USERS_DATASET[2]['id'],
      'forum_id' => self::USERS_DATASET[2]['forum_id'],
      'username' => self::USERS_DATASET[2]['username'],
      'email' => self::USERS_DATASET[2]['email'],
      'birthdate' => self::USERS_DATASET[2]['birthdate'],
      'created_at' => self::USERS_DATASET[2]['created_at'],
      'last_login' => self::USERS_DATASET[2]['last_login_at'],
      'verified' => true,
      'legal' => true,
    ], $result->jsonSerialize());
  }

  public function testGetFalseIfNotFound() {
    $result = $this->user->get(1000);
    $this->assertFalse($result);
  }

  public function testFindByUsername() {
    $user = new UserModel();
    $user->setUsername(self::USERS_DATASET[0]['username']);

    $result = $this->user->find($user);

    $this->assertNotFalse($result);
    $this->assertInstanceOf(UserModel::class, $result);

    $this->assertEquals(self::USERS_DATASET[0]['id'], $result->getId());
    $this->assertEquals(self::USERS_DATASET[0]['forum_id'], $result->getForumId());
    $this->assertEquals(self::USERS_DATASET[0]['username'], $result->getUsername());
    $this->assertEquals(self::USERS_DATASET[0]['email'], $result->getEmail());
  }

  public function testFindFalseIfNotFound() {
    $user = new UserModel();
    $user->setId(1000);

    $result = $this->user->find($user);
    $this->assertFalse($result);
  }

  public function testGetFind() {

    $gotten = $this->user->get(self::USERS_DATASET[0]['id']);
    $this->assertInstanceOf(UserModel::class, $gotten);

    // Find entire user model
    $found = $this->user->find($gotten);
    $this->assertInstanceOf(UserModel::class, $found);

    $this->assertEquals($gotten, $found);

    $gotten2 = $this->user->get(self::USERS_DATASET[1]['id']);
    $this->assertInstanceOf(UserModel::class, $gotten2);

    // Find entire user model
    $found2 = $this->user->find($gotten2);
    $this->assertInstanceOf(UserModel::class, $found2);

    $this->assertEquals($gotten2, $found2);
  }

  public function testFindGet() {

    $userQuery = new UserModel();
    $userQuery->setEmail(self::USERS_DATASET[2]['email']);

    $found = $this->user->find($userQuery);
    $this->assertInstanceOf(UserModel::class, $found);

    $gotten = $this->user->get(self::USERS_DATASET[2]['id']);
    $this->assertInstanceOf(UserModel::class, $gotten);

    $this->assertEquals($found, $gotten);
  }

  /**
   * Expect a PDOException
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage  No user fields to find
   */
  public function testFindEmptyModel() {
    $user = new UserModel();
    $result = $this->user->find($user);
  }

  public function testCount() {
    // How many uses are enabled?
    $user = new UserModel();
    $user->setDisabled(false);

    $result = $this->user->count($user);
    $this->assertEquals(2, $result);

    // How many uses are disabled and PHPUnit?
    $user->setDisabled(true);
    $user->setUsername('PHPUnit');

    $result2 = $this->user->count($user);
    $this->assertEquals(1, $result2);
  }

  public function testAdd() {
    $newUser = new UserModel();
    $newUser->setEmail('newUser@example.com');
    $newUser->setPassword(self::VALID_PASSWORD);
    $newUser->setBirthdate('1980-01-01');

    $this->assertEmpty($newUser->getLastLogin());
    $this->assertEmpty($newUser->getCreatedAt());

    // In this test we clone because php 'now' will not be the same as MySQL NOW()
    $user = clone $newUser;

    // Returns a modified $user
    $result = $this->user->add($user);

    $this->assertInstanceOf(UserModel::class, $result);

    // User now has an Id
    $this->assertInternalType('int', $result->getId());
    $this->assertGreaterThan(3, $result->getId());

    $this->assertNotEmpty($result->getLastLogin());
    $this->assertNotEmpty($result->getCreatedAt());

    // We can also get the user from the database
    $found = $this->user->find($newUser);

    $this->assertInstanceOf(UserModel::class, $found);

    $this->assertEquals($result->getId(), $found->getId());
    $this->assertEquals($user->getEmail(), $found->getEmail());
    $this->assertEquals($user->getPassword(), $found->getPassword());
    $this->assertEquals($user->getBirthdate(), $found->getBirthdate());
  }

  /**
   * Expect a PDOException
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage Missing required user fields
   */
  public function testAddMissingField() {
    $newUser = new UserModel();
    $newUser->setEmail('newUser2@example.com');
    $result = $this->user->add($newUser);
  }

  /**
   * Expect a PDOException
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage Missing required user fields
   */
  public function testAddEmptyModel() {
    $newUser = new UserModel();
    $result = $this->user->add($newUser);
  }

  public function testUpdate() {

    $userToUpdate = $this->user->get(self::USERS_DATASET[1]['id']);
    $newPassword = 'abc-123 Super *1337* password!!';

    // Not verified
    $this->assertFalse($userToUpdate->isVerified());
    $this->assertFalse($userToUpdate->isLegal());
    $this->assertEmpty($userToUpdate->getBirthdate());
    $this->assertEquals(self::USERS_DATASET[1]['password'], $userToUpdate->getPassword());

    $hasUpdatesStep1 = $userToUpdate->hasUpdates();
    $this->assertFalse($hasUpdatesStep1);

    // Make some changes
    $userToUpdate->setLastLogin('now');
    $userToUpdate->setEmailVerify('now');
    $userToUpdate->setForumId('220');
    $userToUpdate->setUsername('Super1337QA');
    $userToUpdate->setBirthdate('1979-12-01');
    $userToUpdate->setPassword($newPassword);

    $hasUpdatesStep2 = $userToUpdate->hasUpdates();
    $this->assertTrue($hasUpdatesStep2);

    $result = $this->user->update($userToUpdate);

    // Update success
    $this->assertTrue($result);
    $hasUpdatesStep3 = $userToUpdate->hasUpdates();
    $this->assertFalse($hasUpdatesStep3);

    // We can find the updated user in database
    $gotten = $this->user->get(self::USERS_DATASET[1]['id']);
    $found = $this->user->find($gotten);
    $this->assertEquals($gotten, $found);

    $this->assertInstanceOf(UserModel::class, $found);

    $this->assertTrue($found->verifyPassword($newPassword));
    $this->assertEquals($userToUpdate->getForumId(), $found->getForumId());
    $this->assertEquals($userToUpdate->getEmail(), $found->getEmail());
    $this->assertEquals($userToUpdate->getUsername(), $found->getUsername());
    $this->assertEquals($userToUpdate->getPassword(), $found->getPassword());
    $this->assertNotEquals(self::USERS_DATASET[1]['password'], $found->getPassword());
    $this->assertNotEquals(self::USERS_DATASET[1]['updated_at'], $found->getUpdatedAt());
    $this->assertEquals($userToUpdate->getBirthdate(), $found->getBirthdate());

    $this->assertTrue($found->isVerified());
    $this->assertTrue($found->isLegal());
  }

  public function testUpdatedAtDoesNotChangeWithLastLoginUpdate() {
    $userToUpdate = $this->user->get(self::USERS_DATASET[1]['id']);
    $this->assertEquals(self::USERS_DATASET[1]['last_login_at'], $userToUpdate->getLastLogin());
    $this->assertEquals(self::USERS_DATASET[1]['updated_at'], $userToUpdate->getUpdatedAt());

    $userToUpdate->setLastLogin('now');

    $result = $this->user->update($userToUpdate);
    $this->assertTrue($result);

    $found = $this->user->find($userToUpdate);

    $this->assertInstanceOf(UserModel::class, $found);
    $this->assertNotEquals(self::USERS_DATASET[1]['last_login_at'], $found->getLastLogin());
    $this->assertEquals(self::USERS_DATASET[1]['updated_at'], $found->getUpdatedAt());
  }

  /**
   * Expect a PDOException
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage No user fields to update
   */
  public function testNoUpdates() {
    $userToUpdate = $this->user->get(self::USERS_DATASET[1]['id']);
    $hasUpdates = $userToUpdate->hasUpdates();
    $this->assertFalse($hasUpdates);

    $result = $this->user->update($userToUpdate);
    $this->assertFalse($result); // should never run
  }

  /**
   * Expect a PDOException
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage No user fields to update
   */
  public function testUpdateIncompleteModel() {

    $userToUpdate = new UserModel();
    $userToUpdate->setUsername('new-username');

    // Model *has* updates
    $hasUpdates = $userToUpdate->hasUpdates();
    $this->assertTrue($hasUpdates);

    $result = $this->user->update($userToUpdate);
    $this->assertFalse($result); // should never run
  }

  public function testFromToken() {
    $token = "3,1512764133,40fda370b0e92acc2748b13f2f2dff4a31616c14a740a3a978c950c662da6725";

    $result = $this->user->fromToken($token);
    $this->assertInstanceOf(UserModel::class, $result);
    $this->assertEquals(self::USERS_DATASET[2]['username'], $result->getUsername());
  }

  /**
  * Expect existing token to no longer match when password changes
  *
  * @expectedException        Exception
  * @expectedExceptionCode    401
  * @expectedExceptionMessage Token Validation Failed
  */
  public function testFromTokenNoLongerWorksAfterPasswordChange() {
    $token = "3,1512764133,40fda370b0e92acc2748b13f2f2dff4a31616c14a740a3a978c950c662da6725";

    $user = $this->user->get(3);

    $this->assertTrue(hash_equals($token, $user->authToken('1512764133')));

    // Change user's password
    $user->setPassword(bin2hex(random_bytes(10)));

    $this->assertFalse(hash_equals($token, $user->authToken('1512764133')));

    // Save into db
    $this->user->update($user);

    // Can no longer get user from this token
    $result = $this->user->fromToken($token);
    $this->assertFalse($result); // should never run
  }

  /**
   * Expect an Exception with an invalid token
   *
   * @expectedException        Exception
   * @expectedExceptionCode    401
   * @expectedExceptionMessage Token Validation Failed
   */
  public function testFromTokenTokenFailed() {
    $token = "3,1512764133,41fda370b0e92acc2748b13f2f2dff4a31616c14a740a3a978c950c662da6725";

    $result = $this->user->fromToken($token);
    $this->assertFalse($result); // should never run
  }

  /**
   * Expect an Exception with an invalid token
   *
   * @expectedException        Exception
   * @expectedExceptionCode    401
   * @expectedExceptionMessage Token Validation Failed
   */
  public function testFromTokenTokenFailed2() {
    $token = "3,4,5,1,2,7,6,3,4,0,8,,,,";

    $result = $this->user->fromToken($token);
    $this->assertFalse($result); // should never run
  }

  /**
   * Expect an Exception if account is disabled
   *
   * @expectedException        Exception
   * @expectedExceptionCode    403
   * @expectedExceptionMessage Account Disabled
   */
  public function testFromTokenDisabledUser() {
    $token = "1,1512763408,4ec8ffd8b76295d0084c5b98f71b36841577c732d798f63cf405e0a3f6ea3ec1";

    $result = $this->user->fromToken($token);
    $this->assertFalse($result); // should never run
  }

  /**
   * Expect an Exception with invalid token
   *
   * @expectedException        Exception
   * @expectedExceptionCode    400
   * @expectedExceptionMessage Invalid User Token
   */
  public function testFromTokenInvalidToken() {
    $token = "1";

    $result = $this->user->fromToken($token);
    $this->assertFalse($result); // should never run
  }

  /**
   * Expect an Exception with invalid token
   *
   * @expectedException        Exception
   * @expectedExceptionCode    400
   * @expectedExceptionMessage Invalid User Token
   */
  public function testFromTokenInvalidToken2() {
    $token = "";

    $result = $this->user->fromToken($token);
    $this->assertFalse($result); // should never run
  }

  /**
   * Expect an Exception if user does not exist
   *
   * @expectedException        Exception
   * @expectedExceptionCode    404
   * @expectedExceptionMessage User Not Found
   */
  public function testFromTokenUserNotFound() {
    $token = "1000000,1,24cd3as35je3";

    $result = $this->user->fromToken($token);
    $this->assertFalse($result); // should never run
  }

}