<?php declare(strict_types=1);

namespace MessageHub\Display;

use Base3\Api\IAssetResolver;
use Base3\Api\IDisplay;
use Base3\Api\IMvcView;
use Base3\Api\IRequest;
use Base3\LinkTarget\Api\ILinkTargetService;
use Base3\Settings\Api\ISettingsStore;
use MessagingFoundation\Api\IMessageTransportRegistry;
use Throwable;

final class MessageTransportAdminDisplay implements IDisplay {

	use AdminDisplayTrait;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IMessageTransportRegistry $transportRegistry,
		private readonly ISettingsStore $settingsStore
	) {}

	public static function getName(): string { return 'messagetransportadmindisplay'; }
	public function setData($data) {}
	public function getHelp(): string { return 'Message transport administration.'; }
	public function getOutput(string $out = 'html', bool $final = false): string { return strtolower($out) === 'json' ? $this->handleJson($final) : $this->handleHtml(); }

	private function handleHtml(): string {
		$this->view->setPath(DIR_PLUGIN . 'MessageHub');
		$this->view->setTemplate('Display/MessageTransportAdminDisplay.php');
		$this->view->assign('service', $this->linkTargetService->getLink(['name' => self::getName(), 'out' => 'json']));
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string)$src));
		return $this->view->loadTemplate();
	}

	private function handleJson(bool $final): string {
		try {
			$payload = $this->request->getJsonBody();
			if(!is_array($payload)) { $payload = []; }
			if(($payload['mode'] ?? '') === 'save-transport') {
				$name = trim((string)($payload['name'] ?? ''));
				$settings = $payload['settings'] ?? [];
				if($name === '' || !is_array($settings)) {
					return $this->json(['ok' => false, 'error' => 'Invalid transport settings payload.'], $final);
				}
				$this->settingsStore->set('messaging_transports', $name, $settings);
				$this->settingsStore->save();
				return $this->json(['ok' => true, 'mode' => 'save-transport'], $final);
			}
			if(($payload['mode'] ?? '') === 'save-default') {
				$settings = $this->settingsStore->get('messaging', 'default', []);
				$settings['default_transport'] = trim((string)($payload['default_transport'] ?? ''));
				$this->settingsStore->set('messaging', 'default', $settings);
				$this->settingsStore->save();
				return $this->json(['ok' => true], $final);
			}

			$request = $this->normalizeListRequest($payload);
			$page = $this->listPageFromRows($this->loadRows(), $request);

			return $this->json($this->pageResponse($page, $request), $final);
		} catch(Throwable $exception) {
			return $this->json(['ok' => false, 'error' => $exception->getMessage()], $final);
		}
	}

	private function loadRows(): array {
		$rows = [];
		foreach($this->transportRegistry->getTransports() as $name => $transport) {
			$rows[] = [
				'name' => $name,
				'label' => $transport->getLabel(),
				'is_default' => $name === $this->transportRegistry->getDefaultTransportName() ? 1 : 0,
				'schema_json' => json_encode($transport->getSchema(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
				'settings_json' => json_encode($this->transportRegistry->getTransportSettings($name), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
			];
		}

		return $rows;
	}

	private function filterRows(array $rows, array $request): array {
		$search = strtolower((string)($request['search'] ?? ''));
		$filters = is_array($request['filters'] ?? null) ? $request['filters'] : [];

		return array_values(array_filter($rows, function(array $row) use ($search, $filters): bool {
			if($search !== '') {
				$haystack = strtolower(implode(' ', [(string)($row['name'] ?? ''), (string)($row['label'] ?? ''), (string)($row['settings_json'] ?? ''), (string)($row['schema_json'] ?? '')]));
				if(strpos($haystack, $search) === false) { return false; }
			}

			$name = isset($filters['name']) && is_scalar($filters['name']) ? strtolower(trim((string)$filters['name'])) : '';
			if($name !== '' && strpos(strtolower((string)($row['name'] ?? '')), $name) === false) { return false; }

			$default = isset($filters['is_default']) && is_scalar($filters['is_default']) ? trim((string)$filters['is_default']) : '';
			if($default !== '' && (string)(int)($row['is_default'] ?? 0) !== $default) { return false; }

			return true;
		}));
	}
}
