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

use Base3\CoreFoundation\Authorization\Api\IAuthorizationService;
use Base3\CoreFoundation\Authorization\Api\IFeature;
use Base3\CoreFoundation\Authorization\Dto\AuthorizationDecision;
use Base3\CoreFoundation\Translation\Api\ITranslation;
use Base3\MessageHub\SettingsTab\MessagingSettingsTab;
use Base3\MessageHub\SettingsTab\MessagetemplateadminSettingsTab;
use PHPUnit\Framework\TestCase;
use Base3\UiFoundation\Api\ISettingsTab;

final class MessagingSettingsTabTest extends TestCase {

	public function testMessagingSettingsAndFeatureHaveSeparateResponsibilities(): void {
		$translation = $this->createMock(ITranslation::class);
		$translation->expects(self::exactly(2))->method('translate')
			->willReturnCallback(function(string $set, string $section, string $key): string {
				self::assertSame('Settings', $set);
				self::assertSame('messagetemplateadminsettingstab', $section);
				return $key;
			});
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('base3');
		$authorization->method('decide')->willReturn(AuthorizationDecision::deny());
		$tab = new MessagetemplateadminSettingsTab($translation, $authorization);
		self::assertInstanceOf(ISettingsTab::class, $tab);
		self::assertInstanceOf(IFeature::class, $tab);
		self::assertSame('messagingsettingstab', $tab->getParentTabName());
		self::assertSame('messagetemplateadmindisplay', $tab->getDisplayName());
		self::assertSame(['group' => 'administration'], $tab->getDefaultAudience());
		self::assertSame('feature', $tab->getPermissions()[0]->scope);
		self::assertSame(MessagetemplateadminSettingsTab::getName(), $tab->getPermissions()[0]->permission);
		self::assertSame('label', $tab->getLabel());
		self::assertSame('description', $tab->getDescription());
		self::assertFalse($tab->isVisible());
	}

	public function testRootIsOnlyNavigation(): void {
		$tab = new MessagingSettingsTab($this->createStub(ITranslation::class));
		self::assertNull($tab->getParentTabName());
		self::assertNull($tab->getDisplayName());
		self::assertNull($tab->getDisplayData());
		self::assertTrue($tab->isVisible());
	}

	public function testAllSupportedTranslationsAreOwnedByMessageHub(): void {
		$root = dirname(__DIR__, 2);
		$classes = [
			'messagingsettingstab', 'messagehubdashboardsettingstab',
			'messagetemplateadminsettingstab', 'messagevariantadminsettingstab',
			'messagetypesyncadminsettingstab', 'messagequeueadminsettingstab',
			'messagedeliverylogadminsettingstab', 'messagetransportadminsettingstab'
		];
		$languages = array_map(static fn(string $name): string => basename($name, '.ini'),
			glob($root . '/lang/Display/*.ini') ?: []);
		self::assertNotEmpty($languages);
		foreach ($languages as $language) {
			$translations = parse_ini_file($root . '/lang/Settings/' . $language . '.ini', true, INI_SCANNER_RAW);
			self::assertIsArray($translations, $language);
			foreach ($classes as $name) {
				self::assertNotEmpty($translations[$name]['label'] ?? '', $language . '/' . $name . '/label');
				self::assertNotEmpty($translations[$name]['description'] ?? '', $language . '/' . $name . '/description');
			}
		}
	}
}
