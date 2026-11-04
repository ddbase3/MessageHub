<?php declare(strict_types=1);

namespace MessageHub\Test;

use Base3\Api\IContainer;
use MessageHub\MessageHubPlugin;
use MessageHub\Repository\DatabaseSchema;
use MessageHub\Service\MessageDeliveryService;
use MessagingFoundation\Api\IMessageQueueService;
use MessagingFoundation\Api\IMessageRenderer;
use MessagingFoundation\Api\IMessageTransportRegistry;
use PHPUnit\Framework\TestCase;

final class MessageHubPluginTest extends TestCase {

	public function testPluginRegistersOwnedServicesUnderFrameworkContracts(): void {
		$registrations = [];
		$container = $this->createMock(IContainer::class);
		$container->expects(self::exactly(14))->method('set')->willReturnCallback(function(string $name, mixed $definition, int $flags) use (&$registrations, &$container): IContainer {
			self::assertArrayNotHasKey($name, $registrations);
			$registrations[$name] = [$definition, $flags];
			return $container;
		});
		$plugin = new MessageHubPlugin($container);
		self::assertSame('messagehubplugin', $plugin::getName());
		$plugin->init();
		self::assertSame($plugin, $registrations['messagehubplugin'][0]);
		foreach([DatabaseSchema::class, MessageDeliveryService::class, IMessageQueueService::class,
			IMessageRenderer::class, IMessageTransportRegistry::class] as $contract) {
			self::assertArrayHasKey($contract, $registrations);
			self::assertIsCallable($registrations[$contract][0]);
			self::assertSame(IContainer::SHARED | IContainer::NOOVERWRITE, $registrations[$contract][1]);
		}
	}
}
