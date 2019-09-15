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
   * The "client_login_username".
   *
   * @var string.
  **/
  protected $client_login_username;

  /**
   * The "ttg_account_id" The auth table id for this user.
   *
   * @var string
  **/
  protected $ttg_account_id;

   /**
   * The game account "email" for this user.
   *
   * @var string
  **/
  protected $email;

  /**
   * The game account "password" for this user.
   *
   * @var string
  **/
  protected $password;

  /**
   * When this record was created
   *
   * @var datetime|null
  **/
  protected $created_at;

  /**
   * When this record was updated
   *
   * @var datetime|null
  **/
  protected $updated_at;

  public function getId() {
    return $this->id;
  }

  public function setId(string $id): self {
    $this->id = $id;
    return $this;
  }

  public function getEmail() {
    return $this->email;
  }

  public function setEmail(string $email): self {
    $this->updateField('email', $email);
    return $this;
  }

  public function getPassword() {
    return $this->password;
  }

  public function setPassword(string $password): self {
    $this->updateField('password', $password);
    return $this;
  }

  public function getUsername() {
    return $this->username;
  }

  public function setUsername(string $username): self {
    $this->updateField('username', $username);
    return $this;
  }

  public function getClientLoginUsername() {
    return $this->client_login_username;
  }

  public function setClientLoginUsername(string $client_login_username): self {
    $this->updateField('client_login_username', $client_login_username);
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

  public function setUpdatedAt(string $updated_at): self 
  {
    $this->updateField('updated_at', $updated_at);
    return $this;
  }

  public function getUpdatedAt() {
    return $this->updated_at;
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
      'email' => $this->getEmail(),
      'client_login_username' => $this->getClientLoginUsername(),
      'ttg_account_id' => $this->getTTGAccountId(),
      'created_at' => $this->getCreatedAt(),
      'updated_at' => $this->getUpdatedAt(),
    ];
  }

}