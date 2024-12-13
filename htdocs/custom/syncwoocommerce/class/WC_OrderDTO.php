<?php

require_once DOL_DOCUMENT_ROOT . '/custom/syncwoocommerce/class/WC_ShippingDTO.php';
require_once DOL_DOCUMENT_ROOT . '/custom/syncwoocommerce/class/WC_BillingDTO.php';
require_once DOL_DOCUMENT_ROOT . '/custom/syncwoocommerce/class/WC_OrderLineItemDTO.php';

class WC_OrderDTO
{
	private $order_id;
	private $status;
	private $total;
	private $currency;
	private $customer_id;
	private $email;

	/** @var WC_ShippingDTO|null */
	private ?WC_ShippingDTO $shipping;
	/** @var WC_BillingDTO|null */
	private ?WC_BillingDTO $billing;
	private $payment_method;
	private $payment_method_title;
	private $date_created;
	private $date_modified;

	/** @var WC_OrderLineItemDTO[] */
	private array $items = [];

	public function __construct($data)
	{
		$this->order_id = $data['order_id'];
		$this->status = $data['status'];
		$this->total = $data['total'];
		$this->currency = $data['currency'];
		$this->customer_id = $data['customer_id'];
		$this->email = $data['email'];
		$this->shipping = new WC_ShippingDTO($data['shipping']);
		$this->billing = new WC_BillingDTO($data['billing']);
		$this->payment_method = $data['payment_method'];
		$this->payment_method_title = $data['payment_method_title'];
		$this->date_created = $data['date_created'];
		$this->date_modified = $data['date_modified'];
		foreach ($data['items'] as $item) {
			$this->items[] = new WC_OrderLineItemDTO($item);
		}
	}

	public function getOrderId(): mixed
	{
		return $this->order_id;
	}

	public function getStatus(): mixed
	{
		return $this->status;
	}

	public function getTotal(): mixed
	{
		return $this->total;
	}

	public function getCurrency(): mixed
	{
		return $this->currency;
	}

	public function getCustomerId(): mixed
	{
		return $this->customer_id;
	}

	public function getEmail(): mixed
	{
		return $this->email;
	}

	public function getShipping(): WC_ShippingDTO
	{
		return $this->shipping;
	}

	public function getBilling(): WC_BillingDTO
	{
		return $this->billing;
	}

	public function getPaymentMethod(): mixed
	{
		return $this->payment_method;
	}

	public function getPaymentMethodTitle(): mixed
	{
		return $this->payment_method_title;
	}

	public function getDateCreated(): mixed
	{
		return $this->date_created;
	}

	public function getDateModified(): mixed
	{
		return $this->date_modified;
	}

	public function getItems(): array
	{
		return $this->items;
	}


}
