<?php declare(strict_types=1);

namespace MessageHub\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Database\Api\IDatabase;
use MessageHub\Display\MessageHubDashboardDisplay;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessageHubDashboardCompletionTest extends TestCase {

	private function display(IDatabase $database, IMvcView $view, IAssetResolver $assets): MessageHubDashboardDisplay {
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('base3');
		$authorization->method('decide')->willReturn(AuthorizationDecision::allow());
		return new MessageHubDashboardDisplay($view, $assets, $database, $authorization);
	}

	public function testDeliveryCountFailureKeepsSuccessfullyLoadedQueueStatistics(): void {
		$database = $this->createStub(IDatabase::class);
		$database->method('multiQuery')->willReturn([['status' => 'queued', 'cnt' => 7], ['status' => 'failed', 'cnt' => 2]]);
		$database->method('scalarQuery')->willThrowException(new RuntimeException('private database error'));
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void {
			$assigned[$key] = $value;
		});
		$view->method('loadTemplate')->willReturn('dashboard');
		self::assertSame('dashboard', $this->display($database, $view, $this->createStub(IAssetResolver::class))->getOutput());
		self::assertSame(7, $assigned['stats']['queued']);
		self::assertSame(2, $assigned['stats']['failed']);
		self::assertSame(0, $assigned['stats']['deliveries']);
		self::assertSame(0, $assigned['stats']['processing']);
	}

	public function testDashboardPassesAssetResolutionThroughExistingResolver(): void {
		$database = $this->createStub(IDatabase::class);
		$database->method('multiQuery')->willReturn([]);
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void {
			$assigned[$key] = $value;
		});
		$view->method('loadTemplate')->willReturn('dashboard');
		$assets = $this->createMock(IAssetResolver::class);
		$assets->expects(self::once())->method('resolve')->with('assets/dashboard.js')->willReturn('/assets/dashboard.js');
		$this->display($database, $view, $assets)->getOutput();
		self::assertIsCallable($assigned['resolve']);
		self::assertSame('/assets/dashboard.js', $assigned['resolve']('assets/dashboard.js'));
	}
}
