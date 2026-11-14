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

namespace Base3\MessageHub\Test\Job;

use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MessageHub\Job\MessageDeliveryCleanupJob;
use Base3\MessageHub\Job\MessageQueueWorkerJob;
use Base3\MessageHub\Service\MessageDeliveryService;
use Base3\MessagingFoundation\Api\IMessageDeliveryRepository;
use Base3\MessagingFoundation\Api\IMessageQueueRepository;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;
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
