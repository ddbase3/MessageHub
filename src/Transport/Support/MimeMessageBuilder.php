<?php declare(strict_types=1);

namespace MessageHub\Transport\Support;

use InvalidArgumentException;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageAddress;
use MessagingFoundation\Dto\MessageAttachment;

final class MimeMessageBuilder {

	/**
	 * @return array{from:string,recipients:array<int,string>,headers:string,body:string,raw:string}
	 */
	public function build(Message $message, array $settings = []): array {
		$fromAddress = trim($message->getFromAddress()) !== ''
			? trim($message->getFromAddress())
			: $this->readString($settings, 'from_address', '');
		$fromName = trim($message->getFromName()) !== ''
			? trim($message->getFromName())
			: $this->readString($settings, 'from_name', '');

		if($fromAddress === '') {
			throw new InvalidArgumentException('The transport needs a from_address setting or message sender address.');
		}

		$to = [];
		$cc = [];
		$bcc = [];
		$recipients = [];

		foreach($message->getRecipients() as $recipient) {
			if(!$recipient instanceof MessageAddress) {
				continue;
			}

			$address = trim($recipient->getAddress());
			if($address === '') {
				continue;
			}

			$formatted = $this->formatAddress($address, $recipient->getName());
			$type = strtolower(trim($recipient->getType()));
			if($type === 'cc') {
				$cc[] = $formatted;
			} elseif($type === 'bcc') {
				$bcc[] = $formatted;
			} else {
				$to[] = $formatted;
			}

			$recipients[] = $address;
		}

		$recipients = array_values(array_unique($recipients));
		if($recipients === []) {
			throw new InvalidArgumentException('The transport needs at least one recipient.');
		}

		$headers = [
			'Date: ' . date(DATE_RFC2822),
			'From: ' . $this->formatAddress($fromAddress, $fromName),
			'Subject: ' . $this->encodeHeader($message->getSubject()),
			'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->messageIdHost($fromAddress) . '>',
			'MIME-Version: 1.0'
		];

		if($to !== []) {
			$headers[] = 'To: ' . implode(', ', $to);
		}

		if($cc !== []) {
			$headers[] = 'Cc: ' . implode(', ', $cc);
		}

		$replyToAddress = trim($message->getReplyToAddress()) !== ''
			? trim($message->getReplyToAddress())
			: $this->readString($settings, 'reply_to_address', '');
		$replyToName = trim($message->getReplyToName()) !== ''
			? trim($message->getReplyToName())
			: $this->readString($settings, 'reply_to_name', '');

		if($replyToAddress !== '') {
			$headers[] = 'Reply-To: ' . $this->formatAddress($replyToAddress, $replyToName);
		}

		$body = $this->buildBody($message, $headers);
		$headerText = implode("\r\n", $headers);

		return [
			'from' => $fromAddress,
			'recipients' => $recipients,
			'headers' => $headerText,
			'body' => $body,
			'raw' => $headerText . "\r\n\r\n" . $body
		];
	}

