<?php
namespace Telltale\Model;

/**
 * Represents a CloudSave in S3
 * Python version can be found here:
 * https://gitlab.telltale.com/online/services/blob/master/scripts/python/searchsaves/ttg/cloudsave.py
 */
class CloudSave extends Model {

  protected $account_id;

  protected $game;

  protected $platform;

  protected $version;

  protected $save_slot;

  protected $directory;

  protected $filename;

  /**
   * Convert a key (aws key including prefix) into a CloudSave model
   *
   * @param string $key
   */
  public function __construct(string $key = null) {
    if ($key) {
      $path_parts = explode('/', $key);

      $this->account_id = $path_parts[0];
      $this->game = $path_parts[1];

      if (count($path_parts) == 6) {
        $this->platform = $path_parts[2];
        $this->version = $path_parts[3];
      }

      $this->filename = end($path_parts);
      $this->directory = prev($path_parts);
      $this->save_slot = explode('_', $this->filename)[1];
    }
  }

  public function getAccountId() {
    return $this->account_id;
  }

  public function getGame() {
    return $this->game;
  }

  public function getPlatform() {
    return $this->platform ?? 'None';
  }

  public function getVersion() {
    return $this->version ?? 'None';
  }

  public function getSaveSlot() {
    return $this->save_slot;
  }

  public function getPrefix() {
    return implode('/', array_filter([
      $this->account_id,
      $this->game,
      $this->platform,
      $this->version,
      $this->directory,
      $this->filename,
    ]));
  }

  public function getDirectory() {
    return $this->directory;
  }

  public function getFilename() {
    return $this->filename;
  }

  /**
   * Returns an array of jsonSerialize values safe to display
   *
   * @return array
   **/
  public function jsonSerialize(): array {
    return [
      'game' => $this->getGame(),
      'platform' => $this->getPlatform(),
      'version' => $this->getVersion(),
      'save_slot' => $this->getSaveSlot()
    ];
  }

}