<?php declare(strict_types=1);

namespace MessageHub\Transport\Support;

use RuntimeException;

final class SmtpClient {

	/**
	 * @param array{from:string,recipients:array<int,string>,raw:string} $message
	 * @return array<string,mixed>
	 */
	public function send(array $message, array $settings, string $password = ''): array {
		$host = $this->readString($settings, 'host', $this->readString($settings, 'smtp_host', ''));
		$port = $this->readInt($settings, 'port', $this->readInt($settings, 'smtp_port', 587));
		$encryption = strtolower($this->readString($settings, 'encryption', $this->readString($settings, 'smtp_secure', 'starttls')));
		$timeout = max(1, min($this->readInt($settings, 'timeout', 20), 120));
		$verifyTls = $this->readBool($settings, 'verify_tls', true);
		$clientName = $this->readString($settings, 'client_name', gethostname() ?: 'localhost');

		if($host === '') {
			throw new RuntimeException('SMTP host is not configured.');
		}

		$transport = in_array($encryption, ['ssl', 'smtps'], true) ? 'ssl' : 'tcp';
		$context = stream_context_create([
			'ssl' => [
				'verify_peer' => $verifyTls,
				'verify_peer_name' => $verifyTls,
				'peer_name' => $host,
				'allow_self_signed' => !$verifyTls
			]
		]);
		$socket = @stream_socket_client(
			$transport . '://' . $host . ':' . $port,
			$errorNumber,
			$errorMessage,
			$timeout,
			STREAM_CLIENT_CONNECT,
			$context
		);

		if(!is_resource($socket)) {
			throw new RuntimeException('SMTP connection failed: ' . ($errorMessage !== '' ? $errorMessage : 'error ' . $errorNumber));
		}

		stream_set_timeout($socket, $timeout);

		try {
			$this->expect($socket, [220]);
			$this->command($socket, 'EHLO ' . $this->sanitizeCommand($clientName), [250]);

			if(in_array($encryption, ['tls', 'starttls'], true)) {
				$this->command($socket, 'STARTTLS', [220]);
				$crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
				if($crypto !== true) {
					throw new RuntimeException('SMTP STARTTLS negotiation failed.');
				}
				$this->command($socket, 'EHLO ' . $this->sanitizeCommand($clientName), [250]);
			}

			$auth = $this->readBool($settings, 'auth', $this->readBool($settings, 'smtp_auth', true));
			$username = $this->readString($settings, 'username', $this->readString($settings, 'smtp_username', ''));
			if($auth) {
				if($username === '') {
					throw new RuntimeException('SMTP authentication is enabled but no username is configured.');
				}
				$this->authenticate($socket, $settings, $username, $password);
			}

			$this->command($socket, 'MAIL FROM:<' . $this->sanitizeCommand($message['from']) . '>', [250]);
			$accepted = [];
			$rejected = [];

			foreach($message['recipients'] as $recipient) {
				try {
					$this->command($socket, 'RCPT TO:<' . $this->sanitizeCommand($recipient) . '>', [250, 251]);
					$accepted[] = $recipient;
				} catch(RuntimeException $exception) {
					$rejected[$recipient] = $exception->getMessage();
				}
			}

			if($accepted === []) {
				throw new RuntimeException('SMTP server rejected all recipients.');
			}

			$this->command($socket, 'DATA', [354]);
			$raw = preg_replace('/(?m)^\./', '..', str_replace(["\r\n", "\r"], "\n", $message['raw'])) ?? $message['raw'];
			$raw = str_replace("\n", "\r\n", $raw);
			fwrite($socket, rtrim($raw, "\r\n") . "\r\n.\r\n");
			$response = $this->expect($socket, [250]);

			try {
				$this->command($socket, 'QUIT', [221]);
			} catch(RuntimeException $exception) {
			}

			return [
				'host' => $host,
				'port' => $port,
				'encryption' => $encryption,
				'accepted_recipients' => $accepted,
				'rejected_recipients' => $rejected,
				'server_response' => $response
			];
		} finally {
			fclose($socket);
		}
	}

	private function authenticate($socket, array $settings, string $username, string $password): void {
		$mode = strtolower($this->readString($settings, 'auth_mode', 'login'));

		if($mode === 'plain') {
			$this->command($socket, 'AUTH PLAIN ' . base64_encode("\0" . $username . "\0" . $password), [235]);
			return;
		}

		$this->command($socket, 'AUTH LOGIN', [334]);
		$this->command($socket, base64_encode($username), [334]);
		$this->command($socket, base64_encode($password), [235]);
	}

	/**
	 * @param array<int,int> $expectedCodes
	 */
	private function command($socket, string $command, array $expectedCodes): string {
		fwrite($socket, $command . "\r\n");

		return $this->expect($socket, $expectedCodes);
	}

	/**
	 * @param array<int,int> $expectedCodes
	 */
	private function expect($socket, array $expectedCodes): string {
		$lines = [];
		$code = 0;

		for($index = 0; $index < 100; $index++) {
			$line = fgets($socket, 4096);
			if($line === false) {
				$metadata = stream_get_meta_data($socket);
				throw new RuntimeException(!empty($metadata['timed_out']) ? 'SMTP response timed out.' : 'SMTP connection closed unexpectedly.');
			}

			$line = rtrim($line, "\r\n");
			$lines[] = $line;
			if(preg_match('/^(\d{3})([ -])/', $line, $matches) !== 1) {
				continue;
			}

			$code = (int)$matches[1];
			if($matches[2] === ' ') {
				break;
			}
		}

		$response = implode("\n", $lines);
		if(!in_array($code, $expectedCodes, true)) {
			throw new RuntimeException('SMTP server returned ' . ($response !== '' ? $response : 'an invalid response.'));
		}

		return $response;
	}

	private function sanitizeCommand(string $value): string {
		return trim(str_replace(["\r", "\n"], '', $value));
	}

	private function readString(array $settings, string $key, string $default = ''): string {
		$value = $settings[$key] ?? $default;

		return is_scalar($value) || $value === null ? trim((string)$value) : $default;
	}

	private function readInt(array $settings, string $key, int $default): int {
		$value = $settings[$key] ?? $default;

		return is_numeric($value) ? (int)$value : $default;
	}

	private function readBool(array $settings, string $key, bool $default): bool {
		if(!array_key_exists($key, $settings)) {
			return $default;
		}

		$value = $settings[$key];
		if(is_bool($value)) {
			return $value;
		}

		return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on', 'enabled'], true);
	}
}
