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
use Base3\MessageHub\Display\MessageTypeSyncAdminDisplay;
use Base3\MessagingFoundation\Api\IMessageTypeSynchronizationService;
use PHPUnit\Framework\TestCase;

final class MessageTypeSyncLanguagePathsTest extends TestCase {

	private function display(array $payload, IMessageTypeSynchronizationService $service, ?ILanguage $language = null,
		?IMvcView $view = null): MessageTypeSyncAdminDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$auth = $this->createStub(IAuthorizationService::class);
		$auth->method('getDefaultAuthority')->willReturn('base3');
		$auth->method('decide')->willReturn(AuthorizationDecision::allow());
		if($language === null) {
			$language = $this->createStub(ILanguage::class);
			$language->method('getLanguage')->willReturn('de');
			$language->method('getLanguages')->willReturn(['de', 'fr']);
		}
		return new MessageTypeSyncAdminDisplay($request, $view ?? $this->createStub(IMvcView::class),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class), $service, $language, $auth);
	}

	public function testUnsupportedLanguageFallsBackToCurrentLanguageBeforeSynchronization(): void {
		$service = $this->createMock(IMessageTypeSynchronizationService::class);
		$service->expects(self::once())->method('syncOne')->with('notice', 'de')->willReturn(['ok' => true]);
		$result = json_decode($this->display(['mode' => 'sync-one', 'type_name' => 'notice', 'language' => 'unknown'], $service)->getOutput('json'), true);
		self::assertTrue($result['ok']);
	}

	public function testEmptyLanguageUsesCurrentLanguageForSyncAll(): void {
		$service = $this->createMock(IMessageTypeSynchronizationService::class);
		$service->expects(self::once())->method('syncAll')->with('de')->willReturn(['ok' => true]);
		self::assertTrue(json_decode($this->display(['mode' => 'sync-all', 'language' => '  '], $service)->getOutput('json'), true)['ok']);
	}

	public function testEmptyLanguageCatalogDefaultsToEnglish(): void {
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn('');
		$language->method('getLanguages')->willReturn([]);
		$service = $this->createMock(IMessageTypeSynchronizationService::class);
		$service->expects(self::once())->method('syncAll')->with('en')->willReturn(['ok' => true]);
		self::assertTrue(json_decode($this->display(['mode' => 'sync-all', 'language' => 'invalid'], $service, $language)->getOutput('json'), true)['ok']);
	}

	public function testSyncExceptionReturnsGenericErrorWithDetailedReason(): void {
		$service = $this->createStub(IMessageTypeSynchronizationService::class);
		$service->method('syncOne')->willThrowException(new \RuntimeException('provider backend unavailable'));
		$result = json_decode($this->display(['mode' => 'sync-one', 'type_name' => 'notice'], $service)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertSame('Message type synchronization request failed.', $result['error']);
		self::assertSame('provider backend unavailable', $result['details']);
	}

	public function testProviderDiscoveryFailureIsHandledAsJsonError(): void {
		$service = $this->createStub(IMessageTypeSynchronizationService::class);
		$service->method('getProviderSummaries')->willThrowException(new \RuntimeException('discovery failed'));
		$result = json_decode($this->display(['mode' => 'providers'], $service)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertSame('discovery failed', $result['details']);
	}

	public function testHtmlNormalizesDuplicateLanguagesAndIncludesCurrentLanguage(): void {
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn(' it ');
		$language->method('getLanguages')->willReturn(['', 'fr', 'fr', 'de', '  ']);
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('loadTemplate')->willReturn('sync html');
		$view->method('assign')->willReturnCallback(static function(string $name, mixed $value) use (&$assigned): void {
			$assigned[$name] = $value;
		});
		$service = $this->createStub(IMessageTypeSynchronizationService::class);
		$service->method('getProviderSummaries')->willReturn([['name' => 'notice']]);
		self::assertSame('sync html', $this->display([], $service, $language, $view)->getOutput());
		self::assertSame('it', $assigned['selectedLanguage']);
		self::assertSame([['value' => 'it', 'label' => 'it'], ['value' => 'fr', 'label' => 'fr'],
			['value' => 'de', 'label' => 'de']], $assigned['languageOptions']);
		self::assertSame([['name' => 'notice']], $assigned['providers']);
	}

	public function testEmptyCurrentLanguageSelectsFirstAvailableLanguage(): void {
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn('   ');
		$language->method('getLanguages')->willReturn([' ', 'fr', 'de', 'fr']);
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void {
			$assigned[$key] = $value;
		});
		$view->method('loadTemplate')->willReturn('sync screen');
		$service = $this->createMock(IMessageTypeSynchronizationService::class);
		$service->expects(self::once())->method('syncAll')->with('fr')->willReturn(['ok' => true]);
		self::assertSame('sync screen', $this->display([], $service, $language, $view)->getOutput());
		self::assertSame('fr', $assigned['selectedLanguage']);
		self::assertSame([['value' => 'fr', 'label' => 'fr'], ['value' => 'de', 'label' => 'de']], $assigned['languageOptions']);
		$response = json_decode($this->display(['mode' => 'sync-all', 'language' => 'unknown'], $service, $language)->getOutput('JSON'), true);
		self::assertTrue($response['ok']);
	}

	public function testFinalJsonResponsePreservesProviderMetadata(): void {
		$service = $this->createStub(IMessageTypeSynchronizationService::class);
		$service->method('getProviderSummaries')->willReturn([['name' => 'notice', 'count' => 2]]);
		$response = json_decode($this->display(['mode' => 'providers'], $service)->getOutput('JSON', true), true);
		self::assertSame('providers', $response['mode']);
		self::assertSame([['name' => 'notice', 'count' => 2]], $response['providers']);
	}

}
