<?php declare(strict_types=1);

namespace MessageHub\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use MessageHub\Display\MessageTransportAdminDisplay;
use MessageHub\Transport\LogMessageTransport;
use MessageHub\Transport\NullMessageTransport;
use MessagingFoundation\Api\IMessageTransportRegistry;
use PHPUnit\Framework\TestCase;

final class MessageTransportConfigurationBranchesTest extends TestCase {

	private function display(array $payload, ISettingsStore $settings, ?IMessageTransportRegistry $registry = null): MessageTransportAdminDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$auth = $this->createStub(IAuthorizationService::class);
		$auth->method('getDefaultAuthority')->willReturn('base3');
		$auth->method('decide')->willReturn(AuthorizationDecision::allow());
		if($registry === null) {
			$registry = $this->createStub(IMessageTransportRegistry::class);
			$registry->method('getTransport')->willReturnCallback(static fn(string $name): ?\MessagingFoundation\Api\IMessageTransport => $name === 'null' ? new NullMessageTransport() : null);
			$registry->method('getTransports')->willReturn(['null' => new NullMessageTransport()]);
			$registry->method('getDefaultTransportName')->willReturn('null');
		}
		return new MessageTransportAdminDisplay($request, $this->createStub(IMvcView::class),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$registry, $settings, $auth);
	}

	public function testActivationNormalizesAllSupportedBooleanFormsAndPreservesOtherSettings(): void {
		foreach(['YES' => true, '  on ' => true, '1' => true, 'true' => true,
			'false' => false, ' OFF ' => false, 'disabled' => false, '' => false, 'unknown' => false] as $input => $expected) {
			$store = $this->createMock(ISettingsStore::class);
			$store->method('get')->willReturn(['secret' => 'keep', 'enabled' => !$expected]);
			$store->expects(self::once())->method('set')->with('messaging_transports', 'null', ['secret' => 'keep', 'enabled' => $expected]);
			$store->expects(self::once())->method('save');
			$result = json_decode($this->display(['mode' => 'set-enabled', 'name' => ' null ', 'enabled' => $input], $store)->getOutput('json'), true);
			self::assertTrue($result['ok']);
			self::assertSame($expected, $result['enabled']);
		}
	}

	public function testGeneralSaveRetainsExplicitlyEnabledSettingAndDoesNotAcceptNewFlag(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['enabled' => '  ON ', 'password' => 'previous']);
		$store->expects(self::once())->method('set')->with('messaging_transports', 'null', ['password' => 'changed', 'enabled' => true]);
		$store->expects(self::once())->method('save');
		$result = json_decode($this->display(['mode' => 'save-transport', 'name' => 'null',
			'settings' => ['password' => 'changed', 'enabled' => 'no']], $store)->getOutput('json'), true);
		self::assertTrue($result['ok']);
	}

	public function testDefaultTransportUpdatePreservesOtherMessagingConfiguration(): void {
		$store = $this->createMock(ISettingsStore::class);
		$store->method('get')->willReturn(['retry_limit' => 5, 'default_transport' => 'outdated']);
		$store->expects(self::once())->method('set')->with('messaging', 'default', ['retry_limit' => 5, 'default_transport' => 'null']);
		$store->expects(self::once())->method('save');
		self::assertTrue(json_decode($this->display(['mode' => 'save-default', 'default_transport' => 'null'], $store)->getOutput('json'), true)['ok']);
	}

	public function testTransportListHonorsRuntimeSettingsAndSchemaDefaults(): void {
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransports')->willReturn([
			'null' => new NullMessageTransport(),
			'log' => new LogMessageTransport($this->createStub(ILogger::class))
		]);
		$registry->method('getDefaultTransportName')->willReturn('log');
		$registry->method('getTransportSettings')->willReturnCallback(static fn(string $name): array => $name === 'null'
			? ['enabled' => 'off', 'reason' => 'audit'] : []);
		$store = $this->createStub(ISettingsStore::class);
		$response = json_decode($this->display(['pageSize' => 10, 'sort' => [['key' => 'name', 'dir' => 'asc']]], $store, $registry)->getOutput('json'), true);
		self::assertSame(2, $response['total']);
		self::assertSame('log', $response['data'][0]['name']);
		self::assertSame(1, $response['data'][0]['is_enabled']);
		self::assertSame(1, $response['data'][0]['is_default']);
		self::assertSame('null', $response['data'][1]['name']);
		self::assertSame(0, $response['data'][1]['is_enabled']);
		self::assertSame(0, $response['data'][1]['is_default']);
		self::assertStringContainsString('audit', $response['data'][1]['settings_summary']);
	}

	public function testTransportListSupportsExactEnabledFilterAndPageBeyondLast(): void {
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransports')->willReturn([
			'null' => new NullMessageTransport(),
			'log' => new LogMessageTransport($this->createStub(ILogger::class))
		]);
		$registry->method('getTransportSettings')->willReturnCallback(static fn(string $name): array => $name === 'null' ? ['enabled' => false] : []);
		$result = json_decode($this->display(['filters' => ['is_enabled' => '0'], 'page' => 2, 'pageSize' => 1],
			$this->createStub(ISettingsStore::class), $registry)->getOutput('json'), true);
		self::assertSame(1, $result['total']);
		self::assertSame(2, $result['page']);
		self::assertSame(1, $result['totalPages']);
		self::assertSame([], $result['data']);
		self::assertFalse($result['hasMore']);
	}

	public function testSettingsSaveFailureIsReturnedWithoutClaimingSuccess(): void {
		$store = $this->createStub(ISettingsStore::class);
		$store->method('save')->willThrowException(new \RuntimeException('store is read only'));
		$result = json_decode($this->display(['mode' => 'reset-transport', 'name' => 'null'], $store)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertSame('store is read only', $result['error']);
	}

	public function testTransportDiscoveryFailureIsReportedAsJsonError(): void {
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransports')->willThrowException(new \RuntimeException('registry error'));
		$result = json_decode($this->display([], $this->createStub(ISettingsStore::class), $registry)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertSame('registry error', $result['error']);
	}

	public function testCombinedNameAndDefaultFiltersUseTheSharedListOwner(): void {
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransports')->willReturn([
			'null' => new NullMessageTransport(),
			'log' => new LogMessageTransport($this->createStub(ILogger::class))
		]);
		$registry->method('getDefaultTransportName')->willReturn('log');
		$registry->method('getTransportSettings')->willReturnCallback(static fn(string $name): array =>
			$name === 'null' ? ['enabled' => false] : []);
		$result = json_decode($this->display(['filters' => ['name' => 'LoG', 'is_default' => 'true'],
			'sort' => [['key' => 'name', 'dir' => 'asc']]], $this->createStub(ISettingsStore::class), $registry)
			->getOutput('json'), true);
		self::assertTrue($result['ok']);
		self::assertSame(1, $result['total']);
		self::assertSame('log', $result['data'][0]['name']);
		self::assertSame(['name' => 'LoG', 'is_default' => 'true'], $result['appliedFilters']);
	}

}
