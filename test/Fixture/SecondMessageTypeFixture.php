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

namespace Base3\MessageHub\Test\Fixture;

use Base3\MessagingFoundation\Api\IMessageTypeProvider;

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