	/**
	 * @param array<int,string> $headers
	 */
	private function buildBody(Message $message, array &$headers): string {
		$text = trim($message->getBodyText());
		$html = trim($message->getBodyHtml());
		if($text === '' && $html !== '') {
			$text = trim(strip_tags($html));
		}

		$attachments = array_values(array_filter(
			$message->getAttachments(),
			fn(mixed $attachment): bool => $attachment instanceof MessageAttachment
		));

		if($attachments === [] && $html === '') {
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';
			$headers[] = 'Content-Transfer-Encoding: base64';
			return $this->encodeBody($text);
		}

		if($attachments === []) {
			$boundary = $this->boundary('alternative');
			$headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
			return $this->buildAlternative($text, $html, $boundary);
		}

		$mixedBoundary = $this->boundary('mixed');
		$headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixedBoundary . '"';
		$parts = [];

		if($html !== '') {
			$alternativeBoundary = $this->boundary('alternative');
			$parts[] = '--' . $mixedBoundary . "\r\n"
				. 'Content-Type: multipart/alternative; boundary="' . $alternativeBoundary . '"' . "\r\n\r\n"
				. $this->buildAlternative($text, $html, $alternativeBoundary);
		} else {
			$parts[] = '--' . $mixedBoundary . "\r\n"
				. 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
				. 'Content-Transfer-Encoding: base64' . "\r\n\r\n"
				. $this->encodeBody($text);
		}

		foreach($attachments as $attachment) {
			$path = $attachment->getPath();
			if(!is_file($path) || !is_readable($path)) {
				throw new InvalidArgumentException('Attachment is not readable: ' . $path);
			}

			$content = file_get_contents($path);
			if($content === false) {
				throw new InvalidArgumentException('Attachment could not be read: ' . $path);
			}

			$name = $attachment->getName() !== '' ? $attachment->getName() : basename($path);
			$mimeType = $attachment->getMimeType() !== '' ? $attachment->getMimeType() : 'application/octet-stream';
			$disposition = $attachment->isInline() ? 'inline' : 'attachment';
			$attachmentHeaders = [
				'Content-Type: ' . $this->sanitizeHeader($mimeType) . '; name="' . $this->escapeQuoted($name) . '"',
				'Content-Transfer-Encoding: base64',
				'Content-Disposition: ' . $disposition . '; filename="' . $this->escapeQuoted($name) . '"'
			];

			if($attachment->isInline()) {
				$contentId = $attachment->getContentId() !== '' ? $attachment->getContentId() : sha1($path);
				$attachmentHeaders[] = 'Content-ID: <' . $this->sanitizeHeader($contentId) . '>';
			}

			$parts[] = '--' . $mixedBoundary . "\r\n"
				. implode("\r\n", $attachmentHeaders) . "\r\n\r\n"
				. chunk_split(base64_encode($content), 76, "\r\n");
		}

		$parts[] = '--' . $mixedBoundary . '--';

		return implode("\r\n", $parts);
	}

	private function buildAlternative(string $text, string $html, string $boundary): string {
		return '--' . $boundary . "\r\n"
			. 'Content-Type: text/plain; charset=UTF-8' . "\r\n"
			. 'Content-Transfer-Encoding: base64' . "\r\n\r\n"
			. $this->encodeBody($text) . "\r\n"
			. '--' . $boundary . "\r\n"
			. 'Content-Type: text/html; charset=UTF-8' . "\r\n"
			. 'Content-Transfer-Encoding: base64' . "\r\n\r\n"
			. $this->encodeBody($html) . "\r\n"
			. '--' . $boundary . '--';
	}

	private function encodeBody(string $value): string {
		return chunk_split(base64_encode($value), 76, "\r\n");
	}

	private function formatAddress(string $address, string $name = ''): string {
		$address = $this->sanitizeHeader($address);
		$name = trim($name);

		return $name !== '' ? $this->encodeHeader($name) . ' <' . $address . '>' : $address;
	}

	private function encodeHeader(string $value): string {
		$value = $this->sanitizeHeader($value);
		if(function_exists('mb_encode_mimeheader')) {
			return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
		}

		return $value;
	}

	private function sanitizeHeader(string $value): string {
		return trim(str_replace(["\r", "\n"], '', $value));
	}

	private function escapeQuoted(string $value): string {
		return str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', ''], $value);
	}

	private function boundary(string $prefix): string {
		return '=_MessageHub_' . $prefix . '_' . bin2hex(random_bytes(12));
	}

	private function messageIdHost(string $fromAddress): string {
		$separator = strrpos($fromAddress, '@');
		$host = $separator === false ? '' : substr($fromAddress, $separator + 1);
		$host = preg_replace('/[^a-zA-Z0-9.-]/', '', $host) ?: '';

		return $host !== '' ? $host : 'localhost';
	}

	private function readString(array $settings, string $key, string $default = ''): string {
		$value = $settings[$key] ?? $default;

		return is_scalar($value) || $value === null ? trim((string)$value) : $default;
	}
}
