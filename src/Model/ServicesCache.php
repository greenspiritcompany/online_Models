<?php
namespace Telltale\Model;

use Psr\Cache\CacheItemInterface;

class ServicesCache implements CacheItemInterface {

  protected $key;
  protected $isHit;
  protected $value;
  protected $expiresAt;

  public function __construct($key, $isHit, $value = null, $expiresAt = null) {
    $this->key = $key;
    $this->isHit = $isHit;
    $this->value = $value;
    $this->expiresAt = $expiresAt;
  }

  public function getKey() {
    return $this->key;
  }

  public function get() {
    return $this->value;
  }

  public function isHit() {
    return $this->isHit;
  }

  public function set($value) {
    $this->value = $value;
    return $this;
  }

  public function expiresAt($expiresAt) {

    if ($expiresAt instanceof \DateTimeInterface) {
      $this->expiresAt = $expiresAt->getTimestamp();
    } elseif (is_int($expiresAt) || null === $expiresAt) {
      $this->expiresAt = $expiresAt;
    } else {
      throw new \InvalidArgumentException('Cache item ttl/expiresAt must be of type integer or \DateTimeInterface.');
    }

    return $this;
  }

  public function expiresAfter($interval) {
    if ($interval === null) {
      $this->expiresAt = null;
      return;
    }

    $expires = new \DateTime();
    if (is_int($interval)) {
      $interval = new \DateInterval('PT'.$interval.'S');
    }

    $this->expiresAt = $expires->add($interval);
    return $this;
  }

  /**
   * Get expiration timestamp
   *
   * @return null
   */
  public function getExpiresAt() {
    return $this->expiresAt;
  }

}