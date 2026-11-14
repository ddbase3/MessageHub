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

namespace Base3\MessageHub\Test\Service;

require_once __DIR__ . '/../Fixture/MessageTypeFixture.php';

use Base3\CoreFoundation\Api\IClassMap;
use Base3\MessageHub\Service\MessageTypeSynchronizationService;
use Base3\MessageHub\Test\Fixture\MessageTypeFixture;
use Base3\MessagingFoundation\Api\IMessageIdGenerator;
use Base3\MessagingFoundation\Api\IMessageTemplateRepository;
use Base3\MessagingFoundation\Api\IMessageVariantRepository;
use Base3\MessagingFoundation\Dto\MessageTemplate;
use Base3\MessagingFoundation\Dto\MessageVariant;
use PHPUnit\Framework\TestCase;

final class MessageTypeSynchronizationServiceTest extends TestCase {

	public function testSyncOneRejectsEmptyAndUnknownType(): void {
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstanceByInterfaceName')->willReturn(null);
		$service = new MessageTypeSynchronizationService($classMap,
			$this->createStub(IMessageTemplateRepository::class), $this->createStub(IMessageVariantRepository::class), $this->createStub(IMessageIdGenerator::class));
		self::assertSame('Missing message type name.', $service->syncOne('   ')['error']);
		self::assertSame('Message type provider not found: unknown', $service->syncOne(' unknown ')['error']);
		self::assertFalse($service->syncOne('unknown')['ok']);
	}

