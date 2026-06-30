# MessageHub

MessageHub is the BASE3 implementation plugin for protocol-neutral, queue-based message delivery.

It provides the concrete runtime implementation for the contracts defined by `MessagingFoundation`:

* message templates
* message variants
* placeholder-based rendering
* message queue
* retry handling
* delivery log
* delivery recipients
* attachment metadata
* transport discovery
* message type synchronization
* administration displays
* worker jobs
* health checks

MessageHub intentionally does **not** hard-code a concrete mail or notification backend. Real delivery is delegated to discoverable implementations of:

```php
MessagingFoundation\Api\IMessageTransport
```

This makes MessageHub usable with different delivery protocols and host environments. In an ILIAS-based BASE3 installation, the first concrete transport is usually provided by `Base3IliasLab` as a PHPMailer transport.

---

## 1. Purpose

MessageHub centralizes message handling for BASE3 plugins.

Without MessageHub, every consumer plugin would need to solve the same problems again:

* define message templates
* manage language variants
* replace placeholders
* queue outgoing messages
* retry failed delivery attempts
* log delivery results
* handle delivery metadata
* expose administration tools
* integrate with workers
* decide how to use mail, SMTP, PHPMailer, webhooks, or other protocols

MessageHub provides these concerns once, behind stable contracts.

A consumer plugin should only need to answer:

```text
Which message type do I provide?
Which context values do I render?
Who should receive the message?
Should the message be queued or sent now?
```

MessageHub handles the rest.

---

## 2. Plugin role in the BASE3 architecture

MessageHub is an implementation plugin.

It depends on:

```text
BASE3 Framework APIs
MessagingFoundation contracts
```

It should not depend on:

```text
Base3Ilias
Base3IliasLab
ILIAS APIs
PHPMailer
specific consumer plugins
legacy MailAdministration code
```

The intended split is:

```text
MessagingFoundation
  Defines contracts, DTOs, events, exceptions.

MessageHub
  Implements templates, queue, rendering, delivery, logs, jobs, checks and admin displays.

Base3IliasLab
  Wires project decisions and provides host-specific transports such as PHPMailer.

Consumer plugins
  Provide message type providers and call IMessageService / IMessageRenderer.
```

This keeps MessageHub reusable. The plugin owns messaging mechanics, but not project-specific transport decisions.

---

## 3. Related plugins

### 3.1 MessagingFoundation

`MessagingFoundation` defines the public API used by MessageHub and consumer plugins.

Important contracts include:

```php
MessagingFoundation\Api\IMessageService
MessagingFoundation\Api\IMessageQueueService
MessagingFoundation\Api\IMessageRenderer
MessagingFoundation\Api\IMessageTransport
MessagingFoundation\Api\IMessageTransportRegistry
MessagingFoundation\Api\IMessageTypeProvider
MessagingFoundation\Api\IMessageTypeSynchronizationService
MessagingFoundation\Api\IMessageTemplateRepository
MessagingFoundation\Api\IMessageVariantRepository
MessagingFoundation\Api\IMessageQueueRepository
MessagingFoundation\Api\IMessageDeliveryRepository
```

Important DTOs include:

```php
MessagingFoundation\Dto\Message
MessagingFoundation\Dto\MessageAddress
MessagingFoundation\Dto\MessageAttachment
MessagingFoundation\Dto\MessageTemplate
MessagingFoundation\Dto\MessageVariant
MessagingFoundation\Dto\QueuedMessage
MessagingFoundation\Dto\MessageDeliveryResult
```

### 3.2 Base3IliasLab

In the ILIAS installation, `Base3IliasLab` is the project plugin.

It can:

* expose MessageHub displays in the ILIAS administration UI
* provide the `PhpMailerMessageTransport`
* configure default messaging settings
* decide which transport is active
* apply project-specific visibility and access rules

The PHPMailer transport belongs in `Base3IliasLab` because PHPMailer is available through the host installation and is therefore a project/host decision, not a MessageHub dependency.

### 3.3 MessageHubDemo

`MessageHubDemo` is a small consumer plugin used to verify the complete MessageHub chain.

It provides:

```php
MessageHubDemo\Message\DemoWelcomeMessageTypeProvider
MessageHubDemo\Display\MessageHubDemoAdminDisplay
```

It demonstrates how another plugin can:

* provide a message type
* synchronize templates
* render a message
* add recipients
* enqueue the message
* optionally send immediately

---

## 4. Directory structure

Typical MessageHub structure:

