<?php declare(strict_types=1);

namespace MessageHub\Service;

use Base3\Api\IClassMap;
use MessagingFoundation\Api\IMessageIdGenerator;
use MessagingFoundation\Api\IMessageTemplateRepository;
use MessagingFoundation\Api\IMessageTypeProvider;
use MessagingFoundation\Api\IMessageTypeSynchronizationService;
use MessagingFoundation\Api\IMessageVariantRepository;
use MessagingFoundation\Dto\MessageTemplate;
use MessagingFoundation\Dto\MessageVariant;

final class MessageTypeSynchronizationService implements IMessageTypeSynchronizationService {

	public function __construct(
		private readonly IClassMap $classMap,
		private readonly IMessageTemplateRepository $templateRepository,
		private readonly IMessageVariantRepository $variantRepository,
		private readonly IMessageIdGenerator $idGenerator
	) {}

	public function syncAll(string $language = 'en'): array {
		$result = $this->createEmptyResult();

		foreach($this->getProviders() as $provider) {
			$providerResult = $this->syncProvider($provider, $language);
			$result = $this->mergeResult($result, $providerResult);
		}

		return $result;
	}

	public function syncOne(string $typeName, string $language = 'en'): array {
		$typeName = trim($typeName);

		if($typeName === '') {
			return $this->createEmptyResult('Missing message type name.');
		}

		$provider = $this->classMap->getInstanceByInterfaceName(IMessageTypeProvider::class, $typeName);

		if(!$provider instanceof IMessageTypeProvider) {
			return $this->createEmptyResult('Message type provider not found: ' . $typeName);
		}

		return $this->syncProvider($provider, $language);
	}

	public function getProviderSummaries(): array {
		$summaries = [];

		foreach($this->getProviders() as $provider) {
			$template = $this->templateRepository->getByType($provider::getName());
			$summaries[] = [
				'name' => $provider::getName(),
				'label' => $provider->getLabel(),
				'description' => $provider->getDescription(),
				'installed' => $template !== null,
				'template_id' => $template !== null ? $template->getId() : '',
				'placeholders' => $provider->getPlaceholders()
			];
		}

		usort($summaries, fn(array $a, array $b) => strcmp((string)$a['name'], (string)$b['name']));

		return $summaries;
	}

	/**
	 * @return array<int, IMessageTypeProvider>
	 */
	private function getProviders(): array {
		$providers = [];
		$instances = $this->classMap->getInstancesByInterface(IMessageTypeProvider::class);

		foreach($instances as $instance) {
			if($instance instanceof IMessageTypeProvider) {
				$providers[] = $instance;
			}
		}

		return $providers;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function syncProvider(IMessageTypeProvider $provider, string $language): array {
		$result = $this->createEmptyResult();
		$language = $this->normalizeLanguage($language);
		$typeName = $provider::getName();
		$template = $this->templateRepository->getByType($typeName);

		if($template === null) {
			$template = new MessageTemplate(
				$this->idGenerator->createId('tpl'),
				$typeName,
				$provider->getLabel(),
				$provider->getDescription(),
				'global',
				'',
				'',
				true
			);

			$this->templateRepository->save($template);
			$result['created_templates']++;
		} else {
			$result['skipped_templates']++;
		}

		$variant = $this->variantRepository->getForTemplate($template->getId(), $language);

		if($variant === null) {
			$variant = new MessageVariant(
				$this->idGenerator->createId('var'),
				$template->getId(),
				$language,
				$provider->getDefaultSubject(),
				$provider->getDefaultBodyText(),
				$provider->getDefaultBodyHtml(),
				true
			);

			$this->variantRepository->save($variant);
			$result['created_variants']++;
		} else {
			$result['skipped_variants']++;
		}

		$result['providers'][] = [
			'name' => $typeName,
			'label' => $provider->getLabel(),
			'template_id' => $template->getId(),
			'language' => $language
		];

		return $result;
	}

	private function normalizeLanguage(string $language): string {
		$language = strtolower(trim($language));

		if($language === '') {
			return 'en';
		}

		return substr($language, 0, 12);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function createEmptyResult(string $error = ''): array {
		return [
			'ok' => $error === '',
			'error' => $error,
			'created_templates' => 0,
			'created_variants' => 0,
			'skipped_templates' => 0,
			'skipped_variants' => 0,
			'providers' => []
		];
	}

	/**
	 * @param array<string, mixed> $result
	 * @param array<string, mixed> $next
	 * @return array<string, mixed>
	 */
	private function mergeResult(array $result, array $next): array {
		$result['ok'] = (bool)$result['ok'] && (bool)$next['ok'];
		$result['error'] = trim((string)$result['error'] . ' ' . (string)$next['error']);
		$result['created_templates'] += (int)$next['created_templates'];
		$result['created_variants'] += (int)$next['created_variants'];
		$result['skipped_templates'] += (int)$next['skipped_templates'];
		$result['skipped_variants'] += (int)$next['skipped_variants'];
		$result['providers'] = array_merge($result['providers'], $next['providers']);

		return $result;
	}
}
