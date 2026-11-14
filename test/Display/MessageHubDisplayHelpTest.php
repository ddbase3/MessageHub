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

namespace Base3\MessageHub\Test\Display;

use Base3\CoreFoundation\Api\IAssetResolver;
use Base3\CoreFoundation\Api\IDisplay;
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\CoreFoundation\Event\Api\IEventManager;
use Base3\CoreFoundation\Language\Api\ILanguage;
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MessageHub\Display\MessageDeliveryLogAdminDisplay;
use Base3\MessageHub\Display\MessageHubDashboardDisplay;
use Base3\MessageHub\Display\MessageQueueAdminDisplay;
use Base3\MessageHub\Display\MessageTemplateAdminDisplay;
use Base3\MessageHub\Display\MessageTransportAdminDisplay;
use Base3\MessageHub\Display\MessageTypeSyncAdminDisplay;
use Base3\MessageHub\Display\MessageVariantAdminDisplay;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\MessageHub\Service\MessageDeliveryService;
use Base3\MessageHub\Service\MessageFilterOptionService;
use Base3\MessagingFoundation\Api\IMessageDeliveryRepository;
use Base3\MessagingFoundation\Api\IMessageIdGenerator;
use Base3\MessagingFoundation\Api\IMessageQueueRepository;
use Base3\MessagingFoundation\Api\IMessageTemplateRepository;
use Base3\MessagingFoundation\Api\IMessageTransportRegistry;
use Base3\MessagingFoundation\Api\IMessageTypeSynchronizationService;
use Base3\MessagingFoundation\Api\IMessageVariantRepository;
use PHPUnit\Framework\TestCase;
use Base3\UiFoundation\Api\IRichTextEditorDisplay;

final class MessageHubDisplayHelpTest extends TestCase {

	/** @return array<string,array{IDisplay,string}> */
	private function displays(IMvcView $view): array {
		$request = $this->createStub(IRequest::class);
		$assets = $this->createStub(IAssetResolver::class);
		$links = $this->createStub(ILinkTargetService::class);
		$auth = $this->createStub(IAuthorizationService::class);
		$db = $this->createStub(IDatabase::class);
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$queues = $this->createStub(IMessageQueueRepository::class);
		$deliveries = $this->createStub(IMessageDeliveryRepository::class);
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$language = $this->createStub(ILanguage::class);
		$ids = $this->createStub(IMessageIdGenerator::class);
		$filterOptions = new MessageFilterOptionService($db, new DatabaseSchema($db));
		$service = new MessageDeliveryService($registry, $deliveries, $queues, $this->createStub(IEventManager::class), $this->createStub(ILogger::class));

		return [
			'messagehub_dashboard_display' => [new MessageHubDashboardDisplay($view, $assets, $db, $auth), 'MessageHub dashboard.'],
			'message_queue_admin_display' => [new MessageQueueAdminDisplay($request, $view, $assets, $links, $queues, $service, $filterOptions, $auth), 'Message queue administration.'],
			'message_delivery_log_admin_display' => [new MessageDeliveryLogAdminDisplay($request, $view, $assets, $links, $deliveries, $filterOptions, $auth), 'Message delivery log.'],
			'message_template_admin_display' => [new MessageTemplateAdminDisplay($request, $view, $assets, $links, $templates, $ids, $registry, $auth), 'Message template administration.'],
			'message_transport_admin_display' => [new MessageTransportAdminDisplay($request, $view, $assets, $links, $registry, $this->createStub(ISettingsStore::class), $auth), 'Message transport administration.'],
			'message_type_sync_admin_display' => [new MessageTypeSyncAdminDisplay($request, $view, $assets, $links, $this->createStub(IMessageTypeSynchronizationService::class), $language, $auth), 'Message type synchronization.'],
			'message_variant_admin_display' => [new MessageVariantAdminDisplay($request, $view, $assets, $links,
				$this->createStub(IMessageVariantRepository::class), $templates, $ids,
				$this->createStub(IRichTextEditorDisplay::class), $language, $auth), 'Message variant administration.']
		];
	}

	public function testEveryDisplayUsesItsOwnLocalizedHelpBrick(): void {
		$view = $this->createStub(IMvcView::class);
		$view->method('getBricks')->willReturnCallback(static fn(string $key): array => ['help' => 'Localized: ' . $key]);
		foreach($this->displays($view) as $key => [$display]) {
			self::assertSame('Localized: ' . $key, $display->getHelp());
		}
	}

	public function testEveryDisplayKeepsItsFallbackForMissingAndBlankHelp(): void {
		foreach([null, [], ['help' => '  ']] as $bricks) {
			$view = $this->createStub(IMvcView::class);
			$view->method('getBricks')->willReturn($bricks);
			foreach($this->displays($view) as [$display, $fallback]) {
				self::assertSame($fallback, $display->getHelp());
			}
		}
	}

	public function testSetDataRemainsAnIntentionalNoOpForEveryAdminDisplay(): void {
		$view = $this->createStub(IMvcView::class);
		foreach($this->displays($view) as [$display, $fallback]) {
			$display->setData(['selected_id' => 'must-not-change-settings']);
			self::assertSame($fallback, $display->getHelp());
			$display->setData(null);
			self::assertSame($fallback, $display->getHelp());
		}
	}

}
