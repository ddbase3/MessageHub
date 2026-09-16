# Privacy and Data Processing in MessageHub

This document describes the privacy-relevant behavior of the MessageHub component itself. MessageHub is the concrete BASE3 messaging implementation for templates, rendering, queue persistence, delivery processing, delivery logs, administration, and protocol-specific transports.

The actual data processed depends on the messages created by consumer plugins and on the transports enabled in the installation.

## Component scope

MessageHub currently provides:

- database-backed message templates and variants
- database-backed queue persistence
- database-backed delivery records
- recipient and attachment metadata persistence
- placeholder rendering
- queue processing and retry handling
- transport discovery and settings
- built-in network and local transports
- delivery lifecycle events
- operational logging
- worker jobs
- administration displays
- message type synchronization

Because MessageHub is an implementation plugin, installing and using it can create persistent copies of message content.

## Database tables

The default database implementation creates:

```text
base3_messaging_templates
base3_messaging_variants
base3_messaging_queue
base3_messaging_deliveries
base3_messaging_recipients
base3_messaging_attachments
```

These tables are created on demand by the component.

## Queue records contain the complete rendered message

`base3_messaging_queue.message_json` stores the serialized `Message` DTO.

Depending on the message, this can contain:

- message type
- subject
- plain text body
- HTML body
- recipient addresses
- recipient names
- attachment paths and metadata
- sender address and name
- reply-to address and name
- arbitrary metadata

The queue therefore must be treated as a store of potentially personal and confidential communication content.

## Delivery records duplicate the complete message

When MessageHub starts a delivery attempt, it creates a row in `base3_messaging_deliveries` and stores another serialized copy of the message in `message_json`.

The delivery table also stores:

- queue ID
- type name
- transport name
- subject
- delivery status
- attempt count
- error message
- serialized delivery result
- creation, update, and sent timestamps

This means one logical message can exist in both the queue and delivery history at the same time.

## Recipient records

`base3_messaging_recipients` stores recipients separately for delivery diagnostics.

Stored fields include:

- recipient kind such as `to`, `cc`, or `bcc`
- recipient address
- recipient label or name

Depending on the transport, the address can be an email address, phone number, chat ID, topic, or another destination identifier.

## Attachment records

`base3_messaging_attachments` stores attachment metadata, including:

- local file path
- filename
- MIME type
- inline flag
- content ID

MessageHub does not copy the attachment bytes into this table. The path itself can still reveal sensitive server or document information.

Email-capable transports can read the referenced local file when delivery occurs.

## Templates and variants

Template and variant tables can store:

- message type identifiers
- labels and descriptions
- scope values
- default transport names
- language identifiers
- subjects
- plain text bodies
- HTML bodies
- fallback and enabled state

Templates usually contain reusable text, but administrators can place personal or confidential information into them. Stored template content should therefore be protected like other editable application content.

## Message metadata

Message metadata is persisted as part of `message_json` in both queue and delivery records.

Consumers should not place secrets, session identifiers, authentication tokens, or unnecessary personal data into metadata.

If metadata is required for downstream processing, keep it limited to the minimum needed values.

## Queue retention

The current source contains no cleanup operation for `base3_messaging_queue`.

Sent, failed, and cancelled queue rows retain their serialized `message_json` unless another administrative or database process removes them.

This is a significant retention boundary because the queue can contain complete rendered communication content.

A deployment should define an explicit retention policy for completed queue rows if indefinite retention is not intended.

## Delivery retention

MessageHub provides `MessageDeliveryCleanupJob`.

When the job is enabled, it reads:

```text
messaging/default.retention_days
```

and defaults to 365 days when the setting is absent.

The cleanup removes old rows from:

- `base3_messaging_deliveries`
- `base3_messaging_recipients`
- `base3_messaging_attachments`

The job does not remove queue records.

The cleanup job is inactive unless the corresponding job configuration enables it.

## Queue processing and retries

New queue records use a maximum of three attempts in the default repository. Failed delivery uses a retry delay of 300 seconds until the maximum attempt count is reached.

Error messages are stored in the queue `last_error` field and can also be stored in the delivery record and emitted through a failure event.

Provider or transport errors can contain endpoint information, remote error text, recipient references, or other operational details.

## Delivery result persistence

