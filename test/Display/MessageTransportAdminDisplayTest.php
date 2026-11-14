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
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MessageHub\Display\MessageTransportAdminDisplay;
use Base3\MessageHub\Transport\NullMessageTransport;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;
use PHPUnit\Framework\TestCase;

final class MessageTransportAdminDisplayTest extends TestCase {

	private function display(array $payload, ISettingsStore $settings, bool $allowed = true): MessageTransportAdminDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransport')->willReturnCallback(static fn(string $name): ?\Base3\MessagingFoundation\Api\IMessageTransport => $name === 'null' ? new NullMessageTransport() : null);
		$registry->method('getTransports')->willReturn(['null' => new NullMessageTransport()]);
		$registry->method('getDefaultTransportName')->willReturn('null');
		$auth = $this->createStub(IAuthorizationService::class);
		$auth->method('getDefaultAuthority')->willReturn('base3');
		$auth->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		return new MessageTransportAdminDisplay($request, $this->createStub(IMvcView::class), $this->createStub(IAssetResolver::class),
			$this->createStub(ILinkTargetService::class), $registry, $settings, $auth);
	}

	public function testInvalidTransportCannotBeConfigured(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects(self::never())->method('save');
		$result = json_decode($this->display(['mode' => 'save-transport', 'name' => 'not-registered', 'settings' => ['secret' => 'value']], $settings)->getOutput('json'), true);
		self::assertFalse($result['ok']);
	}

	public function testSaveTransportCannotOverrideExistingEnabledState(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->willReturn(['enabled' => false, 'other' => 'old']);
		$settings->expects(self::once())->method('set')->with('messaging_transports', 'null', ['reason' => 'test', 'enabled' => false]);
		$settings->expects(self::once())->method('save');
		$result = json_decode($this->display(['mode' => 'save-transport', 'name' => 'null',
			'settings' => ['reason' => 'test', 'enabled' => true]], $settings)->getOutput('json'), true);
		self::assertTrue($result['ok']);
	}

	public function testToggleEnableAndResetUseSettingsStore(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects(self::once())->method('set')->with('messaging_transports', 'null', ['enabled' => true]);
		$settings->expects(self::once())->method('save');
		$result = json_decode($this->display(['mode' => 'set-enabled', 'name' => 'null', 'enabled' => 'yes'], $settings)->getOutput('json'), true);
		self::assertTrue($result['enabled']);
		$reset = $this->createMock(ISettingsStore::class);
		$reset->expects(self::once())->method('remove')->with('messaging_transports', 'null');
		$reset->expects(self::once())->method('save');
		self::assertTrue(json_decode($this->display(['mode' => 'reset-transport', 'name' => 'null'], $reset)->getOutput('json'), true)['ok']);
	}

	public function testUnauthorizedChangeDoesNotWriteSettings(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects(self::never())->method('set');
		$settings->expects(self::never())->method('save');
		try {
			self::assertSame('', $this->display(['mode' => 'save-default', 'default_transport' => 'null'], $settings, false)->getOutput('json'));
			self::assertSame(403, http_response_code());
		} finally { http_response_code(200); }
	}

	public function testDefaultTransportCanBePersisted(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects(self::once())->method('set')->with('messaging', 'default', ['default_transport' => 'null']);
		$settings->expects(self::once())->method('save');
		$result = json_decode($this->display(['mode' => 'save-default', 'default_transport' => 'null'], $settings)->getOutput('json'), true);
		self::assertTrue($result['ok']);
	}


	public function testGeneralSaveCannotEnableTransportWhenNoPreviousEnabledValueExists(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->method('get')->willReturn(['reason' => 'existing']);
		$settings->expects(self::once())->method('set')->with('messaging_transports', 'null', ['reason' => 'updated']);
		$settings->expects(self::once())->method('save');
		$result = json_decode($this->display(['mode' => 'save-transport', 'name' => 'null',
			'settings' => ['enabled' => 'yes', 'reason' => 'updated']], $settings)->getOutput('JSON'), true);
		self::assertTrue($result['ok']);
	}

	public function testInvalidTransportSettingsTypeDoesNotWrite(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects(self::never())->method('set');
		$settings->expects(self::never())->method('save');
		$response = json_decode($this->display(['mode' => 'save-transport', 'name' => 'null', 'settings' => 'invalid'], $settings)->getOutput('json'), true);
		self::assertFalse($response['ok']);
		self::assertSame('Invalid transport settings payload.', $response['error']);
	}

	public function testUnknownTransportCannotBeEnabledResetOrMadeDefault(): void {
		foreach([
			['mode' => 'set-enabled', 'name' => 'missing', 'enabled' => true],
			['mode' => 'reset-transport', 'name' => 'missing'],
			['mode' => 'save-default', 'default_transport' => 'missing']
		] as $payload) {
			$settings = $this->createMock(ISettingsStore::class);
			$settings->expects(self::never())->method('save');
			$settings->expects(self::never())->method('set');
			$result = json_decode($this->display($payload, $settings)->getOutput('json'), true);
			self::assertFalse($result['ok']);
			self::assertSame('Unknown message transport.', $result['error']);
		}
	}

	public function testTransportSettingsStoreExceptionProducesJsonError(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturnCallback(static function(): array {
			throw new \RuntimeException('settings unavailable');
		});
		$result = json_decode($this->display(['mode' => 'set-enabled', 'name' => 'null', 'enabled' => true], $settings)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertSame('settings unavailable', $result['error']);
	}

	public function testListReportsConfiguredTransportAndEnabledFlag(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$result = json_decode($this->display(['pageSize' => 10, 'filters' => ['name' => 'null']], $settings)->getOutput('json'), true);
		self::assertTrue($result['ok']);
		self::assertSame(1, $result['total']);
		self::assertSame('null', $result['data'][0]['name']);
		self::assertSame(1, $result['data'][0]['is_default']);
		self::assertIsArray(json_decode($result['data'][0]['schema_json'], true));
	}

}
