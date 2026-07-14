<?php declare(strict_types=1);

namespace MessageHub\Transport;

use MessageHub\Transport\Support\AbstractMessageTransport;
use MessageHub\Transport\Support\MimeMessageBuilder;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageDeliveryResult;
use RuntimeException;
use Throwable;

final class SendmailMessageTransport extends AbstractMessageTransport implements IMessageTransport {

	public static function getName(): string {
		return 'sendmail';
	}

	public function getLabel(): string {
		return 'Sendmail';
	}

	public function getSettingsSummary(array $settings = []): string {
		return $this->createSummary([
			'Binary: ' . $this->readString($settings, 'binary_path', '/usr/sbin/sendmail'),
			$this->readString($settings, 'from_address', '') !== '' ? 'From: ' . $this->readString($settings, 'from_address', '') : 'Sender from message'
		]);
	}

	public function supports(Message $message, array $settings = []): bool {
		return function_exists('proc_open');
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->isEnabled($settings)) {
			return $this->failure('Sendmail transport is disabled.');
		}

		if(!function_exists('proc_open')) {
			return $this->failure('proc_open is not available in the current runtime.');
		}

		try {
			$binary = $this->readString($settings, 'binary_path', '/usr/sbin/sendmail');
			if($binary === '' || !is_file($binary) || !is_executable($binary)) {
				throw new RuntimeException('Sendmail binary is not executable: ' . $binary);
			}

			$mime = (new MimeMessageBuilder())->build($message, $settings);
			$command = [$binary];
			foreach($this->readArray($settings, 'arguments', ['-i']) as $argument) {
				if(is_scalar($argument) && trim((string)$argument) !== '') {
					$command[] = trim((string)$argument);
				}
			}
			$command[] = '-f';
			$command[] = $mime['from'];
			$command[] = '--';
			foreach($mime['recipients'] as $recipient) {
				$command[] = $recipient;
			}

			$process = proc_open($command, [
				0 => ['pipe', 'r'],
				1 => ['pipe', 'w'],
				2 => ['pipe', 'w']
			], $pipes);

			if(!is_resource($process)) {
				throw new RuntimeException('Unable to start the sendmail process.');
			}

			fwrite($pipes[0], $mime['raw']);
			fclose($pipes[0]);
			$stdout = stream_get_contents($pipes[1]) ?: '';
			$stderr = stream_get_contents($pipes[2]) ?: '';
			fclose($pipes[1]);
			fclose($pipes[2]);
			$exitCode = proc_close($process);

			if($exitCode !== 0) {
				throw new RuntimeException('Sendmail exited with code ' . $exitCode . ': ' . trim($stderr !== '' ? $stderr : $stdout));
			}

			return $this->success('Message accepted by sendmail.', '', [
				'transport' => self::getName(),
				'binary' => $binary,
				'recipient_count' => count($mime['recipients'])
			]);
		} catch(Throwable $exception) {
			return $this->failureFromException('Sendmail', $exception, ['transport' => self::getName()]);
		}
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => false],
				'binary_path' => ['type' => 'string'],
				'arguments' => ['type' => 'array'],
				'from_address' => ['type' => 'string'],
				'from_name' => ['type' => 'string'],
				'reply_to_address' => ['type' => 'string'],
				'reply_to_name' => ['type' => 'string']
			]
		];
	}
}
