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
use Base3\MessageHub\SettingsTab\MessagedeliverylogadminSettingsTab;
use Base3\MessageHub\SettingsTab\MessagehubdashboardSettingsTab;
use Base3\MessageHub\SettingsTab\MessagequeueadminSettingsTab;
use Base3\MessageHub\SettingsTab\MessagetemplateadminSettingsTab;
use Base3\MessageHub\SettingsTab\MessagetransportadminSettingsTab;
use Base3\MessageHub\SettingsTab\MessagetypesyncadminSettingsTab;
use Base3\MessageHub\SettingsTab\MessagevariantadminSettingsTab;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Base3\UiFoundation\Api\ISettingsTab;

final class MessageHubFeatureSettingsTabsTest extends TestCase {

	private const TABS = [
		[MessagehubdashboardSettingsTab::class, 'messagehubdashboarddisplay', 10],
		[MessagetemplateadminSettingsTab::class, 'messagetemplateadmindisplay', 20],
		[MessagevariantadminSettingsTab::class, 'messagevariantadmindisplay', 30],
		[MessagetypesyncadminSettingsTab::class, 'messagetypesyncadmindisplay', 40],
		[MessagequeueadminSettingsTab::class, 'messagequeueadmindisplay', 50],
		[MessagedeliverylogadminSettingsTab::class, 'messagedeliverylogadmindisplay', 60],
		[MessagetransportadminSettingsTab::class, 'messagetransportadmindisplay', 70]
	];

	private function authorization(AuthorizationDecision $decision): IAuthorizationService {
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willReturn('base3');
		$authorization->method('decide')->willReturn($decision);
		return $authorization;
	}

	public function testEveryFeatureTabExposesItsNavigationTargetAndUniquePermission(): void {
		$translation = $this->createStub(ITranslation::class);
		$translation->method('translate')->willReturnCallback(static fn(string $set, string $section, string $key): string => $key);
		foreach(self::TABS as [$class, $displayName, $order]) {
			$tab = new $class($translation, $this->authorization(AuthorizationDecision::deny()));
			self::assertInstanceOf(ISettingsTab::class, $tab);
			self::assertInstanceOf(IFeature::class, $tab);
			self::assertSame('messagingsettingstab', $tab->getParentTabName());
			self::assertSame($displayName, $tab->getDisplayName());
			self::assertSame($order, $tab->getOrder());
			self::assertSame('label', $tab->getLabel());
			self::assertSame('description', $tab->getDescription());
			self::assertSame(['group' => 'administration'], $tab->getDefaultAudience());
			self::assertSame('feature', $tab->getPermissions()[0]->scope);
			self::assertSame($class::getName(), $tab->getPermissions()[0]->permission);
			self::assertNull($tab->getDisplayData());
		}
	}

	public function testEveryFeatureTabVisibilityUsesAuthoritativeDecisions(): void {
		$translation = $this->createStub(ITranslation::class);
		foreach(self::TABS as [$class]) {
			self::assertTrue((new $class($translation, $this->authorization(AuthorizationDecision::allow())))->isVisible(), $class);
			self::assertFalse((new $class($translation, $this->authorization(AuthorizationDecision::deny())))->isVisible(), $class);
			self::assertFalse((new $class($translation, $this->authorization(AuthorizationDecision::indeterminate())))->isVisible(), $class);
		}
	}

	public function testAuthorizationFailureCannotMakeFeatureTabVisible(): void {
		$authorization = $this->createStub(IAuthorizationService::class);
		$authorization->method('getDefaultAuthority')->willThrowException(new RuntimeException('unavailable'));
		$tab = new MessagetransportadminSettingsTab($this->createStub(ITranslation::class), $authorization);
		self::assertFalse($tab->isVisible());
	}
}
