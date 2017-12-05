<?php
namespace Telltale\Tests;

use PHPUnit\DbUnit\DataSet\CsvDataSet;

use Telltale\Model\AdminUser as AdminUserModel;

class AdminUserTest extends AbstractDBTest {

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

    $this->AdminUser = new \Telltale\DataMapper\AdminUser($this->getPDO(), $this->getCache());
  }

  protected function getDataSet() {
    return $this->createXMLDataSet(dirname(__FILE__) . '/../fixtures/AdminUser.xml');
  }

  public function testGetUsername() {
    $result = $this->AdminUser->get('unit_test_user_1');

    $this->assertNotFalse($result);
    $this->assertInstanceOf(AdminUserModel::class, $result);
  }


}