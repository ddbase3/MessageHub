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
use Base3\MessageHub\Job\MessageQueueWorkerJob;
use Base3\MessageHub\Service\MessageDeliveryService;
use Base3\MessagingFoundation\Api\IMessageDeliveryRepository;
use Base3\MessagingFoundation\Api\IMessageQueueRepository;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;
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
