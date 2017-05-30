<?php
namespace Telltale\DataMapper;

use Telltale\Model\LegacyShopOrder as LegacyShopOrderModel;
use PDO;
use PDOException;
use Psr\Cache\CacheItemPoolInterface;

class LegacyShop {

  private $pdo, $cache;

  public function __construct(PDO $pdo, CacheItemPoolInterface $cachePool) {
    $this->pdo = $pdo;
    $this->cache = $cachePool;
  }

  function customerOrders(int $customerId) {

    $cacheItem = $this->cache->getItem('legacyOrders|' . $customerId);

    // Return from cache if found
    $orders = $cacheItem->get();
    if ($orders) {
      return $orders;
    }

    $statement = $this->pdo->prepare(
      'SELECT
        o.id,
        o.total AS total,
        o.created_at AS createdAt,
        o.updated_at AS updatedAt,
        o.transaction_type AS gateway,
        o.merchant_refid AS gatewayReferenceId,
        o.gateway_reqid AS gatewayRequestId,
        o.transaction_id AS gatewayTransactionId,
        o.gateway_reqtoken AS gatewayToken,
        o.status_authorization AS gatewayStatus,
        GROUP_CONCAT(p.name SEPARATOR "||") as products
      FROM ttgshop_orders AS o
      LEFT JOIN ttgshop_orders_products AS op ON op.order_fk = o.id
      LEFT JOIN ttgshop_products AS p ON p.id = op.product_fk
      WHERE o.customer_fk=:customerId AND o.legacy_id IS NULL
      GROUP BY o.id
      ORDER BY o.id DESC;
    ');

    $statement->setFetchMode(PDO::FETCH_CLASS, LegacyShopOrderModel::class);
    $statement->execute([
      'customerId' => $customerId
    ]);
    $orders = $statement->fetchAll();

    // Set cache length
    $exp = new \DateInterval('P1D');
    $cacheItem->expiresAfter($exp);

    // Cache the result
    $this->cacheUser($orders);

    return $orders;
  }

}