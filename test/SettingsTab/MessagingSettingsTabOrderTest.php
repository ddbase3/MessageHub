<?php declare(strict_types=1);

namespace MessageHub\Test\SettingsTab;

use Base3\Translation\Api\ITranslation;
use MessageHub\SettingsTab\MessagingSettingsTab;
use PHPUnit\Framework\TestCase;

final class MessagingSettingsTabOrderTest extends TestCase {

	public function testRootSettingsOrder(): void {
		$tab = new MessagingSettingsTab($this->createStub(ITranslation::class));
		self::assertNull($tab->getParentTabName());
		self::assertSame(115, $tab->getOrder());
	}
}
