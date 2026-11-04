<?php declare(strict_types=1);

namespace MessageHub\Test\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\LinkTarget\Api\ILinkTargetService;
use MessageHub\Display\MessageTemplateAdminDisplay;
use MessageHub\Transport\NullMessageTransport;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Api\IMessageTemplateRepository;
use MessagingFoundation\Api\IMessageTransportRegistry;
use MessagingFoundation\Dto\MessageTemplate;
use PHPUnit\Framework\TestCase;

final class MessageTemplateAdminBranchesTest extends TestCase {

	private function display(array $payload, IMessageTemplateRepository $repository,
		?IMessageTransportRegistry $registry = null, ?IMvcView $view = null,
		?IMessageIdGenerator $ids = null, bool $allowed = true): MessageTemplateAdminDisplay {
		$request = $this->createStub(IRequest::class);
		$request->method('getJsonBody')->willReturn($payload);
		$auth = $this->createStub(IAuthorizationService::class);
		$auth->method('getDefaultAuthority')->willReturn('base3');
		$auth->method('decide')->willReturn($allowed ? AuthorizationDecision::allow() : AuthorizationDecision::deny());
		if($registry === null) {
			$registry = $this->createStub(IMessageTransportRegistry::class);
			$registry->method('getTransport')->willReturnCallback(static fn(string $name): ?\MessagingFoundation\Api\IMessageTransport => $name === 'null' ? new NullMessageTransport() : null);
		}
		if($ids === null) {
			$ids = $this->createStub(IMessageIdGenerator::class);
			$ids->method('createId')->willReturn('template-new');
		}
		return new MessageTemplateAdminDisplay($request, $view ?? $this->createStub(IMvcView::class),
			$this->createStub(IAssetResolver::class), $this->createStub(ILinkTargetService::class),
			$repository, $ids, $registry, $auth);
	}

	public function testNewTemplateUsesTrimmedTypeDefaultLabelAndKnownTransport(): void {
		$repository = $this->createMock(IMessageTemplateRepository::class);
		$repository->expects(self::once())->method('save')->with(self::callback(function(MessageTemplate $template): bool {
			self::assertSame('template-new', $template->getId());
			self::assertSame('notice', $template->getTypeName());
			self::assertSame('notice', $template->getLabel());
			self::assertSame('null', $template->getDefaultTransport());
			self::assertTrue($template->isEnabled());
			return true;
		}));
		$result = json_decode($this->display(['mode' => 'save', 'type_name' => ' notice ',
			'default_transport' => 'null'], $repository)->getOutput('json'), true);
		self::assertTrue($result['ok']);
		self::assertSame('template-new', $result['id']);
	}

	public function testUpdatingExistingTemplateAllowsKnownTransportWithoutChangingScope(): void {
		$repository = $this->createMock(IMessageTemplateRepository::class);
		$repository->expects(self::once())->method('getById')->with('t1')
			->willReturn(new MessageTemplate('t1', 'locked-type', 'Old label', 'Old description', 'project', '42'));
		$repository->expects(self::once())->method('save')->with(self::callback(function(MessageTemplate $template): bool {
			self::assertSame('locked-type', $template->getTypeName());
			self::assertSame('project', $template->getScopeType());
			self::assertSame('42', $template->getScopeId());
			self::assertSame('null', $template->getDefaultTransport());
			self::assertSame('New description', $template->getDescription());
			return true;
		}));
		$result = json_decode($this->display(['mode' => 'save', 'id' => 't1', 'type_name' => 'injected',
			'description' => 'New description', 'default_transport' => 'null'], $repository)->getOutput('json'), true);
		self::assertTrue($result['ok']);
	}

	public function testUnexpectedListModeStillUsesRepositoryPagingAndFilters(): void {
		$requests = [];
		$repository = $this->createStub(IMessageTemplateRepository::class);
		$repository->method('page')->willReturnCallback(function(array $request) use (&$requests): array {
			$requests[] = $request;
			return ['rows' => [
				['id' => 'a', 'type_name' => 'notice', 'enabled' => 1],
				['id' => 'b', 'type_name' => 'alert', 'enabled' => 0]
			], 'total' => 2];
		});
		$result = json_decode($this->display(['mode' => 'unexpected', 'search' => 'ALERT',
			'filters' => ['enabled' => 'no'], 'pageSize' => 10], $repository)->getOutput('json'), true);
		self::assertTrue($result['ok']);
		self::assertSame(1, $result['total']);
		self::assertSame('b', $result['data'][0]['id']);
		self::assertSame('', $requests[0]['search']);
		self::assertSame([], $requests[0]['filters']);
	}

	public function testTemplateDeletionRepositoryExceptionReturnsFailure(): void {
		$repository = $this->createStub(IMessageTemplateRepository::class);
		$repository->method('delete')->willThrowException(new \RuntimeException('cannot delete template'));
		$result = json_decode($this->display(['mode' => 'delete', 'id' => 't1'], $repository)->getOutput('json'), true);
		self::assertFalse($result['ok']);
		self::assertSame('cannot delete template', $result['error']);
	}

	public function testHtmlPassesConfiguredTransportsToViewWithLocalizedLabels(): void {
		$view = $this->createStub(IMvcView::class);
		$assigned = [];
		$view->method('loadTemplate')->willReturn('templates html');
		$view->method('assign')->willReturnCallback(static function(string $key, mixed $value) use (&$assigned): void { $assigned[$key] = $value; });
		$view->method('getBricks')->willReturnCallback(static fn(string $key): array => $key === 'message_template_admin_display'
			? ['transport_label_null' => 'Discard messages'] : []);
		$registry = $this->createStub(IMessageTransportRegistry::class);
		$registry->method('getTransports')->willReturn(['null' => new NullMessageTransport()]);
		self::assertSame('templates html', $this->display([], $this->createStub(IMessageTemplateRepository::class),
			$registry, $view)->getOutput());
		self::assertSame([['value' => 'null', 'label' => 'Discard messages (null)']], $assigned['transport_options']);
	}

	public function testForbiddenListNeverQueriesRepository(): void {
		$repository = $this->createMock(IMessageTemplateRepository::class);
		$repository->expects(self::never())->method('page');
		try {
			self::assertSame('', $this->display(['mode' => 'page'], $repository, null, null, null, false)->getOutput('json'));
			self::assertSame(403, http_response_code());
		} finally { http_response_code(200); }
	}
}
