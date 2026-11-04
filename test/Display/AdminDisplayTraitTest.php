<?php declare(strict_types=1);

namespace MessageHub\Test\Display;

require_once __DIR__ . '/../Fixture/AdminDisplayTraitProbe.php';

use MessageHub\Test\Fixture\AdminDisplayTraitProbe;
use PHPUnit\Framework\TestCase;

final class AdminDisplayTraitTest extends TestCase {

	public function testEmptyPageAndInvalidRequestNormalizeToSafeDefaults(): void {
		$probe = new AdminDisplayTraitProbe();
		$result = $probe->pageRows([], ['page' => -5, 'pageSize' => 0, 'search' => ['ignored'],
			'filters' => ['blank' => ' ', 'invalid' => ['nested']], 'sort' => [['key' => '__sync', 'dir' => 'desc']]]);
		self::assertSame(1, $result['page']);
		self::assertSame(1, $result['pageSize']);
		self::assertSame(0, $result['total']);
		self::assertSame([], $result['appliedFilters']);
		self::assertSame([], $result['appliedSort']);
		self::assertSame('', $result['appliedSearch']);
		self::assertFalse($result['hasMore']);
	}

	public function testSearchFiltersAcrossNestedRowValuesCaseInsensitively(): void {
		$rows = [
			['id' => '1', 'subject' => 'Confirmation', 'metadata' => ['contact' => ['name' => 'Änne']], 'status' => 'sent'],
			['id' => '2', 'subject' => 'Other', 'metadata' => ['contact' => ['name' => 'Bert']], 'status' => 'failed']
		];
		$result = (new AdminDisplayTraitProbe())->pageRows($rows, ['search' => 'confirmation', 'filters' => ['status' => 'sent']]);
		self::assertSame(1, $result['total']);
		self::assertSame('1', $result['data'][0]['id']);
	}

	public function testExactBooleanFiltersNormalizeTextualValues(): void {
		$rows = [
			['id' => 'a', 'enabled' => 1, 'status' => 'queued'],
			['id' => 'b', 'enabled' => 0, 'status' => 'sent']
		];
		$probe = new AdminDisplayTraitProbe();
		self::assertSame('a', $probe->pageRows($rows, ['filters' => ['enabled' => 'enabled']])['data'][0]['id']);
		self::assertSame('b', $probe->pageRows($rows, ['filters' => ['enabled' => 'disabled']])['data'][0]['id']);
		self::assertSame(0, $probe->pageRows($rows, ['filters' => ['status' => 'processing']])['total']);
	}

	public function testSortingNumericalValuesAndPaginationPreserveDeterminism(): void {
		$rows = [
			['id' => 'c', 'priority' => '10'], ['id' => 'a', 'priority' => '2'],
			['id' => 'b', 'priority' => '2']
		];
		$payload = ['sort' => [['key' => 'priority', 'dir' => 'asc']], 'pageSize' => 2, 'page' => 1];
		$probe = new AdminDisplayTraitProbe();
		$first = $probe->pageRows($rows, $payload);
		self::assertSame(['a', 'b'], array_column($first['data'], 'id'));
		self::assertTrue($first['hasMore']);
		self::assertSame(2, $first['totalPages']);
		$second = $probe->pageRows($rows, $payload + ['unused' => 'ignored']);
		self::assertSame('a', $second['data'][0]['id']);
		$descending = $probe->pageRows($rows, ['sort' => [['key' => 'priority', 'dir' => 'desc']]]);
		self::assertSame('c', $descending['data'][0]['id']);
	}

	public function testPagedRepositoryLoadsCompleteResultBeforeApplyingFilters(): void {
		$calls = [];
		$loader = function(array $request) use (&$calls): array {
			$calls[] = $request;
			if($request['page'] === 1) {
				return ['rows' => array_fill(0, 250, ['id' => 'other', 'status' => 'sent']), 'total' => 251];
			}
			return ['rows' => [['id' => 'target', 'status' => 'failed']], 'total' => 251];
		};
		$result = (new AdminDisplayTraitProbe())->pageRepository($loader, ['filters' => ['status' => 'failed'], 'pageSize' => 1]);
		self::assertCount(2, $calls);
		self::assertSame(250, $calls[0]['pageSize']);
		self::assertSame([], $calls[0]['filters']);
		self::assertSame(1, $result['total']);
		self::assertSame('target', $result['data'][0]['id']);
	}

