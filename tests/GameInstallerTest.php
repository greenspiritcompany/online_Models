<?php
namespace Telltale\Tests;

use Telltale\DataMapper\GameInstaller;
use Telltale\Model\GameInstaller as GameInstallerModel;
use Telltale\Model\Entitlement as EntitlementModel;

class GameInstallerTest extends AbstractDBTest {

  private $gameInstaller;

  public function __construct() {
    parent::__construct();

    $this->setupTableSchema('game_installer', "
      `game` varchar(34) NOT NULL DEFAULT '',
      `platform` varchar(16) NOT NULL DEFAULT '',
      `episode` int(11) DEFAULT NULL,
      `filepath` varchar(255) NOT NULL,
      `created_at` datetime NOT NULL,
      `updated_at` datetime DEFAULT NULL,
      `enabled` tinyint(1) NOT NULL DEFAULT '1',
      UNIQUE KEY `game` (`game`,`platform`,`filepath`)
    ");

    $this->gameInstaller = new GameInstaller($this->getPDO(), $this->getCache());
  }

  protected function getDataSet() {
    return $this->createArrayDataSet([
      'game_installer' => [
        [
          'game' => 'PHPUnit',
          'platform' => 'pc',
          'episode' => '1',
          'filepath' => '/PHPUnit1.exe',
          'created_at' => date("Y-m-d H:i:s"),
          'enabled' => 1
        ],
        [
          'game' => 'PHPUnit',
          'platform' => 'mac',
          'episode' => '1',
          'filepath' => '/PHPUnit1.dmg',
          'created_at' => date("Y-m-d H:i:s"),
          'enabled' => 1
        ],
        [
          'game' => 'PHPUnit',
          'platform' => 'pc',
          'episode' => '2',
          'filepath' => '/PHPUnit2.exe',
          'created_at' => date("Y-m-d H:i:s"),
          'enabled' => 0
        ],
        [
          'game' => 'BestGame',
          'platform' => 'pc',
          'filepath' => '/best/pc/BestGame.exe',
          'created_at' => date("Y-m-d H:i:s"),
          'enabled' => 1
        ],
      ],
    ]);
  }

  public function testFromEntitlement() {
    $entitlement = new EntitlementModel();
    $entitlement->setGame('PHPUnit');
    $entitlement->setItem('game_installer');
    $entitlement->setPlatform('telltale');
    $entitlement->setEnabled(true);

    $result = $this->gameInstaller->fromEntitlement($entitlement);

    $this->assertContainsOnlyInstancesOf(GameInstallerModel::class, $result);
    $this->assertInternalType('array', $result);
    $this->assertCount(2, $result); // out of 4 in dataset

    $unsignedDownload = 'https://cdn-vault.telltalegames.com/PHPUnit1.exe';

    $this->assertEquals($unsignedDownload, $result[0]->getDownload());
    $this->assertEquals('https://cdn-vault.telltalegames.com', $result[0]->getCdnRoot());
    $this->assertTrue($result[0]->getEnabled());

    $this->assertEquals([
      'game' => 'PHPUnit',
      'platform' => 'mac',
      'episode' => '1',
      'filepath' => '/PHPUnit1.dmg',
      'download' => 'https://cdn-vault.telltalegames.com/PHPUnit1.dmg',
    ], $result[1]->jsonSerialize());


    // Change to our cdn root
    $cdnRoot = 'https://example.com';
    $result[0]->setCdnRoot($cdnRoot);

    $result[0]->signDownload(function($url) {
      // could be anything
      return md5($url);
    });

    $this->assertNotEquals($unsignedDownload, $result[0]->getDownload());

    // our signed download
    $this->assertEquals(md5($cdnRoot . '/PHPUnit1.exe'), $result[0]->getDownload());

    $this->assertEquals([
      'game' => 'PHPUnit',
      'platform' => 'pc',
      'episode' => '1',
      'filepath' => '/PHPUnit1.exe',
      'download' => md5($cdnRoot . '/PHPUnit1.exe'),
    ], $result[0]->jsonSerialize());

  }

  public function testGetByGame() {

    $result = $this->gameInstaller->getByGame('BestGame');

    $this->assertContainsOnlyInstancesOf(GameInstallerModel::class, $result);
    $this->assertInternalType('array', $result);
    $this->assertCount(1, $result); // out of 4 in dataset

    $this->assertEquals([
      'game' => 'BestGame',
      'platform' => 'pc',
      'episode' => NULL,
      'filepath' => '/best/pc/BestGame.exe',
      'download' => 'https://cdn-vault.telltalegames.com/best/pc/BestGame.exe',
    ], $result[0]->jsonSerialize());

  }

  public function testGetByGameNoResult() {
    $result = $this->gameInstaller->getByGame('MagicalGame');

    $this->assertContainsOnlyInstancesOf(GameInstallerModel::class, $result);
    $this->assertInternalType('array', $result);
    $this->assertCount(0, $result); // out of 4 in dataset
  }

  public function testGetByGamePlatformPC() {
    $result = $this->gameInstaller->getByGamePlatform('PHPUnit', 'pc');

    $this->assertContainsOnlyInstancesOf(GameInstallerModel::class, $result);
    $this->assertInternalType('array', $result);
    $this->assertCount(1, $result); // out of 4 in dataset

    $this->assertEquals([
      'game' => 'PHPUnit',
      'platform' => 'pc',
      'episode' => 1,
      'filepath' => '/PHPUnit1.exe',
      'download' => 'https://cdn-vault.telltalegames.com/PHPUnit1.exe',
    ], $result[0]->jsonSerialize());
  }

  public function testGetByGamePlatformMac() {
    $result = $this->gameInstaller->getByGamePlatform('PHPUnit', 'mac');

    $this->assertContainsOnlyInstancesOf(GameInstallerModel::class, $result);
    $this->assertInternalType('array', $result);
    $this->assertCount(1, $result); // out of 4 in dataset

    $this->assertEquals([
      'game' => 'PHPUnit',
      'platform' => 'mac',
      'episode' => 1,
      'filepath' => '/PHPUnit1.dmg',
      'download' => 'https://cdn-vault.telltalegames.com/PHPUnit1.dmg',
    ], $result[0]->jsonSerialize());
  }

  public function testGetByGamePlatformTelltale() {
    $result = $this->gameInstaller->getByGamePlatform('PHPUnit', 'telltale');

    $this->assertContainsOnlyInstancesOf(GameInstallerModel::class, $result);
    $this->assertInternalType('array', $result);
    $this->assertCount(2, $result); // out of 4 in dataset

    $this->assertEquals([
      'game' => 'PHPUnit',
      'platform' => 'pc',
      'episode' => 1,
      'filepath' => '/PHPUnit1.exe',
      'download' => 'https://cdn-vault.telltalegames.com/PHPUnit1.exe',
    ], $result[0]->jsonSerialize());

    $this->assertEquals([
      'game' => 'PHPUnit',
      'platform' => 'mac',
      'episode' => 1,
      'filepath' => '/PHPUnit1.dmg',
      'download' => 'https://cdn-vault.telltalegames.com/PHPUnit1.dmg',
    ], $result[1]->jsonSerialize());
  }



}