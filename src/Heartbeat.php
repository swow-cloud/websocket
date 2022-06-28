<?php

declare(strict_types = 1);

namespace SwowCloud\WebSocket;

use Hyperf\Engine\Channel;
use Hyperf\Engine\Coroutine;
use Psr\Container\ContainerInterface;
use Swow\Http\Server\Connection;

class Heartbeat
{
    public Channel $channel;

    public Sender $sender;

    public ?int $timeout = null;

    public ?string $message = null;

    public function __construct(ContainerInterface $container, ?int $timeout = null, ?string $message = null)
    {
        $this->sender  = new Sender($container);
        $this->timeout = $timeout;
        $this->message = $timeout;
        $this->channel = new Channel();
    }

    /**
     *循环给客户端发送心跳包
     * @return void
     */
    public function loop() : void
    {
        Coroutine::create(function ()
        {
            while (true) {
                $this->channel->pop(5); //也可以自定义时间休眠
                $connections = FdCollector::getConnections();
                foreach ($connections as $connection) {
                    $this->sender->push($connection->getFd(), $this->message, $this->timeout);
                }
                //停止循环 代表当前进程已退出
                if ($this->channel->isClosing()) {
                    break;
                }
            }
        });
    }

    /**
     * 向指定连接发送心跳包
     *
     * @param \Swow\Http\Server\Connection $connection
     * @param string                       $message
     * @param null|int                     $timeout
     *
     * @return void
     */
    public function send(Connection $connection, string $message, ?int $timeout = null) : void
    {
        $this->sender->push($connection->getFd(), $message, $timeout);
    }
}