	public function testJsonEncoderPreservesUnicodeWithoutEscapingSlashes(): void {
		$json = (new AdminDisplayTraitProbe())->encodeResponse(['message' => 'Grüße', 'url' => 'https://example.test/x']);
		self::assertStringContainsString('Grüße', $json);
		self::assertStringContainsString('https://example.test/x', $json);
	}


	public function testListPageSizeIsCappedAndNonScalarSearchOrInvalidSortAreIgnored(): void {
		$rows = array_map(static fn(int $number): array => ['id' => (string)$number, 'score' => $number], range(1, 300));
		$probe = new AdminDisplayTraitProbe();
		$page = $probe->pageRows($rows, ['pageSize' => 999, 'page' => 1, 'search' => ['invalid'],
			'sort' => [['key' => '__sync']], 'filters' => ['empty' => '  ', 3 => 'bad', 'valid' => []]]);
		self::assertSame(250, $page['pageSize']);
		self::assertCount(250, $page['data']);
		self::assertSame(300, $page['total']);
		self::assertTrue($page['hasMore']);
		self::assertSame(2, $page['totalPages']);
		self::assertSame('', $page['appliedSearch']);
		self::assertSame([], $page['appliedFilters']);
		self::assertSame([], $page['appliedSort']);
	}

	public function testNonExactFiltersAreCaseInsensitiveAndMissingValuesDoNotMatch(): void {
		$rows = [
			['id' => '1', 'name' => 'Alpha Beta', 'enabled_label' => 'Enabled'],
			['id' => '2', 'name' => 'Gamma', 'enabled_label' => 'Disabled']
		];
		$probe = new AdminDisplayTraitProbe();
		self::assertSame('1', $probe->pageRows($rows, ['filters' => ['name' => 'ALPHA']])['data'][0]['id']);
		self::assertSame(0, $probe->pageRows($rows, ['filters' => ['name' => 'missing']])['total']);
		self::assertSame('1', $probe->pageRows($rows, ['filters' => ['enabled' => 'YES']])['data'][0]['id']);
		self::assertSame('2', $probe->pageRows($rows, ['filters' => ['enabled' => 'NO']])['data'][0]['id']);
	}

	public function testDefaultLabelFilterAndExactSuffixUseNormalizedValues(): void {
		$rows = [
			['id' => 'a', 'default_label' => 'Default', 'transport_name' => 'Mail'],
			['id' => 'b', 'default_label' => 'Not default', 'transport_name' => 'Mailchimp']
		];
		$probe = new AdminDisplayTraitProbe();
		self::assertSame('a', $probe->pageRows($rows, ['filters' => ['is_default' => 'true']])['data'][0]['id']);
		self::assertSame('b', $probe->pageRows($rows, ['filters' => ['is_default' => 'false']])['data'][0]['id']);
		self::assertSame(1, $probe->pageRows($rows, ['filters' => ['transport_name_exact' => 'mail']])['total']);
		self::assertSame('a', $probe->pageRows($rows, ['filters' => ['transport_name_exact' => 'Mail']])['data'][0]['id']);
	}

	public function testNaturalStringSortingAndReverseDirectionWithTieBreaker(): void {
		$rows = [['id' => 'b', 'name' => 'file10'], ['id' => 'c', 'name' => 'file2'], ['id' => 'a', 'name' => 'file2']];
		$probe = new AdminDisplayTraitProbe();
		$ascending = $probe->pageRows($rows, ['sort' => [['key' => 'name', 'dir' => 'ASC', 'type' => 'string']]]);
		self::assertSame(['a', 'c', 'b'], array_column($ascending['data'], 'id'));
		$descending = $probe->pageRows($rows, ['sort' => [['key' => 'name', 'dir' => 'desc']]]);
		self::assertSame(['b', 'c', 'a'], array_column($descending['data'], 'id'));
	}

	public function testPagePastEndReturnsEmptyRowsAndKeepsTotal(): void {
		$page = (new AdminDisplayTraitProbe())->pageRows([['id' => 'a'], ['id' => 'b']], ['page' => 9, 'pageSize' => 1]);
		self::assertSame([], $page['data']);
		self::assertSame(2, $page['total']);
		self::assertSame(9, $page['page']);
		self::assertFalse($page['hasMore']);
	}

