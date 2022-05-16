<?php
/**
 * This file is part of SwowCloud
 * @license  https://github.com/swow-cloud/websocket-server/blob/main/LICENSE
 */

declare(strict_types=1);

namespace SwowCloud\WebSocket;

use Psr\Container\ContainerInterface;
use Swow\Http\Status;
use Swow\Http\WebSocketFrame;
use Swow\SocketException;
use SwowCloud\Contract\StdoutLoggerInterface;
use SwowCloud\WebSocket\Exception\BadRequestException;

class Sender
{
    protected ContainerInterface $container;

    /**
     * @var mixed|\SwowCloud\Contract\StdoutLoggerInterface
     */
    protected StdoutLoggerInterface $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = $this->container->get(StdoutLoggerInterface::class);
    }

    /**
     * Push Message
     */
    public function push(int $fd, string|WebSocketFrame $message, ?int $timeout = null): void
    {
        try {
            $connection = FdCollector::get($fd);
            if ($connection->getProtocolType() !== $connection::PROTOCOL_TYPE_WEBSOCKET) {
                throw new SocketException('Unsupported Upgrade Type', Status::BAD_GATEWAY);
            }
            if (is_string($message)) {
                $connection->sendString($message, $timeout);
            } else {
                $connection->sendWebSocketFrame($message);
            }
            $this->logger->debug("[WebSocket] send to #{$fd}");
        } catch (SocketException|BadRequestException $e) {
            $this->logger->error(sprintf('[WebSocket] send to #%s failed: %s', $fd, $e->getMessage()));
        }
    }

    /**
     * Disconnect
     */
    public function disconnect(int $fd): void
    {
        try {
            $connection = FdCollector::get($fd);
            if ($connection->getProtocolType() !== $connection::PROTOCOL_TYPE_WEBSOCKET) {
                throw new SocketException('Unsupported Upgrade Type', Status::BAD_GATEWAY);
            }
            $connection->close();
            FdCollector::del($fd);
            $this->logger->debug("[WebSocket] closed to #{$fd}");
        } catch (SocketException $e) {
            $this->logger->error(sprintf('[WebSocket] closed to #%s failed: %s', $fd, $e->getMessage()));
        }
    }

    /**
     * Broadcast message
     */
    public function broadcastMessage(string|WebSocketFrame $message, array $connections = null): void
    {
        if ($connections === null) {
            $connections = FdCollector::getConnections();
        }
        foreach ($connections as $connection) {
            if ($connection->getProtocolType() !== $connection::PROTOCOL_TYPE_WEBSOCKET) {
                continue;
            }
            try {
                if (is_string($message)) {
                    $connection->sendString($message);
                } else {
                    $connection->sendWebSocketFrame($message);
                }
            } catch (SocketException $exception) {
                /* ignore */
                $this->logger->error($exception->getMessage());
            }
        }
        $this->logger->debug('[WebSocket] send to broadcast message#');
    }
}