```text
MessageHub/
├── README.md
├── VERSION
├── assets/
│   └── messagehub/
├── docs/
├── lang/
│   └── Administration/
│       ├── de.ini
│       └── en.ini
├── src/
│   ├── MessageHubPlugin.php
│   ├── Check/
│   │   └── MessageHubCheck.php
│   ├── Display/
│   │   ├── AdminDisplayTrait.php
│   │   ├── MessageDeliveryLogAdminDisplay.php
│   │   ├── MessageHubDashboardDisplay.php
│   │   ├── MessageQueueAdminDisplay.php
│   │   ├── MessageTemplateAdminDisplay.php
│   │   ├── MessageTransportAdminDisplay.php
│   │   ├── MessageTypeSyncAdminDisplay.php
│   │   └── MessageVariantAdminDisplay.php
│   ├── Job/
│   │   ├── MessageDeliveryCleanupJob.php
│   │   └── MessageQueueWorkerJob.php
│   ├── Repository/
│   │   ├── DatabaseMessageDeliveryRepository.php
│   │   ├── DatabaseMessageQueueRepository.php
│   │   ├── DatabaseMessageTemplateRepository.php
│   │   ├── DatabaseMessageVariantRepository.php
│   │   ├── DatabaseRepositoryTrait.php
│   │   └── DatabaseSchema.php
│   ├── Service/
│   │   ├── MessageDeliveryService.php
│   │   ├── MessageIdGenerator.php
│   │   ├── MessageQueueService.php
│   │   ├── MessageRenderer.php
│   │   ├── MessageSerializer.php
│   │   ├── MessageService.php
│   │   ├── MessageTransportRegistry.php
│   │   └── MessageTypeSynchronizationService.php
│   └── Transport/
│       └── LogMessageTransport.php
└── tpl/
    └── Display/
        ├── MessageDeliveryLogAdminDisplay.php
        ├── MessageHubDashboardDisplay.php
        ├── MessageQueueAdminDisplay.php
        ├── MessageTemplateAdminDisplay.php
        ├── MessageTransportAdminDisplay.php
        ├── MessageTypeSyncAdminDisplay.php
        └── MessageVariantAdminDisplay.php
```

Only PHP classes under `src/` are discovered by the plugin class map. Templates, assets, language files and documentation are loaded by their respective systems.

---

## 5. Plugin initialization

The plugin class is:

```php
MessageHub\MessageHubPlugin
```

Technical name:

```php
messagehubplugin
```

The plugin registers MessageHub services into the shared BASE3 container.

Important service registrations:

```php
MessageHub\Repository\DatabaseSchema

MessagingFoundation\Api\IMessageIdGenerator
MessagingFoundation\Api\IMessageTemplateRepository
MessagingFoundation\Api\IMessageVariantRepository
MessagingFoundation\Api\IMessageQueueRepository
MessagingFoundation\Api\IMessageDeliveryRepository

MessagingFoundation\Api\IMessageRenderer
MessagingFoundation\Api\IMessageTransportRegistry
MessagingFoundation\Api\IMessageTypeSynchronizationService
MessagingFoundation\Api\IMessageQueueService
MessagingFoundation\Api\IMessageService

MessageHub\Service\MessageDeliveryService
```

The registrations use shared services and `NOOVERWRITE` where replacement should remain possible.

---

## 6. Main concepts

### 6.1 Message type

A message type is a discoverable definition of one kind of message.

Consumer plugins define message types by implementing:

```php
MessagingFoundation\Api\IMessageTypeProvider
```

Example technical name:

```php
messagehubdemowelcomemessage
```

A message type provider defines:

* stable technical name
* label
* description
* default subject
* default plain text body
* default HTML body
* available placeholders
* schema

MessageHub does not need to know consumer plugins directly. It discovers message type providers through the class map.

### 6.2 Template

A template identifies a message type and scope.

Table:

```text
base3_messaging_templates
```

A template contains:

* ID
* message type name
* label
* description
* scope type
* scope ID
* default transport
* enabled flag
* created/updated timestamps

Templates do not contain the actual message body. The body lives in variants.

### 6.3 Variant

A variant contains the actual renderable text for one language.

Table:

```text
base3_messaging_variants
```

A variant contains:

* ID
* template ID
* language
* subject
* plain text body
* HTML body
* enabled flag
* created/updated timestamps

A template can have multiple variants, for example:

```text
en
de
fr
```

### 6.4 Message

A message is the runtime DTO that is queued or delivered.

Class:

```php
MessagingFoundation\Dto\Message
```

It contains:

* type name
* subject
* plain text body
* HTML body
* recipients
* attachments
* sender
* reply-to
* metadata

The DTO is immutable. To change it, use helper methods such as:

```php
withRecipient()
withRecipients()
withAttachment()
withAttachments()
withSender()
withReplyTo()
withMetadata()
```

