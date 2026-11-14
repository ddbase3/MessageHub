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

namespace Base3\MessageHub;

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Api\IContainer;
use Base3\CoreFoundation\Api\IPlugin;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
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
