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
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\MessageHub\Display\MessageDeliveryLogAdminDisplay;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\MessageHub\Service\MessageFilterOptionService;
use Base3\MessagingFoundation\Api\IMessageDeliveryRepository;
use PHPUnit\Framework\TestCase;

final class MessageDeliveryLogAdminDisplayTest extends TestCase {

	private function display(array $payload, IMessageDeliveryRepository $repository, bool $allowed = true): MessageDeliveryLogAdminDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$auth = $this->createStub(IAuthorizationService::class);
		$auth->method('getDefaultAuthority')->willReturn('base3');
		$auth->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		$db = $this->createStub(IDatabase::class);
		return new MessageDeliveryLogAdminDisplay($request, $this->createStub(IMvcView::class),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$repository, new MessageFilterOptionService($db, new DatabaseSchema($db)), $auth);
	}

	public function testDetailsReportPresentAndMissingDelivery(): void {
		$repository = $this->createMock(IMessageDeliveryRepository::class);
		$repository->expects(self::once())->method('detail')->with('d1')->willReturn(['id' => 'd1']);
		$result = json_decode($this->display(['mode' => 'detail', 'id' => 'd1'], $repository)->getOutput('json'), true);
		self::assertTrue($result['found']);
		self::assertSame('d1', $result['detail']['id']);
		$missing = $this->createStub(IMessageDeliveryRepository::class);
		$missing->method('detail')->willReturn(null);
		self::assertFalse(json_decode($this->display(['mode' => 'detail', 'id' => 'not-found'], $missing)->getOutput('json'), true)['found']);
	}

	public function testAccessDeniedNeverQueriesPrivateDeliveryHistory(): void {
		$repository = $this->createMock(IMessageDeliveryRepository::class);
		$repository->expects(self::never())->method('detail');
		try {
			self::assertSame('', $this->display(['mode' => 'detail', 'id' => 'private'], $repository, false)->getOutput('json'));
			self::assertSame(403, http_response_code());
		} finally { http_response_code(200); }
	}

	public function testUnexpectedRepositoryErrorBecomesJsonError(): void {
		$repository = $this->createStub(IMessageDeliveryRepository::class);
		$repository->method('detail')->willThrowException(new \RuntimeException('repository unavailable'));
		$result = json_decode($this->display(['mode' => 'detail', 'id' => 'd1'], $repository)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertStringContainsString('repository unavailable', $result['error']);
	}
}
