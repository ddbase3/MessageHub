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

require_once __DIR__ . '/../Fixture/MessageTransportProbe.php';

use Base3\CoreFoundation\ConfigValue\Api\IConfigValueResolver;
use Base3\CoreFoundation\Logger\Api\ILogger;
use Base3\MessageHub\Test\Fixture\MessageTransportProbe;
use Base3\MessagingFoundation\Dto\Message;
use Base3\MessagingFoundation\Dto\MessageAddress;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AbstractMessageTransportTest extends TestCase {

	private function probe(?IConfigValueResolver $resolver = null, ?ILogger $logger = null): MessageTransportProbe {
		return new MessageTransportProbe($resolver ?? $this->createStub(IConfigValueResolver::class), $logger ?? $this->createStub(ILogger::class));
	}

	public function testScalarSettingsAndBooleanNormalization(): void {
		$probe = $this->probe();
		foreach(['true', 'YES', ' on ', 1, true, 'enabled'] as $value) {
			self::assertSame([true, true], $probe->inspectBooleans(['value' => $value]));
		}
		foreach(['false', 'no', 'off', 0, false] as $value) {
			self::assertSame([false, true], $probe->inspectBooleans(['value' => $value]));
		}
		self::assertSame('fallback', $probe->inspectString(['value' => ['invalid']]));
		self::assertSame('hello', $probe->inspectString(['value' => '  hello  ']));
	}

	public function testNumericArrayAndConfiguredValueNormalization(): void {
		$probe = $this->probe();
		self::assertSame(12, $probe->inspectInt(['value' => '12']));
		self::assertSame(7, $probe->inspectInt(['value' => 'wrong']));
		self::assertSame(['a'], $probe->inspectArray(['value' => ['a']]));
		self::assertSame(['default'], $probe->inspectArray(['value' => 'invalid']));
		self::assertTrue($probe->inspectConfigured(['mode' => 'env']));
		self::assertFalse($probe->inspectConfigured([]));
		self::assertFalse($probe->inspectConfigured('  '));
		self::assertTrue($probe->inspectConfigured('token'));
	}

	public function testSecretResolutionUsesOnlyResolverAndHandlesItsFailures(): void {
		$resolver = $this->createStub(IConfigValueResolver::class);
		$resolver->method('resolve')->willReturnCallback(static function(mixed $definition): mixed {
			if($definition === 'boom') { throw new RuntimeException('unavailable'); }
			return $definition === 'token' ? '  resolved  ' : ['not a scalar'];
		});
		$probe = $this->probe($resolver);
		self::assertSame('resolved', $probe->inspectResolved('token', 'default'));
		self::assertSame('default', $probe->inspectResolved('boom', 'default'));
		self::assertSame('default', $probe->inspectResolved(['mode' => 'invalid'], 'default'));
	}

	public function testMessageBodyTextRecipientsAndSummary(): void {
		$probe = $this->probe();
		$message = new Message('alert', ' Subject ', '', '<b>Hello</b> team', [
			new MessageAddress('TO', ' user@example.test ', 'Name'),
			new MessageAddress('cc', 'cc@example.test'),
			new MessageAddress('to', '  ')
		]);
		self::assertSame('Hello team', $probe->inspectBody($message));
		self::assertSame("Subject\n\nHello team", $probe->inspectText($message));
		self::assertSame('Hello team', $probe->inspectText($message, false));
		self::assertCount(1, $probe->inspectRecipients($message, ['to']));
		self::assertCount(2, $probe->inspectRecipients($message, ['to', 'cc']));
		self::assertSame('first | last', $probe->inspectSummary([' first ', '', [], 'last']));
		self::assertSame('Not configured.', $probe->inspectSummary([null, '']));
	}

	public function testSplittingAndTruncatingHandleLengthLimits(): void {
		$probe = $this->probe();
		self::assertSame(['ab', 'cd', 'ef'], $probe->inspectSplit('abcdef', 2));
		self::assertSame([], $probe->inspectSplit('  ', 5));
		self::assertSame([], $probe->inspectSplit('text', 0));
		self::assertSame('', $probe->inspectTruncate('text', 0));
		self::assertSame('short', $probe->inspectTruncate('short', 20));
		self::assertStringStartsWith('a', $probe->inspectTruncate('abcdefgh', 5));
	}

	public function testResultHelpersAndExceptionLogging(): void {
		$logger = $this->createMock(ILogger::class);
		$logger->expects(self::once())->method('warning')->with('Probe delivery failed', self::arrayHasKey('error'));
		$probe = $this->probe(null, $logger);
		self::assertTrue($probe->inspectSuccess());
		self::assertFalse($probe->inspectFailure());
		[$success, $message, $details] = $probe->inspectException(new RuntimeException('failed'));
		self::assertFalse($success);
		self::assertSame('failed', $message);
		self::assertSame(RuntimeException::class, $details['exception']);
	}


	public function testEmptyMessagePartsAndRecipientTypeFiltering(): void {
		$probe = $this->probe();
		self::assertSame('', $probe->inspectText(new Message('notice', '', '', '')));
		self::assertSame('Subject', $probe->inspectText(new Message('notice', ' Subject ', '', '')));
		self::assertSame('HTML body', $probe->inspectBody(new Message('notice', '', '', '<p>HTML body</p>')));
		self::assertSame('plain', $probe->inspectBody(new Message('notice', '', ' plain ', '<b>other</b>')));

		$message = new Message('notice', '', 'Body', '', [
			new MessageAddress('to', ' a@example.test '),
			new MessageAddress('cc', 'b@example.test'),
			new MessageAddress('bcc', 'c@example.test'),
			new MessageAddress('cc', '  ')
		]);
		self::assertSame(['cc'], array_map(static fn(MessageAddress $item): string => $item->getType(),
			$probe->inspectRecipients($message, [' cC ', 'missing'])));
		self::assertCount(3, $probe->inspectRecipients($message, ['TO', 'Cc', 'bcc']));
		self::assertSame('Body', $probe->inspectText($message, false));
	}

	public function testSettingDefaultsAndResolvedScalarsRemainDeterministic(): void {
		$probe = $this->probe();
		self::assertSame([false, true], $probe->inspectBooleans([]));
		self::assertSame([false, true], $probe->inspectBooleans(['value' => 'unknown', 'other' => 'yes']));
		self::assertSame('fallback', $probe->inspectString([]));
		self::assertSame('fallback', $probe->inspectString(['value' => null]));
		self::assertSame(7, $probe->inspectInt([]));
		self::assertSame(['default'], $probe->inspectArray([]));
		self::assertFalse($probe->inspectConfigured(null));
		self::assertTrue($probe->inspectConfigured(0));

		$resolver = $this->createStub(IConfigValueResolver::class);
		$resolver->method('resolve')->willReturnCallback(static fn(mixed $value): mixed => $value);
		$resolved = $this->probe($resolver);
		self::assertSame('42', $resolved->inspectResolved(42));
		self::assertSame('1', $resolved->inspectResolved(true));
		self::assertSame('', $resolved->inspectResolved(null, 'default'));
	}

	public function testTextLimitsKeepSingleCharactersAndDoNotLeakExtraParts(): void {
		$probe = $this->probe();
		self::assertSame(['a'], $probe->inspectSplit('a', 1));
		self::assertSame(['a', 'b', 'c'], $probe->inspectSplit('abc', 1));
		self::assertSame(['short'], $probe->inspectSplit('short', 12));
		self::assertSame('short', $probe->inspectTruncate('short', 5));
		self::assertSame('', $probe->inspectTruncate('short', -2));
		self::assertSame('Not configured.', $probe->inspectSummary([false, null, []]));
	}
}
