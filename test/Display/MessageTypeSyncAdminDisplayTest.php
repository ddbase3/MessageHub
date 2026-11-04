<?php declare(strict_types=1);

namespace MessageHub\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Language\Api\ILanguage;
use Base3\LinkTarget\Api\ILinkTargetService;
use MessageHub\Display\MessageTypeSyncAdminDisplay;
use MessagingFoundation\Api\IMessageTypeSynchronizationService;
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
