# MessageHub FAQ

## What is MessageHub?

MessageHub is the BASE3 implementation of the contracts defined by MessagingFoundation. It provides message templates, language variants, rendering, queue persistence, delivery records, retries, transport discovery, worker jobs, administration displays, and multiple built-in delivery transports.

It is a general messaging component and is not tied to one host application or one delivery protocol.

## Which data is persisted by MessageHub?

The default implementation uses database-backed repositories and creates these tables:

- `base3_messaging_templates`
- `base3_messaging_variants`
- `base3_messaging_queue`
- `base3_messaging_deliveries`
- `base3_messaging_recipients`
- `base3_messaging_attachments`

The tables are created on demand by `DatabaseSchema::ensureTables()`.

## What is stored in the queue?

Each queue row stores operational fields such as status, priority, attempts, retry timing, transport name, and timestamps.

It also stores `message_json`, which is a serialized copy of the complete `Message` DTO. That JSON can contain the rendered subject, plain text body, HTML body, recipients, attachment metadata, sender information, reply-to information, and custom metadata.

## What is stored in delivery records?

A delivery record stores another serialized copy of the complete message plus:

- queue ID
- message type
- transport name
- subject
- status
- attempt count
- error message
- serialized delivery result
- timestamps

Recipients are also stored in `base3_messaging_recipients`, and attachment metadata is stored in `base3_messaging_attachments`.

## Does `sendNow()` bypass the queue?

No. The current implementation first inserts a queue record, then immediately creates a `QueuedMessage` object and calls the delivery service.

This means `sendNow()` still creates a persistent queue row and a delivery record.

## What is the default transport?

`MessageTransportRegistry` reads `messaging/default.default_transport` from `ISettingsStore`.

If no value is configured, the default transport name is:

```text
log
```

The log transport writes the message type and subject to the BASE3 logger and reports the delivery as successful.

## Which built-in transports are included?

The current source contains these transport implementations:

| Technical name | Transport |
| --- | --- |
| `log` | Log only |
| `null` | Successful discard |
| `httpwebhook` | Generic HTTP webhook |
| `slack` | Slack Incoming Webhook |
| `microsoftteams` | Microsoft Teams Webhook |
| `ntfy` | ntfy |
| `telegram` | Telegram Bot API |
| `twiliosms` | Twilio SMS |
| `whatsappcloud` | WhatsApp Cloud API |
| `smtp` | Built-in SMTP client |
| `sendmail` | Local sendmail process |
| `phpmailer` | PHPMailer using PHP mail or SMTP |

All transport implementations are discoverable through `IMessageTransport`.

## Are all transports enabled automatically?

No. Most network transports use an `enabled` setting that defaults to false.

The `log` and `null` transports default to enabled when they have no explicit setting overriding that default.

The administration display can enable or disable each transport independently.

## Where are transport settings stored?

Transport settings are stored through `ISettingsStore` under:

```text
messaging_transports/<transport-name>
```

General messaging settings are stored under:

```text
messaging/default
```

This includes the default transport and can include `retention_days` for delivery cleanup.

## How should transport secrets be stored?

Transport schemas support values that can be resolved through `IConfigValueResolver` for secret fields such as passwords, webhook URLs, bearer tokens, bot tokens, and API access tokens.

For production secrets, a late-resolved ConfigValue definition is preferable to a fixed plaintext value in the editable settings store.

Example:

```json
{
  "mode": "env",
  "name": "SMTP_PASSWORD"
}
```

## Does the transport administration UI mask fixed secrets?

No general masking layer is implemented in `MessageTransportAdminDisplay`.

The display returns the stored transport settings as JSON to the browser and builds editable controls from those values. If a secret is stored as a fixed plaintext value, it can therefore be exposed to users who can access that administration endpoint.

Using a ConfigValue definition avoids placing the resolved secret itself in the settings record.

## How are message types registered?

Consumer plugins implement `IMessageTypeProvider`.

MessageHub discovers these providers through `IClassMap`. A provider defines its technical name, label, description, default subject, default bodies, placeholders, and schema.

