<?php

class WC_OrderLineItemDTO
{
	private $product_id;
	private $product_name;
	private $sku;
	private $quantity;
	private $subtotal;

	public function __construct($data)
	{
		$this->product_id = $data['product_id'];
		$this->product_name = $data['product_name'];
		$this->sku = $data['sku'];
		$this->quantity = $data['quantity'];
		$this->subtotal = $data['subtotal'];
	}

	public function getProductId(): mixed
	{
		return $this->product_id;
	}

	public function getProductName(): mixed
	{
		return $this->product_name;
	}

	public function getSku(): mixed
	{
		return $this->sku;
	}

	public function getQuantity(): mixed
	{
		return $this->quantity;
	}

	public function getSubtotal(): mixed
	{
		return $this->subtotal;
	}


}
