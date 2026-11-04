<?php declare(strict_types=1);

namespace MessageHub\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Database\Api\IDatabase;
use MessageHub\Display\MessageHubDashboardDisplay;
use PHPUnit\Framework\TestCase;

final class MessageHubDashboardBehaviorTest extends TestCase {

	private function display(IDatabase $database, IMvcView $view, bool $allowed = true): MessageHubDashboardDisplay {
		$auth = $this->createStub(IAuthorizationService::class);
		$auth->method('getDefaultAuthority')->willReturn('base3');
		$auth->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		return new MessageHubDashboardDisplay($view, $this->createStub(IAssetResolver::class), $database, $auth);
	}

	private function view(array &$assigned): IMvcView {
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void {
			$assigned[$key] = $value;
		});
		$view->method('loadTemplate')->willReturn('dashboard html');
		return $view;
	}

	public function testDashboardUsesActualQueueStatusCountsAndDeliveryTotal(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects(self::once())->method('connect');
		$database->expects(self::once())->method('multiQuery')->with('SELECT status, COUNT(*) AS cnt FROM base3_messaging_queue GROUP BY status')
			->willReturn([['status' => 'queued', 'cnt' => '4'], ['status' => 'retry_wait', 'cnt' => 2],
				['status' => 'sent', 'cnt' => 9], ['status' => 'unrecognized', 'cnt' => 1]]);
		$database->expects(self::once())->method('scalarQuery')->with('SELECT COUNT(*) FROM base3_messaging_deliveries')->willReturn(23);
		$assigned = [];
		self::assertSame('dashboard html', $this->display($database, $this->view($assigned))->getOutput('json'));
		self::assertSame(4, $assigned['stats']['queued']);
		self::assertSame(2, $assigned['stats']['retry_wait']);
		self::assertSame(9, $assigned['stats']['sent']);
		self::assertSame(0, $assigned['stats']['failed']);
		self::assertSame(23, $assigned['stats']['deliveries']);
		self::assertSame(1, $assigned['stats']['unrecognized']);
	}

	public function testDashboardHandlesDatabaseReadFailureWithoutShowingPrivateError(): void {
		$database = $this->createStub(IDatabase::class);
		$database->method('multiQuery')->willThrowException(new \RuntimeException('database secret')); 
		$assigned = [];
		self::assertSame('dashboard html', $this->display($database, $this->view($assigned))->getOutput());
		self::assertSame(['queued' => 0, 'retry_wait' => 0, 'processing' => 0,
			'sent' => 0, 'failed' => 0, 'deliveries' => 0], $assigned['stats']);
	}

	public function testDashboardDefaultsToZeroWhenDeliveryCountIsNull(): void {
		$database = $this->createStub(IDatabase::class);
		$database->method('multiQuery')->willReturn([]);
		$database->method('scalarQuery')->willReturn(null);
		$assigned = [];
		self::assertSame('dashboard html', $this->display($database, $this->view($assigned))->getOutput());
		self::assertSame(0, $assigned['stats']['deliveries']);
	}

	public function testForbiddenDashboardNeverReadsDatabaseOrTemplates(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects(self::never())->method('connect');
		$database->expects(self::never())->method('multiQuery');
		$view = $this->createMock(IMvcView::class);
		$view->expects(self::never())->method('loadTemplate');
		try {
			self::assertSame('', $this->display($database, $view, false)->getOutput());
			self::assertSame(403, http_response_code());
		} finally { http_response_code(200); }
	}
}
