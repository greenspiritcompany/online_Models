<?php
namespace Telltale\Model;

class AdminUser implements \JsonSerializable {

  // TTG Account Email
  private $email;

  // Jira username - Obsolete
  private $username;
  
  // Jira fullname
  private $name;

  // Jira session UID
  private $jiraSession;

  // Permissions from shared/admin_entitlement_types
  private $permissions = [];

  public function getEmail() {
    return $this->email;
  }

  public function setEmail(string $email): self {
    $this->email = strtolower($email);
    return $this;
  }

  public function getUsername() {
    return $this->username;
  }

  public function setUsername(string $username): self {
    $this->username = strtolower($username);
    return $this;
  }

  public function getName() {
    return $this->name;
  }

  public function setName(string $name): self {
    $this->name = $name;
    return $this;
  }

  public function getJiraSession() {
    return $this->jiraSession;
  }

  public function setJiraSession(string $sessionKey): self {
    $this->jiraSession = $sessionKey;
    return $this;
  }

  public function generateSessionId($length = 32) 
  {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  public function getPermissions(): array {
    return (array)$this->permissions;
  }

  public function hasPermission(string $permission): bool {

    // Admin permission means they have all permissions
    if (in_array('admin', $this->permissions)) {
      return true;
    }

    return in_array(strtolower($permission), $this->permissions);
  }

  public function setPermissions(array $permissions): self {
    $this->permissions = array_map('strtolower', $permissions);
    return $this;
  }

  public function authToken(string $time = '') {
    $time = $time ?: time();
    $toHash = $this->email . ',' . $this->username . ',' . $time . ',' . $this->jiraSession;
    return $this->email . ',' . $this->username . ',' . $time . ',' . hash_hmac('SHA256', $toHash, getenv('AUTH_TOKEN_KEY'));
  }

  public function jsonSerialize() {
    return [
      'username' => $this->username,
      'name' => $this->name,
      'permissions' => $this->permissions,
    ];
  }

}