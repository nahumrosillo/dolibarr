<?php

interface QueueInterface
{

	public function __construct(array $config);

	public function pushMessage(array $data): array;

}