## What does message type synchronization do?

`MessageTypeSynchronizationService` can synchronize one provider or all providers.

For a missing provider definition it creates:

- one global `MessageTemplate`
- one language-specific `MessageVariant`

If the template or language variant already exists, synchronization skips it. It does not overwrite customized content.

## How are templates scoped?

The template DTO and repository support:

- `scope_type`
- `scope_id`

The current renderer resolves the default global template by message type unless a different implementation is introduced.

## How are language variants selected?

The renderer first looks for an enabled variant matching the requested language.

If none exists, it asks the variant repository for an enabled variant marked as fallback.

If neither exists, rendering fails with a `MessageException`.

## Can more than one fallback variant exist?

The database repository actively clears the fallback flag from other variants of the same template when a variant is saved as fallback. The current default repository therefore keeps at most one fallback variant per template.

## How are placeholders rendered?

`MessageRenderer` performs simple string replacement using placeholders in this form:

```text
{{name}}
```

Only scalar and null context values are substituted.

The replacement is applied independently to subject, plain text body, and HTML body.

## Does MessageHub validate required placeholders?

No. Provider schemas and placeholder descriptions can document expected values, but the renderer does not validate required fields against the provider schema before rendering.

Unknown placeholders remain in the text if no matching context value is provided.

## Does MessageHub escape placeholder values?

No automatic output-context escaping is performed by `MessageRenderer`.

The same string replacement is used for plain text and HTML bodies. A consumer that inserts untrusted values into HTML templates must account for the intended output context.

## How does queue retry work?

New queue rows start with:

- status `queued`
- attempts `0`
- maximum attempts `3`

A failed delivery increments the attempt count. If the maximum has not been reached, the status becomes `retry_wait`. Otherwise it becomes `failed`.

The current delivery service uses a retry delay of 300 seconds after a failed delivery.

## Which queue statuses are used?

The current queue implementation uses:

- `queued`
- `retry_wait`
- `processing`
- `sent`
- `failed`
- `cancelled`

The administration display can cancel queue rows and can trigger a processing batch manually.

## How are queue items claimed?

`claimNext()` selects ready rows ordered by ascending priority and creation time, marks them as `processing`, and sets `locked_until`.

The default worker processes up to 20 messages per run.

The current repository does not use the `locked_until` field as a recovery filter for already processing rows. Operational monitoring should therefore include messages that remain in `processing` unexpectedly.

## Which worker jobs are included?

MessageHub provides:

- `messagequeueworkerjob`
- `messagedeliverycleanupjob`

Both are normal BASE3 jobs and read their active state and priority from the `job` configuration group.

If the respective `.active` value is not set to `1`, the job is inactive.

## What does the queue worker do?

`MessageQueueWorkerJob` calls `MessageDeliveryService::processBatch(20)`.

For each claimed message, the delivery service:

1. creates a delivery record
2. resolves the transport
3. reads transport settings
4. attempts delivery
5. stores the delivery result
6. updates queue state
7. emits a sent or failed event
8. writes an operational log entry

## What does the delivery cleanup job remove?

`MessageDeliveryCleanupJob` reads `messaging/default.retention_days`, defaulting to 365 days.

When active, it deletes old rows from:

- `base3_messaging_deliveries`
- `base3_messaging_recipients`
- `base3_messaging_attachments`

It does not delete queue rows.

## Are completed queue messages cleaned up automatically?

No queue cleanup implementation is present in the current source.

`base3_messaging_queue` keeps the serialized `message_json` after a message becomes sent, failed, or cancelled. A deployment that needs queue retention limits must address this at the owning storage boundary.

## What events does MessageHub emit?

The implementation emits:

- `MessageQueuedEvent` after queue insertion
- `MessageSentEvent` after successful delivery
- `MessageFailedEvent` after failed delivery

The failed event includes the error message.

## What does MessageHub log?

The delivery service logs:

- successful deliveries with queue ID and delivery ID
- failed deliveries with queue ID, delivery ID, and error message