Example:

```php
$message = $renderer
	->render('messagehubdemowelcomemessage', 'en', $context)
	->withRecipient(
		new MessageAddress('to', 'jane@example.org', 'Jane Doe')
	)
	->withMetadata(
		[
			'consumer_plugin' => 'ExamplePlugin',
			'object_id' => '123'
		]
	);
```

### 6.5 Queue

The queue stores messages before delivery.

Table:

```text
base3_messaging_queue
```

Queue status values:

```text
queued
processing
sent
failed
cancelled
retry_wait
```

The queue prevents every consumer plugin from implementing its own background delivery logic.

### 6.6 Delivery

A delivery is one concrete delivery attempt.

Table:

```text
base3_messaging_deliveries
```

Deliveries record:

* queue ID
* type name
* transport
* subject
* status
* attempt count
* serialized message
* error message
* transport result
* timestamps

### 6.7 Recipients

Recipients are stored per delivery.

Table:

```text
base3_messaging_recipients
```

Recipient types:

```text
to
cc
bcc
```

### 6.8 Attachments

Attachment metadata is stored per delivery.

Table:

```text
base3_messaging_attachments
```

MessageHub stores attachment metadata, not binary content. The transport reads files from the given path at delivery time.

### 6.9 Transport

A transport performs actual delivery.

Contract:

```php
MessagingFoundation\Api\IMessageTransport
```

Examples:

```text
log
phpmailer
future-webhook
future-sms
future-push
```

MessageHub includes a `LogMessageTransport` for diagnostics. Host-specific real transports should be provided by project plugins.

### 6.10 Message type synchronization

Message type synchronization discovers `IMessageTypeProvider` implementations and creates missing templates and variants.

Service:

```php
MessagingFoundation\Api\IMessageTypeSynchronizationService
```

Implementation:

```php
MessageHub\Service\MessageTypeSynchronizationService
```

It creates:

* missing global templates
* missing default language variants

It does **not** overwrite existing templates or variants.

---

## 7. Database tables

MessageHub creates its tables through:

```php
MessageHub\Repository\DatabaseSchema
```

The schema is created by calling:

```php
ensureTables()
```

Tables use the `base3_` prefix and do not conflict with legacy mail plugins.

### 7.1 `base3_messaging_templates`

Purpose:

```text
Stores message template definitions.
```

Important columns:

```text
id
type_name
label
description
scope_type
scope_id
default_transport
enabled
created_at
updated_at
```

### 7.2 `base3_messaging_variants`

Purpose:

```text
Stores language-specific subject and body variants.
```

Important columns:

```text
id
template_id
language
subject
body_text
body_html
enabled
created_at
updated_at
```

### 7.3 `base3_messaging_queue`

Purpose:

```text
Stores messages waiting for delivery or retry.
```

Important columns:

```text
id
type_name
transport_name
subject
status
priority
attempts
max_attempts
not_before
locked_until
message_json
last_error
created_at
updated_at
processed_at
```

### 7.4 `base3_messaging_deliveries`

Purpose:

```text
Stores delivery attempts and results.
```

Important columns:

```text
id
queue_id
type_name
transport_name
subject
status
attempts
message_json
error_message
result_json
created_at
updated_at
sent_at
```

### 7.5 `base3_messaging_recipients`

Purpose:

```text
Stores recipients for a delivery attempt.
```

Important columns:

```text
id
delivery_id
kind
address
label
```

### 7.6 `base3_messaging_attachments`

Purpose:

```text
Stores attachment metadata for a delivery attempt.
```

Important columns:

```text
id
delivery_id
path
filename
mime_type
inline_flag
content_id
```

---

## 8. Settings

MessageHub uses `ISettingsStore` for editable runtime settings.

### 8.1 Global settings

Group:

```text
messaging
```

Name:

```text
default
```

Example:

```php
[
	'enabled' => true,
	'default_transport' => 'phpmailer',
	'retention_days' => 365
]
```

Important keys:

| Key                 | Meaning                                                   |
| ------------------- | --------------------------------------------------------- |
| `enabled`           | Global messaging switch.                                  |
| `default_transport` | Transport used when no message-specific transport is set. |
| `retention_days`    | Delivery log retention for cleanup jobs.                  |

### 8.2 Transport settings

Group:

```text
messaging_transports
```

Name examples:

```text
phpmailer
log
```

Example PHPMailer settings:

```php
[
	'enabled' => true,
	'mode' => 'mail',
	'from_address' => 'noreply@example.org',
	'from_name' => 'ILIAS',
	'reply_to_address' => '',
	'reply_to_name' => '',
	'smtp_host' => '',
	'smtp_port' => 587,
	'smtp_auth' => true,
	'smtp_username' => '',
	'smtp_password' => '',
	'smtp_secure' => 'tls',
	'debug' => false
]
```

