<?php declare(strict_types=1);

namespace MyApp;

use Exception;

/**
 * LINE Webhook Event wrapper class.
 */
class LineEvent
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data) {}

    /**
     * Returns the event type.
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->data['type'] ?? null;
    }

    /**
     * Returns the reply token.
     *
     * @return string
     */
    public function getReplyToken(): string
    {
        return (string)($this->data['replyToken'] ?? '');
    }

    /**
     * Returns the message type if available.
     *
     * @return string|null
     */
    public function getMessageType(): ?string
    {
        return $this->data['message']['type'] ?? null;
    }

    /**
     * Returns the message text if available.
     *
     * @return string|null
     */
    public function getMessageText(): ?string
    {
        return $this->data['message']['text'] ?? null;
    }

    /**
     * Returns the source type (user, group, room).
     *
     * @return string|null
     */
    public function getSourceType(): ?string
    {
        return $this->data['source']['type'] ?? null;
    }

    /**
     * Returns the target ID based on the source type.
     *
     * @return string|null
     * @throws Exception
     */
    public function getTargetId(): ?string
    {
        $type = $this->getSourceType();
        $source = $this->data['source'] ?? [];

        return match ($type) {
            'user' => $source['userId'] ?? null,
            'group' => $source['groupId'] ?? null,
            'room' => $source['roomId'] ?? null,
            null => null,
            default => throw new Exception("Unknown type : " . $type),
        };
    }

    /**
     * Checks if the event is a valid text message event.
     *
     * @return bool
     */
    public function isValidTextMessageEvent(): bool
    {
        return $this->getType() === 'message'
            && $this->getMessageType() === 'text'
            && $this->getMessageText() !== null
            && $this->getSourceType() !== null;
    }
}
