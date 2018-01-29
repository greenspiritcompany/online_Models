<?php
namespace Telltale\Tests;

use Telltale\DataMapper\VanillaUser;
use Telltale\Model\VanillaUser as VanillaUserModel;

class VanillaUserTest extends AbstractDBTest {

  private $vanillaUser;

  const USER_DATASET = [
    [
      'UserID' => 25,
      'Name' => '25th User',
      'Password' => '',
      'AuthID' => 125,
      'Photo' => 'https://cdn.telltalegames.com/avatars/v1/200x250/GOTG_avatar_0005.png',
      'About' => 'What is a racoon?',
      'Email' => '25th@example.com',
      'DateOfBirth' => '1975-09-16 00:00:00',
      'InsertIPAddress' => '50.232.152.17',
    ],
    [
      'UserID' => 26,
      'Name' => '26th User',
      'Email' => '26th@example.com',
      'Password' => '',
      'AuthID' => 126,
    ]
  ];

  const BANS = [
    [
      'BanType' => 'Name',
      'BanValue' => 'BadName',
      'InsertUserID' => 1,
      'DateInserted' => '2014-05-27 10:07:08',
    ],
    [
      'BanType' => 'Email',
      'BanValue' => '*@10minutemail.com',
      'InsertUserID' => 1,
      'DateInserted' => '2014-05-27 10:07:08',
    ],
    [
      'BanType' => 'Name',
      'BanValue' => '*NeverTest*',
      'InsertUserID' => 1,
      'DateInserted' => '2014-05-27 10:07:08',
    ]
  ];

