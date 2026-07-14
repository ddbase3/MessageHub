<?php declare(strict_types=1);

namespace MessageHub;

use Base3\Api\IClassMap;
use Base3\Api\IContainer;
use Base3\Api\IPlugin;
use Base3\Database\Api\IDatabase;
use Base3\Event\Api\IEventManager;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
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

final class MessageHubPlugin implements IPlugin {

	public function __construct(
		private readonly IContainer $container
	) {}

	public static function getName(): string {
		return 'messagehubplugin';
	}

	public function init() {
		$this->container
			->set(self::getName(), $this, IContainer::SHARED)
			->set(DatabaseSchema::class, fn($c) => new DatabaseSchema($c->get(IDatabase::class)), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMessageIdGenerator::class, fn() => new MessageIdGenerator(), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(MessageFilterOptionService::class, fn($c) => new MessageFilterOptionService(
				$c->get(IDatabase::class),
				$c->get(DatabaseSchema::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMessageTemplateRepository::class, fn($c) => new DatabaseMessageTemplateRepository(
				$c->get(IDatabase::class),
				$c->get(DatabaseSchema::class),
				$c->get(IMessageIdGenerator::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMessageVariantRepository::class, fn($c) => new DatabaseMessageVariantRepository(
				$c->get(IDatabase::class),
				$c->get(DatabaseSchema::class),
				$c->get(IMessageIdGenerator::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMessageQueueRepository::class, fn($c) => new DatabaseMessageQueueRepository(
				$c->get(IDatabase::class),
				$c->get(DatabaseSchema::class),
				$c->get(IMessageIdGenerator::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMessageDeliveryRepository::class, fn($c) => new DatabaseMessageDeliveryRepository(
				$c->get(IDatabase::class),
				$c->get(DatabaseSchema::class),
				$c->get(IMessageIdGenerator::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMessageRenderer::class, fn($c) => new MessageRenderer(
				$c->get(IMessageTemplateRepository::class),
				$c->get(IMessageVariantRepository::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMessageTransportRegistry::class, fn($c) => new MessageTransportRegistry(
				$c->get(IClassMap::class),
				$c->get(ISettingsStore::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMessageTypeSynchronizationService::class, fn($c) => new MessageTypeSynchronizationService(
				$c->get(IClassMap::class),
				$c->get(IMessageTemplateRepository::class),
				$c->get(IMessageVariantRepository::class),
				$c->get(IMessageIdGenerator::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(MessageDeliveryService::class, fn($c) => new MessageDeliveryService(
				$c->get(IMessageTransportRegistry::class),
				$c->get(IMessageDeliveryRepository::class),
				$c->get(IMessageQueueRepository::class),
				$c->get(IEventManager::class),
				$c->get(ILogger::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMessageQueueService::class, fn($c) => new MessageQueueService(
				$c->get(IMessageQueueRepository::class),
				$c->get(IEventManager::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE)
			->set(IMessageService::class, fn($c) => new MessageService(
				$c->get(IMessageQueueService::class),
				$c->get(MessageDeliveryService::class),
				$c->get(IMessageTransportRegistry::class)
			), IContainer::SHARED | IContainer::NOOVERWRITE);
	}
}
