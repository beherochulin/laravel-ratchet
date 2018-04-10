<?php
namespace Askedio\LaravelRatchet;

use Ratchet\ConnectionInterface;
use Ratchet\Wamp\WampServerInterface;

abstract class RatchetWampServer implements WampServerInterface {
    public $subscribedTopics = [];

    protected $console = false;

    public function __construct($console) {
        $this->console = $console;
    }

    public function onEntry($entry) {
        $entryData = json_decode($entry, true);
        if ( !array_key_exists($entryData['category'], $this->subscribedTopics) ) return;
        $topic = $this->subscribedTopics[$entryData['category']];
        $topic->broadcast($entryData);
    }
    public function onSubscribe(ConnectionInterface $conn, $topic) {
        $this->console->info("onSubscribe: {$conn->WAMP->sessionId} topic: $topic {$topic->count()}");

        if ( !array_key_exists($topic->getId(), $this->subscribedTopics) ) {
            $this->subscribedTopics[$topic->getId()] = $topic;
            $this->console->info("subscribed to topic $topic");
        }
    }
    public function onUnSubscribe(ConnectionInterface $conn, $topic) {
        $this->console->info("onUnSubscribe: topic: $topic {$topic->count()}");
    }
    public function onOpen(ConnectionInterface $conn) {
        $this->console->info("onOpen ({$conn->WAMP->sessionId})");
    }
    public function onClose(ConnectionInterface $conn) {
        $this->console->info("onClose ({$conn->WAMP->sessionId})");
    }
    public function onCall(ConnectionInterface $conn, $id, $topic, array $params) {
        $this->console->info('onCall');
        $conn->callError($id, $topic, 'You are not allowed to make calls')->close();
    }
    public function onPublish(ConnectionInterface $conn, $topic, $event, array $exclude, array $eligible) {
        $this->console->info('onPublish');
    }
    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->console->info('onError'.$e->getMessage());
    }
}
