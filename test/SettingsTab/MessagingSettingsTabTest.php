<?php declare(strict_types=1);

namespace MessageHub\Test\SettingsTab;

use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Api\IFeature;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Translation\Api\ITranslation;
use MessageHub\SettingsTab\MessagingSettingsTab;
use MessageHub\SettingsTab\MessagetemplateadminSettingsTab;
use PHPUnit\Framework\TestCase;
use UiFoundation\Api\ISettingsTab;

final class MessagingSettingsTabTest extends TestCase {

	public function testMessagingSettingsAndFeatureHaveSeparateResponsibilities(): void {
		$translation = $this->createMock(ITranslation::class);
		$translation->expects(self::exactly(2))->method('translate')
			->willReturnCallback(function(string $set, string $section, string $key): string {
				self::assertSame('Settings', $set);
				self::assertSame('messagetemplateadminsettingstab', $section);
				return $key;
			});
		$authorization = $this->createMock(IAuthorizationService::class);
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
		$tab = new MessagingSettingsTab($this->createMock(ITranslation::class));
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
