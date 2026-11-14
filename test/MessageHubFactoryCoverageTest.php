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

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MessageHub\MessageHubPlugin;
use Base3\MessageHub\Repository\DatabaseMessageDeliveryRepository;
use Base3\MessageHub\Repository\DatabaseMessageQueueRepository;
use Base3\MessageHub\Repository\DatabaseMessageTemplateRepository;
use Base3\MessageHub\Repository\DatabaseMessageVariantRepository;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\MessageHub\Service\MessageDeliveryService;
use Base3\MessageHub\Service\MessageFilterOptionService;
use Base3\MessageHub\Service\MessageIdGenerator;
use Base3\MessageHub\Service\MessageQueueService;
use Base3\MessageHub\Service\MessageRenderer;
use Base3\MessageHub\Service\MessageService;
use Base3\MessageHub\Service\MessageTransportRegistry;
use Base3\MessageHub\Service\MessageTypeSynchronizationService;
use Base3\MessagingFoundation\Api\IMessageDeliveryRepository;
use Base3\MessagingFoundation\Api\IMessageIdGenerator;
use Base3\MessagingFoundation\Api\IMessageQueueRepository;
use Base3\MessagingFoundation\Api\IMessageQueueService;
use Base3\MessagingFoundation\Api\IMessageRenderer;
use Base3\MessagingFoundation\Api\IMessageService;
use Base3\MessagingFoundation\Api\IMessageTemplateRepository;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;
use Base3\MessagingFoundation\Api\IMessageTypeSynchronizationService;
use Base3\MessagingFoundation\Api\IMessageVariantRepository;
use PHPUnit\Framework\TestCase;

final class MessageHubFactoryCoverageTest extends TestCase {

	public function testEveryRegisteredFactoryConstructsItsOwnedServiceWithFrameworkContracts(): void {
		$definitions = [];
		$services = [
			IDatabase::class => $this->createStub(IDatabase::class),
			IClassMap::class => $this->createStub(IClassMap::class),
			ISettingsStore::class => $this->createStub(ISettingsStore::class),
			IEventManager::class => $this->createStub(IEventManager::class),
			ILogger::class => $this->createStub(ILogger::class)
		];
		$container = $this->createMock(IContainer::class);
		$container->expects(self::exactly(14))->method('set')->willReturnCallback(
			static function(string $key, mixed $definition, int $flags) use (&$definitions, $container): IContainer {
				$definitions[$key] = $definition;
				self::assertSame(IContainer::SHARED | ($key === MessageHubPlugin::getName() ? 0 : IContainer::NOOVERWRITE), $flags);
				return $container;
			}
		);
		$container->method('get')->willReturnCallback(static function(string $key) use (&$services): mixed {
			self::assertArrayHasKey($key, $services);
			return $services[$key];
		});

		$plugin = new MessageHubPlugin($container);
		$plugin->init();
		self::assertSame($plugin, $definitions[MessageHubPlugin::getName()]);

		$expected = [
			DatabaseSchema::class => DatabaseSchema::class,
			IMessageIdGenerator::class => MessageIdGenerator::class,
			MessageFilterOptionService::class => MessageFilterOptionService::class,
			IMessageTemplateRepository::class => DatabaseMessageTemplateRepository::class,
			IMessageVariantRepository::class => DatabaseMessageVariantRepository::class,
			IMessageQueueRepository::class => DatabaseMessageQueueRepository::class,
			IMessageDeliveryRepository::class => DatabaseMessageDeliveryRepository::class,
			IMessageRenderer::class => MessageRenderer::class,
			IMessageTransportRegistry::class => MessageTransportRegistry::class,
			IMessageTypeSynchronizationService::class => MessageTypeSynchronizationService::class,
			MessageDeliveryService::class => MessageDeliveryService::class,
			IMessageQueueService::class => MessageQueueService::class,
			IMessageService::class => MessageService::class
		];
		foreach($expected as $key => $class) {
			self::assertIsCallable($definitions[$key]);
			$instance = $definitions[$key]($container);
			self::assertInstanceOf($class, $instance, $key);
			$services[$key] = $instance;
		}
		self::assertCount(14, $definitions);
	}
}
