<?php
namespace Telltale\Model;

/**
 * From the game_configurator table in the services database
 * Allows users to configure their initial game state using node guids
 *
 */
class GameConfigurator extends Model {

   /**
   * Primary key of the auth users table
   *
   * @var int -
   **/
  protected $user_id;

  /**
   * The game key. Known as the project name in the Tool
   *
   * @var string.
  **/
  protected $game;

  /**
   * The node GUID in the dialogue tool
   *
   * @var string.
  **/
  protected $node_guid;

  /**
   * The datetime when this user record was created
   *
   * @var datetime created_at
   **/
  protected $created_at;

  /**
   * The datetime when this user record was last updated
   *
   * @var datetime|null updated_at
   **/
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

    if (!empty($game) && !ctype_alnum($game)) {
      throw new \InvalidArgumentException('Game must be alphanumeric.', 400);
    }

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
   * Convenience function to get node GUID
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