Each transport returns a `MessageDeliveryResult`. MessageHub serializes the complete result into `base3_messaging_deliveries.result_json`.

Built-in transports can store details such as:

- HTTP status codes
- external provider IDs
- message IDs or SIDs
- delivery counts
- transport mode
- short remote response fragments
- exception class name

The generic HTTP webhook can optionally persist up to 500 characters of the remote response body. Slack and Microsoft Teams results include short response text on successful delivery.

These values should be included in the installation's retention and access review.

## External transfers depend on the selected transport

MessageHub itself supports multiple delivery boundaries. Data leaves the application only when an enabled transport performs delivery.

### SMTP and mail transports

The following transports can transmit message content, recipients, and attachments through mail infrastructure:

- `smtp`
- `phpmailer`
- `sendmail`

The concrete processing location depends on the configured SMTP server, local mail transfer agent, PHP mail setup, or PHPMailer mode.

### Generic HTTP webhook

`httpwebhook` can transmit the complete serialized message to an arbitrary configured HTTP or HTTPS endpoint.

By default, the JSON or form payload can include `Message::toArray()`, which contains recipients, body content, attachment metadata, sender fields, reply-to fields, and metadata.

A custom payload can be used to send a smaller set of fields.

### Slack

The Slack Incoming Webhook transport sends message text and optional configured block data to the configured webhook URL.

### Microsoft Teams

The Microsoft Teams transport sends message text or an adaptive card payload to the configured webhook URL.

### ntfy

The ntfy transport sends message text to one or more topics. A message `to` recipient can be interpreted as the ntfy topic name.

### Telegram

The Telegram transport sends message text to chat IDs through the configured Bot API endpoint. Recipient addresses are interpreted as chat IDs unless a default chat ID is used.

### Twilio SMS

The Twilio transport sends message text and recipient telephone numbers to the Twilio API. Provider message SIDs can be stored in delivery results.

### WhatsApp Cloud API

The WhatsApp transport sends message text and normalized recipient telephone numbers to the configured Meta Graph API endpoint. Returned message IDs can be stored in delivery results.

### Log and Null transports

`log` does not perform external delivery through MessageHub. It writes the message type and subject to the configured BASE3 logger.

`null` discards the message successfully without contacting an external provider.

## Transport credentials and settings

Transport settings are stored through `ISettingsStore` under the `messaging_transports` group.

Settings can include sensitive configuration such as:

- SMTP usernames and passwords
- webhook URLs
- bearer tokens
- basic-auth passwords
- Telegram bot tokens
- Twilio auth tokens
- WhatsApp access tokens
- API endpoints
- sender addresses

Several secret-capable settings are resolved through `IConfigValueResolver`. This allows settings to contain a late-resolved reference such as an environment variable instead of the resolved secret itself.

If a fixed secret is stored directly in `ISettingsStore`, MessageHub treats it as ordinary settings data and does not encrypt it itself.

## Transport administration can expose stored settings

`MessageTransportAdminDisplay` returns each transport's stored settings as `settings_json` to its browser UI and renders editable controls from that JSON.

There is no generic password-style masking for fixed secret values in this display.

Therefore, anyone who can access this administration endpoint may be able to view fixed transport secrets stored directly in the settings record.

For sensitive values, prefer indirect ConfigValue definitions and restrict access to the transport administration endpoint.

## HTTP authentication data

The generic HTTP webhook supports bearer and basic authentication. Other transports use provider-specific tokens or credentials.

Resolved credentials are used to construct outbound request headers or provider requests. MessageHub does not intentionally place resolved secret values into delivery result details.

Exception messages from underlying libraries or remote providers can still contain operational information and should be reviewed before broad log exposure.

## TLS verification

HTTP-based transports support a `verify_tls` setting. The shared request layer can disable peer and host verification when this setting is false.

Disabling TLS verification removes an important server-authentication control and can expose message data or credentials to interception. Production installations should keep verification enabled unless a controlled environment explicitly requires otherwise.

## HTTP response handling

The shared HTTP client accepts only HTTP and HTTPS URLs, does not follow redirects, and limits the in-memory response body to 1 MiB.

Individual transports normally persist only selected response fields or short response excerpts.

## Logging

MessageHub writes operational logs through `ILogger`.

