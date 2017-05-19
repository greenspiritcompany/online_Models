<?php
namespace Telltale\Model;

class EntitlementItem implements \JsonSerializable {
  private $id;
  private $item;
  private $relation;
  private $game;
  private $platform;
  private $expires_at;

  public function __get($prop) {
    if (property_exists($this, $prop)) {
      return $this->$prop;
    }
  }

  public function jsonSerialize() {
    return [
      'id' => $this->id,
      'item' => $this->item,
      'relation' => $this->relation,
      'game' => $this->game,
      'platform' => $this->platform,
      'expires' => $this->expires_at
    ];
  }
}
