<?php


require_once DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/custom/syncwoocommerce/class/AwsSQSAPI.php';
require_once DOL_DOCUMENT_ROOT . '/custom/syncwoocommerce/class/WC_OrderDTO.php';
require_once DOL_DOCUMENT_ROOT . '/custom/syncwoocommerce/class/OrderHelper.php';

class CommandSyncDataFromWooCommerce
{
	public function runSyncDataFromWooCommerceQueue(): void
	{
		global $db, $conf, $langs;

		$syncEnabled = dolibarr_get_const($db, 'SYNCWOOCOMMERCE_ENABLE_SYNC');

		if ($syncEnabled == 1) {

			$accessKey = dolibarr_get_const($db, 'SYNCWOOCOMMERCE_AWS_ACCESS_KEY');
			$secretKey = dolibarr_get_const($db, 'SYNCWOOCOMMERCE_AWS_SECRET_KEY');
			$region = dolibarr_get_const($db, 'SYNCWOOCOMMERCE_AWS_REGION');
			$queueUrl = dolibarr_get_const($db, 'SYNCWOOCOMMERCE_AWS_QUEUE_URL_FOR_SYNC_ORDERS');

			$api = new AwsSQSAPI([
				'accessKey' => $accessKey,
				'secretKey' => $secretKey,
				'region' => $region,
				'queueUrl' => $queueUrl
			]);

			$data = $api->receiveMessage();

			if (!empty($data)) {

				//$operation = $data['operation'];

				$user = new User($db);
				$user->fetch(5);

				$orderDTO = new WC_OrderDTO($data['data']);

				$isNewOrder = false;
				$order = OrderHelper::findWooCommerceOrder($db, $orderDTO->getOrderId());
				if (!$order) {
					$isNewOrder = true;
					$order = new Commande($db);
					$order->lines = [];
				}

				$order->fetch_thirdparty();
				$order->fetch_contact();


				$isNewSociete = false;
				$societe = OrderHelper::getSocieteFromEmail($db, $orderDTO->getEmail());
				if (!$societe) {
					$isNewSociete = true;
					$societe = new Societe($db);
				}

				$societe->setAsCustomer();
				$societe->setExtraField('woocommerce_client_id', $orderDTO->getCustomerId());
				$societe->name = $orderDTO->getBilling()->getFullName();
				$societe->address = $orderDTO->getBilling()->getAddress();

				$societe->country_id = OrderHelper::getCountryByCode($db, $orderDTO->getBilling()->getCountry());
				$societe->state_id = OrderHelper::getRegionByCode($db, $orderDTO->getBilling()->getState(), $orderDTO->getBilling()->getCountry());

				$societe->zip = $orderDTO->getBilling()->getPostcode();
				$societe->town = $orderDTO->getBilling()->getCity();
				$societe->email = $orderDTO->getEmail();
				$societe->phone_mobile = $orderDTO->getBilling()->getPhone();

				$societe->typent_id = 8; // Particular

				if ($isNewSociete) {
					$societe->create($user);
				} else {
					$societe->update(0, $user);
				}

				$isNewContact = false;
				$contact = OrderHelper::getContactFromEmail($db, $orderDTO->getEmail());
				if (!$contact) {
					$isNewContact = true;
					$contact = new Contact($db);
				}

				$contact->socid = $societe->id;
				$contact->lastname = $orderDTO->getBilling()->getFullName();
				$contact->firstname = "";
				$contact->email = $orderDTO->getEmail();
				$contact->phone_perso = $orderDTO->getShipping()->getPhone();
				$contact->address = $orderDTO->getShipping()->getAddress();
				$contact->zip = $orderDTO->getShipping()->getPostcode();
				$contact->town = $orderDTO->getShipping()->getCity();
				$contact->country_id = OrderHelper::getCountryByCode($db, $orderDTO->getShipping()->getCountry());
				$contact->state_id = OrderHelper::getRegionByCode($db, $orderDTO->getShipping()->getState(), $orderDTO->getShipping()->getCountry());

				if ($isNewContact) {
					$contact->create($user);
				} else {
					$contact->update($contact->id, $user);
				}


				$order->socid = $societe->id;
				$date = DateTime::createFromFormat('Y-m-d H:i:s', $orderDTO->getDateCreated());
				$order->date_commande = $date->getTimestamp();
				$order->set_date($user, $date->getTimestamp());

				$order->setExtraField('woocommerce_order_id', $orderDTO->getOrderId());
				$order->add_contact($contact->id, 'CUSTOMER');


				if ($isNewOrder) {
					$order->create($user);
				} else {
					$order->update($user);
				}

				$order->fetch($order->id);

				foreach ($orderDTO->getItems() as $item) {
					$percentDiscount = 0;
					$productId = 0;
					$product = new Product($db);
					$product->fetch(0, $item->getSku());
					if ($product->id != 0) {
						$productId = $product->id;
					}

					$order->addline($item->getProductName(), $product->price, $item->getQuantity(), $product->tva_tx, 0, 0, $productId, $percentDiscount, 0, 0, 'TTC', $product->price_ttc, '', '', 0, -1, 0, 0, null, $product->cost_price);
				}

				$order->update($user);


			}

		}

	}


}