For SMTP mode:

```php
[
	'enabled' => true,
	'mode' => 'smtp',
	'from_address' => 'noreply@example.org',
	'from_name' => 'ILIAS',
	'smtp_host' => 'smtp.example.org',
	'smtp_port' => 587,
	'smtp_auth' => true,
	'smtp_username' => 'smtp-user',
	'smtp_password' => [
		'mode' => 'env',
		'name' => 'SMTP_PASSWORD'
	],
	'smtp_secure' => 'tls',
	'debug' => false
]
```

Secrets should preferably be stored as config value definitions and resolved at runtime by `IConfigValueResolver`.

---

## 9. Admin displays

MessageHub provides several administration displays.

The project plugin decides where these displays are shown.

In `Base3IliasLab`, they are usually exposed under:

```text
Messaging
```

### 9.1 Dashboard

Technical name:

```text
messagehubdashboarddisplay
```

Purpose:

```text
Shows queue and delivery summary information.
```

### 9.2 Templates

Technical name:

```text
messagetemplateadmindisplay
```

Purpose:

```text
Manage message templates.
```

Supports:

* list templates
* search templates
* create templates
* edit templates
* delete templates
* sync message types

### 9.3 Variants

Technical name:

```text
messagevariantadmindisplay
```

Purpose:

```text
Manage language-specific message variants.
```

Supports:

* list variants
* search variants
* create variants
* edit variants
* delete variants

### 9.4 Type Sync

Technical name:

```text
messagetypesyncadmindisplay
```

Purpose:

```text
Discover IMessageTypeProvider implementations and create missing templates/variants.
```

### 9.5 Queue

Technical name:

```text
messagequeueadmindisplay
```

Purpose:

```text
Inspect queued, retrying, failed and processed messages.
```

Supports:

* list queue records
* filter by status, transport and type
* cancel messages
* process a batch manually

### 9.6 Deliveries

Technical name:

```text
messagedeliverylogadmindisplay
```

Purpose:

```text
Inspect delivery attempts and transport results.
```

Supports:

* list deliveries
* filter by status, transport and type
* inspect details
* inspect serialized message data
* inspect result metadata

### 9.7 Transports

Technical name:

```text
messagetransportadmindisplay
```

Purpose:

```text
Inspect discoverable transports and edit transport settings.
```

Supports:

* list transports
* show schema
* show active settings
* set default transport
* edit settings JSON

---

## 10. Worker jobs

MessageHub provides discoverable worker jobs.

### 10.1 Queue worker

Class:

```php
MessageHub\Job\MessageQueueWorkerJob
```

Technical name:

```text
messagequeueworkerjob
```

Purpose:

```text
Claims queued messages and delivers them through the configured transport.
```

The worker processes a batch of queue entries by calling:

```php
MessageHub\Service\MessageDeliveryService::processBatch()
```

### 10.2 Delivery cleanup

Class:

```php
MessageHub\Job\MessageDeliveryCleanupJob
```

Technical name:

```text
messagedeliverycleanupjob
```

Purpose:

```text
Deletes old delivery records based on retention_days.
```

Retention is read from:

```text
messaging/default/retention_days
```

---

## 11. Events

MessageHub fires domain events through `IEventManager`.

Events live in `MessagingFoundation\Event`.

Important events:

```php
MessagingFoundation\Event\MessageQueuedEvent
MessagingFoundation\Event\MessageSentEvent
MessagingFoundation\Event\MessageFailedEvent
```

Typical use cases:

* log additional audit entries
* notify other subsystems
* trigger metrics
* update dashboards
* react to failed delivery
* integrate with monitoring

Example listener registration:

```php
$eventManager->on(
	MessageSentEvent::class,
	function(MessageSentEvent $event): void {
		// react to successful delivery
	}
);
```

Events are synchronous. They should not perform slow unrelated work directly.

---

## 12. Transport model

Transports are discoverable implementations of:

```php
MessagingFoundation\Api\IMessageTransport
```

A transport must provide:

```php
public static function getName(): string;
public function getLabel(): string;
public function supports(Message $message, array $settings = []): bool;
public function send(Message $message, array $settings = []): MessageDeliveryResult;
public function getSchema(): array;
```

### 12.1 Log transport

MessageHub provides:

```php
MessageHub\Transport\LogMessageTransport
```

Technical name:

```text
log
```

Purpose:

```text
Diagnostic transport. It logs the message and marks delivery as successful.
```

