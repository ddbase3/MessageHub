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

require_once __DIR__ . '/../Fixture/HttpMessageTransportProbe.php';

use Base3\CoreFoundation\ConfigValue\Api\IConfigValueResolver;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MessageHub\Test\Fixture\HttpMessageTransportProbe;
use Base3\MessageHub\Transport\Support\HttpResponse;
use PHPUnit\Framework\TestCase;
use JsonException;

final class AbstractHttpMessageTransportTest extends TestCase {

	private function probe(): HttpMessageTransportProbe {
		return new HttpMessageTransportProbe($this->createStub(IConfigValueResolver::class), $this->createStub(ILogger::class));
	}

	public function testErrorExtractionRespectsProviderErrorFields(): void {
		$probe = $this->probe();
		foreach(['message', 'error_description', 'description', 'detail'] as $key) {
			self::assertSame('reason', $probe->error(new HttpResponse(400, json_encode([$key => ' reason ']))));
		}
		self::assertSame('nested', $probe->error(new HttpResponse(400, '{"error":{"message":"nested"}}')));
		self::assertSame('unauthorized', $probe->error(new HttpResponse(401, '{"error":"unauthorized"}')));
	}

	public function testErrorExtractionUsesBodyThenHttpStatusFallback(): void {
		$probe = $this->probe();
		self::assertSame('Bad gateway', $probe->error(new HttpResponse(502, '  Bad gateway  ')));
		self::assertSame('Rejected (HTTP 429).', $probe->error(new HttpResponse(429, ''), 'Rejected'));
	}

	public function testJsonPayloadEncodingRejectsInvalidUtf8BeforeNetwork(): void {
		$this->expectException(JsonException::class);
		$this->probe()->encodePayload(['invalid' => "\xB1"]);
	}


	public function testBlankProviderErrorAndInvalidNestedMessageUseBodyFallback(): void {
		$probe = $this->probe();
		self::assertSame('{"error":{"message":[]}}', $probe->error(new HttpResponse(400, '{"error":{"message":[]}}')));
		self::assertSame('provider error', $probe->error(new HttpResponse(400, '{"error":{"message":[]},"detail":"provider error"}')));
	}

	public function testLongHttpErrorBodyIsTruncatedAndEmptyBodyFallsBackToStatus(): void {
		$probe = $this->probe();
		self::assertTrue(strlen($probe->error(new HttpResponse(500, str_repeat('x', 700)))) <= 503);
		self::assertSame('Web service (HTTP 503).', $probe->error(new HttpResponse(503, "  \t  "), 'Web service'));
	}

}
