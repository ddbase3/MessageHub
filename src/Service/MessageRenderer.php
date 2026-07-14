<?php declare(strict_types=1);

namespace MessageHub\Service;

use MessagingFoundation\Api\IMessageRenderer;
use MessagingFoundation\Api\IMessageTemplateRepository;
use MessagingFoundation\Api\IMessageVariantRepository;
use MessagingFoundation\Dto\Message;
use MessagingFoundation\Exception\MessageException;

final class MessageRenderer implements IMessageRenderer {

	public function __construct(
		private readonly IMessageTemplateRepository $templateRepository,
		private readonly IMessageVariantRepository $variantRepository
	) {}

	public function render(string $typeName, string $language, array $context = [], string $transportName = ''): Message {
		$template = $this->templateRepository->getByType($typeName);

		if($template === null || !$template->isEnabled()) {
			throw new MessageException('Message template not found or disabled: ' . $typeName);
		}

		$requestedLanguage = trim($language);
		$variant = $this->variantRepository->getForTemplate($template->getId(), $requestedLanguage);
		$fallbackUsed = false;

		if($variant === null) {
			$variant = $this->variantRepository->getFallbackForTemplate($template->getId());
			$fallbackUsed = $variant !== null;
		}

		if($variant === null || !$variant->isEnabled()) {
			throw new MessageException('Message variant and fallback variant not found or disabled for template: ' . $typeName);
		}

		$subject = $this->replacePlaceholders($variant->getSubject(), $context);
		$bodyText = $this->replacePlaceholders($variant->getBodyText(), $context);
		$bodyHtml = $this->replacePlaceholders($variant->getBodyHtml(), $context);

		return new Message($typeName, $subject, $bodyText, $bodyHtml, [], [], '', '', '', '', [
			'template_id' => $template->getId(),
			'variant_id' => $variant->getId(),
			'language' => $variant->getLanguage(),
			'requested_language' => $requestedLanguage,
			'fallback_used' => $fallbackUsed,
			'transport_name' => $transportName !== '' ? $transportName : $template->getDefaultTransport()
		]);
	}

	private function replacePlaceholders(string $text, array $context): string {
		foreach($context as $key => $value) {
			if(is_scalar($value) || $value === null) {
				$text = str_replace('{{' . $key . '}}', (string)$value, $text);
			}
		}

		return $text;
	}
}