  public function __construct() {
    parent::__construct();

    $this->setupTableSchema('GDN_User', "
      `UserID` int(11) NOT NULL AUTO_INCREMENT,
      `Name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
      `Password` varbinary(100) NOT NULL,
      `HashMethod` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `Photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `Title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `Location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `About` text COLLATE utf8mb4_unicode_ci,
      `Email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
      `ShowEmail` tinyint(4) NOT NULL DEFAULT '0',
      `Gender` enum('u','m','f') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'u',
      `CountVisits` int(11) NOT NULL DEFAULT '0',
      `CountInvitations` int(11) NOT NULL DEFAULT '0',
      `CountNotifications` int(11) DEFAULT NULL,
      `InviteUserID` int(11) DEFAULT NULL,
      `DiscoveryText` text COLLATE utf8mb4_unicode_ci,
      `Preferences` text COLLATE utf8mb4_unicode_ci,
      `Permissions` text COLLATE utf8mb4_unicode_ci,
      `Attributes` text COLLATE utf8mb4_unicode_ci,
      `DateSetInvitations` datetime DEFAULT NULL,
      `DateOfBirth` datetime DEFAULT NULL,
      `DateFirstVisit` datetime DEFAULT NULL,
      `DateLastActive` datetime DEFAULT NULL,
      `LastIPAddress` varbinary(16) DEFAULT NULL,
      `AllIPAddresses` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `DateInserted` datetime NOT NULL,
      `InsertIPAddress` varbinary(16) DEFAULT NULL,
      `DateUpdated` datetime DEFAULT NULL,
      `UpdateIPAddress` varbinary(16) DEFAULT NULL,
      `HourOffset` int(11) NOT NULL DEFAULT '0',
      `Score` float DEFAULT NULL,
      `Admin` tinyint(4) NOT NULL DEFAULT '0',
      `Confirmed` tinyint(4) NOT NULL DEFAULT '1',
      `Verified` tinyint(4) NOT NULL DEFAULT '0',
      `Banned` tinyint(4) NOT NULL DEFAULT '0',
      `Deleted` tinyint(4) NOT NULL DEFAULT '0',
      `Points` int(11) NOT NULL DEFAULT '0',
      `CountUnreadConversations` int(11) DEFAULT NULL,
      `CountDiscussions` int(11) DEFAULT NULL,
      `CountUnreadDiscussions` int(11) DEFAULT NULL,
      `CountComments` int(11) DEFAULT NULL,
      `CountDrafts` int(11) DEFAULT NULL,
      `CountBookmarks` int(11) DEFAULT NULL,
      `AuthID` int(11) DEFAULT NULL,
      `UpVotes` int(11) NOT NULL DEFAULT '0',
      `DownVotes` int(11) NOT NULL DEFAULT '0',
      PRIMARY KEY (`UserID`),
      KEY `FK_User_Name` (`Name`),
      KEY `IX_User_Email` (`Email`),
      KEY `IX_User_DateLastActive` (`DateLastActive`),
      KEY `IX_User_DateInserted` (`DateInserted`),
      KEY `FK_AuthID` (`AuthID`)
    ");

    $this->setupTableSchema('GDN_UserRole', "
      `UserID` int(11) NOT NULL,
      `RoleID` int(11) NOT NULL,
      PRIMARY KEY (`UserID`,`RoleID`),
      KEY `IX_UserRole_RoleID` (`RoleID`)
    ");

    $this->setupTableSchema('GDN_Ban', "
      `BanID` int(11) NOT NULL AUTO_INCREMENT,
      `BanType` enum('IPAddress','Name','Email') COLLATE utf8mb4_unicode_ci NOT NULL,
      `BanValue` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
      `Notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
      `CountUsers` int(10) unsigned NOT NULL DEFAULT '0',
      `CountBlockedRegistrations` int(10) unsigned NOT NULL DEFAULT '0',
      `InsertUserID` int(11) NOT NULL,
      `DateInserted` datetime NOT NULL,
      `InsertIPAddress` varbinary(16) DEFAULT NULL,
      `UpdateUserID` int(11) DEFAULT NULL,
      `DateUpdated` datetime DEFAULT NULL,
      `UpdateIPAddress` varbinary(16) DEFAULT NULL,
      PRIMARY KEY (`BanID`),
      UNIQUE KEY `UX_Ban` (`BanType`,`BanValue`)
    ");

    $this->vanillaUser = new VanillaUser($this->getPDO(), $this->getCache());
  }

  protected function getDataSet() {
    return $this->createArrayDataSet([
      'GDN_User' => self::USER_DATASET,
      'GDN_Ban' => self::BANS,
    ]);
  }

  public function testGet() {
    $result = $this->vanillaUser->get(self::USER_DATASET[0]['UserID']);

    $this->assertNotFalse($result);
    $this->assertInstanceOf(VanillaUserModel::class, $result);

    $this->assertEquals(self::USER_DATASET[0]['UserID'], $result->getUserID());
    $this->assertEquals(self::USER_DATASET[0]['AuthID'], $result->getAuthId());
    $this->assertEquals(self::USER_DATASET[0]['Name'], $result->getName());
    $this->assertEquals(self::USER_DATASET[0]['Photo'], $result->getPhoto());
    $this->assertEquals(self::USER_DATASET[0]['About'], $result->getAbout());
    $this->assertEquals(self::USER_DATASET[0]['Email'], $result->getEmail());
    $this->assertEquals(self::USER_DATASET[0]['DateOfBirth'], $result->getDateOfBirth());
    $this->assertEquals(self::USER_DATASET[0]['InsertIPAddress'], $result->getInsertIPAddress());
    $this->assertFalse($result->getBanned());
    $this->assertFalse($result->hasUpdates());
    $this->assertEquals([
      'name' => self::USER_DATASET[0]['Name'],
      'photo' => self::USER_DATASET[0]['Photo'],
      'about' => self::USER_DATASET[0]['About'],
      'banned' => false,
    ], $result->jsonSerialize());

  }

  public function testFindBanned() {
    $vanillaUser = new VanillaUserModel();
    $vanillaUser->setEmail(self::USER_DATASET[0]['Email']);
    $result = $this->vanillaUser->findIfBanned($vanillaUser);
    $this->assertFalse($result);
  }

  public function testFindBanned2() {
    $vanillaUser = new VanillaUserModel();
    $vanillaUser->setName('BadName');
    $result = $this->vanillaUser->findIfBanned($vanillaUser);
    $this->assertTrue($result);
  }

  public function testFindBanned3() {
    $vanillaUser = new VanillaUserModel();
    //$vanillaUser->setName('BadName');
    $vanillaUser->setEmail('BadName@10minutemail.com');
    $result = $this->vanillaUser->findIfBanned($vanillaUser);
    $this->assertTrue($result);
  }

  public function testFindBanned4() {
    $vanillaUser = new VanillaUserModel();
    $vanillaUser->setName('BadName');
    $vanillaUser->setEmail('BadName@10minutemail.com');
    $result = $this->vanillaUser->findIfBanned($vanillaUser);
    $this->assertTrue($result);
  }

  public function testFindBanned5() {
    $vanillaUser = new VanillaUserModel();
    $vanillaUser->setName('INeverTest');
    $result = $this->vanillaUser->findIfBanned($vanillaUser);
    $this->assertTrue($result);
  }

  public function testFindBanned6() {
    $vanillaUser = new VanillaUserModel();
    $vanillaUser->setName('NeverTesting');
    $result = $this->vanillaUser->findIfBanned($vanillaUser);
    $this->assertTrue($result);
  }

  public function testAdd() {
    $vanillaUser = new VanillaUserModel();
    $vanillaUser->setName('NewUser');
    $vanillaUser->setEmail('newuser@example.com');
    $vanillaUser->setAuthId(127);
    $vanillaUser->getInsertIPAddress('50.232.152.17');

    // Has no ID
    $this->assertEmpty($vanillaUser->getUserId());

    $result = $this->vanillaUser->add($vanillaUser);

    $this->assertNotFalse($result);
    $this->assertInstanceOf(VanillaUserModel::class, $result);

    $userID = $result->getUserId();
    $this->assertNotEmpty($userID);

    // Can get result back from database
    $result2 = $this->vanillaUser->get($userID);

    $this->assertNotFalse($result2);
    $this->assertInstanceOf(VanillaUserModel::class, $result2);

    $this->assertEquals($vanillaUser->getName(), $result2->getName());
  }

  /**
   * Expect a PDOException
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage SQLSTATE[23000]: Integrity constraint violation
   */
  public function testAddIncomplete() {
    $vanillaUser = new VanillaUserModel();
    $vanillaUser->setName('NewUser2');

    $result = $this->vanillaUser->add($vanillaUser);
    $this->assertFalse($result); // Never runs
  }

  /**
   * Expect a PDOException
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage SQLSTATE[23000]: Integrity constraint violation
   */
  public function testAddEmpty() {
    $vanillaUser = new VanillaUserModel();

    $result = $this->vanillaUser->add($vanillaUser);
    $this->assertFalse($result); // Never runs
  }

  public function testUpdate() {

    $result = $this->vanillaUser->get(self::USER_DATASET[1]['UserID']);

    $this->assertNotFalse($result);
    $this->assertInstanceOf(VanillaUserModel::class, $result);

    $this->assertFalse($result->hasUpdates());
    $this->assertEquals(self::USER_DATASET[1]['Email'], $result->getEmail());

    $result->setEmail('updated-' . self::USER_DATASET[1]['Email']);

    $this->assertTrue($result->hasUpdates());

    $this->vanillaUser->update($result);

    $this->assertFalse($result->hasUpdates());

    $result2 = $this->vanillaUser->get(self::USER_DATASET[1]['UserID']);
    $this->assertNotEquals(self::USER_DATASET[1]['Email'], $result2->getEmail());
    $this->assertEquals('updated-' . self::USER_DATASET[1]['Email'], $result2->getEmail());
  }

  /**
   * Expect a PDOException
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage No Vanilla user fields to update
   */
  public function testNoUpdates() {
    $result = $this->vanillaUser->get(self::USER_DATASET[1]['UserID']);

    $this->assertNotFalse($result);
    $this->assertInstanceOf(VanillaUserModel::class, $result);

    $this->vanillaUser->update($result);
  }

  /**
   * Expect a PDOException
   *
   * @expectedException        PDOException
   * @expectedExceptionMessage No Vanilla user fields to update
   */
  public function testEmptyUpdate() {
    $vanillaUser = new VanillaUserModel();
    $vanillaUser->setEmail('updatedUser@example.com');

    $this->assertTrue($vanillaUser->hasUpdates());

    $this->vanillaUser->update($vanillaUser);
  }


}