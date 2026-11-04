<?php declare(strict_types=1);

namespace MessageHub\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Database\Api\IDatabase;
use Base3\Event\Api\IEventManager;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Logger\Api\ILogger;
use MessageHub\Display\MessageQueueAdminDisplay;
use MessageHub\Repository\DatabaseSchema;
use MessageHub\Service\MessageDeliveryService;
use MessageHub\Service\MessageFilterOptionService;
use MessagingFoundation\Api\IMessageDeliveryRepository;
use MessagingFoundation\Api\IMessageQueueRepository;
use MessagingFoundation\Api\IMessageTransportRegistry;
use PHPUnit\Framework\TestCase;

final class MessageQueueAdminDisplayTest extends TestCase {

	private function display(array $payload, IMessageQueueRepository $queue, bool $allowed = true): MessageQueueAdminDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('base3');
		$authorization->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		$delivery = new MessageDeliveryService($this->createStub(IMessageTransportRegistry::class),
			$this->createStub(IMessageDeliveryRepository::class), $queue, $this->createStub(IEventManager::class), $this->createStub(ILogger::class));
		$db = $this->createStub(IDatabase::class);
		$filter = new MessageFilterOptionService($db, new DatabaseSchema($db));
		return new MessageQueueAdminDisplay($request, $this->createStub(IMvcView::class),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$queue, $delivery, $filter, $authorization);
	}

	public function testUnauthorizedCancelIsNotExecuted(): void {
		$queue = $this->createMock(IMessageQueueRepository::class);
		$queue->expects(self::never())->method('cancel');
		try {
			self::assertSame('', $this->display(['mode' => 'cancel', 'id' => 'q1'], $queue, false)->getOutput('json'));
			self::assertSame(403, http_response_code());
		} finally { http_response_code(200); }
	}

	public function testCancelAndProcessUseRepositoryAndDeliveryService(): void {
		$queue = $this->createMock(IMessageQueueRepository::class);
		$queue->expects(self::once())->method('cancel')->with('q1');
		self::assertTrue(json_decode($this->display(['mode' => 'cancel', 'id' => 'q1'], $queue)->getOutput('json'), true)['ok']);
		$queueProcess = $this->createMock(IMessageQueueRepository::class);
		$queueProcess->expects(self::once())->method('claimNext')->with(3, 300)->willReturn([]);
		$result = json_decode($this->display(['mode' => 'process', 'limit' => 3], $queueProcess)->getOutput('json'), true);
		self::assertSame(0, $result['processed']);
	}

	public function testPageNormalizesSearchAndReturnsConsistentMetadata(): void {
		$queue = $this->createMock(IMessageQueueRepository::class);
		$queue->expects(self::once())->method('page')->with(self::callback(function(array $request): bool {
			self::assertSame(1, $request['page']);
			self::assertSame(250, $request['pageSize']);
			self::assertSame('', $request['search']);
			return true;
		}))->willReturn(['rows' => [['id' => 'q1', 'subject' => 'Hello', 'status' => 'sent']], 'total' => 1]);
		$result = json_decode($this->display(['page' => -10, 'pageSize' => 999, 'search' => 'hello'], $queue)->getOutput('json'), true);
		self::assertSame(1, $result['total']);
		self::assertSame('q1', $result['data'][0]['id']);
		self::assertSame(250, $result['pageSize']);
	}


	public function testQueueRepositoryExceptionIsReturnedAsJsonFailure(): void {
		$queue = $this->createStub(IMessageQueueRepository::class);
		$queue->method('cancel')->willReturnCallback(static function(): void {
			throw new \RuntimeException('queue operation failed');
		});
		$result = json_decode($this->display(['mode' => 'cancel', 'id' => 'q1'], $queue)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertSame('queue operation failed', $result['error']);
	}

	public function testQueueListAlwaysRunsSearchAndPagingThroughOwner(): void {
		$queue = $this->createStub(IMessageQueueRepository::class);
		$queue->method('page')->willReturn(['rows' => [
			['id' => 'a', 'status' => 'queued', 'subject' => 'Example'],
			['id' => 'b', 'status' => 'sent', 'subject' => 'Match']
		], 'total' => 2]);
		$result = json_decode($this->display(['search' => 'match', 'pageSize' => 1], $queue)->getOutput('json'), true);
		self::assertTrue($result['ok']);
		self::assertSame(1, $result['total']);
		self::assertSame('b', $result['data'][0]['id']);
		self::assertSame('match', $result['appliedSearch']);
	}

}
