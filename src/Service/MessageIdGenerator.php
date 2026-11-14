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

use Base3\MessagingFoundation\Api\IMessageIdGenerator;

final class MessageIdGenerator implements IMessageIdGenerator {

	public function createId(string $prefix): string {
		$prefix = strtolower(trim($prefix));
		$prefix = preg_replace('/[^a-z0-9_]+/', '', $prefix) ?: 'id';

		try {
			$random = bin2hex(random_bytes(10));
		} catch(\Throwable $exception) {
			$random = str_replace('.', '', uniqid('', true));
		}

		return $prefix . '_' . date('YmdHis') . '_' . $random;
	}
}