This transport is useful when no real mail transport should be used.

### 12.2 PHPMailer transport

In ILIAS-based installations, the PHPMailer transport is expected to be provided by:

```php
Base3IliasLab\Messaging\PhpMailerMessageTransport
```

Technical name:

```text
phpmailer
```

PHPMailer is not part of MessageHub itself. It belongs to the project plugin because it depends on the host environment.

---

## 13. Consumer plugin usage

A consumer plugin should not write to MessageHub tables directly.

It should use the public contracts from `MessagingFoundation`.

### 13.1 Provide a message type

```php
<?php declare(strict_types=1);

namespace ExamplePlugin\Message;

use MessagingFoundation\Api\IMessageTypeProvider;

final class ExampleWelcomeMessageTypeProvider implements IMessageTypeProvider {

	public static function getName(): string {
		return 'examplewelcomemessage';
	}

	public function getLabel(): string {
		return 'Example welcome message';
	}

	public function getDescription(): string {
		return 'Welcome message sent by ExamplePlugin.';
	}

	public function getDefaultSubject(): string {
		return 'Welcome, {{name}}';
	}

	public function getDefaultBodyText(): string {
		return "Hello {{name}},\n\nwelcome to {{system_name}}.";
	}

	public function getDefaultBodyHtml(): string {
		return '<p>Hello {{name}},</p><p>welcome to <strong>{{system_name}}</strong>.</p>';
	}

	public function getPlaceholders(): array {
		return [
			[
				'name' => 'name',
				'label' => 'Name',
				'description' => 'Recipient display name.',
				'required' => true,
				'example' => 'Jane Doe'
			], [
				'name' => 'system_name',
				'label' => 'System name',
				'description' => 'Name of the current system.',
				'required' => true,
				'example' => 'ILIAS / BASE3'
			]
		];
	}

	public function getSchema(): array {
		return [
			'type' => 'object',
			'properties' => [
				'name' => ['type' => 'string'],
				'system_name' => ['type' => 'string']
			],
			'required' => [
				'name',
				'system_name'
			]
		];
	}
}
```

After the class map sees this provider, MessageHub can synchronize it into a template and default variant.

### 13.2 Render and enqueue a message

```php
use MessagingFoundation\Api\IMessageRenderer;
use MessagingFoundation\Api\IMessageService;
use MessagingFoundation\Dto\MessageAddress;

final class ExampleNotificationService {

	public function __construct(
		private readonly IMessageRenderer $messageRenderer,
		private readonly IMessageService $messageService
	) {}

	public function sendWelcomeMessage(string $email, string $name): string {
		$message = $this->messageRenderer
			->render(
				'examplewelcomemessage',
				'en',
				[
					'name' => $name,
					'system_name' => 'ILIAS / BASE3'
				]
			)
			->withRecipient(
				new MessageAddress('to', $email, $name)
			)
			->withMetadata(
				[
					'consumer_plugin' => 'ExamplePlugin'
				]
			);

		return $this->messageService->enqueue($message);
	}
}
```

The returned string is the queue ID.

### 13.3 Send immediately

```php
$queueId = $this->messageService->sendNow($message);
```

This creates a queue record and processes the message immediately.

Use this only when immediate delivery is explicitly desired. For normal runtime behavior, prefer `enqueue()`.

### 13.4 Choose a specific transport

```php
$queueId = $this->messageService->enqueue(
	$message,
	'phpmailer'
);
```

If no transport is provided, MessageHub uses the default transport from:

```text
messaging/default/default_transport
```

---

## 14. Template synchronization

MessageHub can synchronize message type providers into templates.

Service:

```php
MessagingFoundation\Api\IMessageTypeSynchronizationService
```

Synchronize all discovered providers:

```php
$result = $messageTypeSynchronizationService->synchronizeAll(
	true,
	'en'
);
```

Synchronize one provider:

```php
$result = $messageTypeSynchronizationService->synchronizeProvider(
	new ExampleWelcomeMessageTypeProvider(),
	true,
	'en'
);
```

Parameters:

| Parameter        | Meaning                                        |
| ---------------- | ---------------------------------------------- |
| `createVariants` | Whether to create missing default variants.    |
| `language`       | Language code for the created default variant. |

Synchronization does not overwrite existing records.

This is intentional. After an administrator edits a template or variant, future synchronization should not destroy those edits.

---

## 15. Queue lifecycle

Typical queue lifecycle:

```text
enqueue
  -> queued
  -> worker claims message
  -> processing
  -> transport sends message
  -> sent
```

Failure lifecycle:

```text
enqueue
  -> queued
  -> processing
  -> transport fails
  -> retry_wait
  -> queued again later
  -> processing
  -> failed after max attempts
```

