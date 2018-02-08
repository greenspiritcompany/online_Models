<?php
namespace Telltale\Model;
use DateTime;
use Exception;

class VanillaUser extends Model {

  protected $UserID;
  protected $AuthID;
  protected $Name;
  protected $Photo;
  protected $About;
  protected $Email;
  protected $DateOfBirth;
 // protected $CountVisits;
  protected $DateUpdated;
  //protected $DateFirstVisit;
  //protected $DateLastActive;
  protected $InsertIPAddress;
  protected $LastIPAddress;
  //protected $Score;
  //protected $Admin;
  protected $Banned;
  //protected $Deleted;


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
    $this->updateField('Name', $name);
    return $this;
  }

  public function getPhoto() {
    return $this->Photo;
  }

  public function setPhoto(string $photo): self {
    $this->updateField('Photo', $photo);
    return $this;
  }

  public function getAbout() {
    return $this->About;
  }

  public function setAbout(string $about): self {
    $this->updateField('About', $about);
    return $this;
  }

  public function getEmail() {
    return $this->Email;
  }

  public function setEmail(string $email): self {
    $this->updateField('Email', $email);
    return $this;
  }

  public function getDateOfBirth() {
    return $this->DateOfBirth;
  }

  public function setDateOfBirth(string $dateOfBirth): self {
    $dateOfBirth = new DateTime($dateOfBirth);
    $dateOfBirth = $dateOfBirth->format('Y-m-d H:i:s');
    $this->updateField('DateOfBirth', $dateOfBirth);
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