	public function testCreatesTemplateAndVariantUsingProviderDefaults(): void {
		$provider = new MessageTypeFixture();
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstanceByInterfaceName')->willReturn($provider);
		$templates = $this->createMock(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn(null);
		$templates->expects(self::once())->method('save')->with(self::callback(function(MessageTemplate $template): bool {
			self::assertSame('tpl-1', $template->getId());
			self::assertSame('samplemessagetype', $template->getTypeName());
			self::assertSame('Sample message', $template->getLabel());
			return true;
		}));
		$variants = $this->createMock(IMessageVariantRepository::class);
		$variants->method('getForTemplate')->willReturn(null);
		$variants->expects(self::once())->method('save')->with(self::callback(function(MessageVariant $variant): bool {
			self::assertSame('var-1', $variant->getId());
			self::assertSame('tpl-1', $variant->getTemplateId());
			self::assertSame('en', $variant->getLanguage());
			self::assertSame('Hello {{name}}', $variant->getSubject());
			return true;
		}));
		$generator = $this->createMock(IMessageIdGenerator::class);
		$generator->expects(self::exactly(2))->method('createId')->willReturnCallback(function(string $prefix): string {
			return $prefix === 'tpl' ? 'tpl-1' : 'var-1';
		});
		$result = (new MessageTypeSynchronizationService($classMap, $templates, $variants, $generator))->syncOne('samplemessagetype', '   ');
		self::assertTrue($result['ok']);
		self::assertSame(1, $result['created_templates']);
		self::assertSame(1, $result['created_variants']);
		self::assertSame('en', $result['providers'][0]['language']);
	}

	public function testExistingTemplateAndVariantAreNotOverwritten(): void {
		$provider = new MessageTypeFixture();
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstanceByInterfaceName')->willReturn($provider);
		$template = new MessageTemplate('t1', 'samplemessagetype', 'old');
		$templates = $this->createMock(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn($template);
		$templates->expects(self::never())->method('save');
		$variants = $this->createMock(IMessageVariantRepository::class);
		$variants->method('getForTemplate')->willReturn(new MessageVariant('v1', 't1', 'de', 'old', 'old'));
		$variants->expects(self::never())->method('save');
		$generator = $this->createMock(IMessageIdGenerator::class);
		$generator->expects(self::never())->method('createId');
		$result = (new MessageTypeSynchronizationService($classMap, $templates, $variants, $generator))->syncOne('samplemessagetype', ' DE ');
		self::assertSame(1, $result['skipped_templates']);
		self::assertSame(1, $result['skipped_variants']);
		self::assertSame(0, $result['created_templates']);
		self::assertSame(0, $result['created_variants']);
		self::assertSame('de', $result['providers'][0]['language']);
	}

	public function testSyncAllIgnoresNonProvidersAndAggregatesResults(): void {
		$provider = new MessageTypeFixture();
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn([new \stdClass(), $provider]);
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn(new MessageTemplate('t1', 'samplemessagetype', 'old'));
		$variants = $this->createStub(IMessageVariantRepository::class);
		$variants->method('getForTemplate')->willReturn(new MessageVariant('v1', 't1', 'en', 's', 'b'));
		$service = new MessageTypeSynchronizationService($classMap, $templates, $variants, $this->createStub(IMessageIdGenerator::class));
		$result = $service->syncAll('EN');
		self::assertSame(1, $result['skipped_templates']);
		self::assertSame(1, $result['skipped_variants']);
		self::assertCount(1, $result['providers']);
		self::assertTrue($result['ok']);
	}

	public function testSummariesReportAlreadyInstalledProvidersAndPlaceholders(): void {
		$classMap = $this->createStub(IClassMap::class);
		$classMap->method('getInstancesByInterface')->willReturn([new MessageTypeFixture()]);
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn(new MessageTemplate('template-42', 'samplemessagetype', 'label'));
		$service = new MessageTypeSynchronizationService($classMap, $templates,
			$this->createStub(IMessageVariantRepository::class), $this->createStub(IMessageIdGenerator::class));
		self::assertSame([[
			'name' => 'samplemessagetype', 'label' => 'Sample message',
			'description' => 'A test message type', 'installed' => true,
			'template_id' => 'template-42', 'placeholders' => [['name' => 'name']]
		]], $service->getProviderSummaries());
	}


	public function testEmptyProviderCollectionHasNoSideEffects(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new \stdClass()]);
		$templates = $this->createMock(IMessageTemplateRepository::class);
		$templates->expects(self::never())->method('save');
		$variants = $this->createMock(IMessageVariantRepository::class);
		$variants->expects(self::never())->method('save');
		$service = new MessageTypeSynchronizationService($map, $templates, $variants, $this->createStub(IMessageIdGenerator::class));
		self::assertSame([], $service->getProviderSummaries());
		self::assertSame([
			'ok' => true, 'error' => '', 'created_templates' => 0, 'created_variants' => 0,
			'skipped_templates' => 0, 'skipped_variants' => 0, 'providers' => []
		], $service->syncAll());
	}

	public function testLanguageNormalizationTrimsLowercasesAndLimitsLength(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn(new MessageTypeFixture());
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn(new MessageTemplate('tpl', 'samplemessagetype', 'Label'));
		$variants = $this->createMock(IMessageVariantRepository::class);
		$variants->expects(self::once())->method('getForTemplate')->with('tpl', 'abcdefghijkl')->willReturn(new MessageVariant('v', 'tpl', 'abcdefghijkl', 'S', 'B'));
		$variants->expects(self::never())->method('save');
		$service = new MessageTypeSynchronizationService($map, $templates, $variants, $this->createStub(IMessageIdGenerator::class));
		$result = $service->syncOne('samplemessagetype', ' ABCDEFGHIJKLMNOP ');
		self::assertSame(1, $result['skipped_templates']);
		self::assertSame(1, $result['skipped_variants']);
		self::assertSame('abcdefghijkl', $result['providers'][0]['language']);
	}

	public function testNotInstalledProviderAppearsInSummaries(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new MessageTypeFixture()]);
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn(null);
		$service = new MessageTypeSynchronizationService($map, $templates,
			$this->createStub(IMessageVariantRepository::class), $this->createStub(IMessageIdGenerator::class));
		$summary = $service->getProviderSummaries();
		self::assertCount(1, $summary);
		self::assertFalse($summary[0]['installed']);
		self::assertSame('', $summary[0]['template_id']);
	}

}
