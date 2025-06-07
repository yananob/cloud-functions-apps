<?php declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
// Ensure the global main() function is available
require_once __DIR__ . '/../index.php'; 

use PHPUnit\Framework\TestCase;
use CloudEvents\V1\CloudEventInterface;
use yananob\MyTools\Pocket; // Assuming this is the correct namespace
use yananob\MyTools\Raindrop; // Assuming this is the correct namespace
use yananob\MyTools\Trigger;
use yananob\MyTools\Utils;

class IndexTest extends TestCase
{
    private $configFilePath = __DIR__ . '/../configs/config.json';
    private $pocketConfigPath = __DIR__ . '/../configs/pocket.json';
    private $raindropConfigPath = __DIR__ . '/../configs/raindrop.json';
    private $originalPocketContent;
    private $originalRaindropContent;
    private $originalConfigContent;


    protected function setUp(): void
    {
        // Backup original config files if they exist
        if (file_exists($this->pocketConfigPath)) {
            $this->originalPocketContent = file_get_contents($this->pocketConfigPath);
        }
        if (file_exists($this->raindropConfigPath)) {
            $this->originalRaindropContent = file_get_contents($this->raindropConfigPath);
        }
        if (file_exists($this->configFilePath)) {
            $this->originalConfigContent = file_get_contents($this->configFilePath);
        }

        // Create dummy pocket.json
        file_put_contents($this->pocketConfigPath, json_encode([
            'consumer_key' => 'dummy_pocket_consumer_key',
            'access_token' => 'dummy_pocket_access_token'
        ]));
        // Create dummy raindrop.json
        file_put_contents($this->raindropConfigPath, json_encode([
            'api_key' => 'dummy_raindrop_api_key',
            'collection_id' => 'dummy_collection_id'
        ]));
    }

    protected function tearDown(): void
    {
        // Restore original config files
        if ($this->originalPocketContent !== null) {
            file_put_contents($this->pocketConfigPath, $this->originalPocketContent);
        } else {
            @unlink($this->pocketConfigPath); // Remove if created by test
        }
        if ($this->originalRaindropContent !== null) {
            file_put_contents($this->raindropConfigPath, $this->originalRaindropContent);
        } else {
            @unlink($this->raindropConfigPath); // Remove if created by test
        }
        if ($this->originalConfigContent !== null) {
            file_put_contents($this->configFilePath, $this->originalConfigContent);
        } else {
            @unlink($this->configFilePath); // Remove if created by test
        }
    }

    public function testAddsToPocketAndRaindropOnTrigger()
    {
        // 1. Mock CloudEvent
        $mockEvent = $this->createMock(CloudEventInterface::class);

        // 2. Control getConfig outcome by writing a temporary config.json
        $testUrl = 'http://example.com/test-page';
        $testSettings = [
            "settings" => [
                [
                    "timing" => [
                        "weekdays" => [0, 1, 2, 3, 4, 5, 6], // All days
                        "hour" => (int)date('G') // Use current hour to ensure it matches
                    ],
                    "url" => $testUrl
                ]
            ]
        ];
        file_put_contents($this->configFilePath, json_encode($testSettings));
        
        // Mocks for Pocket, Raindrop, and Trigger cannot be created if classes are final.
        // These mocks won't be used by main() due to direct instantiation anyway.
        // This is the limitation.
        // $mockPocket = $this->createMock(Pocket::class);
        // $mockRaindrop = $this->createMock(Raindrop::class);
        // $mockTrigger = $this->createMock(Trigger::class);

        // We expect 'add' to be called on the *actual* objects, but cannot assert this on mocks
        // if they could be created and injected.
        // e.g., $mockPocket->expects($this->once())->method('add')->with($testUrl);
        
        // We can't easily mock Trigger->isLaunch to return true for the *actual* object
        // used in main(). If the real Trigger class logic is complex or relies on time,
        // this test might be flaky or only pass at certain times.
        // The "* * * * *" timing in config.json is used to make it always trigger.

        // Call the main function
        try {
            main($mockEvent); // main() is void, so no return to check directly
            $this->assertTrue(true); // If it runs without exceptions, consider it a basic pass
        } catch (Exception $e) {
            $this->fail("main() function threw an exception: " . $e->getMessage());
        }

        $this->markTestIncomplete(
            'This test currently only verifies that main() runs with the new Raindrop integration. ' .
            'It cannot verify that Pocket->add() and Raindrop->add() are actually called with the correct URL ' .
            'because main() directly instantiates these services. Refactoring main() for dependency injection ' .
            'is needed for proper unit testing of these interactions.'
        );
    }
}
?>
