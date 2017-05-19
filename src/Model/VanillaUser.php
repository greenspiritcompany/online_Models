<?php
namespace Telltale\Model;
use DateTime;
use Exception;

class VanillaUser implements \JsonSerializable {

  private $UserID;
  private $AuthID;
  private $Name;
  private $Photo;
  private $About;
  private $Email;
  private $DateOfBirth;
 // private $CountVisits;
  private $DateUpdated;
  //private $DateFirstVisit;
  //private $DateLastActive;
  private $InsertIPAddress;
  private $LastIPAddress;
  //private $Score;
  //private $Admin;
  private $Banned;
  //private $Deleted;

  /** 
   * Stores what fields have been set and now differ from their initial values
   * Used by queryUpdateHelper to help build PDO statements
   * 
   * @var array updatedFields
   **/
  protected $updatedFields = [];

  public function getUserID() {
    return (int)$this->UserID;
  }

  public function setUserID(int $userID): self {
    if ($this->UserID) throw new Exception('UserID should not be changed');
    $this->UserID = $userID;
    return $this;
  }

  public function getAuthId() {
    return (int)$this->AuthID;
  }  

  public function setAuthId(int $authID): self {
    if ($this->AuthID) throw new Exception('AuthID should not be changed');
    $this->AuthID = $authID;
    return $this;
  }  

  public function getName() {
    return $this->Name;
  }
  
  public function setName(string $name): self {
    if ($this->Name !== $name) $this->updatedFields[] = 'Name';
    $this->Name = $name;
    return $this;
  }

  public function getPhoto() {
    return $this->Photo;
  }
  
  public function setPhoto(string $photo): self {
    if ($this->Photo !== $photo) $this->updatedFields[] = 'Photo';
    $this->Photo = $photo;
    return $this;
  }

  public function getAbout() {
    return $this->About;
  }
  
  public function setAbout(string $about): self {
    if ($this->About !== $about) $this->updatedFields[] = 'About';
    $this->About = $about;
    return $this;
  }

  public function getEmail() {
    return $this->Email;
  }
  
  public function setEmail(string $email): self {
    if ($this->Email !== $email) $this->updatedFields[] = 'Email';
    $this->Email = $email;
    return $this;
  }

  public function getDateOfBirth() {
    return $this->DateOfBirth;
  }
  
  public function setDateOfBirth(string $dateOfBirth): self {
    $dateOfBirth = new DateTime($dateOfBirth);
    $dateOfBirth = $dateOfBirth->format('Y-m-d H:i:s');
    if ($this->DateOfBirth !== $dateOfBirth) $this->updatedFields[] = 'DateOfBirth';
    $this->DateOfBirth = $dateOfBirth;
    return $this;
  }

  public function getInsertIPAddress() {
    return $this->InsertIPAddress;
  }

  public function getIPAddress() {
    $ipAddress = $this->LastIPAddress ?? $this->InsertIPAddress;
    
    // If it's already a valid IP address, don't bother unpacking it.
    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_IPV6)) {
      return $ipAddress;
    }
    
    return $ipAddress ? inet_ntop($ipAddress) : $ipAddress;
  }

  public function setIPAddress($ipAddress) {
    if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4|FILTER_FLAG_IPV6)) throw new Exception('Invalid IP Address');

    // Pack the IP Address
    $ipAddress = inet_pton($ipAddress);

    if (!$this->InsertIPAddress) {
      $this->InsertIPAddress = $ipAddress;
    }

    $this->LastIPAddress = $ipAddress;

    return $this;
  }

  public function getBanned() {
    return (bool)$this->Banned;
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
   * Returns an array of all fields that have been updated along with their values
   * 
   * @return array
   **/
  public function queryUpdateHelper(): array {
    $keys = array_unique($this->updatedFields);
    $values = [];

    foreach ($keys as $key) {
      $values[] = $this->{$key};
    }

    $this->updatedFields = [];

    return ['keys' => $keys, 'values' => $values];
  }


  /** 
   * Returns an array of jsonSerialize values safe to display to the public
   * 
   * @return array
   **/
  public function jsonSerialize(): array {
    return [
      'name' => $this->getName(),
      'photo' => $this->getPhoto(),
      'about' => $this->getAbout(),
      'banned' => $this->getBanned(),
    ];
  }

}