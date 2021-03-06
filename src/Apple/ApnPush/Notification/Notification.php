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

use Apple\ApnPush\Connection\ConnectionInterface;
use Apple\ApnPush\Exception;
use Apple\ApnPush\Notification\Events\SendMessageCompleteEvent;
use Apple\ApnPush\Notification\Events\SendMessageErrorEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Notification core
 */
class Notification implements NotificationInterface
{
    /**
     * @var ConnectionInterface
     */
    protected $connection;

    /**
     * @var PayloadFactoryInterface
     */
    protected $payloadFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    /**
     * @var bool
     */
    protected $checkForErrors = true;

    /**
     * Construct
     *
     * @param string|ConnectionInterface $connection
     * @param PayloadFactoryInterface    $payloadFactory
     */
    public function __construct($connection = null, PayloadFactoryInterface $payloadFactory = null)
    {
        if (null !== $connection) {
            if ($connection instanceof ConnectionInterface) {
                $this->connection = $connection;
            } else if (is_string($connection)) {
                // Connection is a certificate path file
                $this->connection = new Connection($connection);
            }
        }

        $this->payloadFactory = $payloadFactory ?: new PayloadFactory();
    }

    /**
     * Set connection to manager
     *
     * @param ConnectionInterface $connection
     * @return Notification
     */
    public function setConnection(ConnectionInterface $connection)
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Get connection
     *
     * @return ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Set payload factory for generate apn data
     *
     * @param PayloadFactoryInterface $payloadFactory
     * @return Notification
     */
    public function setPayloadFactory(PayloadFactoryInterface $payloadFactory)
    {
        $this->payloadFactory = $payloadFactory;

        return $this;
    }

    /**
     * Get payload factory
     *
     * @return PayloadFactoryInterface
     */
    public function getPayloadFactory()
    {
        return $this->payloadFactory;
    }

    /**
     * Set logger for logging all actions
     *
     * @param LoggerInterface $logger
     * @return Notification
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Get logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set event dispatcher
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @return Notification
     */
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher = null)
    {
        $this->eventDispatcher = $eventDispatcher;

        return $this;
    }

    /**
     * Get event dispatcher
     *
     * @return EventDispatcherInterface|null
     */
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    /**
     * Set message to iOS devices
     *
     * @param MessageInterface $message
     * @throws SendException
     * @throws Exception\PayloadFactoryUndefinedException
     * @throws Exception\ConnectionUndefinedException
     * @throws Exception\DeviceTokenNotFoundException
     * @return bool
     */
    public function send(MessageInterface $message)
    {
        if (!$this->payloadFactory) {
            throw new Exception\PayloadFactoryUndefinedException();
        }

        if (!$this->connection) {
            throw new Exception\ConnectionUndefinedException();
        }

        if (!$message->getDeviceToken()) {
            throw new Exception\DeviceTokenNotFoundException();
        }

        $payload = $this->payloadFactory->createPayload($message);

        if (!$this->connection->is()) {
            if ($this->logger) {
                $this->logger->debug('Create connection...');
            }

            $this->connection->create();
        }

        $response = (strlen($payload) === $this->connection->write($payload, strlen($payload)));

        if ($this->checkForErrors && $this->connection->isReadyRead()) {
            $responseApple = $this->connection->read(6);
            $error = SendException::parseFromAppleResponse($responseApple, $message);

            if ($this->eventDispatcher) {
                // Call to event: Error send message
                $event = new SendMessageErrorEvent($message, $error);
                $this->eventDispatcher->dispatch(NotificationEvents::SEND_MESSAGE_ERROR, $event);
            }

            if ($this->logger) {
                // Write error to log
                $this->logger->error((string) $error);
                $this->logger->debug('Close connection...');
            }

            $this->connection->close();

            throw $error;
        }

        if ($this->eventDispatcher) {
            // Call to event: Complete send message
            $event = new SendMessageCompleteEvent($message);
            $this->eventDispatcher->dispatch(NotificationEvents::SEND_MESSAGE_COMPLETE, $event);
        }

        if ($this->logger) {
            $this->logger->info(sprintf(
                'Success send notification to device "%s" by message identifier "%s".',
                $message->getDeviceToken(),
                $message->getIdentifier()
            ));
        }

        return $response;
    }

    /**
     * Send message with parameters
     *
     * @param string $deviceToken       Device token (/^[a-z0-9]{64}$/i)
     * @param string $body              Message content
     * @param integer $messIdentifier   Message identifier
     * @param integer $badge
     * @param string $sound             Path to sound file in application or sound key
     * @return bool
     */
    public function sendMessage($deviceToken, $body, $messIdentifier = null, $badge = null, $sound = null)
    {
        $message = $this->createMessage();
        $message->setDeviceToken($deviceToken);
        $message->setBody($body);
        $message->setIdentifier($messIdentifier);
        $message->setBadge($badge);
        $message->setSound($sound);

        return $this->send($message);
    }

    /**
     * Create new message
     *
     * @return Message
     */
    public function createMessage()
    {
        return new Message();
    }

    /**
     * Set status for require check errors
     *
     * @param bool $check
     * @return Notification
     */
    public function setCheckForErrors($check)
    {
        $this->checkForErrors = $check;

        return $this;
    }
}
