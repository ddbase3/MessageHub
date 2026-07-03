<?php declare(strict_types=1);

namespace MessageHub\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use MessagingFoundation\Api\IMessageDeliveryRepository;
use Throwable;

final class MessageDeliveryLogAdminDisplay implements IDisplay {

	use AdminDisplayTrait;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IMessageDeliveryRepository $deliveryRepository
	) {}

	public static function getName(): string { return 'messagedeliverylogadmindisplay'; }
	public function setData($data) {}
	public function getHelp(): string { return 'Message delivery log.'; }

	public function getOutput(string $out = 'html', bool $final = false): string {
		return strtolower($out) === 'json' ? $this->handleJson($final) : $this->handleHtml();
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MessageHub');
		$this->view->setTemplate('Display/MessageDeliveryLogAdminDisplay.php');
		$this->view->assign('service', $this->linkTargetService->getLink(['name' => self::getName(), 'out' => 'json']));
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string)$src));
		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final): string {
		try {
			$payload = $this->request->getJsonBody();
			if(!is_array($payload)) { $payload = []; }
			if(($payload['mode'] ?? '') === 'detail') {
				$detail = $this->deliveryRepository->detail((string)($payload['id'] ?? ''));
				return $this->json(['ok' => true, 'mode' => 'detail', 'found' => $detail !== null, 'detail' => $detail], $final);
			}
			$request = $this->normalizeListRequest($payload);
			$page = $this->repositoryListPage(fn(array $listRequest): array => $this->deliveryRepository->page($listRequest), $request);
			return $this->json($this->pageResponse($page, $request), $final);
		} catch(Throwable $exception) {
			return $this->json(['ok' => false, 'error' => $exception->getMessage()], $final);
		}
	}
}
