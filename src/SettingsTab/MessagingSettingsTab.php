<?php declare(strict_types=1);

namespace MessageHub\SettingsTab;

use UiFoundation\Settings\AbstractSettingsTab;

final class MessagingSettingsTab extends AbstractSettingsTab {

	public function getOrder(): int {
		return 115;
	}
}
