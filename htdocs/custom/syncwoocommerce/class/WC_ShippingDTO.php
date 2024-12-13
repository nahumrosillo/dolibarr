<?php

class WC_ShippingDTO
{
	private $full_name;
	private $address;
	private $city;
	private $state;
	private $postcode;
	private $country;
	private $phone;

	public function __construct($data)
	{
		$this->full_name = $data['full_name'];
		$this->address = $data['address'];
		$this->city = $data['city'];
		$this->state = $data['state'];
		$this->postcode = $data['postcode'];
		$this->country = $data['country'];
		$this->phone = $data['phone'];
	}

	public function getFullName(): mixed
	{
		return $this->full_name;
	}

	public function getAddress(): mixed
	{
		return $this->address;
	}

	public function getCity(): mixed
	{
		return $this->city;
	}

	public function getState(): mixed
	{
		return $this->state;
	}

	public function getPostcode(): mixed
	{
		return $this->postcode;
	}

	public function getCountry(): mixed
	{
		return $this->country;
	}

	public function getPhone(): mixed
	{
		return $this->phone;
	}


}
