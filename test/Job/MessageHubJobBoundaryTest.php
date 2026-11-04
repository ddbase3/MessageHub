<?php declare(strict_types=1);

namespace MessageHub\Test\Job;

use Base3\Configuration\Api\IConfiguration;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use MessageHub\Job\MessageDeliveryCleanupJob;
use MessageHub\Job\MessageQueueWorkerJob;
use MessageHub\Service\MessageDeliveryService;
use MessagingFoundation\Api\IMessageDeliveryRepository;
use MessagingFoundation\Api\IMessageQueueRepository;
use MessagingFoundation\Api\IMessageTransportRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessageHubJobBoundaryTest extends TestCase {

	private function worker(IMessageQueueRepository $queue, IConfiguration $configuration): MessageQueueWorkerJob {
		return new MessageQueueWorkerJob(new MessageDeliveryService(
			$this->createStub(IMessageTransportRegistry::class),
			$this->createStub(IMessageDeliveryRepository::class),
			$queue,
			$this->createStub(IEventManager::class),
			$this->createStub(ILogger::class)
		), $configuration);
	}

	public function testQueueWorkerLoadsConfigurationOnlyOnceForBothAccessors(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->expects(self::once())->method('get')->with('job')->willReturn([
			'messagequeueworkerjob.active' => '1',
			'messagequeueworkerjob.priority' => '12'
		]);
		$job = $this->worker($this->createStub(IMessageQueueRepository::class), $configuration);
		self::assertTrue($job->isActive());
		self::assertSame(12, $job->getPriority());
		self::assertTrue($job->isActive());
	}

	public function testQueueWorkerConvertsNonOneActivationToInactive(): void {
		$configuration = $this->createStub(IConfiguration::class);
		$configuration->method('get')->willReturn([
			'messagequeueworkerjob.active' => 'true',
			'messagequeueworkerjob.priority' => '-4'
		]);
		$job = $this->worker($this->createStub(IMessageQueueRepository::class), $configuration);
		self::assertFalse($job->isActive());
		self::assertSame(-4, $job->getPriority());
	}

	public function testQueueWorkerPropagatesClaimFailureInsteadOfReportingProcessedMessages(): void {
		$queue = $this->createStub(IMessageQueueRepository::class);
		$queue->method('claimNext')->willThrowException(new RuntimeException('queue database unavailable'));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('queue database unavailable');
		$this->worker($queue, $this->createStub(IConfiguration::class))->go();
	}

	public function testCleanupCachesJobConfigurationAcrossChecks(): void {
		$configuration = $this->createMock(IConfiguration::class);
		$configuration->expects(self::once())->method('get')->with('job')->willReturn([
			'messagedeliverycleanupjob.active' => 1,
			'messagedeliverycleanupjob.priority' => 13
		]);
		$job = new MessageDeliveryCleanupJob(
			$this->createStub(IMessageDeliveryRepository::class),
			$this->createStub(ISettingsStore::class),
			$configuration
		);
		self::assertSame(13, $job->getPriority());
		self::assertTrue($job->isActive());
		self::assertSame(13, $job->getPriority());
	}

	public function testCleanupPassesZeroRetentionToRepositoryWithoutInventingADefault(): void {
		$delivery = $this->createMock(IMessageDeliveryRepository::class);
		$delivery->expects(self::once())->method('cleanup')->with(0)->willReturn(0);
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn(['retention_days' => 0]);
		$job = new MessageDeliveryCleanupJob($delivery, $settings, $this->createStub(IConfiguration::class));
		self::assertSame('Message delivery cleanup removed 0 delivery record(s).', $job->go());
	}

	public function testCleanupPropagatesRepositoryFailureWithoutInventingSuccess(): void {
		$delivery = $this->createStub(IMessageDeliveryRepository::class);
		$delivery->method('cleanup')->willThrowException(new RuntimeException('retention store unavailable'));
		$job = new MessageDeliveryCleanupJob($delivery,
			$this->createStub(ISettingsStore::class), $this->createStub(IConfiguration::class));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('retention store unavailable');
		$job->go();
	}
}
