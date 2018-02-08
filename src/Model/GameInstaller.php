<?php
namespace Telltale\Model;

class GameInstaller extends Model {

  /**
   * The game the installer is for
   * See the game table from shared, this is a text key to a "global" database of constant values.
   *
   * @var string
   */
  private $game;

  /**
   * The platform the installer is available for
   * See the platform table from shared, this is a text key to a "global" database of constant values.
   *
   * @var string
   */
  private $platform;

  /**
   * Some legacy installers are only for specific episodes. Should be null if installer handles entire season.
   *
   * @var int|null
   */
  private $episode;

  /**
   * The relative path to the installer after the cdnRootPath
   *
   * @var string
   */
  private $filepath;

  /**
   * The datetime when the game_installer record was created
   *
   * @var string
   */
  private $created_at;

  /**
   * The datetime when the game_installer record was last updated
   *
   * @var string
   */
  private $updated_at;

  /**
   * Toggles the ability for this installer to be downloaded
   *
   * @var int
   */
  private $enabled;

  /**
   * CDN Root url
   * This is not part of the game_installer table structure
   *
   * @var string
   */
  protected $cdnRootPath = 'https://cdn-vault.telltalegames.com';

  /**
   * Full download path
   * This is not part of the game_installer table structure
   *
   * @var string
   */
  protected $download;

  public function __construct() {
    $this->download = $this->cdnRootPath . $this->filepath;
  }

  public function getGame(): string {
    return $this->game;
  }

  public function setGame(string $game): self {
    $this->updateField('game', $game);
    return $this;
  }

  public function getEpisode(): int {
    return $this->episode;
  }

  public function setEpisode(int $episode): self {
    $this->updateField('episode', $episode);
    return $this;
  }

  public function getFilePath(): string {
    return $this->filepath;
  }

  public function setFilePath(string $filepath): self {
    $this->updateField('filepath', $filepath);
    $this->download = $this->cdnRootPath . $this->filepath;
    return self;
  }

  public function getDownload(): string {
    return $this->download;
  }

  public function getEnabled(): bool {
    return (bool)$this->enabled;
  }

  public function getCdnRoot(): string {
    return $this->cdnRootPath;
  }

  public function setCdnRoot(string $cdnRootPath): self {
    $this->cdnRootPath = $cdnRootPath;
    $this->download = $cdnRootPath . $this->filepath;
    return $this;
  }

  public function signDownload(callable $method) {
    $this->download = $method($this->cdnRootPath . $this->filepath);
  }

  public function jsonSerialize(): array {
    return [
      'game' => $this->game,
      'platform' => $this->platform,
      'episode' => $this->episode,
      'filepath' => $this->filepath,
      'download' => $this->download,
    ];
  }
}
