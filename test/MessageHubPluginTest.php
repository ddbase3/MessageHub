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

namespace Base3\MessageHub\Test;

use Base3\CoreFoundation\Api\IContainer;
use Base3\MessageHub\MessageHubPlugin;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\MessageHub\Service\MessageDeliveryService;
use Base3\MessagingFoundation\Api\IMessageQueueService;
use Base3\MessagingFoundation\Api\IMessageRenderer;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;
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
