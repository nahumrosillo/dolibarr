<?php

require_once DOL_DOCUMENT_ROOT . '/custom/syncwoocommerce/class/QueueInterface.php';

class CloudflareQueueAPI implements QueueInterface
{
	public $apiToken;

	public $accountId;
	public $queueId;

	public function __construct(array $config)
	{
		$this->apiToken = $config['apiToken'];
		$this->accountId = $config['accountId'];
		$this->queueId = $config['queueId'];
	}

	public function pushMessage(array $data): array
	{
		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => "https://api.cloudflare.com/client/v4/accounts/$this->accountId/queues/$this->queueId/messages",
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 0,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode($data),
			CURLOPT_HTTPHEADER => array(
				'Content-Type: application/json',
				'Accept: application/json',
				"Authorization: Bearer $this->apiToken",
			),
		));

		$response = curl_exec($curl);

		curl_close($curl);

		$data = json_decode($response, true);

		return $data;
	}


}
