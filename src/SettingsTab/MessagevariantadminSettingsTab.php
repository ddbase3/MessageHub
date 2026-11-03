<?php declare(strict_types=1);

namespace MessageHub\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class MessagevariantadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'messagingsettingstab';
	}

	public function getDisplayName(): ?string {
		return 'messagevariantadmindisplay';
	}

	public function getOrder(): int {
		return 30;
	}
}
