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

use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MessageHub\Transport\LogMessageTransport;
use Base3\MessageHub\Transport\NullMessageTransport;
use Base3\MessagingFoundation\Dto\Message;
use PHPUnit\Framework\TestCase;

final class LocalTransportCompletionTest extends TestCase {

	public function testNullTransportAcceptsExplicitReasonAndTreatsFalseEnabledValuesAsDisabled(): void {
		$transport = new NullMessageTransport();
		$message = new Message('notice', 'Subject', 'Body');
		self::assertSame('null', $transport::getName());
		self::assertSame('Null (discard)', $transport->getLabel());
		self::assertTrue($transport->supports($message));
		self::assertSame('Messages are discarded successfully.', $transport->getSettingsSummary());
		self::assertSame('Messages are discarded successfully | Reason: auditing',
			$transport->getSettingsSummary(['reason' => '  auditing  ']));
		self::assertSame('Messages are discarded successfully.', $transport->getSettingsSummary(['reason' => ['invalid']]));
		foreach(['false', 'off', 0, false, 'none'] as $disabled) {
			self::assertFalse($transport->send($message, ['enabled' => $disabled])->isSuccess());
		}
		foreach(['YES', ' enabled ', 'on', true, 1] as $enabled) {
			$result = $transport->send($message, ['enabled' => $enabled, 'reason' => '  intentionally discarded  ']);
			self::assertTrue($result->isSuccess());
			self::assertSame('intentionally discarded', $result->getDetails()['reason']);
			self::assertTrue($result->getDetails()['discarded']);
		}
		self::assertSame('', $transport->send($message, ['reason' => ['invalid']])->getDetails()['reason']);
	}

	public function testLogTransportEnforcesExplicitDisableAndLogsOnlyWhenEnabled(): void {
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::exactly(3))->method('info');
		$transport = new LogMessageTransport($logger);
		$message = new Message('notice', 'Subject', 'Body');
		self::assertSame('Log only', $transport->getLabel());
		self::assertTrue($transport->supports($message));
		foreach(['NO', false, '0', 'disabled', 0] as $disabled) {
			self::assertFalse($transport->send($message, ['enabled' => $disabled])->isSuccess());
		}
		foreach([[], ['enabled' => 'true'], ['enabled' => true]] as $enabled) {
			$result = $transport->send($message, $enabled);
			self::assertTrue($result->isSuccess());
			self::assertSame('log', $result->getDetails()['transport']);
		}
	}
}
