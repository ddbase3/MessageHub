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

namespace Base3\MessageHub\Test\Display;

use Base3\CoreFoundation\Api\IAssetResolver;
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationDecision;
use Base3\CoreFoundation\Language\Api\ILanguage;
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\MessageHub\Display\MessageVariantAdminDisplay;
use Base3\MessagingFoundation\Api\IMessageIdGenerator;
use Base3\MessagingFoundation\Api\IMessageTemplateRepository;
use Base3\MessagingFoundation\Api\IMessageVariantRepository;
use Base3\MessagingFoundation\Dto\MessageTemplate;
use Base3\MessagingFoundation\Dto\MessageVariant;
use PHPUnit\Framework\TestCase;
use Base3\UiFoundation\Api\IRichTextEditorDisplay;

final class MessageVariantLanguagePathsTest extends TestCase {

	private function display(array $payload, IMessageVariantRepository $repository, ILanguage $language,
		?IMvcView $view = null, ?IRichTextEditorDisplay $editor = null, ?IMessageIdGenerator $ids = null,
		?IMessageTemplateRepository $templates = null, bool $allowed = true): MessageVariantAdminDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$auth = $this->createStub(IAuthorizationService::class);
		$auth->method('getDefaultAuthority')->willReturn('base3');
		$auth->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		if($ids === null) {
			$ids = $this->createStub(IMessageIdGenerator::class);
			$ids->method('createId')->willReturn('var-generated');
		}
		return new MessageVariantAdminDisplay($request, $view ?? $this->createStub(IMvcView::class),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$repository, $templates ?? $this->createStub(IMessageTemplateRepository::class), $ids,
			$editor ?? $this->createStub(IRichTextEditorDisplay::class), $language, $auth);
	}

	private function language(string $current, array $known): ILanguage {
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn($current);
		$language->method('getLanguages')->willReturn($known);
		return $language;
	}

	public function testSaveDefaultsToCurrentLanguageAndEmptyOptionalFields(): void {
		$repository = $this->createMock(IMessageVariantRepository::class);
		$repository->expects(self::once())->method('save')->with(self::callback(function(MessageVariant $variant): bool {
			self::assertSame('de', $variant->getLanguage());
			self::assertSame('', $variant->getBodyText());
			self::assertSame('', $variant->getBodyHtml());
			self::assertTrue($variant->isEnabled());
			return true;
		}));
		$result = json_decode($this->display(['mode' => 'save', 'template_id' => 't1', 'subject' => 'S'], $repository,
			$this->language('de', ['de', 'en']))->getOutput('json'), true);
		self::assertTrue($result['ok']);
		self::assertSame('var-generated', $result['id']);
	}

	public function testSaveDefaultsToEnglishWithoutAvailableLanguages(): void {
		$repository = $this->createMock(IMessageVariantRepository::class);
		$repository->expects(self::once())->method('save')->with(self::callback(function(MessageVariant $variant): bool {
			self::assertSame('en', $variant->getLanguage());
			return true;
		}));
		$result = json_decode($this->display(['mode' => 'save', 'template_id' => 't1', 'subject' => 'S'], $repository,
			$this->language('', []))->getOutput('json'), true);
		self::assertTrue($result['ok']);
	}

	public function testHtmlAddsCurrentLanguageBeforeKnownLanguagesAndDeduplicates(): void {
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void { $assigned[$key] = $value; });
		$view->method('loadTemplate')->willReturn('variant html');
		$editor = $this->createMock(IRichTextEditorDisplay::class);
		$editor->expects(self::once())->method('setData')->with(self::callback(function(array $data): bool {
			self::assertSame('Message body (HTML)', $data['aria_label']);
			self::assertSame('messagehub-variant-body-html', $data['id']);
			return true;
		}));
		$editor->method('getOutput')->willReturn('<textarea></textarea>');
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('listAll')->willReturn([new MessageTemplate('t1', 'notice', 'Notice')]);
		$view->method('getBricks')->willReturnCallback(static fn(string $key): array => $key === 'message_variant_admin_display'
			? ['html_body' => 'Message body (HTML)'] : []);
		self::assertSame('variant html', $this->display([], $this->createStub(IMessageVariantRepository::class),
			$this->language('it', ['', 'en', 'en', ' de ', 'it']), $view, $editor, null, $templates)->getOutput());
		self::assertSame('it', $assigned['selectedLanguage']);
		self::assertSame([['value' => 'en', 'label' => 'en'], ['value' => 'de', 'label' => 'de'],
			['value' => 'it', 'label' => 'it']], $assigned['languageOptions']);
		self::assertSame([['value' => 't1', 'label' => 'notice - Notice']], $assigned['templateOptions']);
		self::assertSame('<textarea></textarea>', $assigned['bodyHtmlEditor']);
	}

	public function testHtmlWithNoLanguagesKeepsEnglishSelection(): void {
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void { $assigned[$key] = $value; });
		$view->method('loadTemplate')->willReturn('html');
		self::assertSame('html', $this->display([], $this->createStub(IMessageVariantRepository::class),
			$this->language('', []), $view)->getOutput());
		self::assertSame('en', $assigned['selectedLanguage']);
		self::assertSame([], $assigned['languageOptions']);
	}

	public function testRepositoryFailureDuringSaveIsReportedWithoutSuccess(): void {
		$repository = $this->createStub(IMessageVariantRepository::class);
		$repository->method('save')->willThrowException(new \RuntimeException('variant write failed'));
		$result = json_decode($this->display(['mode' => 'save', 'id' => 'v1', 'template_id' => 't1',
			'subject' => 'Hello'], $repository, $this->language('en', ['en']))->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertSame('variant write failed', $result['error']);
	}

	public function testFallbackCanBeEnabledOnlyForEnabledVariant(): void {
		$repository = $this->createMock(IMessageVariantRepository::class);
		$repository->expects(self::once())->method('save')->with(self::callback(function(MessageVariant $variant): bool {
			self::assertTrue($variant->isEnabled());
			return true;
		}));
		self::assertTrue(json_decode($this->display(['mode' => 'save', 'template_id' => 't1', 'subject' => 'S',
			'enabled' => '1', 'fallback' => '1'], $repository, $this->language('en', ['en']))->getOutput('json'), true)['ok']);
	}

	public function testUnauthorizedSaveNeverGeneratesIdOrWritesVariant(): void {
		$ids = $this->createMock(IMessageIdGenerator::class);
		$ids->expects(self::never())->method('createId');
		$repository = $this->createMock(IMessageVariantRepository::class);
		$repository->expects(self::never())->method('save');
		try {
			self::assertSame('', $this->display(['mode' => 'save', 'template_id' => 't1', 'subject' => 'S'],
				$repository, $this->language('en', ['en']), null, null, $ids, null, false)->getOutput('json'));
			self::assertSame(403, http_response_code());
		} finally { http_response_code(200); }
	}

	public function testEmptyCurrentLanguageUsesFirstSupportedLanguageInHtml(): void {
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void {
			$assigned[$key] = $value;
		});
		$view->method('loadTemplate')->willReturn('variant settings');
		$display = $this->display([], $this->createStub(IMessageVariantRepository::class),
			$this->language(' ', [' ', 'nl', 'de', 'nl']), $view);
		self::assertSame('variant settings', $display->getOutput('HTML'));
		self::assertSame('nl', $assigned['selectedLanguage']);
		self::assertSame([['value' => 'nl', 'label' => 'nl'], ['value' => 'de', 'label' => 'de']], $assigned['languageOptions']);
	}

	public function testDisabledNonFallbackVariantCanBeSavedAndFinalJsonIsValid(): void {
		$repository = $this->createMock(IMessageVariantRepository::class);
		$repository->expects(self::once())->method('save')->with(self::callback(function(MessageVariant $variant): bool {
			self::assertFalse($variant->isEnabled());
			self::assertSame('nl', $variant->getLanguage());
			return true;
		}));
		$response = json_decode($this->display(['mode' => 'save', 'template_id' => 't1', 'subject' => 'Alert',
			'enabled' => '0', 'fallback' => '0'], $repository, $this->language(' ', ['nl']))->getOutput('json', true), true);
		self::assertTrue($response['ok']);
	}

}
