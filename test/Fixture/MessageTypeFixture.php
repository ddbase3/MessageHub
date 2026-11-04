<?php declare(strict_types=1);

namespace MessageHub\Test\Fixture;

use MessagingFoundation\Api\IMessageTypeProvider;

final class MessageTypeFixture implements IMessageTypeProvider {

	public static function getName(): string { return 'samplemessagetype'; }
	public function getLabel(): string { return 'Sample message'; }
	public function getDescription(): string { return 'A test message type'; }
	public function getDefaultSubject(): string { return 'Hello {{name}}'; }
	public function getDefaultBodyText(): string { return 'Text {{name}}'; }
	public function getDefaultBodyHtml(): string { return '<b>{{name}}</b>'; }
	public function getPlaceholders(): array { return [['name' => 'name']]; }
	public function getSchema(): array { return ['type' => 'object', 'properties' => []]; }
}
