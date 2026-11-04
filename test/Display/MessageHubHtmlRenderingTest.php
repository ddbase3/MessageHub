<?php declare(strict_types=1);

namespace MessageHub\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
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

final class MessageHubHtmlRenderingTest extends TestCase {

	private function view(string $template): IMvcView {
		$view = $this->createMock(IMvcView::class);
		$view->expects(self::once())->method('setTemplate')->with($template);
		$view->method('loadTemplate')->willReturn('rendered html');
		return $view;
	}

	private function authorization(): IAuthorizationService {
		$service = $this->createStub(IAuthorizationService::class);
		$service->method('getDefaultAuthority')->willReturn('base3');
		$service->method('decide')->willReturn(AuthorizationDecision::allow());
		return $service;
	}

	private function filterOptions(): MessageFilterOptionService {
		$db = $this->createStub(IDatabase::class);
		return new MessageFilterOptionService($db, new DatabaseSchema($db));
	}

	private function deliveryService(): MessageDeliveryService {
		return new MessageDeliveryService(
			$this->createStub(IMessageTransportRegistry::class),
			$this->createStub(IMessageDeliveryRepository::class),
			$this->createStub(IMessageQueueRepository::class),
			$this->createStub(IEventManager::class),
			$this->createStub(ILogger::class)
		);
	}

	public function testDashboardRendersThroughExistingView(): void {
		$display = new MessageHubDashboardDisplay($this->view('Display/MessageHubDashboardDisplay.php'),
			$this->createStub(IAssetResolver::class), $this->createStub(IDatabase::class), $this->authorization());
		self::assertSame('rendered html', $display->getOutput());
	}

	public function testQueueDisplayRendersFiltersThroughExistingView(): void {
		$display = new MessageQueueAdminDisplay($this->createStub(IRequest::class), $this->view('Display/MessageQueueAdminDisplay.php'),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$this->createStub(IMessageQueueRepository::class), $this->deliveryService(), $this->filterOptions(), $this->authorization());
		self::assertSame('rendered html', $display->getOutput());
	}

	public function testDeliveryLogDisplayRendersFiltersThroughExistingView(): void {
		$display = new MessageDeliveryLogAdminDisplay($this->createStub(IRequest::class), $this->view('Display/MessageDeliveryLogAdminDisplay.php'),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$this->createStub(IMessageDeliveryRepository::class), $this->filterOptions(), $this->authorization());
		self::assertSame('rendered html', $display->getOutput());
	}

	public function testTransportSettingsDisplayRendersThroughExistingView(): void {
		$display = new MessageTransportAdminDisplay($this->createStub(IRequest::class), $this->view('Display/MessageTransportAdminDisplay.php'),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$this->createStub(IMessageTransportRegistry::class), $this->createStub(ISettingsStore::class), $this->authorization());
		self::assertSame('rendered html', $display->getOutput());
	}

	public function testTemplateSettingsDisplayRendersTransportSelection(): void {
		$display = new MessageTemplateAdminDisplay($this->createStub(IRequest::class), $this->view('Display/MessageTemplateAdminDisplay.php'),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$this->createStub(IMessageTemplateRepository::class), $this->createStub(IMessageIdGenerator::class),
			$this->createStub(IMessageTransportRegistry::class), $this->authorization());
		self::assertSame('rendered html', $display->getOutput());
	}

	public function testVariantSettingsDisplayPassesHtmlEditorThroughExistingView(): void {
		$editor = $this->createMock(IRichTextEditorDisplay::class);
		$editor->expects(self::once())->method('setData')->with(self::callback(function(array $data): bool {
			self::assertSame('body_html', $data['name']);
			self::assertSame('HTML body', $data['aria_label']);
			return true;
		}));
		$editor->method('getOutput')->willReturn('<textarea></textarea>');
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn('de');
		$display = new MessageVariantAdminDisplay($this->createStub(IRequest::class), $this->view('Display/MessageVariantAdminDisplay.php'),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$this->createStub(IMessageVariantRepository::class), $this->createStub(IMessageTemplateRepository::class),
			$this->createStub(IMessageIdGenerator::class), $editor, $language, $this->authorization());
		self::assertSame('rendered html', $display->getOutput());
	}

	public function testTypeSynchronizationDisplayRendersProviderListThroughExistingView(): void {
		$language = $this->createStub(ILanguage::class);
		$language->method('getLanguage')->willReturn('de');
		$display = new MessageTypeSyncAdminDisplay($this->createStub(IRequest::class), $this->view('Display/MessageTypeSyncAdminDisplay.php'),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$this->createStub(IMessageTypeSynchronizationService::class), $language, $this->authorization());
		self::assertSame('rendered html', $display->getOutput());
	}
}
