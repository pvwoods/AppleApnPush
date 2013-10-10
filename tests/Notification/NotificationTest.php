<?php

/**
 * This file is part of the AppleApnPush package
 *
 * (c) Vitaliy Zhuk <zhuk2205@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code
 */

namespace Apple\ApnPush\Notification;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Notification test
 */
class NotificationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Apple\ApnPush\Notification\Connection|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $connection;

    /**
     * {@inheritDoc}
     */
    public function setUp()
    {

        $this->connection = $this->getMock(
            'Apple\ApnPush\Notification\Connection',
            array('is', 'write', 'isReadyRead', 'create', 'close', 'read')
        );
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown()
    {
        unset ($this->connection);
    }

    /**
     * @dataProvider notificationProvider
     */
    public function testNotification(MessageInterface $message)
    {
        $this->connection->expects($this->once())->method('is')
            ->with()->will($this->returnValue(false));

        $this->connection->expects($this->once())->method('create');
        $this->connection->expects($this->once())->method('write')
            ->will($this->returnCallback(function($message){
                return strlen($message);
            }));

        $this->connection->expects($this->once())->method('isReadyRead')
            ->will($this->returnValue(false));

        // Create notification
        $notification = new Notification;

        $payload = new PayloadFactory;
        $notification->setPayloadFactory($payload);
        $notification->setConnection($this->connection);

        // Testing send message
        $this->assertTrue($notification->send($message));
    }

    /**
     * Notification test provider
     */
    public static function notificationProvider()
    {
        return array(
            array(self::createMessage('test1')),
            array(self::createMessage('test2', 1)),
            array(self::createMessage('test3', 2, str_repeat('12', 32)))
        );
    }

    /**
     * Test reopen connection
     */
    public function testReopenConnection()
    {
        // @todo
        $this->markTestIncomplete('@todo');
    }

    /**
     * Test call error event
     *
     * @expectedException \Apple\ApnPush\Notification\SendException
     */
    public function testSendMessageEventError()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('Not found package "symfony/event-dispatcher".');
        }

        $message = self::createMessage('Foo');

        $this->connection->expects($this->any())->method('is')
            ->will($this->returnValue(true));

        $this->connection->expects($this->once())->method('isReadyRead')
            ->will($this->returnValue(true));

        $this->connection->expects($this->once())->method('write')
            ->will($this->returnCallback(function ($a) { return mb_strlen($a); }));

        $eventDispatcher = $this->getMock(
            'Symfony\Component\EventDispatcher\EventDispatcher',
            array('dispatch')
        );

        $eventDispatcher->expects($this->once())->method('dispatch')
            ->with(
                NotificationEvents::SEND_MESSAGE_ERROR,
                $this->isInstanceOf('Apple\ApnPush\Notification\Events\SendMessageErrorEvent'
            ));

        $notification = new Notification();
        $notification->setPayloadFactory(new PayloadFactory());
        $notification->setConnection($this->connection);
        $notification->setEventDispatcher($eventDispatcher);

        $notification->send($message);
    }

    /**
     * Test call complete event
     */
    public function testSendMessageEventComplete()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('Not found package "symfony/event-dispatcher".');
        }

        $message = self::createMessage('Foo');

        $this->connection->expects($this->any())->method('is')
            ->will($this->returnValue(true));

        $this->connection->expects($this->once())->method('isReadyRead')
            ->will($this->returnValue(false));

        $this->connection->expects($this->once())->method('write')
            ->will($this->returnCallback(function ($a) { return mb_strlen($a); }));

        $eventDispatcher = $this->getMock(
            'Symfony\Component\EventDispatcher\EventDispatcher',
            array('dispatch')
        );

        $eventDispatcher->expects($this->once())->method('dispatch')
            ->with(
                NotificationEvents::SEND_MESSAGE_COMPLETE,
                $this->isInstanceOf('Apple\ApnPush\Notification\Events\SendMessageCompleteEvent'
            ));

        $notification = new Notification();
        $notification->setPayloadFactory(new PayloadFactory());
        $notification->setConnection($this->connection);
        $notification->setEventDispatcher($eventDispatcher);

        $notification->send($message);
    }

    /**
     * Create message
     */
    public static function createMessage($body, $identifier = null, $deviceToken = null)
    {
        $message = new Message;

        $message->setBody($body);

        if ($identifier !== null) {
            $message->setIdentifier($identifier);
        }

        if ($deviceToken !== null) {
            $message->setDeviceToken($deviceToken);
        } else {
            $message->setDeviceToken(str_repeat('af', 32));
        }

        return $message;
    }
}
