<?php
namespace Telltale\Model;
use Exception;
use League\OAuth2\Client\Token\AccessToken;

class Association implements \JsonSerializable {

  /**
   * The user id assigned to this account association 
   * The primary key attribute of the auth.users table `id`
   *
   * @var int User ID (id PK) from the auth.users table
   **/
  private $user_id;

  /**
   * Association slug/key 
   * 
   * NOTE this is nullable here but the database requires it. Must be lowercase.
   *
   * @var string|null A descriptive key for the service this association is for. 
  **/
  private $provider;

  /**
   * The User identifier for the association. 
   * 
   * Typically this is the account uid or username.
   * For example: For Xbox provider it will be the XUID, PSN it will be PsnId
   *
   * @var string|null A descriptive key for the service this association is for. 
  **/
  private $identifier;

  /**
   * Represents an Access Token.
   * @link http://tools.ietf.org/html/rfc6749#section-1.4 Access Token (RFC 6749, §1.4)
   *
   * @var AccessToken access_token attributes
  **/
  private $access_token;

  /**
   * When the association was created
   *
   * @var datetime|null 
  **/
  private $created_at;

  /**
   * When the association was updated
   *
   * @var datetime|null 
  **/
  private $updated_at;


  public function __construct() {
    // Decode access_token when set by PDO
    if ($this->access_token && '{' == $this->access_token[0]) {
      $this->access_token = new AccessToken(json_decode($this->access_token, true));
    }
  }

  public function getUserId() {
    return (int)$this->user_id;
  }

  public function setUserId(int $userId): self {
    if ($this->user_id) throw new Exception('User ID should not be changed');
    $this->user_id = $userId;
    return $this;
  }

  public function getProvider() {
    return $this->provider;
  }

  public function setProvider(string $provider): self {
    if ($this->provider) throw new Exception('Association provider should not be changed');
    $this->provider = $provider;
    return $this;
  }

  public function getIdentifier() {
    return $this->identifier;
  }

  public function setIdentifier(string $identifier): self {
    $this->identifier = $identifier;
    return $this;
  }  

  public function getAccessToken() {
    return $this->access_token;
  }

  public function setAccessToken(AccessToken $accessToken): self {
    $this->access_token = $accessToken;
    return $this;
  }

  public function getCreatedAt() {
    return $this->created_at;
  }

  public function getUpdatedAt() {
    return $this->updated_at;
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
      'provider' => $this->getProvider(),
      'associated' => true,
      'accessToken' => $this->getAccessToken(),
    ];
  }

}