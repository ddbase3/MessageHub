<?php declare(strict_types=1);

namespace MessageHub\Test\Job;

use Base3\Configuration\Api\IConfiguration;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use MessageHub\Job\MessageQueueWorkerJob;
use MessageHub\Service\MessageDeliveryService;
use MessagingFoundation\Api\IMessageDeliveryRepository;
use MessagingFoundation\Api\IMessageQueueRepository;
use MessagingFoundation\Api\IMessageTransportRegistry;
use PHPUnit\Framework\TestCase;

final class MessageQueueWorkerJobTest extends TestCase {

	public function testJobUsesConfiguredPriorityAndActivation(): void {
		$config = $this->createMock(IConfiguration::class);
		$config->expects(self::once())->method('get')->with('job')->willReturn([
			'messagequeueworkerjob.active' => 1, 'messagequeueworkerjob.priority' => 25
		]);
		$job = new MessageQueueWorkerJob($this->deliveryService(), $config);
		self::assertSame('messagequeueworkerjob', $job::getName());
		self::assertTrue($job->isActive());
		self::assertSame(25, $job->getPriority());
	}

	public function testInactiveJobDefaultsToPriorityOne(): void {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn([]);
		$job = new MessageQueueWorkerJob($this->deliveryService(), $config);
		self::assertFalse($job->isActive());
		self::assertSame(1, $job->getPriority());
	}

	public function testExecutionProcessesAtMostTwentyClaims(): void {
		$queues = $this->createMock(IMessageQueueRepository::class);
		$queues->expects(self::once())->method('claimNext')->with(20, 300)->willReturn([]);
		$job = new MessageQueueWorkerJob($this->deliveryService($queues), $this->createStub(IConfiguration::class));
		self::assertSame('Message queue worker processed 0 message(s).', $job->go());
	}

	private function deliveryService(?IMessageQueueRepository $repository = null): MessageDeliveryService {
		return new MessageDeliveryService($this->createStub(IMessageTransportRegistry::class),
			$this->createStub(IMessageDeliveryRepository::class), $repository ?? $this->createStub(IMessageQueueRepository::class),
			$this->createStub(IEventManager::class), $this->createStub(ILogger::class));
	}
}
