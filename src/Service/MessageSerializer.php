<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MessageHub for BASE3 Framework.
 *
 * MessageHub implements queue-based messaging for BASE3 applications.
 * It manages templates, retries, deliveries and transport discovery.
 * Transport-neutral contracts are defined by MessagingFoundation.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/MessageHub
 **********************************************************************/

namespace Base3\MessageHub\Service;

use Base3\MessagingFoundation\Dto\Message;
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
