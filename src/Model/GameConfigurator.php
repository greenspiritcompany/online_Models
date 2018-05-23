<?php
namespace Telltale\Model;

/**

 */
class GameConfigurator extends Model {

  protected $user_id;

  protected $game;

  protected $node_guid;

  protected $created_at;

  protected $updated_at;

  public function getUserId(): int {
    return $this->user_id ?? 0;
  }

  public function setUserId(int $user_id): self {
    $this->updateField('user_id', $user_id);
    return $this;
  }

  public function getGame(): string {
    return $this->game ?? '';
  }

  public function setGame(string $game): self {
    $this->updateField('game', $game);
    return $this;
  }

  public function getNodeGuid(): string {
    return $this->node_guid ?? '';
  }

  public function setNodeGuid(string $node_guid): self {
    $this->updateField('node_guid', $node_guid);
    return $this;
  }

  public function getCreatedAt(): string {
    return $this->created_at ?? '';
  }

  public function getUpdatedAt(): string {
    return $this->updated_at ?? '';
  }

  /**
   * Convenience function get get node uid
   *
   * @return string
   */
  public function __toString(): string {
    return $this->getNodeGuid();
  }

  public function jsonSerialize(): array {
    return [
      'game' => $this->getGame(),
      'node_guid' => $this->getNodeGuid(),
      'created_at' => $this->getCreatedAt(),
      'updated_at' => $this->getUpdatedAt(),
    ];
  }

}