<?php declare(strict_types=1);

namespace MyApp;

use CloudEvents\V1\CloudEventInterface;
use yananob\MyTools\Logger;
use yananob\MyTools\Trigger;
use yananob\MyTools\Line;

/**
 * 定刻にメッセージを送信する処理を規定するハンドラクラス。
 */
class TimeMessageHandler
{
    /**
     * @param Logger $logger
     * @param Trigger $trigger
     * @param Line $line
     * @param array<string, mixed> $config
     */
    public function __construct(
        private Logger $logger,
        private Trigger $trigger,
        private Line $line,
        private array $config
    ) {}

    /**
     * CloudEventを処理し、設定された時刻であればメッセージを送信します。
     *
     * @param CloudEventInterface $event
     * @return void
     */
    public function handle(CloudEventInterface $event): void
    {
        if (!isset($this->config["settings"]) || !is_array($this->config["settings"])) {
            $this->logger->log("No settings found in config.");
            return;
        }

        foreach ($this->config["settings"] as $setting) {
            $this->logger->log("Processing target: " . json_encode($setting));

            // TriggerクラスのisLaunchメソッドで、現在の時刻が設定されたタイミングに一致するか確認
            if ($this->trigger->isLaunch($setting["timing"])) {
                $this->logger->log("Sending message");
                $this->line->sendPush(
                    bot: $setting["bot"],       // 使用するLINE Botの名前など
                    target: $setting["target"], // 送信先のユーザーIDまたはグループID
                    message: $setting["message"]// 送信するメッセージ本文
                );
            }
        }

        $this->logger->log("Succeeded.");
    }
}
