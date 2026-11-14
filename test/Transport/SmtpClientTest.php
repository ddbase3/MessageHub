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

namespace Base3\MessageHub\Test\Transport;

use Base3\MessageHub\Transport\Support\SmtpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SmtpClientTest extends TestCase {

	public function testMissingHostIsRejectedBeforeAnyNetworkConnection(): void {
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('SMTP host is not configured.');
		(new SmtpClient())->send(['from' => 'from@example.test', 'recipients' => ['to@example.test'], 'raw' => 'Body'], []);
	}
}
