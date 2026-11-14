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
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationDecision;
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
