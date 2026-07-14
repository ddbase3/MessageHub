<?php declare(strict_types=1);

namespace MessageHub\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use MessageHub\Service\MessageDeliveryService;
use MessageHub\Service\MessageFilterOptionService;
use MessagingFoundation\Api\IMessageQueueRepository;
use Throwable;

final class MessageQueueAdminDisplay implements IDisplay {

	use AdminDisplayTrait;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IMessageQueueRepository $queueRepository,
		private readonly MessageDeliveryService $deliveryService,
		private readonly MessageFilterOptionService $filterOptionService
	) {}

	public static function getName(): string { return 'messagequeueadmindisplay'; }
	public function setData($data) {}
	public function getHelp(): string { return 'Message queue administration.'; }

	public function getOutput(string $out = 'html', bool $final = false): string {
		return strtolower($out) === 'json' ? $this->handleJson($final) : $this->handleHtml();
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MessageHub');
		$this->view->setTemplate('Display/MessageQueueAdminDisplay.php');
		$this->view->assign('service', $this->linkTargetService->getLink(['name' => self::getName(), 'out' => 'json']));
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string)$src));
		$this->view->assign('transport_filter_options', $this->filterOptionService->getQueueTransportOptions());
		$this->view->assign('type_filter_options', $this->filterOptionService->getQueueTypeOptions());
		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final): string {
		try {
			$payload = $this->request->getJsonBody();
			if(!is_array($payload)) { $payload = []; }
			$mode = (string)($payload['mode'] ?? 'page');
			if($mode === 'process') {
				$count = $this->deliveryService->processBatch((int)($payload['limit'] ?? 10));
				return $this->json(['ok' => true, 'mode' => 'process', 'processed' => $count], $final);
			}
			if($mode === 'cancel') {
				$this->queueRepository->cancel((string)($payload['id'] ?? ''));
				return $this->json(['ok' => true, 'mode' => 'cancel'], $final);
			}
			$request = $this->normalizeListRequest($payload);
			$page = $this->repositoryListPage(fn(array $listRequest): array => $this->queueRepository->page($listRequest), $request);
			return $this->json($this->pageResponse($page, $request), $final);
		} catch(Throwable $exception) {
			return $this->json(['ok' => false, 'error' => $exception->getMessage()], $final);
		}
	}
}
