<?php
namespace Askedio\LaravelRatchet\Examples;

use Askedio\LaravelRatchet\RatchetWsServer;

class Pusher extends RatchetWsServer {
    public function onEntry($entry) {
        $this->sendAll($entry[1]);
    }
}
