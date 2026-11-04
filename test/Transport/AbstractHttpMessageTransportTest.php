<?php declare(strict_types=1);

namespace MessageHub\Test\Transport;

require_once __DIR__ . '/../Fixture/HttpMessageTransportProbe.php';

use Base3\ConfigValue\Api\IConfigValueResolver;
use Base3\Logger\Api\ILogger;
use MessageHub\Test\Fixture\HttpMessageTransportProbe;
use MessageHub\Transport\Support\HttpResponse;
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
