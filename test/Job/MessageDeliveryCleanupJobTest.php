<?php declare(strict_types=1);

namespace MessageHub\Test\Job;

use Base3\Configuration\Api\IConfiguration;
use Base3\Settings\Api\ISettingsStore;
use MessageHub\Job\MessageDeliveryCleanupJob;
use MessagingFoundation\Api\IMessageDeliveryRepository;
use PHPUnit\Framework\TestCase;

final class MessageDeliveryCleanupJobTest extends TestCase {

	public function testActivityAndPriorityComeFromJobConfiguration(): void {
		$config = $this->createMock(IConfiguration::class);
		$config->expects(self::once())->method('get')->with('job')->willReturn([
			'messagedeliverycleanupjob.active' => 1, 'messagedeliverycleanupjob.priority' => '7'
		]);
		$job = new MessageDeliveryCleanupJob($this->createStub(IMessageDeliveryRepository::class), $this->createStub(ISettingsStore::class), $config);
		self::assertSame('messagedeliverycleanupjob', $job::getName());
		self::assertTrue($job->isActive());
		self::assertSame(7, $job->getPriority());
	}

	public function testDefaultConfigurationDisablesJob(): void {
		$config = $this->createStub(IConfiguration::class);
		$config->method('get')->willReturn([]);
		$job = new MessageDeliveryCleanupJob($this->createStub(IMessageDeliveryRepository::class), $this->createStub(ISettingsStore::class), $config);
		self::assertFalse($job->isActive());
		self::assertSame(1, $job->getPriority());
	}

	public function testCleanupHonorsRetentionSettingAndReportsCount(): void {
		$repository = $this->createMock(IMessageDeliveryRepository::class);
		$repository->expects(self::once())->method('cleanup')->with(14)->willReturn(3);
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn(['retention_days' => '14']);
		$job = new MessageDeliveryCleanupJob($repository, $settings, $this->createStub(IConfiguration::class));
		self::assertSame('Message delivery cleanup removed 3 delivery record(s).', $job->go());
	}

	public function testCleanupUsesYearDefault(): void {
		$repository = $this->createMock(IMessageDeliveryRepository::class);
		$repository->expects(self::once())->method('cleanup')->with(365)->willReturn(0);
		$job = new MessageDeliveryCleanupJob($repository, $this->createStub(ISettingsStore::class), $this->createStub(IConfiguration::class));
		self::assertStringContainsString('removed 0', $job->go());
	}
}
