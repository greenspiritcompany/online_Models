<?php
namespace Telltale\Model;

class Entitlement implements \JsonSerializable {

  /**
    * customer_fk from ttgshop_games needs to be joined with stage_auth.users on customer_fk == customer_id.
    * The primary key attribute of the stage_auth.users table `id` is then used as the user_id.
    *
    * @var int User ID (id PK) from the stage_auth.users table.
    */
  private $user_id;

  /**
    * Used in-game (or website) to unlock features or the entire game using a string value with special meaning
    * behind it.
    *
    * ttgshop_games imported items are set to be "seasonpass" because the items in the ttgshop_games are all season passes.
    *
    * NOTE this is nullable here but the database requires it. The data must be all lowercase.
    *
    * @example seasonpass or tshirt_starlord_04
    * @var string|null A descriptive key of what the player is entitled to, like a key.
    */
  private $relation;

  /**
    * Used in-game primarilly to filter out certain relation entitlements.
    *
    * ttgshop_games imported items are set to be "game_installer" because items in the ttgshop_games are all full games.
    *
    * NOTE this is nullable here but the database requires it.
    *
    * @var string|null A variant of the relation which is more specific than relation.
    */
  private $item;

  /**
    * Every entitlement is associated with a game, currently, event DLC.
    *
    * TODO check https://jira.telltalegames.com/browse/ONL-1657 for the mapping from ttgshop_games.item_fk.
    *
    * @var string|null see the game table from shared, this is a text key to a "global" database of constant values.
    */
  private $game;

  /**
    * ttgshop_games uses a special key of "telltale", which means that the platform is one supported by the Telltale installer.
    *
    * @var string|null see the platform table from shared, this is a text key to a "global" database of constant values.
    */
  private $platform;

  /**
    * In-game, this is used to create an entitlement linking it back to the access_point_id of the player.
    *
    * ttgshop_games imported as a constant string of "ttgshop_games".
    *
    * @var string|null used primarily for debugging or analytics, it highlights how an entitlement was created. It may also be used in
    * refund logic.
    */
  private $source;

  /**
    * A unique ID related to the source, used to find the exact reference.
    *
    * ttgshop_games imported as the ID pk from ttgshop_games.
    *
    * @var int|null an ID related to the source if there's an ID related to it.
    */
  private $source_id;

    /** 
    * The datetime when the entitlement record was created
    * 
    * @var datetime created_at
    */
  private $created_at;

  /**
    * Entitlements should not allow being deleted.
    *
    * ttgshop_games imported from enabled, TODO check type if from int.
    *
    * TODO could we protect rows from being deleted?
    *
    * @var bool allows an entitlement to be used, it's false if this entitlement should not longer be used. This is true by default, if you want to revoke an entitlement, set it to false.
    */
  private $enabled;

  /**
    * Not a database field, this virtual field is specific for entitlements created by a code. The process is as follows:
    *
    * ttgshop_games doesn't use this.
    *
    * Possible values: -1 Prevent from being used, 0 Unused, 1 Used.
    *
    * 1. A code is created in the `entitlement_codes` table with a `used` of 0.
    * 2. Make sure `entitlement_codes` relates to `entitlement_items`.
    * 3. Create an `entitlement` with the fields from `entitlement_codes` except `user_id`.
    * 4. Set the `entitlement` source to be `entitlement_code` and source_id to be the code from `entitlement_codes`.
    * 5. Set the `used` to be 1 if it's False and successful.
    *
    * @var int check if the code has been redeemed, for entitlements created by a code.
    */
  private $used;

  /**
    * This might be needed for textures in the game or customizations. Designed for customizations on a personal level and not global.
    *
    * TODO check that the data type is JSON
    *
    * ttgshop_games, add in the serial_id and that's all.
    *
    * Don't migrate these game's serial_ids from ttgshop_games:
    * backtothefuture
    * puzzleagent2
    * jurassicpark
    * lawandorder
    * walkingdead1
    * fables
    * walkingdead
    * borderlands
    * gameofthrones
    * minecraft
    * walkingdeadm
    * minecraftap
    * batman
    * walkingdead3
    *
    * @var array extra attributes (must be JSON), not currently used but could be for anything related to the record.
    */
  private $attributes;

  public function __construct() {
    // Decode attributes when set by PDO
    if ($this->attributes) {
      $this->attributes = json_decode($this->attributes);
    }
  }

  public function getUserId() {
    return (int)$this->user_id;
  }

  public function setUserId(int $userId): self {
    $this->user_id = $userId;
    return $this;
  }

  public function getRelation() {
    return $this->relation;
  }

  public function setRelation(string $relation): self {
    $this->relation = $relation;
    return $this;
  }

  public function getItem() {
      return $this->item;
  }

  public function setItem(string $item): self {
    $this->item = $item;
    return $this;
  }

  public function getGame() {
    return $this->game;
  }

  public function setGame(string $game): self {
    $this->game = $game;
    return $this;
  }

  public function getPlatform() {
    return $this->platform;
  }

  public function setPlatform(string $platform): self {
    $this->platform = $platform;
    return $this;
  }

  public function getSource() {
    return $this->source;
  }

  public function setSource(string $source): self {
    $this->source = $source;
    return $this;
  }

  public function getSourceId() {
    return $this->source_id;
  }

  public function setSourceId($sourceId): self {
    $this->source_id = $sourceId;
    return $this;
  }

  public function getCreatedAt() {
    return $this->created_at;
  }

  public function getEnabled() {
    return (bool)$this->enabled;
  }

  public function setEnabled($enabled): self {
    $enabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
    // Cast bool to int for 1 or 0 in db
    $this->enabled = (int)$enabled;
    return $this;
  }

  public function getUsed() {
    return (bool)$this->used;
  }

  public function setUsed(bool $used): self {
    $this->used = (int)$used;
    return $this;
  }

  public function getAttributes() {
    return $this->attributes ?? [];
  }

  public function setAttributes(array $attributes): self {
    $this->attributes = $attributes;
    return $this;
  }

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

  public function jsonSerialize(): array {
    return [
      'game' => $this->getGame(),
      'relation' => $this->getRelation(),
      'item' => $this->getItem(),
      'platform' => $this->getPlatform(),
      'created_at' => $this->getCreatedAt(),
      'attributes' => $this->getAttributes(),
    ];
  }
}
