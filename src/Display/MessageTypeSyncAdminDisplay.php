<?php declare(strict_types=1);

namespace MessageHub\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use MessagingFoundation\Api\IMessageTypeSynchronizationService;
use Throwable;

final class MessageTypeSyncAdminDisplay implements IDisplay {

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IMessageTypeSynchronizationService $messageTypeSynchronizationService
	) {}

	public static function getName(): string {
		return 'messagetypesyncadmindisplay';
	}

	public function setData($data) {
		// no-op
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		$out = strtolower((string) $out);

		if($out === 'json') {
			return $this->handleJson($final);
		}

		return $this->handleHtml();
	}

	public function getHelp(): string {
		return 'Message type synchronization.';
	}

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MessageHub');
		$this->view->setTemplate('Display/MessageTypeSyncAdminDisplay.php');
		$this->view->assign(
			'service',
			$this->linkTargetService->getLink(
				[
					'name' => self::getName(),
					'out' => 'json'
				]
			)
		);
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string) $src));
		$this->view->assign('providers', $this->messageTypeSynchronizationService->getProviderSummaries());

		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final = false): string {
		try {
			$response = $this->buildJsonResponse();
		} catch(Throwable $e) {
			$response = [
				'ok' => false,
				'error' => 'Message type synchronization request failed.',
				'details' => $e->getMessage(),
			];
		}

		if($final && !headers_sent()) {
			header('Content-Type: application/json; charset=utf-8');
		}

		return (string) json_encode(
			$response,
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildJsonResponse(): array {
		$payload = $this->request->getJsonBody();

		if(!is_array($payload)) {
			$payload = [];
		}

		$mode = (string) ($payload['mode'] ?? 'providers');
		$language = (string) ($payload['language'] ?? 'en');

		if($mode === 'sync-all') {
			return $this->messageTypeSynchronizationService->syncAll($language);
		}

		if($mode === 'sync-one') {
			return $this->messageTypeSynchronizationService->syncOne((string) ($payload['type_name'] ?? ''), $language);
		}

		return [
			'ok' => true,
			'mode' => 'providers',
			'providers' => $this->messageTypeSynchronizationService->getProviderSummaries()
		];
	}
}