Manual cancellation:

```text
queued/retry_wait
  -> cancelled
```

### 15.1 Claiming

The queue repository claims messages by status and `not_before`.

Ready statuses:

```text
queued
retry_wait
```

The worker moves claimed messages to:

```text
processing
```

### 15.2 Retry behavior

Failed messages are retried until `max_attempts` is reached.

Default behavior:

```text
max_attempts = 3
retry delay = 300 seconds
```

After the last failed attempt, the queue entry becomes:

```text
failed
```

### 15.3 Delivery records

Every delivery attempt creates a delivery record, even failed attempts.

This makes it possible to inspect:

* attempted transport
* serialized message
* recipients
* attachments
* error message
* result metadata
* timestamps

---

## 16. Checks

MessageHub provides a check class:

```php
MessageHub\Check\MessageHubCheck
```

Technical name:

```text
messagehubcheck
```

The check verifies:

* storage tables can be ensured
* transports are discoverable

Checks are intended for diagnostics and administration.

---

## 17. Installation

### 17.1 Requirements

MessageHub requires:

```text
BASE3 Framework
MessagingFoundation
IDatabase
ISettingsStore
ILogger
IEventManager
IClassMap
```

For ILIAS usage with PHPMailer:

```text
Base3Ilias
Base3IliasLab
PHPMailer available through ILIAS Composer runtime
```

### 17.2 Copy plugin

Copy the plugin directory into the BASE3 plugin location.

In the ILIAS embedded layout used by this project, this is typically:

```text
components/Base3/MessageHub
```

Also ensure that `MessagingFoundation` is installed:

```text
components/Base3/MessagingFoundation
```

### 17.3 Clear or regenerate class map

If class map caching is enabled, clear or regenerate it after installing or updating MessageHub.

The class map must discover:

```text
messagehubplugin
messagequeueworkerjob
messagedeliverycleanupjob
messagehubcheck
messagehubdashboarddisplay
messagetemplateadmindisplay
messagevariantadmindisplay
messagetypesyncadmindisplay
messagequeueadmindisplay
messagedeliverylogadmindisplay
messagetransportadmindisplay
log
```

### 17.4 Ensure tables

Tables are created through `DatabaseSchema::ensureTables()`.

This is called by repositories and checks as needed.

No legacy migration is performed.

MessageHub uses only new `base3_messaging_*` tables.

---

## 18. Configuration checklist

### 18.1 Global settings

Ensure:

```text
group: messaging
name: default
```

Example:

```php
[
	'enabled' => true,
	'default_transport' => 'phpmailer',
	'retention_days' => 365
]
```

### 18.2 PHPMailer settings

Ensure:

```text
group: messaging_transports
name: phpmailer
```

Minimum working mail-mode example:

```php
[
	'enabled' => true,
	'mode' => 'mail',
	'from_address' => 'noreply@example.org',
	'from_name' => 'ILIAS',
	'reply_to_address' => '',
	'reply_to_name' => '',
	'smtp_host' => '',
	'smtp_port' => 587,
	'smtp_auth' => true,
	'smtp_username' => '',
	'smtp_password' => '',
	'smtp_secure' => 'tls',
	'debug' => false
]
```

Important:

```text
from_address must not be empty for real PHPMailer delivery.
```

If `from_address` is empty and the message itself has no sender, delivery fails with:

```text
PHPMailer transport needs a from_address setting or message from address.
```

Fix:

```text
Messaging -> Transports -> phpmailer -> Edit settings JSON
```

Set:

```php
'from_address' => 'noreply@example.org'
```

---

## 19. Administration workflow

Recommended first setup:

```text
1. Install MessagingFoundation.
2. Install MessageHub.
3. Install a transport provider, for example Base3IliasLab PHPMailer transport.
4. Clear/regenerate class map.
5. Open Messaging -> Transports.
6. Configure the default transport.
7. Set from_address for PHPMailer.
8. Open Messaging -> Type Sync.
9. Synchronize message types.
10. Open Messaging -> Templates and Variants.
11. Adjust texts if needed.
12. Enqueue a test message.
13. Run the queue worker or process a queue batch manually.
14. Check Messaging -> Deliveries.
```

---

## 20. Troubleshooting

### 20.1 Transport not visible

Cause:

```text
The transport class was not discovered by the class map.
```

Check:

* class is under `src/`
* namespace matches path
* class implements `IMessageTransport`
* `getName()` returns a stable lowercase name
* class map cache was cleared/regenerated
* constructor dependencies can be resolved

### 20.2 No default transport

Symptom:

```text
Message transport not found: log
```

or messages are sent through the wrong transport.

