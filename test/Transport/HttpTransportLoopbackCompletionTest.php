<?php declare(strict_types=1);

namespace MessageHub\Test\Transport;

use Base3\ConfigValue\Api\IConfigValueResolver;
use Base3\Logger\Api\ILogger;
use MessageHub\Transport\HttpWebhookMessageTransport;
use MessageHub\Transport\MicrosoftTeamsMessageTransport;
use MessageHub\Transport\NtfyMessageTransport;
use MessageHub\Transport\SlackMessageTransport;
use MessageHub\Transport\TelegramMessageTransport;
use MessageHub\Transport\WhatsAppCloudMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageAddress;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class HttpTransportLoopbackCompletionTest extends TestCase {

	private function transport(string $class): object {
		$resolver = $this->createStub(IConfigValueResolver::class);
		$resolver->method('resolve')->willReturnCallback(static fn(mixed $value): mixed => $value);
		return new $class($resolver, $this->createStub(ILogger::class));
	}

	private function message(array $addresses = [], string $subject = 'Notice', string $body = 'Body'): Message {
		return new Message('notice', $subject, $body, '', array_map(
			static fn(string $address): MessageAddress => new MessageAddress('to', $address),
			$addresses
		));
	}

	private function receive($socket): array {
		stream_set_timeout($socket, 5);
		$firstLine = fgets($socket);
		if($firstLine === false) {
			throw new RuntimeException('Loopback server did not receive the request line.');
		}

		$headers = [];
		while(($line = fgets($socket)) !== false && trim($line) !== '') {
			$separator = strpos($line, ':');
			if($separator !== false) {
				$name = strtolower(trim(substr($line, 0, $separator)));
				$headers[$name] = trim(substr($line, $separator + 1));
			}
		}

		$remaining = (int)($headers['content-length'] ?? 0);
		$body = '';
		while($remaining > 0) {
			$part = fread($socket, min(8192, $remaining));
			if($part === false || $part === '') {
				throw new RuntimeException('Loopback server received an incomplete request body.');
			}
			$body .= $part;
			$remaining -= strlen($part);
		}

		return [
			'request' => trim($firstLine),
			'headers' => $headers,
			'body' => $body
		];
	}

	/**
	 * @param array<int,array{status:int,body:string}> $replies
	 * @return array{0:mixed,1:array<int,array<string,mixed>>}
	 */
	private function exchange(array $replies, callable $client): array {
		if(!function_exists('curl_init') || !function_exists('pcntl_fork') || !function_exists('stream_socket_server')) {
			self::markTestSkipped('The local HTTP transport tests require cURL, pcntl and stream sockets.');
		}

		$server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
		if($server === false) {
			self::markTestSkipped('Local loopback sockets are unavailable: ' . $error);
		}

		$port = (int)substr((string)strrchr((string)stream_socket_get_name($server, false), ':'), 1);
		$statusFile = tempnam(sys_get_temp_dir(), 'messagehub-http-fixture-');
		if($statusFile === false) {
			fclose($server);
			self::fail('Unable to create a local HTTP fixture file.');
		}

		$pid = pcntl_fork();
		if($pid === -1) {
			fclose($server);
			unlink($statusFile);
			self::fail('Unable to fork a local HTTP fixture.');
		}

		if($pid === 0) {
			$requests = [];
			try {
				foreach($replies as $reply) {
					$connection = @stream_socket_accept($server, 5);
					if($connection === false) {
						throw new RuntimeException('Local HTTP fixture did not receive the expected request.');
					}
					try {
						$requests[] = $this->receive($connection);
						$body = $reply['body'];
						$headers = 'HTTP/1.1 ' . $reply['status'] . " Fixture\r\n"
							. "Content-Type: application/json\r\n"
							. 'Content-Length: ' . strlen($body) . "\r\n"
							. "Connection: close\r\nX-Fixture: local\r\n\r\n";
						$response = $headers . $body;
						$offset = 0;
						while($offset < strlen($response)) {
							$written = fwrite($connection, substr($response, $offset, 16384));
							if($written === false || $written === 0) {
								throw new RuntimeException('Local HTTP fixture failed to write its response.');
							}
							$offset += $written;
						}
					} finally {
						fclose($connection);
					}
				}
				file_put_contents($statusFile, json_encode(['requests' => $requests], JSON_THROW_ON_ERROR));
			} catch(Throwable $exception) {
				file_put_contents($statusFile, json_encode(['error' => $exception->getMessage()], JSON_THROW_ON_ERROR));
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
		} catch(Throwable $exception) {
			$clientError = $exception;
		} finally {
			pcntl_waitpid($pid, $status);
			$fixture = json_decode((string)file_get_contents($statusFile), true);
			unlink($statusFile);
		}

		self::assertIsArray($fixture);
		self::assertArrayNotHasKey('error', $fixture, (string)($fixture['error'] ?? ''));
		self::assertCount(count($replies), $fixture['requests']);
		if($clientError !== null) {
			throw $clientError;
		}
		return [$result, $fixture['requests']];
	}

	private function reply(int $status, string $body): array {
		return ['status' => $status, 'body' => $body];
	}

	public function testWebhookJsonDeliveryAppliesNestedPayloadHeadersAndResponseId(): void {
		[$result, $requests] = $this->exchange([$this->reply(201, '{"data":{"id":42}}')], function(int $port) {
			return $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
				'enabled' => true,
				'endpoint' => 'http://127.0.0.1:' . $port . '/webhook',
				'method' => 'PUT', 'content_type' => 'json',
				'headers' => ['X-Test' => ' marker ', '' => 'ignored'],
				'auth_mode' => 'bearer', 'bearer_token' => 'local-token',
				'custom_payload' => ['nested' => ['subject' => '{{subject}}', 'type' => '{{type_name}}'], 'count' => 2],
				'include_message' => false, 'response_id_path' => 'data.id',
				'include_response_body' => true, 'timeout' => 3
			]);
		});

		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame('42', $result->getExternalId());
		self::assertSame(201, $result->getDetails()['status_code']);
		self::assertSame('{"data":{"id":42}}', $result->getDetails()['response']);
		self::assertStringStartsWith('PUT /webhook ', $requests[0]['request']);
		self::assertSame('Bearer local-token', $requests[0]['headers']['authorization']);
		self::assertSame('marker', $requests[0]['headers']['x-test']);
		self::assertSame('BASE3-MessageHub/1.0', $requests[0]['headers']['user-agent']);
		self::assertSame(['nested' => ['subject' => 'Notice', 'type' => 'notice'], 'count' => 2],
			json_decode($requests[0]['body'], true));
	}

	public function testWebhookFormAndTextDeliveriesUseTheirActualBodyFormats(): void {
		foreach(['form', 'text'] as $format) {
			[$result, $requests] = $this->exchange([$this->reply(200, '{"ok":true}')], function(int $port) use ($format) {
				return $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
					'enabled' => true, 'endpoint' => 'http://127.0.0.1:' . $port . '/submit',
					'content_type' => $format, 'method' => 'PATCH',
					'auth_mode' => 'basic', 'username' => 'local', 'password' => 'password',
					'custom_payload' => ['subject' => '{{subject}}', 'value' => 5],
					'include_message' => false, 'text_template' => 'Message: {{subject}}|{{body_text}}'
				]);
			});
			self::assertTrue($result->isSuccess(), $result->getMessage());
			self::assertSame('Basic ' . base64_encode('local:password'), $requests[0]['headers']['authorization']);
			self::assertStringStartsWith('PATCH /submit ', $requests[0]['request']);
			if($format === 'form') {
				self::assertSame(['subject' => 'Notice', 'value' => '5'], [] + (function() use ($requests): array {
					parse_str($requests[0]['body'], $form);
					return $form;
				})());
				self::assertStringContainsString('application/x-www-form-urlencoded', $requests[0]['headers']['content-type']);
			} else {
				self::assertSame('Message: Notice|Body', $requests[0]['body']);
				self::assertStringContainsString('text/plain', $requests[0]['headers']['content-type']);
			}
		}
	}

	public function testWebhookRejectsProviderErrorAndIgnoresNonScalarResponseId(): void {
		[$error] = $this->exchange([$this->reply(422, '{"error":{"message":"validation denied"}}')], function(int $port) {
			return $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
				'enabled' => true, 'endpoint' => 'http://127.0.0.1:' . $port . '/rejected',
				'expected_status_min' => 200, 'expected_status_max' => 299
			]);
		});
		self::assertFalse($error->isSuccess());
		self::assertSame('validation denied', $error->getMessage());
		self::assertSame(422, $error->getDetails()['status_code']);

		[$result] = $this->exchange([$this->reply(202, '{"data":{"id":{"invalid":true}}}')], function(int $port) {
			return $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
				'enabled' => true, 'endpoint' => 'http://127.0.0.1:' . $port,
				'response_id_path' => 'data.id'
			]);
		});
		self::assertTrue($result->isSuccess());
		self::assertSame('', $result->getExternalId());
	}

	public function testSlackSuccessfulAndRejectedWebhooksUseHttpResponse(): void {
		foreach([200, 429] as $status) {
			[$result, $requests] = $this->exchange([$this->reply($status, $status === 200 ? 'ok' : '{"error":"rate limited"}')], function(int $port) {
				return $this->transport(SlackMessageTransport::class)->send($this->message(), [
					'enabled' => true, 'webhook_url' => 'http://127.0.0.1:' . $port . '/slack',
					'unfurl_links' => true, 'unfurl_media' => false,
					'blocks' => [['type' => 'section', 'text' => ['type' => 'plain_text', 'text' => 'Local']]]
				]);
			});
			self::assertSame($status === 200, $result->isSuccess());
			self::assertStringStartsWith('POST /slack ', $requests[0]['request']);
			$payload = json_decode($requests[0]['body'], true);
			self::assertSame('Notice' . "\n\n" . 'Body', $payload['text']);
			self::assertTrue($payload['unfurl_links']);
			self::assertCount(1, $payload['blocks']);
			if($status === 429) {
				self::assertSame('rate limited', $result->getMessage());
			} else {
				self::assertSame('ok', $result->getDetails()['response']);
			}
		}
	}

	public function testTeamsWorkflowAndAdaptiveCardsReachSuccessResponsePath(): void {
		foreach(['workflow_text', 'adaptive_card'] as $mode) {
			[$result, $requests] = $this->exchange([$this->reply(200, 'accepted')], function(int $port) use ($mode) {
				return $this->transport(MicrosoftTeamsMessageTransport::class)->send($this->message(), [
					'enabled' => true, 'webhook_url' => 'http://127.0.0.1:' . $port . '/teams',
					'payload_mode' => $mode
				]);
			});
			self::assertTrue($result->isSuccess(), $result->getMessage());
			self::assertSame($mode, $result->getDetails()['payload_mode']);
			$payload = json_decode($requests[0]['body'], true);
			if($mode === 'adaptive_card') {
					self::assertSame('AdaptiveCard', $payload['attachments'][0]['content']['type']);
					self::assertCount(2, $payload['attachments'][0]['content']['body']);
				} else {
					self::assertSame("Notice\n\nBody", $payload['text']);
				}
		}
	}

	public function testTeamsFailedWebhookReturnsProviderError(): void {
		[$result] = $this->exchange([$this->reply(403, '{"message":"not authorized"}')], function(int $port) {
			return $this->transport(MicrosoftTeamsMessageTransport::class)->send($this->message(), [
				'enabled' => true, 'webhook_url' => 'http://127.0.0.1:' . $port
			]);
		});
		self::assertFalse($result->isSuccess());
		self::assertSame('not authorized', $result->getMessage());
	}

	public function testNtfyMultipleTopicsBuildsHeadersAndCollectsIds(): void {
		[$result, $requests] = $this->exchange([
			$this->reply(200, '{"id":"one"}'),
			$this->reply(200, '{"id":"two"}')
		], function(int $port) {
			return $this->transport(NtfyMessageTransport::class)->send($this->message([' /ops/ ', 'dev']), [
				'enabled' => true, 'base_url' => 'http://127.0.0.1:' . $port,
				'auth_mode' => 'bearer', 'token' => 'local-token',
				'tags' => ['warning', '', 'active'], 'priority' => 'high',
				'markdown' => true, 'cache' => false, 'firebase' => false
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame('one', $result->getExternalId());
		self::assertSame(2, $result->getDetails()['deliveries']);
		self::assertSame(['one', 'two'], $result->getDetails()['message_ids']);
		self::assertStringStartsWith('POST /ops ', $requests[0]['request']);
		self::assertStringStartsWith('POST /dev ', $requests[1]['request']);
		self::assertSame('Bearer local-token', $requests[0]['headers']['authorization']);
		self::assertSame('warning,active', $requests[0]['headers']['tags']);
		self::assertSame('yes', $requests[0]['headers']['markdown']);
		self::assertSame('no', $requests[0]['headers']['cache']);
		self::assertSame('Body', $requests[0]['body']);
	}

	public function testNtfyRejectsProviderErrorWithSelectedTopic(): void {
		[$result] = $this->exchange([$this->reply(503, '{"message":"not available"}')], function(int $port) {
			return $this->transport(NtfyMessageTransport::class)->send($this->message(), [
				'enabled' => true, 'base_url' => 'http://127.0.0.1:' . $port,
				'default_topic' => 'team', 'auth_mode' => 'basic', 'username' => 'user', 'password' => 'secret'
			]);
		});
		self::assertFalse($result->isSuccess());
		self::assertSame('not available', $result->getMessage());
		self::assertSame('team', $result->getDetails()['topic']);
	}

	public function testTelegramCollectsIdsFromMultipleChatsAndHandlesProviderErrors(): void {
		[$result, $requests] = $this->exchange([
			$this->reply(200, '{"ok":true,"result":{"message_id":111}}'),
			$this->reply(200, '{"ok":true,"result":{"message_id":222}}')
		], function(int $port) {
			return $this->transport(TelegramMessageTransport::class)->send($this->message(['chat-one', 'chat-two']), [
				'enabled' => true, 'api_base_url' => 'http://127.0.0.1:' . $port,
				'bot_token' => 'local-token', 'parse_mode' => 'HTML', 'disable_notification' => true
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame('111', $result->getExternalId());
		self::assertSame([ '111', '222' ], $result->getDetails()['message_ids']);
		self::assertSame(2, $result->getDetails()['deliveries']);
		self::assertStringStartsWith('POST /botlocal-token/sendMessage ', $requests[0]['request']);
		self::assertSame('chat-one', json_decode($requests[0]['body'], true)['chat_id']);
		self::assertSame('HTML', json_decode($requests[1]['body'], true)['parse_mode']);

		[$rejected] = $this->exchange([$this->reply(200, '{"ok":false,"description":"unknown chat"}')], function(int $port) {
			return $this->transport(TelegramMessageTransport::class)->send($this->message(['chat-one']), [
				'enabled' => true, 'api_base_url' => 'http://127.0.0.1:' . $port,
				'bot_token' => 'local-token'
			]);
		});
		self::assertFalse($rejected->isSuccess());
		self::assertSame('unknown chat', $rejected->getMessage());
	}

	public function testWhatsAppCloudCollectsIdsAndIgnoresRecipientPunctuation(): void {
		[$result, $requests] = $this->exchange([$this->reply(200, '{"messages":[{"id":"wa-1"}]}')], function(int $port) {
			return $this->transport(WhatsAppCloudMessageTransport::class)->send($this->message(['+49 (123) 567']), [
				'enabled' => true, 'api_base_url' => 'http://127.0.0.1:' . $port,
				'api_version' => 'v22.0', 'phone_number_id' => 'abc', 'access_token' => 'token',
				'preview_url' => true
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame('wa-1', $result->getExternalId());
		self::assertSame(1, $result->getDetails()['deliveries']);
		self::assertStringStartsWith('POST /v22.0/abc/messages ', $requests[0]['request']);
		$payload = json_decode($requests[0]['body'], true);
		self::assertSame('49123567', $payload['to']);
		self::assertTrue($payload['text']['preview_url']);
	}

	public function testWhatsAppCloudFailsOnProviderErrorsAndUnusableRecipientAddresses(): void {
		[$rejected] = $this->exchange([$this->reply(403, '{"error":{"message":"forbidden recipient"}}')], function(int $port) {
			return $this->transport(WhatsAppCloudMessageTransport::class)->send($this->message(['+49 123']), [
				'enabled' => true, 'api_base_url' => 'http://127.0.0.1:' . $port,
				'api_version' => 'v22.0', 'phone_number_id' => 'abc', 'access_token' => 'token'
			]);
		});
		self::assertFalse($rejected->isSuccess());
		self::assertSame('forbidden recipient', $rejected->getMessage());
		self::assertSame('49123', $rejected->getDetails()['recipient']);

		$result = $this->transport(WhatsAppCloudMessageTransport::class)->send($this->message(['###']), [
			'enabled' => true, 'api_version' => 'v22.0', 'phone_number_id' => 'abc', 'access_token' => 'token'
		]);
		self::assertFalse($result->isSuccess());
		self::assertStringContainsString('no usable recipient', $result->getMessage());
	}

	public function testWebhookMissingJsonPathReturnsEmptyIdWithoutResponseDetails(): void {
		[$result] = $this->exchange([$this->reply(200, '{"data":{"id":null}}')], function(int $port) {
			return $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
				'enabled' => true, 'endpoint' => 'http://127.0.0.1:' . $port,
				'response_id_path' => 'data.unknown'
			]);
		});
		self::assertTrue($result->isSuccess());
		self::assertSame('', $result->getExternalId());
		self::assertArrayNotHasKey('response', $result->getDetails());
	}

	public function testSlackAcceptsConfiguredBlocksWithoutText(): void {
		[$result, $requests] = $this->exchange([$this->reply(200, 'ok')], function(int $port) {
			return $this->transport(SlackMessageTransport::class)->send($this->message([], '', ''), [
				'enabled' => true, 'webhook_url' => 'http://127.0.0.1:' . $port,
				'blocks' => [['type' => 'section', 'text' => ['type' => 'plain_text', 'text' => 'Update']]]
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame('', json_decode($requests[0]['body'], true)['text']);
		self::assertCount(1, json_decode($requests[0]['body'], true)['blocks']);
	}

	public function testTeamsAdaptiveCardUsesPlaceholderWhenMessageIsEmpty(): void {
		[$result, $requests] = $this->exchange([$this->reply(200, '')], function(int $port) {
			return $this->transport(MicrosoftTeamsMessageTransport::class)->send($this->message([], '', ''), [
				'enabled' => true, 'webhook_url' => 'http://127.0.0.1:' . $port,
				'payload_mode' => 'adaptive_card'
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		$body = json_decode($requests[0]['body'], true)['attachments'][0]['content']['body'];
		self::assertCount(1, $body);
		self::assertSame('Message', $body[0]['text']);
	}

	public function testNtfyUsesDefaultTopicAndSubjectFallbackForEmptyBody(): void {
		[$result, $requests] = $this->exchange([$this->reply(200, '{}')], function(int $port) {
			return $this->transport(NtfyMessageTransport::class)->send($this->message([], 'Only subject', ''), [
				'enabled' => true, 'base_url' => 'http://127.0.0.1:' . $port,
				'default_topic' => '/alerts/', 'tags' => 'urgent',
				'auth_mode' => 'basic', 'username' => 'local', 'password' => 'pw'
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame('', $result->getExternalId());
		self::assertStringStartsWith('POST /alerts ', $requests[0]['request']);
		self::assertSame('Only subject', $requests[0]['body']);
		self::assertSame('urgent', $requests[0]['headers']['tags']);
		self::assertSame('Basic ' . base64_encode('local:pw'), $requests[0]['headers']['authorization']);
	}

	public function testTelegramDefaultChatCanSucceedWithoutProviderMessageId(): void {
		[$result, $requests] = $this->exchange([$this->reply(200, '{"ok":true,"result":{}}')], function(int $port) {
			return $this->transport(TelegramMessageTransport::class)->send($this->message(), [
				'enabled' => true, 'api_base_url' => 'http://127.0.0.1:' . $port,
				'bot_token' => 'token', 'default_chat_id' => 'backup-chat'
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame('', $result->getExternalId());
		self::assertSame([], $result->getDetails()['message_ids']);
		self::assertSame('backup-chat', json_decode($requests[0]['body'], true)['chat_id']);
	}

	public function testWhatsAppCloudCanSucceedWithoutProviderMessageId(): void {
		[$result, $requests] = $this->exchange([$this->reply(200, '{"messages":[]}')], function(int $port) {
			return $this->transport(WhatsAppCloudMessageTransport::class)->send($this->message(['+49 111', 'invalid']), [
				'enabled' => true, 'api_base_url' => 'http://127.0.0.1:' . $port,
				'api_version' => 'v22.0', 'phone_number_id' => 'abc', 'access_token' => 'token'
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame('', $result->getExternalId());
		self::assertSame([], $result->getDetails()['message_ids']);
		self::assertCount(1, $requests);
	}



	public function testWebhookUsesDefaultMessagePayloadAndDoesNotInventMissingBearerToken(): void {
		[$result, $requests] = $this->exchange([$this->reply(200, '{"id":null}')], function(int $port) {
			return $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
				'enabled' => true,
				'endpoint' => 'http://127.0.0.1:' . $port . '/payload',
				'auth_mode' => 'bearer', 'bearer_token' => '',
				'response_id_path' => 'id'
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame('', $result->getExternalId());
		self::assertArrayNotHasKey('authorization', $requests[0]['headers']);
		$payload = json_decode($requests[0]['body'], true);
		self::assertSame('notice', $payload['message']['type_name']);
		self::assertSame('Notice', $payload['message']['subject']);
	}

	public function testWebhookHonorsExplicitStatusRangeAndTruncatesCapturedResponse(): void {
		$body = str_repeat('R', 650);
		[$result] = $this->exchange([$this->reply(302, $body)], function(int $port) {
			return $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
				'enabled' => true,
				'endpoint' => 'http://127.0.0.1:' . $port,
				'expected_status_min' => 300,
				'expected_status_max' => 399,
				'include_response_body' => true
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame(302, $result->getDetails()['status_code']);
		self::assertTrue(strlen($result->getDetails()['response']) <= 503);
		self::assertStringStartsWith(str_repeat('R', 40), $result->getDetails()['response']);
	}

	public function testWebhookUnsupportedMethodIsRejectedBeforeContactingEndpoint(): void {
		if(!function_exists('curl_init')) {
			self::markTestSkipped('The HTTP transport requires the installed cURL extension.');
		}
		$result = $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
			'enabled' => true, 'endpoint' => 'http://127.0.0.1:1/not-contacted', 'method' => 'TRACE'
		]);
		self::assertFalse($result->isSuccess());
		self::assertStringContainsString('Unsupported HTTP method', $result->getMessage());
		self::assertSame(RuntimeException::class, $result->getDetails()['exception']);
	}

	public function testNtfyDeduplicatesRecipientsAndDoesNotSendDuplicateNotifications(): void {
		[$result, $requests] = $this->exchange([$this->reply(200, '{}')], function(int $port) {
			return $this->transport(NtfyMessageTransport::class)->send($this->message([' /updates/ ', 'updates', ' /updates/ ']), [
				'enabled' => true,
				'base_url' => 'http://127.0.0.1:' . $port,
				'tags' => ['hello', 2, '', ['invalid'], 'hello'],
				'click_url' => 'https://example.test/details'
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame(1, $result->getDetails()['deliveries']);
		self::assertCount(1, $requests);
		self::assertStringStartsWith('POST /updates ', $requests[0]['request']);
		self::assertSame('hello,2,hello', $requests[0]['headers']['tags']);
		self::assertSame('https://example.test/details', $requests[0]['headers']['click']);
	}

	public function testNtfyStopsAtSecondProviderFailureAfterFirstAcceptedTopic(): void {
		[$result, $requests] = $this->exchange([
			$this->reply(200, '{"id":"first"}'),
			$this->reply(429, '{"error":"rate limit"}')
		], function(int $port) {
			return $this->transport(NtfyMessageTransport::class)->send($this->message(['first', 'second']), [
				'enabled' => true, 'base_url' => 'http://127.0.0.1:' . $port
			]);
		});
		self::assertFalse($result->isSuccess());
		self::assertSame('rate limit', $result->getMessage());
		self::assertSame(429, $result->getDetails()['status_code']);
		self::assertSame('second', $result->getDetails()['topic']);
		self::assertCount(2, $requests);
	}

	public function testTelegramSplitsLongMessagesAndOmittingSubjectIsRespected(): void {
		[$result, $requests] = $this->exchange([
			$this->reply(200, '{"ok":true,"result":{"message_id":101}}'),
			$this->reply(200, '{"ok":true,"result":{"message_id":102}}')
		], function(int $port) {
			return $this->transport(TelegramMessageTransport::class)->send($this->message(['primary'], 'Ignored subject', str_repeat('A', 4300)), [
				'enabled' => true,
				'api_base_url' => 'http://127.0.0.1:' . $port,
				'bot_token' => 'token',
				'include_subject' => false,
				'disable_web_page_preview' => true
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame(2, $result->getDetails()['deliveries']);
		self::assertSame(['101', '102'], $result->getDetails()['message_ids']);
		self::assertCount(2, $requests);
		self::assertSame(str_repeat('A', 4000), json_decode($requests[0]['body'], true)['text']);
		self::assertSame(str_repeat('A', 300), json_decode($requests[1]['body'], true)['text']);
		self::assertTrue(json_decode($requests[0]['body'], true)['link_preview_options']['is_disabled']);
		self::assertArrayNotHasKey('parse_mode', json_decode($requests[0]['body'], true));
	}

	public function testWhatsAppCloudSplitsLongTextAndTracksEachProviderResponse(): void {
		[$result, $requests] = $this->exchange([
			$this->reply(200, '{"messages":[{"id":"part-1"}]}'),
			$this->reply(200, '{"messages":[]}')
		], function(int $port) {
			return $this->transport(WhatsAppCloudMessageTransport::class)->send($this->message(['+49 123'], 'Ignored', str_repeat('B', 4100)), [
				'enabled' => true,
				'api_base_url' => 'http://127.0.0.1:' . $port,
				'api_version' => 'v22.0',
				'phone_number_id' => 'sender',
				'access_token' => 'token',
				'include_subject' => false
			]);
		});
		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertSame(2, $result->getDetails()['deliveries']);
		self::assertSame(['part-1'], $result->getDetails()['message_ids']);
		self::assertCount(2, $requests);
		self::assertSame(str_repeat('B', 4000), json_decode($requests[0]['body'], true)['text']['body']);
		self::assertSame(str_repeat('B', 100), json_decode($requests[1]['body'], true)['text']['body']);
	}

	public function testTeamsAndSlackResponseSummariesAreBounded(): void {
		foreach([
			[MicrosoftTeamsMessageTransport::class, 'teams', 300],
			[SlackMessageTransport::class, 'slack', 200]
		] as [$class, $path, $limit]) {
			[$result, $requests] = $this->exchange([$this->reply(200, str_repeat('x', $limit + 120))], function(int $port) use ($class, $path) {
				return $this->transport($class)->send($this->message([], 'Subject', 'Body'), [
					'enabled' => true,
					'webhook_url' => 'http://127.0.0.1:' . $port . '/' . $path,
					'include_subject' => false
				]);
			});
			self::assertTrue($result->isSuccess(), $result->getMessage());
			self::assertTrue(strlen($result->getDetails()['response']) <= $limit + 3);
			self::assertStringStartsWith('POST /' . $path . ' ', $requests[0]['request']);
			self::assertSame('Body', json_decode($requests[0]['body'], true)['text']);
		}
	}


	public function testLargeHttpResponseIsCappedAndMarkedAsTruncated(): void {
		require_once __DIR__ . '/../Fixture/HttpMessageTransportProbe.php';
		[$response, $requests] = $this->exchange([
			$this->reply(200, str_repeat('z', 1024 * 1024 + 4096))
		], function(int $port) {
			$probe = new \MessageHub\Test\Fixture\HttpMessageTransportProbe(
				$this->createStub(IConfigValueResolver::class),
				$this->createStub(ILogger::class)
			);
			return $probe->requestFromFixture('http://127.0.0.1:' . $port . '/large');
		});
		self::assertSame(200, $response->getStatusCode());
		self::assertSame(1024 * 1024, strlen($response->getBody()));
		self::assertSame('1', $response->getHeaders()['x-messagehub-response-truncated']);
		self::assertSame('local', $response->getHeaders()['x-fixture']);
		self::assertCount(1, $requests);
	}

	public function testWebhookNonArrayCustomPayloadUsesTheStandardMessagePayload(): void {
		[$result, $requests] = $this->exchange([$this->reply(200, '{"ok":true}')], function(int $port) {
			return $this->transport(HttpWebhookMessageTransport::class)->send($this->message(), [
				'enabled' => true,
				'endpoint' => 'http://127.0.0.1:' . $port . '/fallback-payload',
				'content_type' => 'json',
				'custom_payload' => 'not-an-array'
			]);
		});

		self::assertTrue($result->isSuccess(), $result->getMessage());
		self::assertStringStartsWith('POST /fallback-payload ', $requests[0]['request']);
		$payload = json_decode($requests[0]['body'], true);
		self::assertIsArray($payload);
		self::assertSame('notice', $payload['message']['type_name']);
		self::assertSame('Notice', $payload['message']['subject']);
	}
}
