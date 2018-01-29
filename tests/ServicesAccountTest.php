<?php
namespace Telltale\Tests;

use Telltale\DataMapper\ServicesAccount;
use Telltale\Model\ServicesAccount as ServicesAccountModel;

class ServicesAccountTest extends AbstractDBTest {

  const ACCOUNT_DATASET = [
    [
      'id' => 100,
      'username' => 'PHPUnit',
      'password' => 'PHPUnit-Password',
      'email' => 'phpunit@example.com',
      'last_login' => '0000-00-00 00:00:00',
      'login_hash' => 'd4e4043dfb591034d8544ea7b47f295ed7d4792b',
      'created_at' => '1375809432',
      'ttg_account_id' => 110
    ],
    [
      'id' => 200,
      'username' => 'ServicesAccountTest',
      'password' => 'ServicesAccountTest-Password',
      'email' => 'ServicesAccountTest@example.com',
      'last_login' => '0000-00-00 00:00:00',
      'login_hash' => '01532d3fde911a20fecc7134ad34ec090b671c86',
      'created_at' => '1387501390',
      'ttg_account_id' => 210
    ],
  ];

  private $servicesAccount;

  public function __construct() {
    parent::__construct();

    $this->setupTableSchema('account', "
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `username` varchar(50) NOT NULL,
      `password` varchar(255) NOT NULL,
      `group_id` int(11) NOT NULL DEFAULT '1',
      `email` varchar(255) NOT NULL,
      `last_login` varchar(25) NOT NULL,
      `previous_login` varchar(25) NOT NULL DEFAULT '0',
      `login_hash` varchar(255) NOT NULL,
      `user_id` int(11) NOT NULL DEFAULT '0',
      `created_at` int(11) NOT NULL DEFAULT '0',
      `updated_at` int(11) NOT NULL DEFAULT '0',
      `is_anonymous` tinyint(1) NOT NULL DEFAULT '1',
      `web_customer_fk` int(11) DEFAULT NULL,
      `client_login_username` varchar(50) NOT NULL DEFAULT '',
      `xboxlive` varchar(65) DEFAULT NULL,
      `psn` varchar(65) DEFAULT NULL,
      `ttg_account_id` int(11) DEFAULT NULL,
      `skybound_insider` tinyint(4) DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `i_emailusername` (`email`,`username`),
      KEY `i_usernameemail` (`username`,`email`),
      KEY `u_xbox_live` (`xboxlive`),
      KEY `u_psn` (`psn`),
      KEY `web_customer_fk_idx` (`web_customer_fk`),
      KEY `ttg_account_id_idx` (`ttg_account_id`)
    ");

    $this->servicesAccount = new ServicesAccount($this->getPDO(), $this->getCache());
  }

  protected function getDataSet() {
    return $this->createArrayDataSet([
      'account' => self::ACCOUNT_DATASET,
    ]);
  }

  public function testFind() {
    $servicesAccount = new ServicesAccountModel();
    $servicesAccount->setId(self::ACCOUNT_DATASET[0]['id']);
    $servicesAccount->setUsername(self::ACCOUNT_DATASET[0]['username']);
    $servicesAccount->setTTGAccountId(self::ACCOUNT_DATASET[0]['ttg_account_id']);
    $servicesAccount->setCreatedAt(self::ACCOUNT_DATASET[0]['created_at']);

    $result = $this->servicesAccount->find($servicesAccount);

    $this->assertNotFalse($result);
    $this->assertInstanceOf(ServicesAccountModel::class, $result);

    $this->assertEquals(self::ACCOUNT_DATASET[0]['id'], $result->getId());
    $this->assertEquals(self::ACCOUNT_DATASET[0]['username'], $result->getUsername());
    $this->assertEquals(self::ACCOUNT_DATASET[0]['ttg_account_id'], $result->getTTGAccountId());
    $this->assertEquals(self::ACCOUNT_DATASET[0]['created_at'], $result->getCreatedAt());
    $this->assertEquals([
      'id' => self::ACCOUNT_DATASET[0]['id'],
      'username' => self::ACCOUNT_DATASET[0]['username'],
      'ttg_account_id' => self::ACCOUNT_DATASET[0]['ttg_account_id'],
      'created_at' => self::ACCOUNT_DATASET[0]['created_at'],
    ], $result->jsonSerialize());
  }

  public function testFindNonExistentAccount() {
    $servicesAccount = new ServicesAccountModel();
    $servicesAccount->setUsername('NonExistentAccount');

    $result = $this->servicesAccount->find($servicesAccount);
    $this->assertFalse($result);
  }

  public function testGetIdFromAuthId() {
    $result = $this->servicesAccount->getIdFromAuthId(self::ACCOUNT_DATASET[1]['ttg_account_id']);
    $this->assertEquals(self::ACCOUNT_DATASET[1]['id'], $result);
  }

  public function testGetIdFromAuthIdNoResult() {
    $result = $this->servicesAccount->getIdFromAuthId(1000);
    $this->assertEquals(0, $result);
  }

}