<?php
namespace Telltale\DataMapper;

use Psr\Cache\CacheItemPoolInterface;
use Telltale\Model\ServicesCache as ServicesCacheItem;

class ServicesCache implements CacheItemPoolInterface {

  /**
   * @type \Memcached
   */
  protected $cache;

  /**
   * @type ServicesCacheItem[] deferred
   */
  protected $deferred = [];

  public function __construct(\Memcached $cache) {
    $this->cache = $cache;
  }

  public function getItem($key) {
    $value = $this->cache->get($key);
    if ($this->cache->getResultCode() !== \Memcached::RES_SUCCESS) {
      return $this->buildItem($key, false);
    }
    return $this->buildItem($key, true, $value);
  }

  public function getItems(array $keys = []) {
    if (empty($keys)) {
      return [];
    }

    $data = $this->cache->getMulti($keys);
    if ($data === false) {
      $data = [];
    }
    return $this->buildItems($keys, $data);
  }

  public function hasItem($key) {
    $client = $this->cache->get($key);
    return $client->getResultCode() == \Memcached::RES_SUCCESS;
  }

  public function clear() {
    $this->cache->flush();
    return true;
  }

  public function deleteItem($key) {
    $this->cache->delete($key);
    return true;
  }

  public function deleteItems(array $keys) {
    $this->cache->deleteMulti($keys);
    return true;
  }

  public function save(ServicesCacheItem $item) {

    $expiresAt = $item->getExpiresAt();
    $expiresAt = $expiresAt ? $expiresAt->getTimeStamp() : 0;

    $this->client->set(
      $item->getKey(),
      $item->get(),
      $$expiresAt
    );

    return true;
  }

  public function saveDeferred(ServicesCacheItem $item) {
    $this->deferred[$item->getKey()] = $item;
    return true;
  }

  public function commit() {
    $saved = true;
    foreach ($this->deferred as $item) {
        if (!$this->save($item)) {
            $saved = false;
        }
    }
    $this->deferred = [];

    return $saved;
  }

  /**
   * Build Items
   *
   * @param array $keys
   * @param array $data
   * @return array
   */
  protected function buildItems($keys, $data) {
    $result = [];

    foreach($keys as $key) {
      if (array_key_exists($key, $data)) {
        $result[$key] = $this->buildItem($key, true, $data[$key]);
      } else {
        $result[$key] = $this->buildItem($key, false);
      }
    }
    return $result;
  }

  /**
   * Build Item object
   *
   * @param string $key
   * @param bool $isHit
   * @param mixed $value stored value
   * @return Item
   */
  protected function buildItem($key, $isHit, $value = null) {
    return new ServicesCacheItem($key, $isHit, $value);
  }

}