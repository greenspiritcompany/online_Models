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
        order.id
        order.total AS total,
        order.created_at AS createdAt,
        order.updated_at AS updatedAt,
        order.transaction_type AS gateway,
        order.merchant_refid AS gatewayReferenceId,
        order.gateway_refid AS gatewayRequestId,
        order.transaction_id AS gatewayTransactionId
        order.gateway_reqtoken AS gatewayToken,
        order.status_authorization AS status,
        GROUP_CONCAT(product.name SEPARATOR "||") as products
      FROM ttgshop_orders AS order
      LEFT JOIN ttgshop_orders_products as op on op_order_fk = order.id
      LEFT JOIN ttgshop_products as product on product.id = op.product_fk
      WHERE order.customer_fk=:customerId AND order.legacy_id IS NULL
      GROUP BY order.id
      ORDER BY order.id DESC
      '
    );

    $statement->setFetchMode(PDO::FETCH_CLASS, LegacyShopOrderModel::class);
    $statement->execute();
    $orders = $statement->fetchAll();

    // Set cache length
    $exp = new \DateInterval('P1D');
    $cacheItem->expiresAfter($exp);

    // Cache the result
    $this->cacheUser($orders);

    return $orders;
  }

}