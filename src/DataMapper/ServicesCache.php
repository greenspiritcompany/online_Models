<?php
namespace Telltale\DataMapper;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface as CacheItem;
use Telltale\Model\ServicesCache as ServicesCacheItem;

/**
* Services Cache Pool
*
*/
class ServicesCache implements CacheItemPoolInterface {

  const SEPARATOR_TAG = '|';

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

  public function getItem(string $key): ServicesCacheItem {
    $value = $this->cache->get($key);
    if ($this->cache->getResultCode() !== \Memcached::RES_SUCCESS) {
      return $this->buildItem($key, false);
    }
    return $this->buildItem($key, true, $value);
  }

  public function getItems(array $keys = []): array {
    if (empty($keys)) {
      return [];
    }

    $data = $this->cache->getMulti($keys);
    if ($data === false) {
      $data = [];
    }
    return $this->buildItems($keys, $data);
  }

  public function hasItem(string $key): bool {
    $client = $this->cache->get($key);
    return $client->getResultCode() == \Memcached::RES_SUCCESS;
  }

  public function clear() {
    $this->cache->flush();
    return true;
  }

  public function deleteItem(string $key): bool {
    $this->cache->delete($key);
    return true;
  }

  public function deleteItems(array $keys): bool {
    $this->cache->deleteMulti($keys);
    return true;
  }

  public function save(CacheItem $item): bool {

    $this->cache->set(
      $item->getKey(),
      $item->get(),
      $item->getExpiresAt()
    );

    return true;
  }

  public function saveDeferred(CacheItem $item): bool {
    $this->deferred[$item->getKey()] = $item;
    return true;
  }

  public function commit(): bool {
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
  protected function buildItems(array $keys, $data): array {
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
  protected function buildItem(string $key, bool $isHit, $value = null): ServicesCacheItem {
    return new ServicesCacheItem($key, $isHit, $value);
  }

}