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

namespace Base3\MessageHub\Test\Transport;

use Base3\MessageHub\Transport\Support\HttpResponse;
use PHPUnit\Framework\TestCase;

final class HttpResponseTest extends TestCase {

	public function testSuccessIsLimitedToTwoHundreds(): void {
		foreach([199 => false, 200 => true, 204 => true, 299 => true, 300 => false, 500 => false] as $status => $expected) {
			self::assertSame($expected, (new HttpResponse($status, ''))->isSuccessful());
		}
	}

	public function testResponseExposesBodyHeadersAndJsonWhenObjectOrArray(): void {
		$response = new HttpResponse(201, '{"ok":true}', ['x-id' => '12']);
		self::assertSame(201, $response->getStatusCode());
		self::assertSame('{"ok":true}', $response->getBody());
		self::assertSame(['x-id' => '12'], $response->getHeaders());
		self::assertSame(['ok' => true], $response->getJson());
		self::assertSame([1, 2], (new HttpResponse(200, '[1,2]'))->getJson());
		self::assertNull((new HttpResponse(400, 'invalid'))->getJson());
		self::assertNull((new HttpResponse(200, 'null'))->getJson());
	}
}
