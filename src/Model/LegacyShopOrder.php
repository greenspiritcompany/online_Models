<?php
namespace Telltale\Model;

class LegacyShopOrder implements \JsonSerializable {
  /**
   * The primary key for the order
   * @var int Order ID from ENV_store.ttgshop_orders table
   **/
  private $id;

  /**
   * Order total
   *
   * @var int 
   **/
  private $total;

  /** 
   * The datetime when this order record was created
   * 
   * @var datetime createdAt
   **/
  private $createdAt;

  /** 
   * The datetime when this order record was last updated
   * 
   * @var datetime updatedAt
   **/
  private $updatedAt;

  /**
   * Name of the payment gateway used to process the order
   * Can only be 'Cybersource' or 'Paypal'
   *
   * @var string gateway 
   **/
  private $gateway;

  /**
   * Gateway Reference ID
   *
   * Known as "merchantReferenceCode" for CyberSource and "CORRELATIONID" for Paypal
   * 
   * Found as merchant_refid in the table
   *
   * @var string gatewayReferenceId
   **/
  private $gatewayReferenceId;

  /**
   * Gateway Request ID
   *
   * ID used to talk to the gateway at time of order process
   * Known as "requestID" for CyberSource and "PAYERID" for Paypal
   * 
   * Found as gateway_refid in the table
   *
   * @var string gatewayRequestId 
   **/
  private $gatewayRequestId;

  /**
   * Gateway Transaction ID
   *
   * Not used for Cybersource known as "PAYMENTINFO_0_TRANSACTIONID" for Paypal
   *
   * Found as transaction_id in the table
   *
   * @var string gatewayTransactionId 
   **/
  private $gatewayTransactionId;

  /**
   * Gateway Token
   *
   * Known as "requestToken" for CyberSource and "TOKEN" for Paypal
   *
   * Found as gateway_reqtoken in the table
   *
   * @var string  
   **/
  private $gatewayToken;

  /**
   * Gateway Authorization Status
   * Can only be 'authorized' 'captured' or 'refunded'
   *
   * Found as status_authorization in the table
   * 
   * @var string status
   **/
  private $status;

  /**
   * Array of the product names contained in the order
   *
   * @var array 
   **/
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
        'type' => strtolower($this->gateway) == 'cybersource' ? 'Credit Card' : $this->gateway
      ],
      'status' => strtolower($this->status) == 'captured' ? 'Completed' : $this->status,
      'products' => $this->products,
    ];
  }
}