<?php declare(strict_types=1);

namespace MessageHub\Service;

use MessagingFoundation\Dto\Message;
use RuntimeException;

final class MessageSerializer {

	public function encode(Message $message): string {
		$json = json_encode($message->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if(!is_string($json)) {
			throw new RuntimeException('Message could not be encoded.');
		}

		return $json;
	}

	public function decode(string $json): Message {
		$data = json_decode($json, true);

		if(json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
			throw new RuntimeException('Message JSON could not be decoded: ' . json_last_error_msg());
		}

		return Message::fromArray($data);
	}
}
