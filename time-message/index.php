<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use yananob\MyTools\Logger;  // 独自ロガークラス
use yananob\MyTools\Utils;   // 独自ユーティリティクラス (設定ファイル読み込みなど)
use yananob\MyTools\Trigger; // 独自トリガークラス (タイミング判定)
use yananob\MyTools\Line;    // 独自LINE送信用クラス

FunctionsFramework::cloudEvent('main', 'main');

// 設定された時刻にLINEメッセージを送信する
function main(CloudEventInterface $event): void
{
    $logger = new Logger("time-message");
    $trigger = new Trigger();
    $line = new Line(__DIR__ . '/configs/line.json');

    $config = Utils::getConfig(__DIR__ . "/configs/config.json");
    
    foreach ($config["settings"] as $setting) {
        $logger->log("Processing target: " . json_encode($setting));

        // TriggerクラスのisLaunchメソッドで、現在の時刻が設定されたタイミング (cron形式などを想定) に一致するか確認
        if ($trigger->isLaunch($setting["timing"])) {
            $logger->log("Sending message");
            $line->sendPush(
                bot: $setting["bot"],       // 使用するLINE Botの名前など
                target: $setting["target"], // 送信先のユーザーIDまたはグループID
                message: $setting["message"]// 送信するメッセージ本文
            );
        }
    };

    $logger->log("Succeeded.");
}
