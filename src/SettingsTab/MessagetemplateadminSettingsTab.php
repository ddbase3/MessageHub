<?php declare(strict_types=1);

namespace MessageHub\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class MessagetemplateadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'messagingsettingstab';
	}

	public function getDisplayName(): ?string {
		return 'messagetemplateadmindisplay';
	}

	public function getOrder(): int {
		return 20;
	}
}