The delivery service logs successful deliveries with:

- queue ID
- delivery ID

Failed deliveries additionally log the error message.

The `log` transport records:

- message type
- message subject

Transport exception handling logs the transport name and exception message.

Subjects and errors can contain personal or confidential information. The retention and visibility of the active BASE3 logger should therefore be reviewed as part of the MessageHub deployment.

## PHPMailer debug mode

The PHPMailer transport exposes a `debug` setting. When enabled in SMTP mode, PHPMailer server-level debugging is activated.

SMTP debug output can reveal protocol details and should not be enabled routinely in production environments handling sensitive communication.

## Administration displays

MessageHub provides administration displays for templates, variants, transports, queue records, delivery records, type synchronization, and dashboard information.

The delivery detail endpoint can return the complete stored message, recipient list, attachment metadata, provider result, and error information.

These views can expose communication content and operational credentials or configuration. They should be treated as privileged administration functions.

## Authorization boundary

The current MessageHub administration display classes do not inject `IUsermanager`, `IAccesscontrol`, or another component-specific authorization service.

MessageHub therefore does not itself decide which user may access its administration endpoints.

The application that exposes these displays must enforce the intended access policy at its own administration or routing boundary.

## Request-forgery boundary

The current JSON administration actions do not implement a MessageHub-specific CSRF token.

Actions can include:

- template changes
- variant changes
- transport setting changes
- transport enable/disable
- default transport changes
- queue cancellation
- manual queue processing
- message type synchronization

If these routes are exposed in a browser-authenticated environment, the surrounding host must provide the appropriate request-forgery protection.

## Browser session storage

Several MessageHub administration grids use the ClientStack ModularGrid `SessionStoragePlugin`.

The stored grid state includes items such as:

- search query
- filters
- column state

This can preserve search terms and filter values in browser `sessionStorage` for the lifetime of the browser session.

The grid storage does not intentionally store complete message bodies, but users should avoid entering unnecessary personal data into generic search fields.

## Attachment file access

Email-capable transports read attachment files from local paths at delivery time.

MessageHub does not provide a dedicated attachment storage abstraction or access token around those paths. The message producer is responsible for providing a path that the delivery process is allowed to read.

The local file can contain personal or confidential information, and the transport will transmit its contents to the selected mail destination.

## Template rendering and HTML

MessageHub performs direct placeholder substitution. It does not escape context values based on whether they are inserted into plain text, a subject, or an HTML body.

A consumer that inserts untrusted content into an HTML template should ensure that the value is safe for that HTML context.

MessageHub also does not sanitize stored HTML variants before they are sent.

## Message type synchronization

Synchronization creates missing templates and variants from discoverable provider defaults. It does not overwrite existing customized template or variant content.

This behavior avoids silently replacing administrator-edited content, but it also means obsolete or sensitive custom content remains stored until explicitly changed or deleted.

## Events

MessageHub emits queued, sent, and failed events through the BASE3 event manager.

Event listeners can observe:

- queue IDs
- delivery IDs
- failure error messages

Any listener that persists, forwards, or logs event data creates an additional data-processing boundary outside MessageHub.

## Backups and replicas

Database backups can contain full message bodies, recipient addresses, attachment paths, transport results, and template content.

Settings backups can contain transport endpoint definitions and, if fixed values are used, credentials.

Deletion from the live database does not remove copies already stored in backups or replicas. Backup retention should be aligned with the messaging retention policy.

## Data minimization

Consumer plugins should minimize message content before it reaches MessageHub.

Recommended principles include:

- send only required recipient addresses
- avoid unnecessary personal information in subjects
- avoid secrets in message bodies and metadata
- avoid internal identifiers unless required
- use custom webhook payloads when the remote service does not need the complete message
- keep provider response-body storage disabled unless operationally necessary
- avoid fixed plaintext transport secrets where late resolution is available

## Retention checklist

A production deployment should explicitly decide retention for:

- completed queue rows
- failed queue rows
- cancelled queue rows
- delivery records
- recipient records
- attachment metadata
- provider response details
- error messages
- templates and variants
- transport settings
- application logs
- SMTP or mail-server logs
- backups

The built-in delivery cleanup job addresses only delivery, recipient, and attachment history. It does not by itself provide a complete messaging retention policy.
