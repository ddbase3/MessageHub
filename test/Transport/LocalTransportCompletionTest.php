<?php declare(strict_types=1);

namespace MessageHub\Test\Transport;

use Base3\Logger\Api\ILogger;
use MessageHub\Transport\LogMessageTransport;
use MessageHub\Transport\NullMessageTransport;
use MessagingFoundation\Dto\Message;
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
