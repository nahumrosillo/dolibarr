<?php

use Aws\Exception\AwsException;
use Aws\Sqs\SqsClient;

require_once __DIR__ . '/../vendor/autoload.php';


require_once DOL_DOCUMENT_ROOT . '/custom/syncwoocommerce/class/QueueInterface.php';

class AwsSQSAPI implements QueueInterface
{
	public string $accessKey;
	public string $secretKey;
	public string $region;
	public string $queueUrl;

	public function __construct(array $config)
	{
		$this->accessKey = $config['accessKey'];
		$this->secretKey = $config['secretKey'];
		$this->region = $config['region'];
		$this->queueUrl = $config['queueUrl'];
	}

	public function pushMessage(array $data): array
	{
		try {
			$sqs = new SqsClient([
				'region' => $this->region,
				'credentials' => [
					'key' => $this->accessKey,
					'secret' => $this->secretKey,
				]
			]);

			$result = $sqs->sendMessage([
				'QueueUrl' => $this->queueUrl,
				"MessageGroupId" => "messageGroup1",
				'MessageBody' => json_encode($data),
				'MessageDeduplicationId' => sha1(rand()),
			]);

			return $result->toArray();
		} catch (AwsException $e) {
			// output error message if fails
			error_log($e->getMessage());
		}

		return [];
	}

	public function receiveMessage(): array
	{
		try {
			$sqs = new SqsClient([
				'region' => $this->region,
				'credentials' => [
					'key' => $this->accessKey,
					'secret' => $this->secretKey,
				]
			]);

			$result = $sqs->receiveMessage([
				'QueueUrl' => $this->queueUrl,
				'MaxNumberOfMessages' => 1,
				'VisibilityTimeout' => 60,
				'WaitTimeSeconds' => 0,
			]);

			if (!empty($result->get('Messages'))) {

				$message = $result->get('Messages')[0];
				$data = json_decode($message['Body'], true);

				$result = $sqs->deleteMessage([
					'QueueUrl' => $this->queueUrl, // REQUIRED
					'ReceiptHandle' => $result->get('Messages')[0]['ReceiptHandle'] // REQUIRED
				]);

			} else {
				echo "No messages in queue. \n";
			}


			return $result->toArray();
		} catch (AwsException $e) {
			// output error message if fails
			error_log($e->getMessage());
		}

		return [];
	}
}
