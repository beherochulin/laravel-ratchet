<?php
namespace Askedio\LaravelRatchet;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use GrahamCampbell\Throttle\Facades\Throttle;

abstract class RatchetWsServer implements MessageComponentInterface {
    protected $clients;
    protected $console;
    protected $connections;
    protected $conn;
    protected $throttled = false;

    public function __construct($console) {
        $this->clients = new \SplObjectStorage();
        $this->console = $console;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->conn = $conn;

        $this->attach()->throttle()->limit();
    }

    protected function attach() {
        $this->clients->attach($this->conn);
        $this->console->info(sprintf('Connected: %d', $this->conn->resourceId));

        $this->connections = count($this->clients);
        $this->console->info(sprintf('%d %s', $this->connections, str_plural('connection', $this->connections)));

        return $this;
    }
    protected function throttle() {
        if ($this->isThrottled($this->conn, 'onOpen')) {
            $this->console->info(sprintf('Connection throttled: %d', $this->conn->resourceId));
            $this->conn->send(trans('ratchet::messages.tooManyConnectionAttempts'));
            $this->throttled = true;
            $this->conn->close();
        }

        return $this;
    }
    protected function limit() {
        if ( $connectionLimit = config('ratchet.connectionLimit') && $this->connections - 1 >= $connectionLimit ) {
            $this->console->info(sprintf('To many connections: %d of %d', $this->connections - 1, $connectionLimit));
            $this->conn->send(trans('ratchet::messages.tooManyConnections'));
            $this->conn->close();
        }

        return $this;
    }
    protected function isThrottled($conn, $setting) {
        $connectionThrottle = explode(':', config(sprintf('ratchet.throttle.%s', $setting)));

        return !Throttle::attempt(
            [
                'ip'    => $conn->remoteAddress,
                'route' => $setting,
            ],
            (int) $connectionThrottle[0],
            (int) $connectionThrottle[1]
        );
    }
    public function onMessage(ConnectionInterface $conn, $input) {
        $this->console->comment(sprintf('Message from %d: %s', $conn->resourceId, $input));

        if ($this->isThrottled($conn, 'onMessage') ) {
            $this->console->info(sprintf('Message throttled: %d', $conn->resourceId));
            $this->send($conn, trans('ratchet::messages.tooManyMessages'));
            $this->throttled = true;

            if ( config('ratchet.abortOnMessageThrottle') ) $this->abort($conn);
        }
    }
    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        $this->console->error(sprintf('Disconnected: %d', $conn->resourceId));
    }
    public function onError(ConnectionInterface $conn, \Exception $exception) {
        $message = $exception->getMessage();
        $conn->close();
        $this->console->error(sprintf('Error: %s', $message));
    }
    public function abort(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        $conn->close();
    }
    public function send(ConnectionInterface $conn, $message) {
        $conn->send($message);
    }
    public function sendAll($message) {
        foreach ( $this->clients as $client ) {
            $client->send($message);
        }
    }
}
