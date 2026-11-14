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

use Base3\CoreFoundation\ConfigValue\Api\IConfigValueResolver;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MessageHub\Transport\SendmailMessageTransport;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\MessageAddress;
use PHPUnit\Framework\TestCase;

final class SendmailProcessTest extends TestCase {

	private function transport(): SendmailMessageTransport {
		$resolver = $this->createStub(IConfigValueResolver::class);
		$resolver->method('resolve')->willReturnCallback(static fn(mixed $value): mixed => $value);
		return new SendmailMessageTransport($resolver, $this->createStub(ILogger::class));
	}

	private function message(): Message {
		return new Message('notice', 'Monthly report', 'Text content', '<p>HTML content</p>', [
			new MessageAddress('to', 'first@example.test'),
			new MessageAddress('cc', 'second@example.test')
		]);
	}

	private function executable(string $script): string {
		$path = tempnam(sys_get_temp_dir(), 'messagehub-sendmail-');
		if($path === false) {
			self::fail('Unable to create temporary sendmail fixture.');
		}
		file_put_contents($path, "#!/bin/sh\n" . $script . "\n");
		chmod($path, 0700);
		return $path;
	}

	public function testControlledSendmailProcessReceivesMimeAndReportsAcceptedRecipients(): void {
		$capture = tempnam(sys_get_temp_dir(), 'messagehub-mime-');
		if($capture === false) {
			self::fail('Unable to create temporary MIME capture.');
		}
		$binary = $this->executable('cat > ' . escapeshellarg($capture) . "\nexit 0");
		try {
			$result = $this->transport()->send($this->message(), [
				'enabled' => true,
				'binary_path' => $binary,
				'arguments' => [' -i ', '', 0, ['ignored']],
				'from_address' => 'sender@example.test',
				'from_name' => 'Reports'
			]);
			self::assertTrue($result->isSuccess(), $result->getMessage());
			self::assertSame('sendmail', $result->getDetails()['transport']);
			self::assertSame(2, $result->getDetails()['recipient_count']);
			self::assertSame($binary, $result->getDetails()['binary']);
			$mime = (string)file_get_contents($capture);
			self::assertStringContainsString('Monthly report', $mime);
			self::assertStringContainsString('multipart/alternative', $mime);
			self::assertStringContainsString('first@example.test', $mime);
			self::assertStringContainsString('second@example.test', $mime);
		} finally {
			unlink($binary);
			unlink($capture);
		}
	}

	public function testNonzeroProcessExitIsReportedAsDeliveryFailure(): void {
		$binary = $this->executable("cat > /dev/null\necho 'test rejection' >&2\nexit 23");
		try {
			$result = $this->transport()->send($this->message(), [
				'enabled' => true, 'binary_path' => $binary, 'from_address' => 'sender@example.test'
			]);
			self::assertFalse($result->isSuccess());
			self::assertStringContainsString('Sendmail exited with code 23', $result->getMessage());
			self::assertStringContainsString('test rejection', $result->getMessage());
			self::assertSame(\RuntimeException::class, $result->getDetails()['exception']);
		} finally {
			unlink($binary);
		}
	}


	public function testErrorOutputFallsBackToStdoutWhenStderrIsEmpty(): void {
		$binary = $this->executable("cat > /dev/null\necho 'stdout rejection'\nexit 17");
		try {
			$result = $this->transport()->send($this->message(), [
				'enabled' => true,
				'binary_path' => $binary,
				'from_address' => 'sender@example.test'
			]);
			self::assertFalse($result->isSuccess());
			self::assertStringContainsString('Sendmail exited with code 17', $result->getMessage());
			self::assertStringContainsString('stdout rejection', $result->getMessage());
		} finally {
			unlink($binary);
		}
	}

	public function testNonExecutableBinaryIsRejectedBeforeStartingProcess(): void {
		$binary = tempnam(sys_get_temp_dir(), 'messagehub-nonexec-');
		if($binary === false) {
			self::fail('Unable to create temporary binary fixture.');
		}
		try {
			chmod($binary, 0600);
			$result = $this->transport()->send($this->message(), ['enabled' => true, 'binary_path' => $binary]);
			self::assertFalse($result->isSuccess());
			self::assertStringContainsString('Sendmail binary is not executable', $result->getMessage());
		} finally {
			unlink($binary);
		}
	}

	public function testExecutableProcessDoesNotRunWithMissingSenderOrRecipients(): void {
		$binary = $this->executable('exit 0');
		try {
			$withoutSender = $this->transport()->send($this->message(), ['enabled' => true, 'binary_path' => $binary]);
			self::assertFalse($withoutSender->isSuccess());
			self::assertStringContainsString('sender', strtolower($withoutSender->getMessage()));

			$withoutRecipients = new Message('notice', 'Subject', 'Body');
			$result = $this->transport()->send($withoutRecipients, [
				'enabled' => true, 'binary_path' => $binary, 'from_address' => 'sender@example.test'
			]);
			self::assertFalse($result->isSuccess());
			self::assertStringContainsString('recipient', strtolower($result->getMessage()));
		} finally {
			unlink($binary);
		}
	}
}
