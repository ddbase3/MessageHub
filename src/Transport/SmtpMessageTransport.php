<?php declare(strict_types=1);

namespace MessageHub\Transport;

use MessageHub\Transport\Support\AbstractMessageTransport;
use MessageHub\Transport\Support\MimeMessageBuilder;
use MessageHub\Transport\Support\SmtpClient;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageDeliveryResult;
use Throwable;

final class SmtpMessageTransport extends AbstractMessageTransport implements IMessageTransport {

	public static function getName(): string {
		return 'smtp';
	}

	public function getLabel(): string {
		return 'SMTP';
	}

	public function getSettingsSummary(array $settings = []): string {
		$host = $this->readString($settings, 'host', '');
		$port = $this->readInt($settings, 'port', 587);
		$encryption = strtolower($this->readString($settings, 'encryption', 'starttls'));
		$auth = $this->readBool($settings, 'auth', true);

		return $this->createSummary([
			$host !== '' ? 'Server: ' . $host . ':' . $port : 'Server: not configured',
			'Encryption: ' . ($encryption !== '' ? strtoupper($encryption) : 'None'),
			'Authentication: ' . ($auth ? 'enabled' : 'disabled'),
			$auth ? 'Username: ' . ($this->readString($settings, 'username', '') !== '' ? $this->readString($settings, 'username', '') : 'not configured') : '',
			$auth ? 'Password: ' . ($this->hasConfiguredValue($settings['password'] ?? null) ? 'configured' : 'not configured') : ''
		]);
	}

	public function supports(Message $message, array $settings = []): bool {
		return function_exists('stream_socket_client');
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->isEnabled($settings)) {
			return $this->failure('SMTP transport is disabled.');
		}

		try {
			$mime = (new MimeMessageBuilder())->build($message, $settings);
			$details = (new SmtpClient())->send($mime, $settings, $this->resolveString($settings['password'] ?? ''));
			$details['transport'] = self::getName();

			return $this->success('Message accepted by the SMTP server.', '', $details);
		} catch(Throwable $exception) {
			return $this->failureFromException('SMTP', $exception, ['transport' => self::getName()]);
		}
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => false],
				'host' => ['type' => 'string'],
				'port' => ['type' => 'integer'],
				'encryption' => ['type' => 'string', 'enum' => ['', 'starttls', 'tls', 'ssl', 'smtps']],
				'verify_tls' => ['type' => 'boolean'],
				'client_name' => ['type' => 'string'],
				'timeout' => ['type' => 'integer'],
				'auth' => ['type' => 'boolean'],
				'auth_mode' => ['type' => 'string', 'enum' => ['login', 'plain']],
				'username' => ['type' => 'string'],
				'password' => ['description' => 'ConfigValue definition or fixed secret'],
				'from_address' => ['type' => 'string'],
				'from_name' => ['type' => 'string'],
				'reply_to_address' => ['type' => 'string'],
				'reply_to_name' => ['type' => 'string']
			]
		];
	}
}
