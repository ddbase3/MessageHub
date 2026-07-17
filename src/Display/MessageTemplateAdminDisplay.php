<?php declare(strict_types=1);

namespace MessageHub\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use InvalidArgumentException;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Api\IMessageTemplateRepository;
use MessagingFoundation\Api\IMessageTransportRegistry;
use MessagingFoundation\Dto\MessageTemplate;
use Throwable;

final class MessageTemplateAdminDisplay implements IDisplay {

	use AdminDisplayTrait;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IMessageTemplateRepository $templateRepository,
		private readonly IMessageIdGenerator $idGenerator,
		private readonly IMessageTransportRegistry $transportRegistry
	) {}

	public static function getName(): string { return 'messagetemplateadmindisplay'; }
	public function setData($data) {}
	public function getHelp(): string { return 'Message template administration.'; }
	public function getOutput(string $out = 'html', bool $final = false): string { return strtolower($out) === 'json' ? $this->handleJson($final) : $this->handleHtml(); }

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MessageHub');
		$this->view->setTemplate('Display/MessageTemplateAdminDisplay.php');
		$this->view->assign('service', $this->linkTargetService->getLink(['name' => self::getName(), 'out' => 'json']));
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string)$src));
		$this->view->assign('transport_options', $this->getTransportOptions());
		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final): string {
		try {
			$payload = $this->request->getJsonBody();
			if(!is_array($payload)) { $payload = []; }
			$mode = (string)($payload['mode'] ?? 'page');
			if($mode === 'save') {
				$id = trim((string)($payload['id'] ?? ''));
				$existing = null;
				if($id === '') {
					$id = $this->idGenerator->createId('tpl');
				} else {
					$existing = $this->templateRepository->getById($id);
					if($existing === null) { throw new InvalidArgumentException('Message template not found.'); }
				}

				$typeName = $existing !== null ? $existing->getTypeName() : trim((string)($payload['type_name'] ?? ''));
				if($typeName === '') { throw new InvalidArgumentException('Message type ID is required.'); }

				$defaultTransport = trim((string)($payload['default_transport'] ?? ''));
				if($defaultTransport !== '' && $this->transportRegistry->getTransport($defaultTransport) === null) {
					throw new InvalidArgumentException('Unknown default transport: ' . $defaultTransport);
				}

				$template = new MessageTemplate(
					$id,
					$typeName,
					trim((string)($payload['label'] ?? $typeName)),
					trim((string)($payload['description'] ?? '')),
					$existing !== null ? $existing->getScopeType() : 'global',
					$existing !== null ? $existing->getScopeId() : '',
					$defaultTransport,
					(string)($payload['enabled'] ?? '1') === '1'
				);
				$this->templateRepository->save($template);
				return $this->json(['ok' => true, 'mode' => 'save', 'id' => $id], $final);
			}
			if($mode === 'delete') {
				$this->templateRepository->delete((string)($payload['id'] ?? ''));
				return $this->json(['ok' => true, 'mode' => 'delete'], $final);
			}
			$request = $this->normalizeListRequest($payload);
			$page = $this->repositoryListPage(fn(array $listRequest): array => $this->templateRepository->page($listRequest), $request);
			return $this->json($this->pageResponse($page, $request), $final);
		} catch(Throwable $exception) {
			return $this->json(['ok' => false, 'error' => $exception->getMessage()], $final);
		}
	}

	private function getTransportOptions(): array {
		$options = [];

		foreach($this->transportRegistry->getTransports() as $name => $transport) {
			$options[] = [
				'value' => $name,
				'label' => $transport->getLabel() . ' (' . $name . ')'
			];
		}

		return $options;
	}
}
