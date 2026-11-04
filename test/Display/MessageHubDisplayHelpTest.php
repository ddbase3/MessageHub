<?php declare(strict_types=1);

namespace MessageHub\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Database\Api\IDatabase;
use Base3\Event\Api\IEventManager;
use Base3\Language\Api\ILanguage;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Logger\Api\ILogger;
use Base3\Settings\Api\ISettingsStore;
use MessageHub\Display\MessageDeliveryLogAdminDisplay;
use MessageHub\Display\MessageHubDashboardDisplay;
use MessageHub\Display\MessageQueueAdminDisplay;
use MessageHub\Display\MessageTemplateAdminDisplay;
use MessageHub\Display\MessageTransportAdminDisplay;
use MessageHub\Display\MessageTypeSyncAdminDisplay;
use MessageHub\Display\MessageVariantAdminDisplay;
use MessageHub\Repository\DatabaseSchema;
use MessageHub\Service\MessageDeliveryService;
use MessageHub\Service\MessageFilterOptionService;
use MessagingFoundation\Api\IMessageDeliveryRepository;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Api\IMessageQueueRepository;
use MessagingFoundation\Api\IMessageTemplateRepository;
use MessagingFoundation\Api\IMessageTransportRegistry;
use MessagingFoundation\Api\IMessageTypeSynchronizationService;
use MessagingFoundation\Api\IMessageVariantRepository;
use PHPUnit\Framework\TestCase;
use UiFoundation\Api\IRichTextEditorDisplay;

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
