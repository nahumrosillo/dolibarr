<?php

class OrderHelper
{

	public static function findWooCommerceOrder(DoliDB $db, string $wcOrderId): ?Commande
	{
		$order = new Commande($db);
		$sql = "SELECT fk_object as ORDER_ID FROM " . MAIN_DB_PREFIX . "commande_extrafields WHERE woocommerce_order_id = '" . $db->escape($wcOrderId) . "'";
		$resql = $db->query($sql);

		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$order->fetch($obj->ORDER_ID);
				return $order;
			}
		} else {
			dol_print_error($db);
		}

		return null;
	}

	public static function getSocieteFromEmail(DoliDB $db, string $email): ?Societe
	{
		$customer = new Societe($db);
		$sql = "SELECT rowid as CONTACT_ID FROM " . MAIN_DB_PREFIX . "societe WHERE email = '" . $db->escape($email) . "'";
		$resql = $db->query($sql);

		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$customer->fetch($obj->CONTACT_ID);
				return $customer;
			} else {
				return null;
			}
		} else {
			dol_print_error($db);
		}

		return null;
	}

	public static function getContactFromEmail(DoliDB $db, string $email): ?Contact
	{
		$contact = new Contact($db);
		$sql = "SELECT rowid as CONTACT_ID FROM " . MAIN_DB_PREFIX . "socpeople WHERE email = '" . $db->escape($email) . "'";
		$resql = $db->query($sql);

		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$contact->fetch($obj->CONTACT_ID);
				return $contact;
			}
		} else {
			dol_print_error($db);
		}
		return null;
	}

	public static function getCountryByCode(DoliDB $db, string $countryCode): ?int
	{
		$sql = "SELECT rowid as id FROM " . MAIN_DB_PREFIX . "c_country WHERE code = '" . $db->escape($countryCode) . "'";
		$resql = $db->query($sql);

		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				return $obj->id;
			}
		} else {
			dol_print_error($db);
		}

		return null;
	}

	public static function getRegionByCode(DoliDB $db, string $regionCode, string $countryCode): ?int
	{

		$sql = "SELECT CD.rowid as id FROM " . MAIN_DB_PREFIX . "c_departements CD INNER JOIN " . MAIN_DB_PREFIX . "c_regions LCR ON CD.fk_region = LCR.code_region INNER JOIN " . MAIN_DB_PREFIX . "c_country LLC ON LCR.fk_pays = LLC.rowid WHERE LCR.code_region = 401 AND CD.code_departement = '" . $db->escape($regionCode) . "' AND LLC.code = '" . $db->escape($countryCode) . "'";

		$resql = $db->query($sql);

		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				return $obj->id;
			} else {
				return null;
			}
		} else {
			dol_print_error($db);
		}

		return null;
	}
}
