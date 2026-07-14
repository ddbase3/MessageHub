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
				if(!$this->hasTransport($name) || !is_array($settings)) {
					return $this->json(['ok' => false, 'error' => 'Invalid transport settings payload.'], $final);
				}

				$currentSettings = $this->settingsStore->get('messaging_transports', $name, []);
				unset($settings['enabled']);

				if(array_key_exists('enabled', $currentSettings)) {
					$settings['enabled'] = $this->readBool($currentSettings['enabled'], false);
				}

				$this->settingsStore->set('messaging_transports', $name, $settings);
				$this->settingsStore->save();
				return $this->json(['ok' => true, 'mode' => 'save-transport'], $final);
			}

			if(($payload['mode'] ?? '') === 'set-enabled') {
				$name = trim((string)($payload['name'] ?? ''));
				if(!$this->hasTransport($name)) {
					return $this->json(['ok' => false, 'error' => 'Unknown message transport.'], $final);
				}

				$settings = $this->settingsStore->get('messaging_transports', $name, []);
				$settings['enabled'] = $this->readBool($payload['enabled'] ?? false, false);
				$this->settingsStore->set('messaging_transports', $name, $settings);
				$this->settingsStore->save();

				return $this->json([
					'ok' => true,
					'mode' => 'set-enabled',
					'name' => $name,
					'enabled' => $settings['enabled']
				], $final);
			}

			if(($payload['mode'] ?? '') === 'reset-transport') {
				$name = trim((string)($payload['name'] ?? ''));
				if(!$this->hasTransport($name)) {
					return $this->json(['ok' => false, 'error' => 'Unknown message transport.'], $final);
				}

				$this->settingsStore->remove('messaging_transports', $name);
				$this->settingsStore->save();

				return $this->json(['ok' => true, 'mode' => 'reset-transport', 'name' => $name], $final);
			}

			if(($payload['mode'] ?? '') === 'save-default') {
				$name = trim((string)($payload['default_transport'] ?? ''));
				if(!$this->hasTransport($name)) {
					return $this->json(['ok' => false, 'error' => 'Unknown message transport.'], $final);
				}

				$settings = $this->settingsStore->get('messaging', 'default', []);
				$settings['default_transport'] = $name;
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

	private function hasTransport(string $name): bool {
		return $name !== '' && $this->transportRegistry->getTransport($name) !== null;
	}

	private function loadRows(): array {
		$rows = [];
		foreach($this->transportRegistry->getTransports() as $name => $transport) {
			$settings = $this->transportRegistry->getTransportSettings($name);
			$schema = $transport->getSchema();
			$rows[] = [
				'name' => $name,
				'label' => $transport->getLabel(),
				'is_enabled' => $this->isTransportEnabled($settings, $schema) ? 1 : 0,
				'is_default' => $name === $this->transportRegistry->getDefaultTransportName() ? 1 : 0,
				'settings_summary' => $transport->getSettingsSummary($settings),
				'schema_json' => json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
				'settings_json' => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
			];
		}

		return $rows;
	}

	private function isTransportEnabled(array $settings, array $schema): bool {
		if(array_key_exists('enabled', $settings)) {
			return $this->readBool($settings['enabled'], false);
		}

		$properties = isset($schema['properties']) && is_array($schema['properties']) ? $schema['properties'] : [];
		$enabled = isset($properties['enabled']) && is_array($properties['enabled']) ? $properties['enabled'] : [];

		if(array_key_exists('default', $enabled)) {
			return $this->readBool($enabled['default'], false);
		}

		return $properties === [];
	}

	private function readBool(mixed $value, bool $default): bool {
		if(is_bool($value)) {
			return $value;
		}

		if(is_scalar($value)) {
			$normalized = strtolower(trim((string)$value));
			if(in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true)) {
				return true;
			}
			if(in_array($normalized, ['0', 'false', 'no', 'off', 'disabled', ''], true)) {
				return false;
			}
		}

		return $default;
	}

	private function filterRows(array $rows, array $request): array {
		$search = strtolower((string)($request['search'] ?? ''));
		$filters = is_array($request['filters'] ?? null) ? $request['filters'] : [];

		return array_values(array_filter($rows, function(array $row) use ($search, $filters): bool {
			if($search !== '') {
				$haystack = strtolower(implode(' ', [(string)($row['name'] ?? ''), (string)($row['label'] ?? ''), (string)($row['settings_summary'] ?? ''), (string)($row['settings_json'] ?? ''), (string)($row['schema_json'] ?? '')]));
				if(strpos($haystack, $search) === false) { return false; }
			}

			$name = isset($filters['name']) && is_scalar($filters['name']) ? strtolower(trim((string)$filters['name'])) : '';
			if($name !== '' && strpos(strtolower((string)($row['name'] ?? '')), $name) === false) { return false; }

			$enabled = isset($filters['is_enabled']) && is_scalar($filters['is_enabled']) ? trim((string)$filters['is_enabled']) : '';
			if($enabled !== '' && (string)(int)($row['is_enabled'] ?? 0) !== $enabled) { return false; }

			$default = isset($filters['is_default']) && is_scalar($filters['is_default']) ? trim((string)$filters['is_default']) : '';
			if($default !== '' && (string)(int)($row['is_default'] ?? 0) !== $default) { return false; }

			return true;
		}));
	}
}
