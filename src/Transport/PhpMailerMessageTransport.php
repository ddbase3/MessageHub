<?php declare(strict_types=1);

namespace MessageHub\Transport;

use MessageHub\Transport\Support\AbstractMessageTransport;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Dto\MessageAddress;
use MessagingFoundation\Dto\MessageAttachment;
use MessagingFoundation\Dto\MessageDeliveryResult;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Throwable;

final class PhpMailerMessageTransport extends AbstractMessageTransport implements IMessageTransport {

	public static function getName(): string {
		return 'phpmailer';
	}

	public function getLabel(): string {
		return 'PHPMailer';
	}

	public function getSettingsSummary(array $settings = []): string {
		$mode = strtolower($this->readString($settings, 'mode', 'mail'));
		$summary = [
			$mode === 'smtp' ? 'SMTP' : 'PHP mail'
		];
		$fromAddress = $this->readString($settings, 'from_address', '');
		$fromName = $this->readString($settings, 'from_name', '');

		if($fromAddress !== '') {
			$summary[] = 'From: ' . ($fromName !== '' ? $fromName . ' <' . $fromAddress . '>' : $fromAddress);
		}

		if($mode === 'smtp') {
			$host = $this->readString($settings, 'smtp_host', '');
			$port = $this->readInt($settings, 'smtp_port', 587);
			$secure = strtolower($this->readString($settings, 'smtp_secure', 'tls'));
			$summary[] = $host !== '' ? 'Server: ' . $host . ':' . $port : 'Server: not configured';
			$summary[] = 'Encryption: ' . ($secure !== '' ? strtoupper($secure === 'tls' ? 'STARTTLS' : $secure) : 'None');
			$summary[] = 'Authentication: ' . ($this->readBool($settings, 'smtp_auth', true) ? 'enabled' : 'disabled');
			$summary[] = 'Password: ' . ($this->hasConfiguredValue($settings['smtp_password'] ?? null) ? 'configured' : 'not configured');
		}

		return $this->createSummary($summary);
	}

	public function supports(Message $message, array $settings = []): bool {
		return class_exists(PHPMailer::class);
	}

