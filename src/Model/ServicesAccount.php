<?php
namespace Telltale\Model;

class ServicesAccount extends Model {

  /**
   * The "id" of the services account record
   *
   * @var string -
   **/
  protected $id;

  /**
   * The "username". Not really used.
   *
   * @var string.
  **/
  protected $username;

  /**
   * The "ttg_account_id" The auth table id for this user.
   *
   * @var string
  **/
  protected $ttg_account_id;

  /**
   * When this record was created
   *
   * @var datetime|null
  **/
  protected $created_at;

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