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

use Base3\CoreFoundation\Api\IClassMap;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\MessageHub\Service\MessageTransportRegistry;
use Base3\MessageHub\Transport\LogMessageTransport;
use Base3\MessageHub\Transport\NullMessageTransport;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MessagingFoundation\Api\IMessageTransport;
use PHPUnit\Framework\TestCase;

final class MessageTransportRegistryTest extends TestCase {

	public function testDiscoversValidTransportsSortsAndCachesResults(): void {
		$log = new LogMessageTransport($this->createStub(ILogger::class));
		$null = new NullMessageTransport();
		$classMap = $this->createMock(IClassMap::class);
		$classMap->expects(self::once())->method('getInstancesByInterface')->with(IMessageTransport::class)->willReturn([$null, new \stdClass(), $log]);
		$registry = new MessageTransportRegistry($classMap, $this->createStub(ISettingsStore::class));
		self::assertSame(['log', 'null'], array_keys($registry->getTransports()));
		self::assertSame($log, $registry->getTransport(' log '));
		self::assertSame($null, $registry->getTransport('null'));
		self::assertNull($registry->getTransport('missing'));
		self::assertSame(['log', 'null'], array_keys($registry->getTransports()));
	}

	public function testDefaultTransportAndSettingsAreReadFromTheirOwningGroups(): void {
		$settings = $this->createMock(ISettingsStore::class);
		$settings->expects(self::exactly(2))->method('get')->willReturnCallback(function(string $group, string $name, array $default): array {
			self::assertSame([], $default);
			if($group === 'messaging' && $name === 'default') {
				return ['default_transport' => '  null  '];
			}
			self::assertSame('messaging_transports', $group);
			self::assertSame('null', $name);
			return ['enabled' => true];
		});
		$registry = new MessageTransportRegistry($this->createStub(IClassMap::class), $settings);
		self::assertSame('null', $registry->getDefaultTransportName());
		self::assertSame(['enabled' => true], $registry->getTransportSettings('null'));
	}

	public function testEmptyDefaultSettingsSelectLogTransport(): void {
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn(['default_transport' => '   ']);
		self::assertSame('log', (new MessageTransportRegistry($this->createStub(IClassMap::class), $settings))->getDefaultTransportName());
	}


	public function testRegistryWithNoValidInstancesReturnsEmptyAndDoesNotInventFallback(): void {
		$map = $this->createMock(IClassMap::class);
		$map->expects(self::once())->method('getInstancesByInterface')->willReturn([null, false, new \stdClass()]);
		$registry = new MessageTransportRegistry($map, $this->createStub(ISettingsStore::class));
		self::assertSame([], $registry->getTransports());
		self::assertNull($registry->getTransport('null'));
		self::assertNull($registry->getTransport());
	}

	public function testUnnamedLookupUsesDefaultNameButDoesNotReplaceExplicitUnknownName(): void {
		$map = $this->createStub(IClassMap::class);
		$map->method('getInstancesByInterface')->willReturn([new NullMessageTransport()]);
		$settings = $this->createStub(ISettingsStore::class);
		$settings->method('get')->willReturn(['default_transport' => ' null ']);
		$registry = new MessageTransportRegistry($map, $settings);
		self::assertInstanceOf(NullMessageTransport::class, $registry->getTransport('  '));
		self::assertNull($registry->getTransport('unknown'));
	}

}
