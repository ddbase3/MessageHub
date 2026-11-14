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
use Base3\MessagingFoundation\Dto\MessageVariant;
use PHPUnit\Framework\TestCase;
use Base3\UiFoundation\Api\IRichTextEditorDisplay;

final class MessageVariantAdminDisplayTest extends TestCase {

	private function display(array $payload, IMessageVariantRepository $repository, bool $allowed = true): MessageVariantAdminDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn('en');
		$auth = $this->createStub(IAuthorizationService::class);
		$auth->method('getDefaultAuthority')->willReturn('base3');
		$auth->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		$ids = $this->createStub(IMessageIdGenerator::class);
		$ids->method('createId')->willReturn('var-new');
		return new MessageVariantAdminDisplay($request, $this->createStub(IMvcView::class),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$repository, $this->createStub(IMessageTemplateRepository::class), $ids,
			$this->createStub(IRichTextEditorDisplay::class), $language, $auth);
	}

	public function testDisabledFallbackIsRejectedBeforePersistence(): void {
		$repository = $this->createMock(IMessageVariantRepository::class);
		$repository->expects(self::never())->method('save');
		$result = json_decode($this->display(['mode' => 'save', 'template_id' => 't1', 'subject' => 'S',
			'fallback' => '1', 'enabled' => '0'], $repository)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertStringContainsString('must be enabled', $result['error']);
	}

	public function testNewVariantUsesGeneratedIdAndTrimsInput(): void {
		$repository = $this->createMock(IMessageVariantRepository::class);
		$repository->expects(self::once())->method('save')->with(self::callback(function(MessageVariant $variant): bool {
			self::assertSame('var-new', $variant->getId());
			self::assertSame('t1', $variant->getTemplateId());
			self::assertSame('Hello', $variant->getSubject());
			return true;
		}));
		$result = json_decode($this->display(['mode' => 'save', 'template_id' => ' t1 ', 'subject' => ' Hello ', 'language' => 'en'], $repository)->getOutput('json'), true);
		self::assertTrue($result['ok']);
		self::assertSame('var-new', $result['id']);
	}

	public function testDeleteAndForbiddenWriteHaveDistinctBehaviors(): void {
		$repository = $this->createMock(IMessageVariantRepository::class);
		$repository->expects(self::once())->method('delete')->with('v1');
		self::assertTrue(json_decode($this->display(['mode' => 'delete', 'id' => 'v1'], $repository)->getOutput('json'), true)['ok']);
		try {
			self::assertSame('', $this->display(['mode' => 'delete', 'id' => 'v2'], $repository, false)->getOutput('json'));
			self::assertSame(403, http_response_code());
		} finally { http_response_code(200); }
	}


	public function testUpdatingExistingVariantUsesSuppliedIdAndFlags(): void {
		$repository = $this->createMock(IMessageVariantRepository::class);
		$repository->expects(self::once())->method('save')->with(self::callback(function(MessageVariant $variant): bool {
			self::assertSame('var-old', $variant->getId());
			self::assertSame('template-1', $variant->getTemplateId());
			self::assertSame('de', $variant->getLanguage());
			self::assertSame('<b>Hallo</b>', $variant->getBodyHtml());
			self::assertTrue($variant->isEnabled());
			return true;
		}));
		$result = json_decode($this->display(['mode' => 'save', 'id' => ' var-old ', 'template_id' => ' template-1 ',
			'language' => ' de ', 'subject' => 'Subject', 'body_text' => 'Text', 'body_html' => '<b>Hallo</b>',
			'fallback' => '1', 'enabled' => '1'], $repository)->getOutput('json'), true);
		self::assertTrue($result['ok']);
		self::assertSame('var-old', $result['id']);
	}

	public function testExceptionFromVariantRepositoryIsReportedAsJsonFailure(): void {
		$repository = $this->createStub(IMessageVariantRepository::class);
		$repository->method('delete')->willReturnCallback(static function(): void {
			throw new \RuntimeException('cannot delete');
		});
		$result = json_decode($this->display(['mode' => 'delete', 'id' => 'v1'], $repository)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertSame('cannot delete', $result['error']);
	}

}
