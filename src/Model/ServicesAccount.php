<?php
namespace Telltale\Model;

class ServicesAccount implements \JsonSerializable {

  /**
   * The "id" of the services account record
   *
   * @var string -
   **/
  private $id;

  /**
   * The "username". Not really used.
   *
   * @var string.
  **/
  private $username;

  /**
   * The "ttg_account_id" The auth table id for this user.
   *
   * @var string
  **/
  private $ttg_account_id;

  /**
   * When this record was created
   *
   * @var datetime|null
  **/
  private $created_at;

  public function getId() {
    return $this->id;
  }

  public function setId(string $id): self {
    $this->id = $id;
    return $this;
  }

  public function getUsername() {
    return $this->username;
  }

  public function setUsername(string $username): self {
    $this->username = $username;
    return $this;
  }

  public function getTTGAccountId() {
    return $this->ttg_account_id;
  }

  public function setTTGAccountId(string $ttg_account_id): self {
    $this->ttg_account_id = $ttg_account_id;
    return $this;
  }

  public function setCreatedAt(string $created_at): self {
    $this->created_at = $created_at;
    return $this;
  }

  public function getCreatedAt() {
    return $this->created_at;
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
      'id' => $this->getId(),
      'username' => $this->getUsername(),
      'ttg_account_id' => $this->getTTGAccountId(),
      'created_at' => $this->getCreatedAt(),
    ];
  }

}