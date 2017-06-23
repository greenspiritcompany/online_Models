<?php
namespace Telltale\DataMapper;

use Telltale\Model\GameInstaller as GameInstallerModel;
use Telltale\Model\Entitlement as EntitlementModel;
use PDO;
use PDOException;

class GameInstaller {

  public function __construct(PDO $pdo) {
    $this->pdo = $pdo;
  }

  // @TODO: Add methods to add and edit game installers for use in internal API

  /**
   * Get installer urls available to an entitlement
   *
   * @param EntitlementModel $entitlement
   * @return array
   */
  public function fromEntitlement(EntitlementModel $entitlement): array {

    if ($entitlement->getItem() !== 'game_installer') {
      throw new \UnexpectedValueException('This entitlement is not for a game installer', 400);
    }

    if (!$entitlement->getEnabled()) {
      throw new \UnexpectedValueException('This entitlement has been disabled.', 403);
    }

    return $this->getByGamePlatform($entitlement->getGame(), $entitlement->getPlatform());
  }

  /**
   * Get list of installer urls by game slug
   *
   * @param string $game
   * @return array
   */
  public function getByGame(string $game): array {
    $statement = $this->pdo->prepare(
      'SELECT game, platform, episode, filepath, created_at, updated_at, enabled
      FROM game_installer
      WHERE game = :game AND enabled = 1
      ORDER BY episode ASC, platform DESC'
    );

    $statement->execute(['game' => $game]);
    return $statement->fetchAll(PDO::FETCH_CLASS, GameInstallerModel::class);
  }

  /**
   * Get list of installer urls by game slug limited by platform
   *
   * @param string $game
   * @param string $platform
   * @return array
   */
  public function getByGamePlatform(string $game, string $platform): array {

    // Not a valid platform. Placeholder for "All platforms Telltale Store supports" (pc/mac/linux?)
    if ($platform == 'telltale') {
      return $this->getByGame($game);
    }

    $statement = $this->pdo->prepare(
      'SELECT game, platform, episode, filepath, created_at, updated_at, enabled
      FROM game_installer
      WHERE game = :game AND enabled = 1 AND platform = :platform
      ORDER BY episode ASC, platform DESC'
    );

    $statement->execute(['game' => $game, 'platform' => $platform]);
    return $statement->fetchAll(PDO::FETCH_CLASS, GameInstallerModel::class);
  }

}