<?php declare(strict_types=1);

namespace MessageHub\Service;

use Base3\Api\IClassMap;
use Base3\Settings\Api\ISettingsStore;
use MessagingFoundation\Api\IMessageTransport;
use MessagingFoundation\Api\IMessageTransportRegistry;

final class MessageTransportRegistry implements IMessageTransportRegistry {

	/**
	 * @var array<string,IMessageTransport>|null
	 */
	private ?array $transports = null;

	public function __construct(
		private readonly IClassMap $classMap,
		private readonly ISettingsStore $settingsStore
	) {}

	public function getTransports(): array {
		if($this->transports !== null) {
			return $this->transports;
		}

		$this->transports = [];
		$instances = $this->classMap->getInstancesByInterface(IMessageTransport::class);

		foreach($instances as $instance) {
			if(!$instance instanceof IMessageTransport) {
				continue;
			}

			$this->transports[$instance::getName()] = $instance;
		}

		ksort($this->transports);
		return $this->transports;
	}

	public function getTransport(string $name = ''): ?IMessageTransport {
		$name = trim($name) !== '' ? trim($name) : $this->getDefaultTransportName();
		$transports = $this->getTransports();
		return $transports[$name] ?? null;
	}

	public function getDefaultTransportName(): string {
		$settings = $this->settingsStore->get('messaging', 'default', []);
		$name = trim((string)($settings['default_transport'] ?? ''));
		return $name !== '' ? $name : 'log';
	}

	public function getTransportSettings(string $name): array {
		return $this->settingsStore->get('messaging_transports', $name, []);
	}
}
