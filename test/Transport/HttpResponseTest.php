<?php declare(strict_types=1);

namespace MessageHub\Test\Transport;

use MessageHub\Transport\Support\HttpResponse;
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
