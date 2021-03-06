<?php

namespace Knp\Bundle\KnpBundlesBundle\Producer;

use OldSound\RabbitMqBundle\RabbitMq\ConsumerInterface;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Class ExecutorProducer
 *
 * This producer will not send anything to rabbitmq but will execute instantly the consumer
 */
class ExecutorProducer implements ProducerInterface
{
    public function __construct(ConsumerInterface $consumer)
    {
        $this->consumer = $consumer;
    }

    public function publish($msgBody, $routingKey = '')
    {
        $msg = new AMQPMessage($msgBody);
        $this->consumer->execute($msg);
    }
}