Check:

```text
messaging/default/default_transport
```

Set it to:

```text
phpmailer
```

or another available transport.

### 20.3 PHPMailer sender error

Error:

```text
PHPMailer transport needs a from_address setting or message from address.
```

Fix:

Set:

```text
messaging_transports/phpmailer/from_address
```

Example:

```php
'from_address' => 'noreply@example.org'
```

Alternatively, the consumer plugin can set a sender on the message:

```php
$message = $message->withSender(
	'noreply@example.org',
	'ILIAS'
);
```

For normal system behavior, prefer the transport setting.

### 20.4 Message type does not appear

Cause:

```text
The IMessageTypeProvider was not discovered.
```

Check:

* provider class is under the consumer plugin `src/` directory
* namespace matches file path
* provider implements `IMessageTypeProvider`
* provider has a stable lowercase `getName()`
* class map cache was cleared/regenerated

Then run:

```text
Messaging -> Type Sync
```

### 20.5 Template exists but rendering fails

Possible causes:

* template is disabled
* variant is missing
* variant is disabled
* requested language has no variant
* fallback language has no variant
* placeholder context is incomplete

Check:

```text
Messaging -> Templates
Messaging -> Variants
```

### 20.6 Message remains queued

Possible causes:

* worker is not running
* message has `not_before` in the future
* status is `retry_wait`
* transport failed and message is waiting for retry
* queue worker job is inactive or not discovered

Check:

```text
Messaging -> Queue
Messaging -> Deliveries
```

Run manually:

```text
Messaging -> Queue -> Process batch
```

### 20.7 Delivery failed

Check:

```text
Messaging -> Deliveries -> detail
```

Inspect:

* error message
* transport name
* message JSON
* recipients
* attachment paths
* result JSON

For PHPMailer specifically, check:

* `from_address`
* recipient email
* mail mode vs SMTP mode
* SMTP credentials
* SMTP host and port
* attachment readability
* server mail configuration

---

## 21. Design decisions

### 21.1 Why queue-first?

The queue is central because message delivery is a cross-cutting concern.

If MessageHub did not provide a queue, every consumer plugin would likely implement its own queue, retry handling and log table. That would duplicate logic and lead to inconsistent behavior.

MessageHub therefore makes queueing part of the core implementation.

### 21.2 Why not use `IStateStore` for the queue?

The queue is business/domain data and must be searchable, pageable and inspectable.

`IStateStore` is better suited for small operational markers such as locks, cursors or last-run timestamps.

MessageHub therefore uses dedicated database tables for queue and delivery data.

### 21.3 Why string IDs?

MessageHub uses generated string IDs instead of relying on database `insertId()` behavior.

This keeps the implementation portable across host adapters and avoids assumptions about auto-increment behavior.

Example ID prefixes:

```text
tpl_
var_
que_
del_
rcp_
att_
```

### 21.4 Why no legacy migration?

MessageHub is a new BASE3-native implementation.

It intentionally does not migrate old MailAdministration tables and does not use legacy scanners or compatibility adapters.

This allows it to coexist with old mail plugins in the same installation.

### 21.5 Why PHPMailer outside MessageHub?

MessageHub is protocol-neutral.

PHPMailer availability depends on the host/project environment, especially in ILIAS where PHPMailer is available through the ILIAS Composer runtime.

Therefore PHPMailer belongs into a project or host integration plugin such as `Base3IliasLab`.

### 21.6 Why message type providers?

Message type providers allow consumer plugins to declare messages without coupling MessageHub to those plugins.

MessageHub discovers providers and turns them into editable templates.

This keeps the dependency direction clean:

```text
Consumer plugin -> MessagingFoundation
MessageHub -> MessagingFoundation
MessageHub does not depend on Consumer plugin
```

---

## 22. Extension points

### 22.1 Add a new message type

Implement:

```php
MessagingFoundation\Api\IMessageTypeProvider
```

Then synchronize message types.

### 22.2 Add a new transport

Implement:

```php
MessagingFoundation\Api\IMessageTransport
```

Example technical names:

```text
webhook
sms
push
matrix
teams
smtp
```

The transport should expose a useful schema through `getSchema()` so administration UIs can display or validate settings.

### 22.3 Add event listeners

Listen to:

```php
MessageQueuedEvent
MessageSentEvent
MessageFailedEvent
```

Use this for side effects such as metrics, monitoring or domain reactions.

### 22.4 Replace repositories

MessageHub repositories are registered behind interfaces.

A project plugin may replace:

```php
IMessageTemplateRepository
IMessageVariantRepository
IMessageQueueRepository
IMessageDeliveryRepository
```

