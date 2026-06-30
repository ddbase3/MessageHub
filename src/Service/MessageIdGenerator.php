<?php declare(strict_types=1);

namespace MessageHub\Service;

use MessagingFoundation\Api\IMessageIdGenerator;

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
