<?php declare(strict_types=1);

/***********************************************************************
 * This file is part of MessageHub for BASE3 Framework.
 *
 * MessageHub implements queue-based messaging for BASE3 applications.
 * It manages templates, retries, deliveries and transport discovery.
 * Transport-neutral contracts are defined by MessagingFoundation.
 *
 * Developed by Daniel Dahme
 * Licensed under GPL-3.0
 * https://www.gnu.org/licenses/gpl-3.0.en.html
 *
 * https://base3.de
 * https://github.com/ddbase3/MessageHub
 **********************************************************************/

namespace Base3\MessageHub\Display;

use Base3\CoreFoundation\Usermanager\Permission;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationRequest;
use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Api\IAssetResolver;
use Base3\CoreFoundation\Api\IDisplay;
use Base3\CoreFoundation\Api\IMvcView;
use Base3\CoreFoundation\Api\IRequest;
use Base3\CoreFoundation\LinkTarget\Api\ILinkTargetService;
use Base3\MessageHub\Service\MessageFilterOptionService;
use Base3\MessagingFoundation\Api\IMessageDeliveryRepository;
use Throwable;

final class MessageDeliveryLogAdminDisplay implements IDisplay {

	use AdminDisplayTrait;

	public function __construct(
		private readonly IRequest $request,
		private readonly IMvcView $view,
		private readonly IAssetResolver $assetResolver,
		private readonly ILinkTargetService $linkTargetService,
		private readonly IMessageDeliveryRepository $deliveryRepository,
		private readonly MessageFilterOptionService $filterOptionService,
		private readonly IAuthorizationService $settingsAuthorization
	) {}

	public static function getName(): string { return 'messagedeliverylogadmindisplay'; }
	public function setData($data) {}
	public function getHelp(): string {
		$this->view->setPath(dirname(__DIR__, 2));
		$this->view->loadBricks('Display');
		$translations = $this->view->getBricks('message_delivery_log_admin_display');

		return is_array($translations) && trim((string)($translations['help'] ?? '')) !== ''
			? (string)$translations['help']
			: 'Message delivery log.';
	}

	public function getOutput(string $out = 'html', bool $final = false): string {
		if (!$this->settingsAuthorization->decide(new AuthorizationRequest(
			$this->settingsAuthorization->getDefaultAuthority(),
			Permission::for('feature', \Base3\MessageHub\SettingsTab\MessagedeliverylogadminSettingsTab::getName())
		))->isAllowed()) {
			http_response_code(403);
			return '';
		}

		return strtolower($out) === 'json' ? $this->handleJson($final) : $this->handleHtml();
	}

	private function handleHtml(): string {
		$this->view->setPath(dirname(__DIR__, 2));
		$this->view->loadBricks('Display');
		$commonTranslations = $this->view->getBricks('messagehub_common');
		$commonTranslations = is_array($commonTranslations) ? $commonTranslations : [];
		$translations = $this->view->getBricks('message_delivery_log_admin_display');
		$translations = array_merge($commonTranslations, is_array($translations) ? $translations : []);
		$gridStrings = $this->view->getBricks('clientstack_modulargrid');
		$gridStrings = is_array($gridStrings) ? $gridStrings : [];
		$this->view->setTemplate('Display/MessageDeliveryLogAdminDisplay.php');
		$this->view->assign('translations', $translations);
		$this->view->assign('grid_strings', $gridStrings);
		$this->view->assign('service', $this->linkTargetService->getLink(['name' => self::getName(), 'out' => 'json']));
		$this->view->assign('resolve', fn($src) => $this->assetResolver->resolve((string)$src));
		$this->view->assign('transport_filter_options', $this->filterOptionService->getDeliveryTransportOptions());
		$this->view->assign('type_filter_options', $this->filterOptionService->getDeliveryTypeOptions());
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
