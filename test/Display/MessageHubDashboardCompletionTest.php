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
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationDecision;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\MessageHub\Display\MessageHubDashboardDisplay;
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
