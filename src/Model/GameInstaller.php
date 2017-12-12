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

  public function getDownload(): string {
    return $this->download;
  }

  public function getEnabled(): bool {
    return (bool)$this->enabled;
  }

  public function getCdnRoot(): string {
    return $this->cdnRootPath;
  }

  public function getFilePath(): string {
    return $this->filepath;
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
