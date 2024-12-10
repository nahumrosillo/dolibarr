<?php
/* Copyright (C) 2024 Nahúm Rosillo
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modSyncWoocommerce_SyncWoocommerceTriggers.class.php
 * \ingroup syncwoocommerce
 * \brief   Example trigger.
 *
 * Put detailed description here.
 *
 * \remarks You can create other triggers by copying this one.
 * - File name should be either:
 *      - interface_99_modSyncWoocommerce_MyTrigger.class.php
 *      - interface_99_all_MyTrigger.class.php
 * - The file must stay in core/triggers
 * - The class name must be InterfaceMytrigger
 */

require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once DOL_DOCUMENT_ROOT . '/custom/syncwoocommerce/class/CloudflareQueueAPI.php';
require_once DOL_DOCUMENT_ROOT . '/custom/syncwoocommerce/class/AwsSQSAPI.php';
require_once DOL_DOCUMENT_ROOT . '/custom/syncwoocommerce/class/CategorieHelper.php';
require_once DOL_DOCUMENT_ROOT . '/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/variants/class/ProductCombination.class.php';
require_once DOL_DOCUMENT_ROOT . '/variants/class/ProductAttribute.class.php';
require_once DOL_DOCUMENT_ROOT . '/variants/class/ProductAttributeValue.class.php';
require_once DOL_DOCUMENT_ROOT . '/variants/class/ProductCombination2ValuePair.class.php';


/**
 *  Class of triggers for SyncWoocommerce module
 */
class InterfaceSyncWoocommerceTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = "demo";
		$this->description = "SyncWoocommerce triggers.";
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'syncwoocommerce@syncwoocommerce';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 * All functions "runTrigger" are triggered if file
	 * is inside directory core/triggers
	 *
	 * @param string $action Event action code
	 * @param CommonObject $object Object
	 * @param User $user Object user
	 * @param Translate $langs Object langs
	 * @param Conf $conf Object conf
	 * @return int                    Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('syncwoocommerce')) {
			return 0; // If module is not enabled, we do nothing
		}

		// Put here code you want to execute when a Dolibarr business events occurs.
		// Data and type of action are stored into $object and $action

		// You can isolate code for each action in a separate method: this method should be named like the trigger in camelCase.
		// For example : COMPANY_CREATE => public function companyCreate($action, $object, User $user, Translate $langs, Conf $conf)
		$methodName = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($action)))));
		$callback = array($this, $methodName);
		if (is_callable($callback)) {
			dol_syslog(
				"Trigger '" . $this->name . "' for action '$action' launched by " . __FILE__ . ". id=" . $object->id
			);

			return call_user_func($callback, $action, $object, $user, $langs, $conf);
		}

		// Or you can execute some code here
		switch ($action) {
			// Users
			//case 'USER_CREATE':
			//case 'USER_MODIFY':
			//case 'USER_NEW_PASSWORD':
			//case 'USER_ENABLEDISABLE':
			//case 'USER_DELETE':

			// Actions
			//case 'ACTION_MODIFY':
			//case 'ACTION_CREATE':
			//case 'ACTION_DELETE':

			// Groups
			//case 'USERGROUP_CREATE':
			//case 'USERGROUP_MODIFY':
			//case 'USERGROUP_DELETE':

			// Companies
			//case 'COMPANY_CREATE':
			//case 'COMPANY_MODIFY':
			//case 'COMPANY_DELETE':

			// Contacts
			//case 'CONTACT_CREATE':
			//case 'CONTACT_MODIFY':
			//case 'CONTACT_DELETE':
			//case 'CONTACT_ENABLEDISABLE':

			// Products
			case 'PRODUCT_CREATE':
			case 'PRODUCT_MODIFY':
			case 'PRODUCT_DELETE':


				/** @var Product $object */

				$syncEnabled = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_ENABLE_SYNC');

				if ($syncEnabled == "1") {
					$warehouseId = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_WAREHOUSE_ID');

					$object->load_stock();

					$stockForWeb = $object->stock_warehouse[$warehouseId]->real ?? 0;

					$categories = [];
					$category = new Categorie($this->db);

					$categoryList = $category->containing($object->id, 'product');
					foreach ($categoryList as $cat) {
						$categories[] = [
							'id' => $cat->id,
							'label' => $cat->label,
							'description' => $cat->description,
							'position' => $cat->position,
							'fk_parent' => $cat->fk_parent
						];
					}

					$attributes = [];
					$parentDTO = null;
					if ($object->isVariant()) {

						$pc = new ProductCombination($this->db);
						$isOK = $pc->fetchByFkProductChild($object->id);

						if ($isOK > 0) {
							$attributes = $this->getAttributesFromVariant($this->db, $object->id);

							$parent = new Product($this->db);
							$parent->fetch($pc->fk_product_parent);
							$parentDTO = $parent->ref;
						}


					}

					if ($object->hasVariants()) {
						$attributes = $this->getAllAttributesFromParent($this->db, $object->id);
					}

					$files = self::getOrderedProductAttachments($this->db, $object->ref);

					$attachments = [];
					foreach ($files as $file) {

						$relativeName = $file['name'];
						$ext = strtolower(substr($relativeName, strrpos($relativeName, '.') + 1));

						$imagesMimeTypeAllowed = [
							'jpg',
							'jpeg',
							'png',
							'gif',
							'bmp',
							'tiff',
							'webp'
						];

						if (!in_array($ext, $imagesMimeTypeAllowed)) {
							continue;
						}

						$publicUrl = dol_buildpath("/custom/syncwoocommerce/syncwoocommerce_download_file.php?file=" . '/produit/' . dol_sanitizeFileName($object->ref) . "/" . $file['relativename'], 2);

						$attachments[] = [
							'public_url' => $publicUrl
						];
					}

					if (!empty($categories)) {
						$json = [
							"operation" => $action,
							"data" => [
								'product' => [
									'id' => $object->id,
									'has_variant' => $object->hasVariants() > 0,
									'is_variant' => $object->isVariant(),
									'parent' => $parentDTO,
									'label' => $object->label,
									'description' => $object->description,
									'reference' => $object->ref,
									'status' => $object->status,
									'price' => $object->price,
									'price_ttc' => $object->price_ttc,
									'price_min' => $object->price_min,
									'price_min_ttc' => $object->price_min_ttc,
									'tax_rate' => $object->tva_tx,
									'real_stock' => $stockForWeb,
									'theoretical_stock' => $stockForWeb,
									'barcode' => $object->barcode,
									'categories' => $categories,
									'attachments' => $attachments,
									'attributes' => $attributes
								]
							]
						];

						$accessKey = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_ACCESS_KEY');
						$secretKey = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_SECRET_KEY');
						$region = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_REGION');
						$queueUrl = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_QUEUE_URL');

						$api = new AwsSQSAPI([
							'accessKey' => $accessKey,
							'secretKey' => $secretKey,
							'region' => $region,
							'queueUrl' => $queueUrl
						]);

						/*
							$apiToken = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_CLOUDFLARE_API_TOKEN');
							$accountId = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_CLOUDFLARE_ACCOUNT_ID');
							$queueId = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_CLOUDFLARE_QUEUE_ID');

							$api = new CloudflareQueueAPI([
								'apiToken' => $apiToken,
								'accountId' => $accountId,
								'queueId' => $queueId
							]);*/

						$result = $api->pushMessage($json);


					}


				}

				break;

			//case 'PRODUCT_PRICE_MODIFY':
			//	break;
			//case 'PRODUCT_SET_MULTILANGS':
			//case 'PRODUCT_DEL_MULTILANGS':

			//Stock movement
			case
			'STOCK_MOVEMENT':
				/** @var MouvementStock $object */
				$warehouseId = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_WAREHOUSE_ID');
				$syncEnabled = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_ENABLE_SYNC');

				if ($syncEnabled == "1" && $warehouseId == $object->warehouse_id) {

					$productId = $object->product_id;
					$qty = $object->qty; // It can be negative
					$price = $object->price; // 9.91736

					$product = new Product($this->db);
					$product->fetch($productId);
					$product->load_stock();

					$stock = $product->stock_warehouse[$warehouseId]->real ?? 0;

					$categories = [];
					$category = new Categorie($this->db);

					$categoryList = $category->containing($object->id, 'product');
					foreach ($categoryList as $cat) {
						$categories[] = [
							'id' => $cat->id,
							'label' => $cat->label,
							'description' => $cat->description,
							'position' => $cat->position,
							'fk_parent' => $cat->fk_parent
						];
					}

					if (!empty($categories)) {
						$json = [
							"operation" => $action,
							"data" => [
								"product" => [
									'id' => $productId,
									'reference' => $product->ref,
									'status' => $product->status,
									'price' => $product->price,
									'price_ttc' => $product->price_ttc,
									'price_min' => $product->price_min,
									'price_min_ttc' => $product->price_min_ttc,
									'tax_rate' => $product->tva_tx,
									'real_stock' => $stock,
									'theoretical_stock' => $product->stock_theorique,
									'barcode' => $product->barcode,
									'categories' => $categories
								],
								'warehouse' => [
									'id' => $warehouseId
								],
								'quantity' => $qty,
								'price' => $price
							]
						];

						$accessKey = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_ACCESS_KEY');
						$secretKey = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_SECRET_KEY');
						$region = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_REGION');
						$queueUrl = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_QUEUE_URL');

						$api = new AwsSQSAPI([
							'accessKey' => $accessKey,
							'secretKey' => $secretKey,
							'region' => $region,
							'queueUrl' => $queueUrl
						]);


						/*
						$apiToken = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_CLOUDFLARE_API_TOKEN');
						$accountId = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_CLOUDFLARE_ACCOUNT_ID');
						$queueId = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_CLOUDFLARE_QUEUE_ID');

						$api = new CloudflareQueueAPI([
							'apiToken' => $apiToken,
							'accountId' => $accountId,
							'queueId' => $queueId
						]);*/
						$result = $api->pushMessage($json);
					}
				}

				//dol_syslog("STOCK_MOVEMENT Trigger '" . $this->name . "' for action '" . $action . "' launched by " . __FILE__ . ". id=" . $object->id);
				break;

			//MYECMDIR
			//case 'MYECMDIR_CREATE':
			//case 'MYECMDIR_MODIFY':
			//case 'MYECMDIR_DELETE':

			// Sales orders
			//case 'ORDER_CREATE':
			//case 'ORDER_MODIFY':
			//case 'ORDER_VALIDATE':
			//case 'ORDER_DELETE':
			//case 'ORDER_CANCEL':
			//case 'ORDER_SENTBYMAIL':
			//case 'ORDER_CLASSIFY_BILLED':		// TODO Replace it with ORDER_BILLED
			//case 'ORDER_CLASSIFY_UNBILLED':	// TODO Replace it with ORDER_UNBILLED
			//case 'ORDER_SETDRAFT':
			//case 'LINEORDER_INSERT':
			//case 'LINEORDER_UPDATE':
			//case 'LINEORDER_DELETE':

			// Supplier orders
			//case 'ORDER_SUPPLIER_CREATE':
			//case 'ORDER_SUPPLIER_MODIFY':
			//case 'ORDER_SUPPLIER_VALIDATE':
			//case 'ORDER_SUPPLIER_DELETE':
			//case 'ORDER_SUPPLIER_APPROVE':
			//case 'ORDER_SUPPLIER_CLASSIFY_BILLED':		// TODO Replace with ORDER_SUPPLIER_BILLED
			//case 'ORDER_SUPPLIER_CLASSIFY_UNBILLED':		// TODO Replace with ORDER_SUPPLIER_UNBILLED
			//case 'ORDER_SUPPLIER_REFUSE':
			//case 'ORDER_SUPPLIER_CANCEL':
			//case 'ORDER_SUPPLIER_SENTBYMAIL':
			//case 'ORDER_SUPPLIER_RECEIVE':
			//case 'LINEORDER_SUPPLIER_DISPATCH':
			//case 'LINEORDER_SUPPLIER_CREATE':
			//case 'LINEORDER_SUPPLIER_UPDATE':
			//case 'LINEORDER_SUPPLIER_DELETE':

			// Proposals
			//case 'PROPAL_CREATE':
			//case 'PROPAL_MODIFY':
			//case 'PROPAL_VALIDATE':
			//case 'PROPAL_SENTBYMAIL':
			//case 'PROPAL_CLASSIFY_BILLED':		// TODO Replace it with PROPAL_BILLED
			//case 'PROPAL_CLASSIFY_UNBILLED':		// TODO Replace it with PROPAL_UNBILLED
			//case 'PROPAL_CLOSE_SIGNED':
			//case 'PROPAL_CLOSE_REFUSED':
			//case 'PROPAL_DELETE':
			//case 'LINEPROPAL_INSERT':
			//case 'LINEPROPAL_UPDATE':
			//case 'LINEPROPAL_DELETE':

			// SupplierProposal
			//case 'SUPPLIER_PROPOSAL_CREATE':
			//case 'SUPPLIER_PROPOSAL_MODIFY':
			//case 'SUPPLIER_PROPOSAL_VALIDATE':
			//case 'SUPPLIER_PROPOSAL_SENTBYMAIL':
			//case 'SUPPLIER_PROPOSAL_CLOSE_SIGNED':
			//case 'SUPPLIER_PROPOSAL_CLOSE_REFUSED':
			//case 'SUPPLIER_PROPOSAL_DELETE':
			//case 'LINESUPPLIER_PROPOSAL_INSERT':
			//case 'LINESUPPLIER_PROPOSAL_UPDATE':
			//case 'LINESUPPLIER_PROPOSAL_DELETE':

			// Contracts
			//case 'CONTRACT_CREATE':
			//case 'CONTRACT_MODIFY':
			//case 'CONTRACT_ACTIVATE':
			//case 'CONTRACT_CANCEL':
			//case 'CONTRACT_CLOSE':
			//case 'CONTRACT_DELETE':
			//case 'LINECONTRACT_INSERT':
			//case 'LINECONTRACT_UPDATE':
			//case 'LINECONTRACT_DELETE':

			// Bills
			//case 'BILL_CREATE':
			//case 'BILL_MODIFY':
			//case 'BILL_VALIDATE':
			//case 'BILL_UNVALIDATE':
			//case 'BILL_SENTBYMAIL':
			//case 'BILL_CANCEL':
			//case 'BILL_DELETE':
			//case 'BILL_PAYED':
			//case 'LINEBILL_INSERT':
			//case 'LINEBILL_UPDATE':
			//case 'LINEBILL_DELETE':

			// Recurring Bills
			//case 'BILLREC_MODIFY':
			//case 'BILLREC_DELETE':
			//case 'BILLREC_AUTOCREATEBILL':
			//case 'LINEBILLREC_MODIFY':
			//case 'LINEBILLREC_DELETE':

			//Supplier Bill
			//case 'BILL_SUPPLIER_CREATE':
			//case 'BILL_SUPPLIER_UPDATE':
			//case 'BILL_SUPPLIER_DELETE':
			//case 'BILL_SUPPLIER_PAYED':
			//case 'BILL_SUPPLIER_UNPAYED':
			//case 'BILL_SUPPLIER_VALIDATE':
			//case 'BILL_SUPPLIER_UNVALIDATE':
			//case 'LINEBILL_SUPPLIER_CREATE':
			//case 'LINEBILL_SUPPLIER_UPDATE':
			//case 'LINEBILL_SUPPLIER_DELETE':

			// Payments
			//case 'PAYMENT_CUSTOMER_CREATE':
			//case 'PAYMENT_SUPPLIER_CREATE':
			//case 'PAYMENT_ADD_TO_BANK':
			//case 'PAYMENT_DELETE':

			// Online
			//case 'PAYMENT_PAYBOX_OK':
			//case 'PAYMENT_PAYPAL_OK':
			//case 'PAYMENT_STRIPE_OK':

			// Donation
			//case 'DON_CREATE':
			//case 'DON_UPDATE':
			//case 'DON_DELETE':

			// Interventions
			//case 'FICHINTER_CREATE':
			//case 'FICHINTER_MODIFY':
			//case 'FICHINTER_VALIDATE':
			//case 'FICHINTER_CLASSIFY_BILLED':			// TODO Replace it with FICHINTER_BILLED
			//case 'FICHINTER_CLASSIFY_UNBILLED':		// TODO Replace it with FICHINTER_UNBILLED
			//case 'FICHINTER_DELETE':
			//case 'LINEFICHINTER_CREATE':
			//case 'LINEFICHINTER_UPDATE':
			//case 'LINEFICHINTER_DELETE':

			// Members
			//case 'MEMBER_CREATE':
			//case 'MEMBER_VALIDATE':
			//case 'MEMBER_SUBSCRIPTION':
			//case 'MEMBER_MODIFY':
			//case 'MEMBER_NEW_PASSWORD':
			//case 'MEMBER_RESILIATE':
			//case 'MEMBER_DELETE':

			// Categories
			case 'CATEGORY_CREATE':
			case 'CATEGORY_MODIFY':
			case 'CATEGORY_DELETE':
				/** @var Categorie $object */

				$syncEnabled = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_ENABLE_SYNC');

				if ($syncEnabled == "1") {

					$rootCategory = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_ROOT_CATEGORY_ID');

					if ($rootCategory == $object->id) {
						break;
					}

					$categoryHelper = new CategorieHelper($this->db);
					$tree = $categoryHelper->getChildrenTree($rootCategory);

					$json = [
						"operation" => $action,
						"data" => [
							//'tree' => $tree,
							'category' => [
								'id' => $object->id,
								'label' => $object->label,
								'description' => $object->description,
								'position' => $object->position,
								'fk_parent' => $object->fk_parent,
								'children' => $categoryHelper->getChildrenData($object->id)
							]
						]
					];

					$accessKey = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_ACCESS_KEY');
					$secretKey = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_SECRET_KEY');
					$region = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_REGION');
					$queueUrl = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_AWS_QUEUE_URL');

					$api = new AwsSQSAPI([
						'accessKey' => $accessKey,
						'secretKey' => $secretKey,
						'region' => $region,
						'queueUrl' => $queueUrl
					]);

					/*
					$apiToken = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_CLOUDFLARE_API_TOKEN');
					$accountId = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_CLOUDFLARE_ACCOUNT_ID');
					$queueId = dolibarr_get_const($this->db, 'SYNCWOOCOMMERCE_CLOUDFLARE_QUEUE_ID');

					$api = new CloudflareQueueAPI([
						'apiToken' => $apiToken,
						'accountId' => $accountId,
						'queueId' => $queueId
					]);
					*/
					$result = $api->pushMessage($json);
				}

				break;

			//case 'CATEGORY_SET_MULTILANGS':

			// Projects
			//case 'PROJECT_CREATE':
			//case 'PROJECT_MODIFY':
			//case 'PROJECT_DELETE':

			// Project tasks
			//case 'TASK_CREATE':
			//case 'TASK_MODIFY':
			//case 'TASK_DELETE':

			// Task time spent
			//case 'TASK_TIMESPENT_CREATE':
			//case 'TASK_TIMESPENT_MODIFY':
			//case 'TASK_TIMESPENT_DELETE':
			//case 'PROJECT_ADD_CONTACT':
			//case 'PROJECT_DELETE_CONTACT':
			//case 'PROJECT_DELETE_RESOURCE':

			// Shipping
			//case 'SHIPPING_CREATE':
			//case 'SHIPPING_MODIFY':
			//case 'SHIPPING_VALIDATE':
			//case 'SHIPPING_SENTBYMAIL':
			//case 'SHIPPING_BILLED':
			//case 'SHIPPING_CLOSED':
			//case 'SHIPPING_REOPEN':
			//case 'SHIPPING_DELETE':

			// and more...

			default:
				dol_syslog("Trigger '" . $this->name . "' for action '" . $action . "' launched by " . __FILE__ . ". id=" . $object->id);
				break;
		}

		return 0;
	}


	/**
	 * Get ordered attached files of a product
	 *
	 * @param DoliDB $db Database handler
	 * @param string $productRef Reference of the product
	 * @return array List of ordered attached files
	 */
	function getOrderedProductAttachments($db, $productRef)
	{
		$upload_dir = DOL_DATA_ROOT . "/produit/" . dol_sanitizeFileName($productRef);
		$files = dol_dir_list($upload_dir, "files", 0, '', '', 'date', SORT_ASC, 0);

		// Get the order from the database
		$sql = "SELECT rowid, filename, position FROM " . MAIN_DB_PREFIX . "ecm_files WHERE filepath = 'produit/" . $db->escape($productRef) . "' ORDER BY position ASC";
		$resql = $db->query($sql);

		if ($resql) {
			$orderedFiles = [];
			while ($obj = $db->fetch_object($resql)) {
				foreach ($files as $file) {
					if ($file['name'] == $obj->filename) {
						$file['position'] = $obj->position;
						$orderedFiles[] = $file;
						break;
					}
				}
			}
			return $orderedFiles;
		} else {
			return $files;
		}
	}

	/**
	 * @param $db
	 * @param Product $product
	 * @return array<Product>
	 */
	public
	function getVariants($db, Product $product): array
	{
		$variants = [];

		if ($product->id && $product->hasVariants()) {
			// Obtener variantes
			$sql = "SELECT rowid, fk_product_child as child FROM " . MAIN_DB_PREFIX . "product_attribute_combination WHERE fk_product_parent = " . $product->id;
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {

					$childId = $obj->child;
					$child = new Product($db);
					$child->fetch($childId);
					$variants[] = $child;
				}
			}
		}

		return $variants;
	}

	/**
	 * @param $db
	 * @param Product $product
	 * @return Product|null
	 */
	public
	function getParent($db, Product $product): ?Product
	{
		if ($product->id && $product->isVariant()) {
			// Obtener variantes
			$sql = "SELECT fk_product_parent as parent FROM " . MAIN_DB_PREFIX . "product_attribute_combination WHERE fk_product_child = " . $product->id . " LIMIT 1";
			$resql = $db->query($sql);
			if ($resql) {
				$obj = $db->fetch_object($resql);
				$parentId = $obj->parent;

				$parent = new Product($db);
				$parent->fetch($parentId);

				return $parent;

			}
		}

		return null;
	}

	/**
	 * @param DoliDB $db
	 * @param int $parentId
	 * @return array
	 */
	public function getAllAttributesFromParent(DoliDB $db, int $parentId): array
	{
		$attributes = [];
		$pc = new ProductCombination($db);
		$attributesTMP = $pc->getUniqueAttributesAndValuesByFkProductParent($parentId);
		foreach ($attributesTMP as $attribute) {

			/** @var ProductAttributeValue[] $values */
			$values = $attribute->values;

			$productAttributeValues = [];
			foreach ($values as $value) {
				$productAttributeValues[] = [
					'id' => $value->id,
					'ref' => $value->ref,
					'value' => $value->value,
				];

			}

			$attributes[] = [
				'id' => $attribute->id,
				'ref' => $attribute->ref,
				'label' => $attribute->label,
				'values' => $productAttributeValues
			];

		}
		return $attributes;
	}

	/**
	 * @param DoliDB $db
	 * @param int $variantId
	 * @return array
	 */
	public function getAttributesFromVariant(DoliDB $db, int $variantId): array
	{
		$attributes = [];

		$productCombination = new ProductCombination($db);
		$isOK = $productCombination->fetchByFkProductChild($variantId);

		if ($isOK > 0) {
			$pc2 = new ProductCombination2ValuePair($db);
			$combinations = $pc2->fetchByFkCombination($productCombination->id);

			/** @var ProductCombination2ValuePair $combination */
			foreach ($combinations as $combination) {
				$attributeId = $combination->fk_prod_attr;
				$attributeValue = $combination->fk_prod_attr_val;

				$pa = new ProductAttribute($db);
				$pa->fetch($attributeId);

				$pav = new ProductAttributeValue($db);
				$pav->fetch($attributeValue);

				$attributes[] = [
					'id' => $pa->id,
					'ref' => $pa->ref,
					'label' => $pa->label,
					'value' => $pav->value
				];
			}

			return $attributes;


		}

		return [];


	}
}
