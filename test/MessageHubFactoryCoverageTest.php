<?php declare(strict_types=1);

namespace MessageHub\Test;

use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Database\Api\IDatabase;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use MessageHub\MessageHubPlugin;
use MessageHub\Repository\DatabaseMessageDeliveryRepository;
use MessageHub\Repository\DatabaseMessageQueueRepository;
use MessageHub\Repository\DatabaseMessageTemplateRepository;
use MessageHub\Repository\DatabaseMessageVariantRepository;
use MessageHub\Repository\DatabaseSchema;
use MessageHub\Service\MessageDeliveryService;
use MessageHub\Service\MessageFilterOptionService;
use MessageHub\Service\MessageIdGenerator;
use MessageHub\Service\MessageQueueService;
use MessageHub\Service\MessageRenderer;
use MessageHub\Service\MessageService;
use MessageHub\Service\MessageTransportRegistry;
use MessageHub\Service\MessageTypeSynchronizationService;
use MessagingFoundation\Api\IMessageDeliveryRepository;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Api\IMessageQueueRepository;
use MessagingFoundation\Api\IMessageQueueService;
use MessagingFoundation\Api\IMessageRenderer;
use MessagingFoundation\Api\IMessageService;
use MessagingFoundation\Api\IMessageTemplateRepository;
use MessagingFoundation\Api\IMessageTransportRegistry;
use MessagingFoundation\Api\IMessageTypeSynchronizationService;
use MessagingFoundation\Api\IMessageVariantRepository;
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
