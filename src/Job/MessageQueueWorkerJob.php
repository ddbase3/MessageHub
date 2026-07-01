<?php declare(strict_types=1);

namespace MessageHub\Job;

use Base3\Configuration\Api\IConfiguration;
use Base3\Worker\Api\IJob;
use MessageHub\Service\MessageDeliveryService;

final class MessageQueueWorkerJob implements IJob {

        private const DEFAULT_PRIORITY = 1;

        private ?array $messageHubIliasConf = null;

        public function __construct(
                private readonly MessageDeliveryService $deliveryService,
                private readonly IConfiguration $configuration
        ) {}

        public static function getName(): string { return 'messagequeueworkerjob'; }

        public function isActive() {
                $conf = $this->getMessageHubIliasConf();
                return ((int)($conf['messagequeueworkerjob.active'] ?? 0)) === 1;
        }

        public function getPriority() {
                $conf = $this->getMessageHubIliasConf();
                return (int)($conf['messagequeueworkerjob.priority'] ?? self::DEFAULT_PRIORITY);
        }

        public function go() {
                $count = $this->deliveryService->processBatch(20);
                return 'Message queue worker processed ' . $count . ' message(s).';
        }

        private function getMessageHubIliasConf(): array {
                if ($this->messageHubIliasConf === null) {
                        $this->messageHubIliasConf = (array)$this->configuration->get('job');
                }
                return $this->messageHubIliasConf;
        }
}