	public function testRepositoryPagesWithoutTotalAreLoadedUntilPartialBatch(): void {
		$calls = [];
		$loader = static function(array $request) use (&$calls): array {
			$calls[] = $request;
			return $request['page'] === 1
				? ['rows' => array_fill(0, 250, ['id' => 'ordinary'])]
				: ['rows' => [['id' => 'special', 'status' => 'failed']]];
		};
		$page = (new AdminDisplayTraitProbe())->pageRepository($loader, [
			'filters' => ['status' => 'failed'], 'page' => 1, 'pageSize' => 3,
			'sort' => [['key' => 'id', 'dir' => 'desc']]
		]);
		self::assertCount(2, $calls);
		self::assertSame([], $calls[0]['sort']);
		self::assertSame('', $calls[0]['search']);
		self::assertSame(1, $page['total']);
		self::assertSame('special', $page['data'][0]['id']);
	}


	public function testMixedRowsAndUnsupportedFilterValuesAreSafelyIgnored(): void {
		$rows = [null, 'not-a-row', ['id' => 'a', 'contact' => ['registered' => true, 'alias' => null]],
			['id' => 'b', 'contact' => ['registered' => false, 'alias' => new \stdClass()]]];
		$probe = new AdminDisplayTraitProbe();
		$page = $probe->pageRows($rows, ['search' => '', 'filters' => ['status' => '', 'invalid' => [], 'ignored' => null]]);
		self::assertSame(2, $page['total']);
		self::assertSame(['a', 'b'], array_column($page['data'], 'id'));
		self::assertSame([], $page['appliedFilters']);
	}

	public function testSortConfigurationNormalizesDirectionAndEmptyType(): void {
		$probe = new AdminDisplayTraitProbe();
		$rows = [['id' => 'b', 'score' => 3], ['id' => 'a', 'score' => 2]];
		$unsorted = $probe->pageRows($rows, ['sort' => ['unexpected']]);
		self::assertSame([], $unsorted['appliedSort']);
		$sorted = $probe->pageRows($rows, ['sort' => [['key' => ' score ', 'dir' => 'up', 'type' => ' ']]]);
		self::assertSame([['key' => 'score', 'dir' => 'asc', 'type' => 'string']], $sorted['appliedSort']);
		self::assertSame(['a', 'b'], array_column($sorted['data'], 'id'));
	}

	public function testRepositoryScanStopsAtExistingOneHundredPageLimit(): void {
		$calls = 0;
		$loader = static function(array $request) use (&$calls): array {
			$calls++;
			return ['rows' => array_fill(0, 250, ['id' => (string)$request['page'], 'status' => 'ready']), 'total' => 30000];
		};
		$page = (new AdminDisplayTraitProbe())->pageRepository($loader, ['pageSize' => 3, 'page' => 1]);
		self::assertSame(100, $calls);
		self::assertSame(25000, $page['total']);
		self::assertCount(3, $page['data']);
		self::assertSame('1', $page['data'][0]['id']);
	}

	public function testExactTransportFiltersAreDistinctFromSubstringFilters(): void {
		$rows = [['id' => 'x', 'default_transport' => 'smtp', 'transport_name' => 'smtp'],
			['id' => 'y', 'default_transport' => 'smtp_backup', 'transport_name' => 'smtp_backup']];
		$probe = new AdminDisplayTraitProbe();
		self::assertSame('x', $probe->pageRows($rows, ['filters' => ['default_transport_exact' => 'SMTP']])['data'][0]['id']);
		self::assertSame(1, $probe->pageRows($rows, ['filters' => ['transport_name_exact' => 'smtp']])['total']);
		self::assertSame(2, $probe->pageRows($rows, ['filters' => ['transport_name' => 'smtp']])['total']);
	}

	public function testFinalJsonModeKeepsUnicodeAndLiteralSlashes(): void {
		$json = (new AdminDisplayTraitProbe())->encodeResponse(['path' => '/a/b', 'label' => 'Grüße'], true);
		self::assertStringContainsString('/a/b', $json);
		self::assertStringContainsString('Grüße', $json);
	}

}
