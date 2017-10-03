<?php
namespace Telltale\Model;

/**
 * From the Global Shared Game(s) table
 * Contains the master game 'key' or 'slug' the full 'display name' if the game is enabled/active and if CloudSync is enabled for it.
 *
 */
class Game implements \JsonSerializable {

  /**
   * Primary key of the game row
   *
   * @var int -
   **/
  private $id;

  /**
   * The game key. Known as the project name in the Tool
   *
   * @var string.
  **/
  private $game;

  /**
   * Full display name
   *
   * @var string
  **/
  private $display_name;

  /**
   * Is the game active / enabled / available
   *
   * @var bool
  **/
  private $active;

  /**
   * Is CloudSync enabled for this game
   *
   * @var bool
  **/
  private $cloudsync;


  public function getId(): int {
    return (int)$this->id;
  }

  public function setId(int $id): self {
    $this->id = $id;
    return $this;
  }

  public function getGame(): string {
    return $this->game ?? '';
  }

  public function setGame(string $game): self {
    $this->game = $game;
    return $this;
  }

  public function getDisplayName(): string {
    return $this->display_name ?? '';
  }

  public function setDisplayName(string $display_name): self {
    $this->display_name = $display_name;
    return $this;
  }

  public function setActive($active): self {
    $active = filter_var($active, FILTER_VALIDATE_BOOLEAN);
    // Cast bool to int for 1 or 0 in db
    $this->active = (int)$active;
    return $this;
  }

  public function getActive() {
    return (bool)$this->active;
  }

  public function setCloudSync($cloudsync): self {
    $cloudsync = filter_var($cloudsync, FILTER_VALIDATE_BOOLEAN);
    // Cast bool to int for 1 or 0 in db
    $this->cloudsync = (int)$cloudsync;
    return $this;
  }

  public function getCloudsync() {
    return (bool)$this->cloudsync;
  }

  /**
   * Returns an array of all fields that have been set along with their values
   *
   * @return array
   **/
  public function queryHelper(): array {
    $reflection = new \ReflectionClass($this);
    $properties = $reflection->getProperties(\ReflectionProperty::IS_PRIVATE);

    $keys = [];
    $values = [];

    foreach ($properties as $property) {
        $name = $property->getName();
        if (isset($this->{$name})) {
            $keys[] = $name;
            $values[] = $this->{$name};
        }
    }

    return ['keys' => $keys, 'values' => $values];
  }

    /**
   * Returns an array of jsonSerialize values safe to display to the public
   *
   * @return array
   **/
   public function jsonSerialize(): array {
    return [
      'key' => $this->getGame(),
      'name' => $this->getDisplayName(),
      'active' => $this->getActive(),
      'cloudsync' => $this->getCloudsync(),
    ];
  }
}