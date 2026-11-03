<?php declare(strict_types=1);

namespace MessageHub\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class MessagetypesyncadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'messagingsettingstab';
	}

	public function getDisplayName(): ?string {
		return 'messagetypesyncadmindisplay';
	}

	public function getOrder(): int {
		return 40;
	}
}
