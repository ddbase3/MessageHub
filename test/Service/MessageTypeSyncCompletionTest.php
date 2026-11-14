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
require_once __DIR__ . '/../Fixture/SecondMessageTypeFixture.php';

use Base3\CoreFoundation\Api\IClassMap;
use Base3\MessageHub\Service\MessageTypeSynchronizationService;
use Base3\MessageHub\Test\Fixture\MessageTypeFixture;
use Base3\MessageHub\Test\Fixture\SecondMessageTypeFixture;
use Base3\MessagingFoundation\Api\IMessageIdGenerator;
use Base3\MessagingFoundation\Api\IMessageTemplateRepository;
use Base3\MessagingFoundation\Api\IMessageVariantRepository;
use Base3\MessagingFoundation\Dto\MessageTemplate;
use Base3\MessagingFoundation\Dto\MessageVariant;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessageTypeSyncCompletionTest extends TestCase {

	public function testMultipleProvidersCombineCreatedAndSkippedCountersWithoutChangingExistingTemplates(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new MessageTypeFixture(), new SecondMessageTypeFixture()]);
		$templates = $this->createMock(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturnCallback(static fn(string $name): ?MessageTemplate =>
			$name === 'samplemessagetype' ? new MessageTemplate('existing-tpl', $name, 'unchanged') : null);
		$templates->expects(self::once())->method('save')->with(self::callback(function(MessageTemplate $template): bool {
			self::assertSame('secondmessage', $template->getTypeName());
			self::assertSame('new-tpl', $template->getId());
			return true;
		}));
		$variants = $this->createMock(IMessageVariantRepository::class);
		$variants->method('getForTemplate')->willReturnCallback(static fn(string $templateId): ?MessageVariant =>
			$templateId === 'existing-tpl' ? new MessageVariant('existing-var', $templateId, 'de', 'old', 'old') : null);
		$variants->expects(self::once())->method('save')->with(self::callback(function(MessageVariant $variant): bool {
			self::assertSame('new-tpl', $variant->getTemplateId());
			self::assertSame('de', $variant->getLanguage());
			self::assertSame('Second subject', $variant->getSubject());
			return true;
		}));
		$ids = $this->createStub(IMessageIdGenerator::class);
		$ids->method('createId')->willReturnCallback(static fn(string $prefix): string => $prefix === 'tpl' ? 'new-tpl' : 'new-var');
		$result = (new MessageTypeSynchronizationService($map, $templates, $variants, $ids))->syncAll(' DE ');
		self::assertTrue($result['ok']);
		self::assertSame(1, $result['created_templates']);
		self::assertSame(1, $result['skipped_templates']);
		self::assertSame(1, $result['created_variants']);
		self::assertSame(1, $result['skipped_variants']);
		self::assertSame(['samplemessagetype', 'secondmessage'], array_column($result['providers'], 'name'));
		self::assertSame(['de', 'de'], array_column($result['providers'], 'language'));
	}

	public function testProviderSummariesAreSortedByTechnicalNameEvenIfClassMapIsUnsorted(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new SecondMessageTypeFixture(), new \stdClass(), new MessageTypeFixture()]);
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturnCallback(static fn(string $name): ?MessageTemplate =>
			$name === 'secondmessage' ? new MessageTemplate('second-tpl', $name, 'Second') : null);
		$service = new MessageTypeSynchronizationService($map, $templates,
			$this->createStub(IMessageVariantRepository::class), $this->createStub(IMessageIdGenerator::class));
		$summaries = $service->getProviderSummaries();
		self::assertSame(['samplemessagetype', 'secondmessage'], array_column($summaries, 'name'));
		self::assertSame([false, true], array_column($summaries, 'installed'));
		self::assertSame(['', 'second-tpl'], array_column($summaries, 'template_id'));
		self::assertSame([['name' => 'status']], $summaries[1]['placeholders']);
	}

	public function testRepositoryFailureDuringSynchronizationIsNotReportedAsSuccess(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn(new MessageTypeFixture());
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willThrowException(new RuntimeException('template database unavailable'));
		$service = new MessageTypeSynchronizationService($map, $templates,
			$this->createStub(IMessageVariantRepository::class), $this->createStub(IMessageIdGenerator::class));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('template database unavailable');
		$service->syncOne('samplemessagetype');
	}

	public function testClassMapReturningWrongObjectCannotSynchronizeUnknownType(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstanceByInterfaceName')->willReturn(new \stdClass());
		$templates = $this->createMock(IMessageTemplateRepository::class);
		$templates->expects(self::never())->method('save');
		$service = new MessageTypeSynchronizationService($map, $templates,
			$this->createStub(IMessageVariantRepository::class), $this->createStub(IMessageIdGenerator::class));
		$result = $service->syncOne('invalid');
		self::assertFalse($result['ok']);
		self::assertSame('Message type provider not found: invalid', $result['error']);
		self::assertSame([], $result['providers']);
	}
}
