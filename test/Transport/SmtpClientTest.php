<?php declare(strict_types=1);

namespace MessageHub\Test\Transport;

use MessageHub\Transport\Support\SmtpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SmtpClientTest extends TestCase {

	public function testMissingHostIsRejectedBeforeAnyNetworkConnection(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('SMTP host is not configured.');
		(new SmtpClient())->send(['from' => 'from@example.test', 'recipients' => ['to@example.test'], 'raw' => 'Body'], []);
	}
}
