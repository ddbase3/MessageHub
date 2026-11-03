<?php declare(strict_types=1);

namespace MessageHub\SettingsTab;

use UiFoundation\Settings\AbstractFeatureSettingsTab;

final class MessagequeueadminSettingsTab extends AbstractFeatureSettingsTab {

	public function getParentTabName(): ?string {
		return 'messagingsettingstab';
	}

	public function getDisplayName(): ?string {
		return 'messagequeueadmindisplay';
	}

	public function getOrder(): int {
		return 50;
	}
}
