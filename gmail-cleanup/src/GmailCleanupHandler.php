<?php declare(strict_types=1);

namespace MyApp;

use CloudEvents\V1\CloudEventInterface;
use Google\Service\Gmail;
use yananob\MyTools\Logger;

/**
 * Gmailの整理処理を規定するハンドラクラス。
 */
class GmailCleanupHandler
{
    /**
     * @param Logger $logger
     * @param Gmail $service
     * @param Query $query
     * @param array<string, mixed> $config
     * @param array<int, string> $filteredWords
     */
    public function __construct(
        private Logger $logger,
        private Gmail $service,
        private Query $query,
        private array $config,
        private array $filteredWords = ['exception']
    ) {}

    /**
     * CloudEventを処理し、設定されたターゲットに基づいてメールを削除（ゴミ箱へ移動）します。
     *
     * @param CloudEventInterface $event
     * @return void
     */
    public function handle(CloudEventInterface $event): void
    {
        $user = 'me';

        if (!isset($this->config["targets"]) || !is_array($this->config["targets"])) {
            $this->logger->log("No targets found in config.");
            return;
        }

        foreach ($this->config["targets"] as $target) {
            $this->logger->log($this->filterLogMessage("Processing target: " . json_encode($target)));
            $params = [
                "maxResults" => 20,
                "q" => $this->query->build($target),
                "includeSpamTrash" => false,
            ];
            $this->logger->log($this->filterLogMessage("Listing messages: " . json_encode($params)));
            $results = $this->service->users_messages->listUsersMessages($user, $params);

            if (count($results->getMessages()) == 0) {
                $this->logger->log("No results found.");
                continue;
            }

            $this->logger->log("Deleting messages:");
            foreach ($results->getMessages() as $message) {
                $message_b = $this->service->users_messages->get($user, $message->id);
                $this->logger->log($this->filterLogMessage("[{$message->id}] {$message_b->snippet}"));
                $this->service->users_messages->trash($user, $message->id);
            }
        }

        $this->logger->log("Succeeded.");
    }

    /**
     * ログメッセージから特定のワードをフィルタリングします。
     *
     * @param string $message オリジナルのログメッセージ。
     * @return string フィルタリングされたログメッセージ。
     */
    private function filterLogMessage(string $message): string
    {
        foreach ($this->filteredWords as $word) {
            $message = str_ireplace($word, '[MASKED]', $message);
        }
        return $message;
    }
}
