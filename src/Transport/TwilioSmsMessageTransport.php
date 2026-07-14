<?php declare(strict_types=1);

namespace MessageHub\Transport;

use MessageHub\Transport\Support\AbstractHttpMessageTransport;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageDeliveryResult;
use Throwable;

final class TwilioSmsMessageTransport extends AbstractHttpMessageTransport implements IMessageTransport {

	public static function getName(): string {
		return 'twiliosms';
	}

	public function getLabel(): string {
		return 'Twilio SMS';
	}

	public function getSettingsSummary(array $settings = []): string {
		$sender = $this->readString($settings, 'messaging_service_sid', '');
		if($sender !== '') {
			$sender = 'Messaging service: ' . $sender;
		} else {
			$sender = 'From: ' . ($this->readString($settings, 'from_number', '') ?: 'not configured');
		}

		return $this->createSummary([
			'Account SID: ' . ($this->readString($settings, 'account_sid', '') ?: 'not configured'),
			'Auth token: ' . ($this->hasConfiguredValue($settings['auth_token'] ?? null) ? 'configured' : 'not configured'),
			$sender
		]);
	}

	public function supports(Message $message, array $settings = []): bool {
		return function_exists('curl_init') && $message->getAttachments() === [];
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->isEnabled($settings)) {
			return $this->failure('Twilio SMS transport is disabled.');
		}

		if($message->getAttachments() !== []) {
			return $this->failure('Twilio SMS transport does not support message attachments.');
		}

		$accountSid = $this->readString($settings, 'account_sid', '');
		$authToken = $this->resolveString($settings['auth_token'] ?? '');
		$fromNumber = $this->readString($settings, 'from_number', '');
		$messagingServiceSid = $this->readString($settings, 'messaging_service_sid', '');
		$recipients = $this->getRecipients($message);
		$body = $this->getMessageText($message, $this->readBool($settings, 'include_subject', true));

		if($accountSid === '' || $authToken === '') {
			return $this->failure('Twilio SMS needs account_sid and auth_token settings.');
		}

		if($fromNumber === '' && $messagingServiceSid === '') {
			return $this->failure('Twilio SMS needs from_number or messaging_service_sid.');
		}

		if($recipients === []) {
			return $this->failure('Twilio SMS needs at least one TO recipient.');
		}

		if($body === '') {
			return $this->failure('Twilio SMS needs message text.');
		}

		$endpoint = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($accountSid) . '/Messages.json';
		$messageSids = [];

		try {
			foreach($recipients as $recipient) {
				$payload = [
					'To' => trim($recipient->getAddress()),
					'Body' => $body
				];
				if($messagingServiceSid !== '') {
					$payload['MessagingServiceSid'] = $messagingServiceSid;
				} else {
					$payload['From'] = $fromNumber;
				}

				$statusCallback = $this->readString($settings, 'status_callback', '');
				if($statusCallback !== '') {
					$payload['StatusCallback'] = $statusCallback;
				}

				$response = $this->request('POST', $endpoint, [
					'Authorization' => 'Basic ' . base64_encode($accountSid . ':' . $authToken),
					'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8'
				], http_build_query($payload, '', '&', PHP_QUERY_RFC3986), $this->readInt($settings, 'timeout', 20), $this->readBool($settings, 'verify_tls', true));

				if(!$response->isSuccessful()) {
					return $this->failure($this->getHttpError($response, 'Twilio rejected the SMS.'), [
						'status_code' => $response->getStatusCode(),
						'recipient' => $recipient->getAddress()
					]);
				}

				$json = $response->getJson();
				$sid = is_array($json) && is_scalar($json['sid'] ?? null) ? (string)$json['sid'] : '';
				if($sid !== '') {
					$messageSids[] = $sid;
				}
			}

			return $this->success('Message sent by Twilio SMS.', $messageSids[0] ?? '', [
				'transport' => self::getName(),
				'deliveries' => count($recipients),
				'message_sids' => $messageSids
			]);
		} catch(Throwable $exception) {
			return $this->failureFromException('Twilio SMS', $exception);
		}
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => false],
				'account_sid' => ['type' => 'string'],
				'auth_token' => ['description' => 'ConfigValue definition or fixed secret'],
				'from_number' => ['type' => 'string'],
				'messaging_service_sid' => ['type' => 'string'],
				'status_callback' => ['type' => 'string'],
				'include_subject' => ['type' => 'boolean'],
				'timeout' => ['type' => 'integer'],
				'verify_tls' => ['type' => 'boolean']
			]
		];
	}
}
