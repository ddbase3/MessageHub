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

namespace Base3\MessageHub\Job;

use Base3\CoreFoundation\Configuration\Api\IConfiguration;
use Base3\CoreFoundation\Settings\Api\ISettingsStore;
use Base3\CoreFoundation\Worker\Api\IJob;
use Base3\MessagingFoundation\Api\IMessageDeliveryRepository;

final class MessageDeliveryCleanupJob implements IJob {

        private const DEFAULT_PRIORITY = 1;

        private ?array $messageHubIliasConf = null;

        public function __construct(
                private readonly IMessageDeliveryRepository $deliveryRepository,
                private readonly ISettingsStore $settingsStore,
                private readonly IConfiguration $configuration
        ) {}

        public static function getName(): string { return 'messagedeliverycleanupjob'; }

        public function isActive() {
                $conf = $this->getMessageHubIliasConf();
                return ((int)($conf['messagedeliverycleanupjob.active'] ?? 0)) === 1;
        }

        public function getPriority() {
                $conf = $this->getMessageHubIliasConf();
                return (int)($conf['messagedeliverycleanupjob.priority'] ?? self::DEFAULT_PRIORITY);
        }

        public function go() {
                $settings = $this->settingsStore->get('messaging', 'default', []);
                $retentionDays = (int)($settings['retention_days'] ?? 365);
                $count = $this->deliveryRepository->cleanup($retentionDays);
                return 'Message delivery cleanup removed ' . $count . ' delivery record(s).';
        }

        private function getMessageHubIliasConf(): array {
                if ($this->messageHubIliasConf === null) {
                        $this->messageHubIliasConf = (array)$this->configuration->get('job');
                }
                return $this->messageHubIliasConf;
        }
}
