<?php
namespace Telltale\Model;

class AdminUser implements \JsonSerializable {

  // Jira username
  private $username;
  
  // Jira fullname
  private $name;

  // Jira session UID
  private $jiraSession;

  // Permissions from shared/admin_entitlement_types
  private $permissions = [];

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
    $toHash = $this->username . ',' . $time . ',' . $this->jiraSession;
    return $this->username . ',' . $time . ',' . hash_hmac('SHA256', $toHash, getenv('AUTH_TOKEN_KEY'));
  }

  public function jsonSerialize() {
    return [
      'username' => $this->username,
      'name' => $this->name,
      'permissions' => $this->permissions,
    ];
  }

}