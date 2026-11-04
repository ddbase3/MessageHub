<?php declare(strict_types=1);

namespace MessageHub\Test\SettingsTab;

use Base3\Authorization\Api\IAuthorizationService;
use Base3\Authorization\Api\IFeature;
use Base3\Authorization\Dto\AuthorizationDecision;
use Base3\Translation\Api\ITranslation;
use MessageHub\SettingsTab\MessagedeliverylogadminSettingsTab;
use MessageHub\SettingsTab\MessagehubdashboardSettingsTab;
use MessageHub\SettingsTab\MessagequeueadminSettingsTab;
use MessageHub\SettingsTab\MessagetemplateadminSettingsTab;
use MessageHub\SettingsTab\MessagetransportadminSettingsTab;
use MessageHub\SettingsTab\MessagetypesyncadminSettingsTab;
use MessageHub\SettingsTab\MessagevariantadminSettingsTab;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use UiFoundation\Api\ISettingsTab;

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
