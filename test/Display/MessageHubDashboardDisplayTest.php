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

final class MessageHubDashboardDisplayTest extends TestCase {

	private function authorization(bool $allowed): IAuthorizationService {
		$auth = $this->createStub(IAuthorizationService::class);
		$auth->method('getDefaultAuthority')->willReturn('base3');
		$auth->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		return $auth;
	}

	public function testForbiddenDashboardNeverQueriesDatabase(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects(self::never())->method('connect');
		$display = new MessageHubDashboardDisplay($this->createStub(IMvcView::class),
			$this->createStub(IAssetResolver::class), $database, $this->authorization(false));
		try {
			self::assertSame('', $display->getOutput('html'));
			self::assertSame(403, http_response_code());
		} finally { http_response_code(200); }
	}

	public function testAuthorizedDashboardCollectsQueueAndDeliveryStatistics(): void {
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('loadTemplate')->willReturn('<dashboard>');
		$view->method('assign')->willReturnCallback(function(string $name, mixed $value) use (&$assigned): void { $assigned[$name] = $value; });
		$database = $this->createMock(IDatabase::class);
		$database->expects(self::once())->method('connect');
		$database->method('multiQuery')->willReturn([['status' => 'queued', 'cnt' => '4'], ['status' => 'failed', 'cnt' => '2']]);
		$database->method('scalarQuery')->willReturn(17);
		$display = new MessageHubDashboardDisplay($view, $this->createStub(IAssetResolver::class), $database, $this->authorization(true));
		self::assertSame('<dashboard>', $display->getOutput());
		self::assertSame(4, $assigned['stats']['queued']);
		self::assertSame(2, $assigned['stats']['failed']);
		self::assertSame(17, $assigned['stats']['deliveries']);
		self::assertSame(0, $assigned['stats']['processing']);
	}

	public function testDashboardShowsZeroStatisticsWhenDataQueriesFail(): void {
		$assigned = [];
		$view = $this->createStub(IMvcView::class);
		$view->method('assign')->willReturnCallback(function(string $name, mixed $value) use (&$assigned): void { $assigned[$name] = $value; });
		$database = $this->createStub(IDatabase::class);
		$database->method('multiQuery')->willThrowException(new \RuntimeException('database unavailable'));
		$display = new MessageHubDashboardDisplay($view, $this->createStub(IAssetResolver::class), $database, $this->authorization(true));
		$display->getOutput();
		self::assertSame(0, $assigned['stats']['queued']);
		self::assertSame(0, $assigned['stats']['deliveries']);
	}
}
