<?php declare(strict_types=1);

namespace MessageHub\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\LinkTarget\Api\ILinkTargetService;
use MessageHub\Display\MessageTemplateAdminDisplay;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Api\IMessageTemplateRepository;
use MessagingFoundation\Api\IMessageTransportRegistry;
use MessagingFoundation\Dto\MessageTemplate;
use PHPUnit\Framework\TestCase;

final class MessageTemplateAdminDisplayTest extends TestCase {

	private function display(array $payload, bool $allowed, ?IMessageTemplateRepository $templates = null,
		?IMessageTransportRegistry $transports = null): MessageTemplateAdminDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$auth = $this->createStub(IAuthorizationService::class);
		$auth->method('getDefaultAuthority')->willReturn('base3');
		$auth->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		$ids = $this->createStub(IMessageIdGenerator::class);
		$ids->method('createId')->willReturn('tpl-new');
		return new MessageTemplateAdminDisplay($request, $this->createStub(IMvcView::class),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$templates ?? $this->createStub(IMessageTemplateRepository::class), $ids,
			$transports ?? $this->createStub(IMessageTransportRegistry::class), $auth);
	}

	public function testUnauthorizedSaveDoesNotModifyTemplates(): void {
		$repository = $this->createMock(IMessageTemplateRepository::class);
		$repository->expects(self::never())->method('save');
		$display = $this->display(['mode' => 'save', 'type_name' => 'alert'], false, $repository);
		try {
			self::assertSame('', $display->getOutput('json'));
			self::assertSame(403, http_response_code());
		} finally { http_response_code(200); }
	}

	public function testMissingTypeAndUnknownTransportReturnValidationErrors(): void {
		$missing = json_decode($this->display(['mode' => 'save'], true)->getOutput('json'), true);
		self::assertFalse($missing['ok']);
		self::assertStringContainsString('type ID is required', $missing['error']);
		$unknown = json_decode($this->display(['mode' => 'save', 'type_name' => 'alert', 'default_transport' => 'unknown'], true)->getOutput('json'), true);
		self::assertFalse($unknown['ok']);
		self::assertStringContainsString('Unknown default transport', $unknown['error']);
	}

	public function testSavingNewTemplatePreservesDefaultScopeAndEnabledFlag(): void {
		$repository = $this->createMock(IMessageTemplateRepository::class);
		$repository->expects(self::once())->method('save')->with(self::callback(function(MessageTemplate $template): bool {
			self::assertSame('tpl-new', $template->getId());
			self::assertSame('alert', $template->getTypeName());
			self::assertSame('global', $template->getScopeType());
			self::assertFalse($template->isEnabled());
			return true;
		}));
		$result = json_decode($this->display(['mode' => 'save', 'type_name' => 'alert', 'enabled' => '0'], true, $repository)->getOutput('json'), true);
		self::assertTrue($result['ok']);
		self::assertSame('tpl-new', $result['id']);
	}

	public function testDeletingTemplateForwardsIdentifier(): void {
		$repository = $this->createMock(IMessageTemplateRepository::class);
		$repository->expects(self::once())->method('delete')->with('tpl-42');
		$result = json_decode($this->display(['mode' => 'delete', 'id' => 'tpl-42'], true, $repository)->getOutput('json'), true);
		self::assertTrue($result['ok']);
		self::assertSame('delete', $result['mode']);
	}


	public function testUpdatingMissingTemplateReturnsValidationErrorWithoutSaving(): void {
		$repository = $this->createMock(IMessageTemplateRepository::class);
		$repository->expects(self::once())->method('getById')->with('missing')->willReturn(null);
		$repository->expects(self::never())->method('save');
		$result = json_decode($this->display(['mode' => 'save', 'id' => 'missing', 'type_name' => 'notices'], true, $repository)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertSame('Message template not found.', $result['error']);
	}

	public function testUpdatingTemplatePreservesTypeAndScopeFromSavedRecord(): void {
		$existing = new MessageTemplate('tpl-old', 'locked-type', 'Old', '', 'project', 'project-7', 'log', true);
		$repository = $this->createMock(IMessageTemplateRepository::class);
		$repository->expects(self::once())->method('getById')->with('tpl-old')->willReturn($existing);
		$repository->expects(self::once())->method('save')->with(self::callback(function(MessageTemplate $template): bool {
			self::assertSame('tpl-old', $template->getId());
			self::assertSame('locked-type', $template->getTypeName());
			self::assertSame('project', $template->getScopeType());
			self::assertSame('project-7', $template->getScopeId());
			self::assertSame('Changed', $template->getLabel());
			self::assertFalse($template->isEnabled());
			return true;
		}));
		$result = json_decode($this->display(['mode' => 'save', 'id' => 'tpl-old', 'type_name' => 'untrusted',
			'label' => ' Changed ', 'enabled' => '0'], true, $repository)->getOutput('json'), true);
		self::assertTrue($result['ok']);
	}

	public function testRepositoryExceptionIsReturnedAsFailureWithoutSaving(): void {
		$repository = $this->createStub(IMessageTemplateRepository::class);
		$repository->method('getById')->willReturnCallback(static function(): ?MessageTemplate {
			throw new \RuntimeException('repository unavailable');
		});
		$result = json_decode($this->display(['mode' => 'save', 'id' => 'existing'], true, $repository)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertSame('repository unavailable', $result['error']);
	}

}
