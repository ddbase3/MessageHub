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

use Base3\MessageHub\Service\MessageRenderer;
use Base3\MessageHub\Repository\DatabaseMessageVariantRepository;
use Base3\MessageHub\Repository\DatabaseSchema;
use Base3\CoreFoundation\Database\Api\IDatabase;
use Base3\MessagingFoundation\Api\IMessageIdGenerator;
use Base3\MessagingFoundation\Api\IMessageTemplateRepository;
use Base3\MessagingFoundation\Api\IMessageVariantRepository;
use Base3\MessagingFoundation\Dto\MessageTemplate;
use Base3\MessagingFoundation\Dto\MessageVariant;
use Base3\MessagingFoundation\Exception\MessageException;
use PHPUnit\Framework\TestCase;

final class MessageRendererTest extends TestCase {

	public function testSuccessfulRenderingSubstitutesOnlyScalarContextAndUsesTemplateTransport(): void {
		$template = new MessageTemplate('tpl', 'invoice', 'Invoice', '', 'global', '', 'smtp', true);
		$variant = new MessageVariant('var', 'tpl', 'de', 'Hallo {{name}}', 'Nr {{number}}; {{array}}', '<b>{{enabled}}</b>');
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn($template);
		$variants = $this->createMock(IMessageVariantRepository::class);
		$variants->expects(self::once())->method('getForTemplate')->with('tpl', 'de')->willReturn($variant);
		$message = (new MessageRenderer($templates, $variants))->render('invoice', ' de ', [
			'name' => 'Ada', 'number' => 7, 'enabled' => false, 'array' => ['ignored']
		]);
		self::assertSame('Hallo Ada', $message->getSubject());
		self::assertSame('Nr 7; {{array}}', $message->getBodyText());
		self::assertSame('<b></b>', $message->getBodyHtml());
		self::assertSame(['template_id' => 'tpl', 'variant_id' => 'var', 'language' => 'de',
			'requested_language' => 'de', 'fallback_used' => false, 'transport_name' => 'smtp'], $message->getMetadata());
	}

	public function testExplicitTransportOverridesTemplateTransport(): void {
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn(new MessageTemplate('t', 'alert', 'Alert', '', 'global', '', 'smtp'));
		$variants = $this->createStub(IMessageVariantRepository::class);
		$variants->method('getForTemplate')->willReturn(new MessageVariant('v', 't', 'en', 'subject', 'body'));
		self::assertSame('null', (new MessageRenderer($templates, $variants))->render('alert', 'en', [], 'null')->getMetadata()['transport_name']);
	}

	public function testMissingAndDisabledTemplateAreRejected(): void {
		foreach([null, new MessageTemplate('t', 'type', 'label', '', 'global', '', '', false)] as $template) {
			$templates = $this->createStub(IMessageTemplateRepository::class);
			$templates->method('getByType')->willReturn($template);
			try {
				(new MessageRenderer($templates, $this->createStub(IMessageVariantRepository::class)))->render('type', 'en');
				self::fail('Missing or disabled template accepted.');
			} catch(MessageException $exception) {
				self::assertStringContainsString('not found or disabled', $exception->getMessage());
			}
		}
	}

	public function testDisabledVariantIsRejected(): void {
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn(new MessageTemplate('tpl', 't', 'T'));
		$variants = $this->createStub(IMessageVariantRepository::class);
		$variants->method('getForTemplate')->willReturn(new MessageVariant('v', 'tpl', 'en', 's', 'b', '', false));
		$this->expectException(MessageException::class);
		$this->expectExceptionMessage('fallback variant not found or disabled');
		(new MessageRenderer($templates, $variants))->render('t', 'en');
	}


	public function testNonScalarPlaceholderValuesRemainUnexpanded(): void {
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn(new MessageTemplate('tpl', 'notice', 'Notice'));
		$variants = $this->createStub(IMessageVariantRepository::class);
		$variants->method('getForTemplate')->willReturn(new MessageVariant('v', 'tpl', 'en',
			'{{one}}|{{zero}}|{{null}}|{{array}}|{{object}}|{{missing}}', 'Plain {{one}}'));
		$message = (new MessageRenderer($templates, $variants))->render('notice', 'en', [
			'one' => true, 'zero' => false, 'null' => null, 'array' => [1], 'object' => new \stdClass()
		]);
		self::assertSame('1|||{{array}}|{{object}}|{{missing}}', $message->getSubject());
		self::assertSame('Plain 1', $message->getBodyText());
	}



	public function testMissingRequestedVariantRendersTheActualRepositoryFallback(): void {
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn(new MessageTemplate('tpl', 'alert', 'Alert', '', 'global', '', 'log'));
		$queries = [];
		$db = $this->createStub(IDatabase::class);
		$db->method('escape')->willReturnCallback(static fn(string $value): string => $value);
		$db->method('singleQuery')->willReturnCallback(function(string $sql) use (&$queries): ?array {
			$queries[] = $sql;
			if(str_contains($sql, 'AND language=')) {
				return null;
			}
			if(str_contains($sql, 'fallback_flag=1')) {
				return ['id' => 'fallback', 'template_id' => 'tpl', 'language' => 'en',
					'subject' => 'Alert {{code}}', 'body_text' => 'Reference {{code}}', 'body_html' => '',
					'fallback_flag' => 1, 'enabled' => 1];
			}
			return ['existing' => true];
		});
		$repository = new DatabaseMessageVariantRepository($db, new DatabaseSchema($db), $this->createStub(IMessageIdGenerator::class));
		$message = (new MessageRenderer($templates, $repository))->render('alert', ' fr ', ['code' => 42]);
		self::assertSame('Alert 42', $message->getSubject());
		self::assertSame('Reference 42', $message->getBodyText());
		self::assertSame('en', $message->getMetadata()['language']);
		self::assertSame('fr', $message->getMetadata()['requested_language']);
		self::assertTrue($message->getMetadata()['fallback_used']);
		self::assertStringContainsString('AND enabled=1', implode(' ', $queries));
	}

	public function testRepositoryWithNoActiveFallbackRejectsRendering(): void {
		$templates = $this->createStub(IMessageTemplateRepository::class);
		$templates->method('getByType')->willReturn(new MessageTemplate('tpl', 'alert', 'Alert'));
		$db = $this->createStub(IDatabase::class);
		$db->method('singleQuery')->willReturnCallback(static function(string $sql): ?array {
			return str_starts_with($sql, 'SHOW ') ? ['existing' => true] : null;
		});
		$repository = new DatabaseMessageVariantRepository($db, new DatabaseSchema($db), $this->createStub(IMessageIdGenerator::class));
		$this->expectException(MessageException::class);
		$this->expectExceptionMessage('Message variant and fallback variant not found or disabled');
		(new MessageRenderer($templates, $repository))->render('alert', 'fr');
	}

}
