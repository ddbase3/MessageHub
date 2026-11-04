<?php declare(strict_types=1);

namespace MessageHub\Test\Transport;

use Base3\ConfigValue\Api\IConfigValueResolver;
use Base3\Logger\Api\ILogger;
use MessageHub\Transport\SmtpMessageTransport;
use MessageHub\Transport\Support\SmtpClient;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageAddress;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class SmtpLoopbackSessionTest extends TestCase {

	private function readLine($socket): string {
		$line = fgets($socket);
		if($line === false) {
			throw new RuntimeException('SMTP fixture expected another command.');
		}
		return rtrim($line, "\r\n");
	}

	private function requireCommand($socket, string $expected): void {
		$actual = $this->readLine($socket);
		if($actual !== $expected) {
			throw new RuntimeException('SMTP fixture expected ' . $expected . ', received ' . $actual);
		}
	}

	private function reply($socket, string $line): void {
		fwrite($socket, $line . "\r\n");
	}

	private function greeting($socket, string $clientName = 'client.example.test'): void {
		$this->reply($socket, '220 local SMTP fixture');
		$this->requireCommand($socket, 'EHLO ' . $clientName);
		$this->reply($socket, '250-local service');
		$this->reply($socket, '250 SIZE 65536');
	}

	private function receiveMessage($socket, array $recipients, array $reject = []): string {
		$this->requireCommand($socket, 'MAIL FROM:<sender@example.test>');
		$this->reply($socket, '250 Sender accepted');
		foreach($recipients as $address) {
			$this->requireCommand($socket, 'RCPT TO:<' . $address . '>');
			$this->reply($socket, in_array($address, $reject, true) ? '550 No such recipient' : '250 Recipient accepted');
		}
		if(count($recipients) === count($reject)) {
			return '';
		}
		$this->requireCommand($socket, 'DATA');
		$this->reply($socket, '354 End with dot');
		$data = [];
		while(($line = $this->readLine($socket)) !== '.') {
			$data[] = $line;
		}
		$this->reply($socket, '250 Queued as local-test');
		$this->requireCommand($socket, 'QUIT');
		$this->reply($socket, '221 Goodbye');
		return implode("\n", $data);
	}

	private function simulate(callable $handler, callable $client): mixed {
		if(!function_exists('pcntl_fork') || !function_exists('stream_socket_server')) {
			self::markTestSkipped('Local SMTP fixture requires pcntl and stream sockets.');
		}
		$server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
		if($server === false) {
			self::markTestSkipped('Loopback TCP sockets are unavailable: ' . $error);
		}
		$port = (int)substr((string)strrchr((string)stream_socket_get_name($server, false), ':'), 1);
		$statusFile = tempnam(sys_get_temp_dir(), 'messagehub-smtp-status-');
		if($statusFile === false) {
			fclose($server);
			self::fail('Unable to create local SMTP status file.');
		}
		$pid = pcntl_fork();
		if($pid === -1) {
			fclose($server);
			unlink($statusFile);
			self::fail('Unable to fork local SMTP fixture.');
		}
		if($pid === 0) {
			try {
				$socket = @stream_socket_accept($server, 5);
				if($socket === false) {
					throw new RuntimeException('SMTP fixture did not receive a connection.');
				}
				stream_set_timeout($socket, 4);
				try {
					$handler($socket);
				} finally {
					fclose($socket);
				}
				file_put_contents($statusFile, 'ok');
			} catch(Throwable $error) {
				file_put_contents($statusFile, get_class($error) . ': ' . $error->getMessage());
			} finally {
				fclose($server);
			}
			exit(0);
		}
		fclose($server);
		$result = null;
		$clientError = null;
		try {
			$result = $client($port);
		} catch(Throwable $error) {
			$clientError = $error;
		} finally {
			pcntl_waitpid($pid, $status);
			$fixtureStatus = (string)file_get_contents($statusFile);
			unlink($statusFile);
		}
		self::assertSame('ok', $fixtureStatus, 'Local SMTP fixture: ' . $fixtureStatus);
		if($clientError !== null) {
			throw $clientError;
		}
		return $result;
	}

	private function send(int $port, array $settings = [], array $recipients = ['recipient@example.test'], string $raw = 'Body'): array {
		return (new SmtpClient())->send([
			'from' => 'sender@example.test', 'recipients' => $recipients, 'raw' => $raw
		], array_replace([
			'host' => '127.0.0.1', 'port' => $port, 'encryption' => '', 'auth' => false,
			'timeout' => 3, 'client_name' => 'client.example.test'
		], $settings));
	}

	public function testSmtpClientHandlesMultilineGreetingPartialRejectionAndDotStuffing(): void {
		$contents = tempnam(sys_get_temp_dir(), 'messagehub-smtp-body-');
		if($contents === false) {
			self::fail('Unable to create SMTP body capture.');
		}
		try {
			$result = $this->simulate(function($socket) use ($contents): void {
				$this->greeting($socket, 'client.example.testHELLO');
				$body = $this->receiveMessage($socket,
					['bad@example.test', 'recipient@example.test'], ['bad@example.test']);
				file_put_contents($contents, $body);
			}, fn(int $port): array => $this->send($port, [
				'client_name' => "client.example.test\r\nHELLO"
			], ['bad@example.test', 'recipient@example.test'], ".private\r\nHello\n.world"));
			self::assertSame(['recipient@example.test'], $result['accepted_recipients']);
			self::assertArrayHasKey('bad@example.test', $result['rejected_recipients']);
			self::assertStringContainsString('550 No such recipient', $result['rejected_recipients']['bad@example.test']);
			self::assertStringContainsString('250 Queued as local-test', $result['server_response']);
			self::assertSame('127.0.0.1', $result['host']);
			self::assertStringContainsString('..private', (string)file_get_contents($contents));
			self::assertStringContainsString('..world', (string)file_get_contents($contents));
		} finally {
			unlink($contents);
		}
	}

	public function testLoginAndPlainAuthenticationFollowTheirDifferentProtocols(): void {
		foreach(['login', 'plain'] as $mode) {
			$result = $this->simulate(function($socket) use ($mode): void {
				$this->greeting($socket);
				if($mode === 'plain') {
					$this->requireCommand($socket, 'AUTH PLAIN ' . base64_encode("\0alice\0secret"));
					$this->reply($socket, '235 Authenticated');
				} else {
					$this->requireCommand($socket, 'AUTH LOGIN');
					$this->reply($socket, '334 Username');
					$this->requireCommand($socket, base64_encode('alice'));
					$this->reply($socket, '334 Password');
					$this->requireCommand($socket, base64_encode('secret'));
					$this->reply($socket, '235 Authenticated');
				}
				$this->receiveMessage($socket, ['recipient@example.test']);
			}, fn(int $port): array => $this->sendAuthenticated($port, $mode));
			self::assertSame(['recipient@example.test'], $result['accepted_recipients']);
		}
	}

	private function sendAuthenticated(int $port, string $mode): array {
		return (new SmtpClient())->send([
			'from' => 'sender@example.test', 'recipients' => ['recipient@example.test'], 'raw' => 'Body'
		], [
			'host' => '127.0.0.1', 'port' => $port, 'encryption' => '', 'auth' => true,
			'username' => 'alice', 'auth_mode' => $mode, 'timeout' => 3,
			'client_name' => 'client.example.test'
		], 'secret');
	}

	public function testAuthenticationRequiresUsernameAfterGreeting(): void {
		$message = $this->simulate(function($socket): void {
			$this->greeting($socket);
		}, function(int $port): string {
			try {
				$this->send($port, ['auth' => true]);
				return '';
			} catch(RuntimeException $exception) {
				return $exception->getMessage();
			}
		});
		self::assertStringContainsString('no username is configured', $message);
	}

	public function testServerRejectionAndClosedConnectionAreReported(): void {
		$greetingError = $this->simulate(function($socket): void {
			$this->reply($socket, '554 Service unavailable');
		}, function(int $port): string {
			try {
				$this->send($port);
				return '';
			} catch(RuntimeException $exception) {
				return $exception->getMessage();
			}
		});
		self::assertStringContainsString('554 Service unavailable', $greetingError);

		$closedError = $this->simulate(static function($socket): void {
			// Connection closes without a greeting.
		}, function(int $port): string {
			try {
				$this->send($port);
				return '';
			} catch(RuntimeException $exception) {
				return $exception->getMessage();
			}
		});
		self::assertStringContainsString('connection closed unexpectedly', $closedError);
	}

	public function testAllRejectedRecipientsDoNotProceedToData(): void {
		$error = $this->simulate(function($socket): void {
			$this->greeting($socket);
			$this->receiveMessage($socket, ['recipient@example.test'], ['recipient@example.test']);
		}, function(int $port): string {
			try {
				$this->send($port);
				return '';
			} catch(RuntimeException $exception) {
				return $exception->getMessage();
			}
		});
		self::assertSame('SMTP server rejected all recipients.', $error);
	}

	public function testSmtpMessageTransportUsesItsRealClientAndRecordsDelivery(): void {
		$result = $this->simulate(function($socket): void {
			$this->greeting($socket);
			$this->receiveMessage($socket, ['recipient@example.test']);
		}, function(int $port) {
			$resolver = $this->createStub(IConfigValueResolver::class);
			$resolver->method('resolve')->willReturnCallback(static fn(mixed $value): mixed => $value);
			$message = new Message('notice', 'Subject', 'Body', '', [new MessageAddress('to', 'recipient@example.test')]);
			$transport = new SmtpMessageTransport($resolver, $this->createStub(ILogger::class));
			return $transport->send($message, [
				'enabled' => true,
				'from_address' => 'sender@example.test',
				'host' => '127.0.0.1', 'port' => $port,
				'encryption' => '', 'auth' => false,
				'timeout' => 3, 'client_name' => 'client.example.test'
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame('smtp', $result->getDetails()['transport']);
		self::assertSame(['recipient@example.test'], $result->getDetails()['accepted_recipients']);
	}


	public function testRecipientAcceptedWith251AndQuitRejectionDoesNotInvalidateSentMessage(): void {
		$result = $this->simulate(function($socket): void {
			$this->greeting($socket);
			$this->requireCommand($socket, 'MAIL FROM:<sender@example.test>');
			$this->reply($socket, '250 OK');
			$this->requireCommand($socket, 'RCPT TO:<recipient@example.test>');
			$this->reply($socket, '251 User not local; will forward');
			$this->requireCommand($socket, 'DATA');
			$this->reply($socket, '354 Continue');
			while($this->readLine($socket) !== '.') {}
			$this->reply($socket, '250 Queued for sending');
			$this->requireCommand($socket, 'QUIT');
			$this->reply($socket, '500 Quit not supported');
		}, fn(int $port): array => $this->send($port));
		self::assertSame(['recipient@example.test'], $result['accepted_recipients']);
		self::assertSame([], $result['rejected_recipients']);
		self::assertSame('250 Queued for sending', $result['server_response']);
	}

	public function testEhloRejectionStopsBeforeEnvelopeOrMessageData(): void {
		$error = $this->simulate(function($socket): void {
			$this->reply($socket, '220 Local fixture');
			$this->requireCommand($socket, 'EHLO client.example.test');
			$this->reply($socket, '501 Invalid EHLO name');
		}, function(int $port): string {
			try {
				$this->send($port);
				return '';
			} catch(RuntimeException $exception) {
				return $exception->getMessage();
			}
		});
		self::assertStringContainsString('501 Invalid EHLO name', $error);
	}

	public function testStartTlsRejectionNeverAttemptsToSendCredentials(): void {
		$error = $this->simulate(function($socket): void {
			$this->greeting($socket);
			$this->requireCommand($socket, 'STARTTLS');
			$this->reply($socket, '454 TLS not available');
		}, function(int $port): string {
			try {
				$this->send($port, ['encryption' => 'starttls']);
				return '';
			} catch(RuntimeException $exception) {
				return $exception->getMessage();
			}
		});
		self::assertStringContainsString('454 TLS not available', $error);
	}

	public function testRejectedDataCommandNeverSubmitsMessageBody(): void {
		$error = $this->simulate(function($socket): void {
			$this->greeting($socket);
			$this->requireCommand($socket, 'MAIL FROM:<sender@example.test>');
			$this->reply($socket, '250 OK');
			$this->requireCommand($socket, 'RCPT TO:<recipient@example.test>');
			$this->reply($socket, '250 Recipient accepted');
			$this->requireCommand($socket, 'DATA');
			$this->reply($socket, '552 Message rejected');
		}, function(int $port): string {
			try {
				$this->send($port);
				return '';
			} catch(RuntimeException $exception) {
				return $exception->getMessage();
			}
		});
		self::assertStringContainsString('552 Message rejected', $error);
	}

	public function testBlankSmtpHostFailsWithoutOpeningANetworkConnection(): void {
		try {
			(new SmtpClient())->send(['from' => 'sender@example.test', 'recipients' => ['user@example.test'], 'raw' => 'Body'],
				['host' => ' ', 'auth' => false]);
			self::fail('Missing SMTP host was accepted.');
		} catch(RuntimeException $exception) {
			self::assertSame('SMTP host is not configured.', $exception->getMessage());
		}
	}
}