This can be useful for a different database backend or more advanced storage behavior.

### 22.5 Replace renderer

A project can replace:

```php
IMessageRenderer
```

This can be useful for advanced templating, localization or placeholder validation.

---

## 23. Security and privacy notes

MessageHub may store sensitive information in queue and delivery tables.

Examples:

* recipient email addresses
* rendered subjects
* rendered message bodies
* metadata
* attachment paths
* transport errors

Use appropriate retention settings.

Recommended:

```text
retention_days = 365
```

or shorter depending on the project.

Do not store secrets in message metadata.

Transport passwords should not be stored as plain settings values when avoidable. Prefer config value definitions, for example:

```php
[
	'mode' => 'env',
	'name' => 'SMTP_PASSWORD'
]
```

Delivery logs are useful for diagnostics, but they are also operational records that may contain personal data.

---

## 24. Development notes

### 24.1 Coding style

MessageHub follows BASE3 conventions:

* `<?php declare(strict_types=1);`
* one class per PHP file
* namespaces match paths
* constructor injection
* services registered in plugin `init()`
* runtime code depends on interfaces
* discoverable classes use stable lowercase `getName()` values
* displays and templates are kept parallel

### 24.2 Display pattern

Displays usually follow this pattern:

```php
public function getOutput(string $out = 'html', bool $final = false): string {
	if(strtolower($out) === 'json') {
		return $this->handleJson($final);
	}

	return $this->handleHtml();
}
```

HTML is rendered through:

```php
IMvcView
```

JSON requests are read through:

```php
IRequest::getJsonBody()
```

Links are generated with:

```php
ILinkTargetService
```

Assets are resolved with:

```php
IAssetResolver
```

### 24.3 Database usage

Repositories use:

```php
Base3\Database\Api\IDatabase
```

They call `connect()` before database work and use `escape()` when constructing SQL.

### 24.4 Logging

MessageHub logs through:

```php
Base3\Logger\Api\ILogger
```

Use structured context with a stable scope:

```php
[
	'scope' => 'messagehub'
]
```

---

## 25. Example: full consumer flow

```php
<?php declare(strict_types=1);

namespace ExamplePlugin\Service;

use MessagingFoundation\Api\IMessageRenderer;
use MessagingFoundation\Api\IMessageService;
use MessagingFoundation\Dto\MessageAddress;

final class ExampleMessageService {

	public function __construct(
		private readonly IMessageRenderer $messageRenderer,
		private readonly IMessageService $messageService
	) {}

	public function sendExample(string $email, string $name): string {
		$message = $this->messageRenderer
			->render(
				'examplewelcomemessage',
				'en',
				[
					'name' => $name,
					'system_name' => 'BASE3'
				]
			)
			->withRecipient(
				new MessageAddress('to', $email, $name)
			)
			->withMetadata(
				[
					'consumer_plugin' => 'ExamplePlugin'
				]
			);

		return $this->messageService->enqueue($message);
	}
}
```

---

## 26. Current limitations

MessageHub currently provides the core messaging mechanics but does not yet include:

* advanced placeholder validation UI
* attachment storage abstraction
* per-scope template inheritance UI
* tenant-specific transport routing
* transport testing UI
* rich WYSIWYG template editor
* automatic bounce handling
* delivery webhooks
* rate limiting
* advanced worker execution policies

These can be added without changing the core architecture.

---

## 27. Recommended next steps

Useful next improvements:

1. Add sender fields to the demo UI.
2. Add a transport test action to `MessageTransportAdminDisplay`.
3. Add placeholder preview/validation to template and variant displays.
4. Add an execution policy to the queue worker job.
5. Add retention settings UI.
6. Add optional state-store locks around queue worker execution.
7. Add support for attachment storage through a dedicated file storage abstraction.
8. Add per-message priority and delay fields to consumer examples.
9. Add a “render preview” action for templates.
10. Add tests for rendering, queue transitions and retry behavior.

---

## 28. License

MessageHub follows the license policy of the surrounding BASE3 project.

Add the concrete license text or reference here if required by the project:

```text
GPL-3.0
```

---

## 29. Summary

MessageHub provides a BASE3-native messaging implementation.

It is:

* protocol-neutral
* queue-first
* template-based
* variant-aware
* worker-ready
* transport-extensible
* discoverable through BASE3 class map mechanisms
* configurable through `ISettingsStore`
* observable through delivery logs and events
* reusable by consumer plugins through `MessagingFoundation`

Consumer plugins should not implement their own queues or delivery tables. They should provide message type providers and use:

```php
IMessageRenderer
IMessageService
```

MessageHub then handles rendering, queueing, delivery, retrying and logging.
