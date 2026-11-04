<?php declare(strict_types=1);

namespace MessageHub\Test\Fixture;

use MessagingFoundation\Api\IMessageTypeProvider;

final class SecondMessageTypeFixture implements IMessageTypeProvider {

	public static function getName(): string { return 'secondmessage'; }
	public function getLabel(): string { return 'Second'; }
	public function getDescription(): string { return 'Second provider'; }
	public function getDefaultSubject(): string { return 'Second subject'; }
	public function getDefaultBodyText(): string { return 'Second text'; }
	public function getDefaultBodyHtml(): string { return '<b>Second</b>'; }
	public function getPlaceholders(): array { return [['name' => 'status']]; }
	public function getSchema(): array { return ['type' => 'object']; }
}
