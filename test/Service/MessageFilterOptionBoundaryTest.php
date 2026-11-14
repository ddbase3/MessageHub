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

use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\MessageHub\Service\MessageFilterOptionService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MessageFilterOptionBoundaryTest extends TestCase {

	public function testEmptyResultProducesNoInventedFilterOptions(): void {
		$database = $this->createMock(IDatabase::class);
		$database->expects(self::once())->method('connect');
		$database->method('singleQuery')->willReturn(['present' => true]);
		$database->expects(self::once())->method('multiQuery')->with(
			'SELECT DISTINCT transport_name AS value FROM base3_messaging_deliveries WHERE transport_name IS NOT NULL AND transport_name <> \'\' ORDER BY transport_name ASC'
		)->willReturn([]);
		$service = new MessageFilterOptionService($database, new DatabaseSchema($database));
		self::assertSame([], $service->getDeliveryTransportOptions());
	}

	public function testMixedFilterRowsKeepTrimmedScalarsAndIgnoreMissingValues(): void {
		$database = $this->createStub(IDatabase::class);
		$database->method('singleQuery')->willReturn(['present' => true]);
		$database->method('multiQuery')->willReturn([
			['value' => "  admin  "],
			['value' => 0],
			['value' => ' '],
			['value' => null],
			['value' => 'urgent']
		]);
		$service = new MessageFilterOptionService($database, new DatabaseSchema($database));
		self::assertSame([
			['value' => 'admin', 'label' => 'admin'],
			['value' => '0', 'label' => '0'],
			['value' => 'urgent', 'label' => 'urgent']
		], $service->getQueueTypeOptions());
	}

	public function testFilterDatabaseFailurePropagatesInsteadOfShowingStaleOptions(): void {
		$database = $this->createStub(IDatabase::class);
		$database->method('singleQuery')->willReturn(['present' => true]);
		$database->method('multiQuery')->willThrowException(new RuntimeException('filter database unavailable'));
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('filter database unavailable');
		(new MessageFilterOptionService($database, new DatabaseSchema($database)))->getTemplateTransportOptions();
	}
}
