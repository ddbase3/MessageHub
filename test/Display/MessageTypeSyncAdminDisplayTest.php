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

final class MessageTypeSyncAdminDisplayTest extends TestCase {

	private function display(array $payload, IMessageTypeSynchronizationService $service, bool $allowed = true): MessageTypeSyncAdminDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn('de');
		$language->method('getLanguages')->willReturn(['de', 'fr']);
		$auth = $this->createStub(IAuthorizationService::class);
		$auth->method('getDefaultAuthority')->willReturn('base3');
		$auth->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		return new MessageTypeSyncAdminDisplay($request, $this->createStub(IMvcView::class),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$service, $language, $auth);
	}

	public function testProviderListingUsesSynchronizationService(): void {
		$service = $this->createMock(IMessageTypeSynchronizationService::class);
		$service->expects(self::once())->method('getProviderSummaries')->willReturn([['name' => 'notice']]);
		$result = json_decode($this->display(['mode' => 'providers'], $service)->getOutput('json'), true);
		self::assertTrue($result['ok']);
		self::assertSame('notice', $result['providers'][0]['name']);
	}

	public function testSyncAllUsesNormalizedLanguage(): void {
		$service = $this->createMock(IMessageTypeSynchronizationService::class);
		$service->expects(self::once())->method('syncAll')->with('fr')->willReturn(['ok' => true, 'created_templates' => 2]);
		$result = json_decode($this->display(['mode' => 'sync-all', 'language' => ' fr '], $service)->getOutput('json'), true);
		self::assertSame(2, $result['created_templates']);
	}

	public function testSyncOnePassesExactTypeNameToOwner(): void {
		$service = $this->createMock(IMessageTypeSynchronizationService::class);
		$service->expects(self::once())->method('syncOne')->with('notice', 'de')->willReturn(['ok' => true]);
		self::assertTrue(json_decode($this->display(['mode' => 'sync-one', 'type_name' => 'notice'], $service)->getOutput('json'), true)['ok']);
	}

	public function testForbiddenSynchronizationDoesNotInvokeAnyService(): void {
		$service = $this->createMock(IMessageTypeSynchronizationService::class);
		$service->expects(self::never())->method('syncAll');
		try {
			self::assertSame('', $this->display(['mode' => 'sync-all'], $service, false)->getOutput('json'));
			self::assertSame(403, http_response_code());
		} finally { http_response_code(200); }
	}
}
