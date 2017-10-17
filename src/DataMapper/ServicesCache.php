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

  /**
   * Returns a Cache Item representing the specified key.
   *
   * @param string $key
   * @return ServicesCacheItem
   */
  public function getItem($key) {
    $value = $this->cache->get($key);
    if ($this->cache->getResultCode() !== \Memcached::RES_SUCCESS) {
      return $this->buildItem($key, false);
    }
    return $this->buildItem($key, true, $value);
  }

  /**
   * Returns a traversable set of cache items.
   *
   * @param array $keys
   * @return array
   */
  public function getItems($keys = []) {
    if (empty($keys)) {
      return [];
    }

    $data = $this->cache->getMulti($keys);
    if ($data === false) {
      $data = [];
    }
    return $this->buildItems($keys, $data);
  }

  /**
   * Confirms if the cache contains specified cache item.
   *
   * @param string $key
   * @return bool
   */
  public function hasItem($key) {
    $client = $this->cache->get($key);
    return $client->getResultCode() == \Memcached::RES_SUCCESS;
  }

  /**
   * Deletes all items in the pool
   *
   * @return bool
   */
  public function clear() {
    $this->cache->flush();
    return true;
  }

  /**
   * Removes the item from the pool.
   *
   * @param string $key
   * @return bool
   */
  public function deleteItem($key) {
    $this->cache->delete($key);
    return true;
  }

  /**
   * Removes multiple items from the pool.
   *
   * @param array $keys
   * @return bool
   */
  public function deleteItems($keys) {
    $this->cache->deleteMulti($keys);
    return true;
  }

  /**
   * Persists a cache item immediately.
   *
   * @param CacheItem $item
   * @return bool
   */
  public function save($item) {

    $this->cache->set(
      $item->getKey(),
      $item->get(),
      $item->getExpiresAt()
    );

    return true;
  }

  /**
   * Sets a cache item to be persisted later.
   *
   * @param CacheItem $item
   * @return bool
   */
  public function saveDeferred($item) {
    $this->deferred[$item->getKey()] = $item;
    return true;
  }

  /**
   * Persists any deferred cache items.
   *
   * @return bool
   */
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