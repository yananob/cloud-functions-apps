<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

Google\CloudFunctions\FunctionsFramework::cloudEvent('main', 'main');

// CloudEventを処理するメイン関数
// 設定されたタイミングで指定されたURLをPocketおよびRaindropに追加する
function main(CloudEvents\V1\CloudEventInterface $event): void
{
    $logger = new yananob\MyTools\Logger("web-fetch");
    $trigger = new yananob\MyTools\Trigger();

    // dirname(__FILE__) は現在のファイルのディレクトリパスを返す
    $config = yananob\MyTools\Utils::getConfig(dirname(__FILE__) . "/configs/config.json");
    
    foreach ($config["settings"] as $setting) {
        $logger->log("Processing target: " . json_encode($setting));

        if ($trigger->isLaunch($setting["timing"])) {
            $logger->log("Adding page to Pocket and Raindrop");
            $pocket = new yananob\MyTools\Pocket(__DIR__ . '/configs/pocket.json');
            $raindrop = new yananob\MyTools\Raindrop(__DIR__ . '/configs/raindrop.json');
            $pocket->add($setting["url"]);
            $raindrop->add($setting["url"]);
        }
    };

    $logger->log("Succeeded.");
}
