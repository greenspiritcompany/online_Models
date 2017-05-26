<?php
namespace Telltale\Model;

class LegacyShopOrder implements \JsonSerializable {
  private $id;
  private $total;
  private $createdAt;
  private $updatedAt;
  private $gateway;
  private $gatewayReferenceId;
  private $gatewayRequestId;
  private $gatewayTransactionId;
  private $gatewayToken;
  private $status;
  private $products;

  public function __construct() {
    if (!is_array($this->products)) {
      $this->products = isset($this->products) ? explode('||', $this->products) : [];
    }
  }

  public function __get($prop) {
    if (property_exists($this, $prop)) {
      return $this->$prop;
    }
  }

  /**
   * Return user friendly order response in the same format as the legacy api
   *
   */ 
  public function jsonSerialize() {
    return [
      'order_id' => $this->id,
      'created_at' => $this->createdAt,
      'updated_at' => $this->updatedAt,
      'payment' => [
        'total' => $this->total,
        'type' => strtolower($this->type) == 'cybersource' ? 'Credit Card' : $this->type
      ],
      'status' => strtolower($this->status) == 'captured' ? 'Completed' : $this->status,
      'products' => $this->products,
    ];
  }
}
