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

namespace Base3\MessageHub\Test\SettingsTab;

use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\MessageHub\SettingsTab\MessagingSettingsTab;
use PHPUnit\Framework\TestCase;

final class MessagingSettingsTabOrderTest extends TestCase {

	public function testRootSettingsOrder(): void {
		$tab = new MessagingSettingsTab($this->createStub(ITranslation::class));
		self::assertNull($tab->getParentTabName());
		self::assertSame(115, $tab->getOrder());
	}
}
