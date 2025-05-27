<?php declare(strict_types=1);

// Composerのオートローダーを読み込む
require_once __DIR__ . '/vendor/autoload.php';

// Google Cloud Functions Framework を使用して CloudEvent 関数 'main' を登録
Google\CloudFunctions\FunctionsFramework::cloudEvent('main', 'main');

// CloudEventを処理するメイン関数
// 設定されたタイミングで指定されたURLをPocketに追加する
function main(CloudEvents\V1\CloudEventInterface $event): void
{
    // ロガーを初期化 (ログ識別子は "web-fetch")
    $logger = new yananob\MyTools\Logger("web-fetch");
    // トリガーオブジェクトを初期化 (タイミング判定用)
    $trigger = new yananob\MyTools\Trigger();

    // 設定ファイル (config.json) を読み込み
    // dirname(__FILE__) は現在のファイルのディレクトリパスを返す
    $config = yananob\MyTools\Utils::getConfig(dirname(__FILE__) . "/configs/config.json");
    
    // 設定ファイル内の "settings" 配列をループ処理
    foreach ($config["settings"] as $setting) {
        $logger->log("Processing target: " . json_encode($setting)); // 現在処理中の設定をログに出力

        // TriggerクラスのisLaunchメソッドで、現在の時刻が設定されたタイミングに一致するか確認
        if ($trigger->isLaunch($setting["timing"])) {
            $logger->log("Adding page to Pocket"); // Pocketに追加処理を行うことをログに出力
            // Pocketオブジェクトを初期化 (Pocketの設定ファイルパスを指定)
            $pocket = new yananob\MyTools\Pocket(__DIR__ . '/configs/pocket.json');
            // 設定されたURLをPocketに追加
            $pocket->add($setting["url"]);
        }
    };

    $logger->log("Succeeded."); // 全ての処理が完了したことをログに出力
}
