<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Google\CloudFunctions\FunctionsFramework;
use CloudEvents\V1\CloudEventInterface;
use yananob\MyTools\Logger;
use yananob\MyTools\Trigger;
use yananob\MyTools\Utils;
use yananob\MyTools\Pocket;
use yananob\MyTools\Raindrop;

// CloudEventを処理するメイン関数
// 設定されたタイミングで指定されたURLをPocketおよびRaindropに追加する
FunctionsFramework::cloudEvent('main', 'main');
function main(CloudEventInterface $event): void
{
    $logger = new Logger("web-fetch");
    $trigger = new Trigger();

    $config = Utils::getConfig(dirname(__FILE__) . "/configs/config.json");

    $pocket = new Pocket(__DIR__ . '/configs/pocket.json');
    $raindrop = new Raindrop(__DIR__ . '/configs/raindrop.json');
    foreach ($config["settings"] as $setting) {
        $logger->log("Processing target: " . json_encode($setting));

        if ($trigger->isLaunch($setting["timing"])) {
            $logger->log("Timing matched, adding page to Pocket and Raindrop");
            $pocket->add($setting["url"]);
            $raindrop->add($setting["url"]);
        }
    };

    $logger->log("Succeeded.");
}
