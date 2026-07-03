<?php declare(strict_types=1);

namespace MessageHub\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Api\IMessageTemplateRepository;
use MessagingFoundation\Api\IMessageVariantRepository;
use MessagingFoundation\Dto\MessageVariant;
use Throwable;

final class MessageVariantAdminDisplay implements IDisplay {

	use AdminDisplayTrait;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IMessageVariantRepository $variantRepository,
		private readonly IMessageTemplateRepository $templateRepository,
		private readonly IMessageIdGenerator $idGenerator
	) {}

	public static function getName(): string { return 'messagevariantadmindisplay'; }
	public function setData($data) {}
	public function getHelp(): string { return 'Message variant administration.'; }
	public function getOutput(string $out = 'html', bool $final = false): string { return strtolower($out) === 'json' ? $this->handleJson($final) : $this->handleHtml(); }

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MessageHub');
		$this->view->setTemplate('Display/MessageVariantAdminDisplay.php');
		$this->view->assign('service', $this->linkTargetService->getLink(['name' => self::getName(), 'out' => 'json']));
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string)$src));
		$this->view->assign('templateOptions', array_map(fn($tpl) => ['value' => $tpl->getId(), 'label' => $tpl->getTypeName() . ' - ' . $tpl->getLabel()], $this->templateRepository->listAll()));
		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final): string {
		try {
			$payload = $this->request->getJsonBody();
			if(!is_array($payload)) { $payload = []; }
			$mode = (string)($payload['mode'] ?? 'page');
			if($mode === 'save') {
				$id = trim((string)($payload['id'] ?? ''));
				if($id === '') { $id = $this->idGenerator->createId('var'); }
				$variant = new MessageVariant($id, trim((string)$payload['template_id']), trim((string)($payload['language'] ?? 'en')), trim((string)$payload['subject']), (string)($payload['body_text'] ?? ''), (string)($payload['body_html'] ?? ''), (string)($payload['enabled'] ?? '1') === '1');
				$this->variantRepository->save($variant);
				return $this->json(['ok' => true, 'mode' => 'save', 'id' => $id], $final);
			}
			if($mode === 'delete') {
				$this->variantRepository->delete((string)($payload['id'] ?? ''));
				return $this->json(['ok' => true, 'mode' => 'delete'], $final);
			}
			$request = $this->normalizeListRequest($payload);
			$page = $this->repositoryListPage(fn(array $listRequest): array => $this->variantRepository->page($listRequest), $request);
			return $this->json($this->pageResponse($page, $request), $final);
		} catch(Throwable $exception) {
			return $this->json(['ok' => false, 'error' => $exception->getMessage()], $final);
		}
	}
}