	public function send(Message $message, array $settings = []): MessageDeliveryResult {
		if(!$this->isEnabled($settings)) {
			return $this->failure('PHPMailer transport is disabled.');
		}

		if(!class_exists(PHPMailer::class)) {
			return $this->failure('PHPMailer is not available in the current runtime.');
		}

		try {
			$mail = $this->createMailer($settings);
			$this->applySender($mail, $message, $settings);
			$this->applyRecipients($mail, $message->getRecipients());
			$this->applyBody($mail, $message);
			$this->applyAttachments($mail, $message->getAttachments());
			$mail->send();

			return $this->success('Message sent by PHPMailer.', '', [
				'transport' => self::getName(),
				'mode' => $this->readString($settings, 'mode', 'mail')
			]);
		} catch(PHPMailerException $exception) {
			return $this->failureFromException('PHPMailer', $exception, ['transport' => self::getName()]);
		} catch(Throwable $exception) {
			return $this->failureFromException('PHPMailer', $exception, ['transport' => self::getName()]);
		}
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'enabled' => ['type' => 'boolean', 'default' => false],
				'mode' => ['type' => 'string', 'enum' => ['mail', 'smtp']],
				'from_address' => ['type' => 'string'],
				'from_name' => ['type' => 'string'],
				'reply_to_address' => ['type' => 'string'],
				'reply_to_name' => ['type' => 'string'],
				'smtp_host' => ['type' => 'string'],
				'smtp_port' => ['type' => 'integer'],
				'smtp_auth' => ['type' => 'boolean'],
				'smtp_username' => ['type' => 'string'],
				'smtp_password' => ['description' => 'ConfigValue definition or fixed secret'],
				'smtp_secure' => ['type' => 'string', 'enum' => ['', 'tls', 'ssl', 'starttls', 'smtps']],
				'debug' => ['type' => 'boolean']
			]
		];
	}

	private function createMailer(array $settings): PHPMailer {
		$mail = new PHPMailer(true);
		$mail->CharSet = 'UTF-8';
		$mail->Encoding = 'base64';

		if(strtolower($this->readString($settings, 'mode', 'mail')) === 'smtp') {
			$mail->isSMTP();
			$mail->SMTPDebug = $this->readBool($settings, 'debug', false) ? SMTP::DEBUG_SERVER : SMTP::DEBUG_OFF;
			$mail->Host = $this->readString($settings, 'smtp_host', '');
			$mail->Port = $this->readInt($settings, 'smtp_port', 587);
			$mail->SMTPAuth = $this->readBool($settings, 'smtp_auth', true);
			$mail->Username = $this->readString($settings, 'smtp_username', '');
			$mail->Password = $this->resolveString($settings['smtp_password'] ?? '');
			$secure = strtolower($this->readString($settings, 'smtp_secure', 'tls'));

			if($secure === 'tls' || $secure === 'starttls') {
				$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
			} elseif($secure === 'ssl' || $secure === 'smtps') {
				$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
			}

			return $mail;
		}

		$mail->isMail();
		return $mail;
	}

	private function applySender(PHPMailer $mail, Message $message, array $settings): void {
		$fromAddress = $message->getFromAddress() !== '' ? $message->getFromAddress() : $this->readString($settings, 'from_address', '');
		$fromName = $message->getFromName() !== '' ? $message->getFromName() : $this->readString($settings, 'from_name', '');

		if($fromAddress === '') {
			throw new \InvalidArgumentException('PHPMailer transport needs a from_address setting or message sender address.');
		}

		$mail->setFrom($fromAddress, $fromName);
		$replyToAddress = $message->getReplyToAddress() !== '' ? $message->getReplyToAddress() : $this->readString($settings, 'reply_to_address', '');
		$replyToName = $message->getReplyToName() !== '' ? $message->getReplyToName() : $this->readString($settings, 'reply_to_name', '');

		if($replyToAddress !== '') {
			$mail->addReplyTo($replyToAddress, $replyToName);
		}
	}

	/**
	 * @param array<int,MessageAddress> $recipients
	 */
	private function applyRecipients(PHPMailer $mail, array $recipients): void {
		$count = 0;
		foreach($recipients as $recipient) {
			if(!$recipient instanceof MessageAddress || trim($recipient->getAddress()) === '') {
				continue;
			}

			$type = strtolower($recipient->getType());
			if($type === 'cc') {
				$mail->addCC($recipient->getAddress(), $recipient->getName());
			} elseif($type === 'bcc') {
				$mail->addBCC($recipient->getAddress(), $recipient->getName());
			} else {
				$mail->addAddress($recipient->getAddress(), $recipient->getName());
			}
			$count++;
		}

		if($count === 0) {
			throw new \InvalidArgumentException('PHPMailer transport needs at least one recipient.');
		}
	}

	private function applyBody(PHPMailer $mail, Message $message): void {
		$mail->Subject = $message->getSubject();
		if($message->getBodyHtml() !== '') {
			$mail->isHTML(true);
			$mail->Body = $message->getBodyHtml();
			$mail->AltBody = $message->getBodyText() !== '' ? $message->getBodyText() : trim(strip_tags($message->getBodyHtml()));
			return;
		}

		$mail->Body = $message->getBodyText();
	}

	/**
	 * @param array<int,MessageAttachment> $attachments
	 */
	private function applyAttachments(PHPMailer $mail, array $attachments): void {
		foreach($attachments as $attachment) {
			if(!$attachment instanceof MessageAttachment) {
				continue;
			}

			$path = $attachment->getPath();
			if(!is_file($path) || !is_readable($path)) {
				throw new \InvalidArgumentException('Attachment is not readable: ' . $path);
			}

			$name = $attachment->getName() !== '' ? $attachment->getName() : basename($path);
			if($attachment->isInline()) {
				$contentId = $attachment->getContentId() !== '' ? $attachment->getContentId() : sha1($path);
				$mail->addEmbeddedImage($path, $contentId, $name);
			} else {
				$mail->addAttachment($path, $name);
			}
		}
	}
}
