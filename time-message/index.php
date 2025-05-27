<?php declare(strict_types=1);

// Composerのオートローダーを読み込む
require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use yananob\MyTools\Logger;  // 独自ロガークラス
use yananob\MyTools\Utils;   // 独自ユーティリティクラス (設定ファイル読み込みなど)
use yananob\MyTools\Trigger; // 独自トリガークラス (タイミング判定)
use yananob\MyTools\Line;    // 独自LINE送信用クラス

// CloudEvent関数として 'main' 関数を登録
FunctionsFramework::cloudEvent('main', 'main');

// CloudEventを処理するメイン関数
// 設定された時刻にLINEメッセージを送信する
function main(CloudEventInterface $event): void
{
    // ロガーを初期化 (ログ識別子は "time-message")
    $logger = new Logger("time-message");
    // トリガーオブジェクトを初期化 (タイミング判定用)
    $trigger = new Trigger();
    // LINE送信用オブジェクトを初期化 (LINEの設定ファイルパスを指定)
    $line = new Line(__DIR__ . '/configs/line.json');

    // メインの設定ファイル (config.json) を読み込み
    $config = Utils::getConfig(__DIR__ . "/configs/config.json");
    
    // 設定ファイル内の "settings" 配列をループ処理
    foreach ($config["settings"] as $setting) {
        $logger->log("Processing target: " . json_encode($setting)); // 現在処理中の設定をログに出力

        // TriggerクラスのisLaunchメソッドで、現在の時刻が設定されたタイミング (cron形式などを想定) に一致するか確認
        if ($trigger->isLaunch($setting["timing"])) {
            $logger->log("Sending message"); // メッセージ送信タイミングであることをログに出力
            // LINEメッセージを送信
            $line->sendPush(
                bot: $setting["bot"],       // 使用するLINE Botの名前など
                target: $setting["target"], // 送信先のユーザーIDまたはグループID
                message: $setting["message"]// 送信するメッセージ本文
            );
        }
    };

    $logger->log("Succeeded."); // 全ての処理が完了したことをログに出力
}
