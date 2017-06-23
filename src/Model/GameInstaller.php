<?php
namespace Telltale\Model;

class GameInstaller implements \JsonSerializable {

  private $game;
  private $platform;
  private $episode;
  private $filepath;
  private $created_at;
  private $updated_at;
  private $enabled;

  protected $cdnRootPath = 'https://cdn-vault.telltalegames.com';
  protected $download;
  protected $signer;

  public function __construct() {
    $this->download = $this->cdnRootPath . $this->filepath;
  }

  public function getDownload() {
    return $this->cdnRootPath . $this->filepath;
  }

  public function getEnabled() {
    return $this->enabled;
  }

  public function getCdnRoot() {
    return $this->cdnRootPath;
  }

  public function setCdnRoot(string $cdnRootPath) {
    $this->cdnRootPath = $cdnRootPath;
    $this->download = $cdnRootPath . $this->filepath;
    return $this;
  }

  public function signDownload(callable $method) {
    $this->download = $method($this->cdnRootPath . $this->filepath);
  }

  public function jsonSerialize() {
    return [
      'game' => $this->game,
      'platform' => $this->platform,
      'episode' => $this->episode,
      'filepath' => $this->filepath,
      'download' => $this->download,
    ];
  }
}