The `log` transport additionally logs:

- message type
- message subject

Transport base classes also log exception messages for transport failures.

## Does MessageHub store provider responses?

It can.

`MessageDeliveryResult` is serialized into `result_json`. Built-in transports can place external message IDs, status codes, provider-specific IDs, recipient counts, or short response fragments in the result details.

The generic HTTP webhook can optionally store up to 500 characters of the response body. Slack and Microsoft Teams results also include short response fragments.

## Which transports send attachment content?

The email-oriented transports can read and send local attachment files:

- `smtp`
- `sendmail`
- `phpmailer`

The generic HTTP webhook can include attachment metadata through `Message::toArray()`, but it does not upload the referenced file content automatically.

The current Slack, Microsoft Teams, ntfy, Telegram, Twilio SMS, and WhatsApp Cloud text transports reject messages with attachments.

## How does the generic HTTP webhook work?

`httpwebhook` supports POST, PUT, and PATCH with JSON, form, or text content.

It can:

- send the complete serialized message
- send a custom payload
- replace message placeholders in a custom payload or text template
- add configured headers
- use bearer or basic authentication
- read an external ID from a JSON response path
- optionally include part of the response body in delivery details

The endpoint and secret values can be resolved through `IConfigValueResolver`.

## Does MessageHub follow HTTP redirects?

No. The shared HTTP transport layer disables redirects and restricts request protocols to HTTP and HTTPS.

## Is TLS verification enabled?

The HTTP transport layer supports a `verify_tls` setting and defaults it to true when the transport reads that setting with the standard default.

Several transport schemas expose this option. Disabling TLS verification weakens server authentication and should be restricted to controlled diagnostic situations.

## How large can an HTTP response be?

The shared HTTP transport reads at most 1 MiB of response body into memory. If a larger response is received, it marks the response as truncated internally.

Only smaller transport-selected excerpts are normally persisted in delivery result details.

## Does MessageHub include administration displays?

Yes. The current plugin provides displays for:

- dashboard
- templates
- variants
- queue
- delivery log
- transports
- message type synchronization

These displays expose HTML and JSON operations for administration tasks.

## Does MessageHub itself enforce administrator permissions?

No user or permission service is injected into the MessageHub administration displays.

The component therefore does not itself establish the authorization boundary for those endpoints. The host application that exposes the displays must ensure that only intended administrators can reach them.

## Does MessageHub implement its own CSRF token for administration actions?

No component-specific CSRF token is visible in the current MessageHub display endpoints. They accept JSON POST requests through `IRequest`.

If the host exposes these endpoints in a browser session, request-forgery protection must be provided at the surrounding application boundary.

## Does the administration UI use browser storage?

Several ModularGrid-based MessageHub displays use `sessionStorage` for grid state such as:

- search query
- filters
- visible columns

This state is browser-local and scoped to the browser session. Search terms and filter values can therefore remain available until that session storage is cleared.

## What is shown in delivery detail?

The delivery detail endpoint returns:

- delivery identifiers
- type and transport
- subject
- status and errors
- the complete decoded stored message
- the decoded delivery result
- recipients
- attachment metadata
- timestamps

This can contain personal and confidential information and should be treated as an administrative diagnostic view.

## Does MessageHub validate recipient ownership or consent?

No. It delivers to destinations supplied by the message producer according to the selected transport.

Consent, unsubscribe logic, recipient eligibility, and business rules belong to the consumer application.

## Does MessageHub provide bounce processing or delivery webhooks?

No automatic bounce processing or inbound delivery-status callback handling is included in the current source.

Some provider transports return outbound message IDs, but MessageHub does not currently use those IDs to synchronize later provider-side delivery state.

## Can MessageHub be extended with another transport?

Yes. Implement `MessagingFoundation\Api\IMessageTransport` with a stable lowercase `getName()` value and make the class discoverable through the BASE3 class map.

The transport is then available through `IMessageTransportRegistry` without adding it to a hard-coded transport switch.
