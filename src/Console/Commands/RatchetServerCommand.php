<?php
namespace Askedio\LaravelRatchet\Console\Commands;

use Ratchet\Http\HttpServer;
use Ratchet\Wamp\WampServer;
use Ratchet\Server\IoServer;
use Ratchet\Server\IpBlackList;
use Ratchet\WebSocket\WsServer;
use Illuminate\Console\Command;
use React\EventLoop\LoopInterface;
use React\ZMQ\Context as ZMQContext;
use Ratchet\Wamp\WampServerInterface;
use Ratchet\MessageComponentInterface;
use React\Socket\Server as SocketServer;
use React\EventLoop\Factory as EventLoop;
use Askedio\LaravelRatchet\RatchetWsServer;
use Askedio\LaravelRatchet\RatchetWampServer;
use Symfony\Component\Console\Input\InputOption;

class RatchetServerCommand extends Command {
    protected $name = 'ratchet:serve';
    protected $description = 'Start Ratchet Server';

    protected $host;
    protected $port;
    protected $class;
    protected $driver;
    protected $eventLoop;
    protected $serverInstance;
    protected $ratchetServer;


    protected function getOptions() {
        return [
            ['host', null, InputOption::VALUE_OPTIONAL, 'Ratchet server host', config('ratchet.host', '0.0.0.0')],
            ['port', 'p', InputOption::VALUE_OPTIONAL, 'Ratchet server port', config('ratchet.port', 8080)],
            ['class', null, InputOption::VALUE_OPTIONAL, 'Class that implements MessageComponentInterface.', config('ratchet.class')],
            ['driver', null, InputOption::VALUE_OPTIONAL, 'Ratchet connection driver [IoServer|WsServer|WampServer]', 'WampServer'],
            ['zmq', 'z', null, 'Bind server to a ZeroMQ socket (always on for WampServer)'],
        ];
    }
    public function handle() {
        $this->host = $this->option('host');

        $this->port = intval($this->option('port'));

        $this->class = $this->option('class');

        $this->driver = $this->option('driver');

        $this->startServer();
    }
    private function startServer($driver = null) {
        if ( ! $driver ) $driver = $this->driver;

        $this->info(sprintf('Starting %s server on: %s:%d', $this->option('driver'), $this->host, $this->port));

        $this->createServerInstance();

        $this->{'start'.$driver}()->run();
    }
    private function createServerInstance() {
        if (! $this->serverInstance instanceof $this->class) {
            $class = $this->class;
            $this->serverInstance = $this->ratchetServer = new $class($this);
        }
    }
    private function bootWithBlacklist() {
        $this->serverInstance = new IpBlackList($this->serverInstance);

        foreach ( config('ratchet.blackList') as $host ) {
            $this->serverInstance->blockAddress($host);
        }
    }
    private function bootWebSocketServer($withZmq = false) {
        if ( $withZmq || $this->option('zmq') ) $this->bootZmqConnection();

        $this->serverInstance = new HttpServer(
            new WsServer($this->serverInstance)
        );

        return $this->bootIoServer();
    }
    private function startWampServer() {
        if ( ! $this->serverInstance instanceof RatchetWampServer ) {
            throw new \Exception("{$this->class} must be an instance of ".RatchetWampServer::class." to create a Wamp server");
        }

        // Decorate the server instance with a WampServer
        $this->serverInstance = new WampServer($this->serverInstance);

        return $this->bootWebSocketServer(true);
    }
    private function startWsServer() {
        if ( ! $this->serverInstance instanceof RatchetWsServer ) {
            throw new \Exception("{$this->class} must be an instance of ".RatchetWsServer::class." to create a WebSocket server");
        }

        $this->bootWithBlacklist();

        return $this->bootWebSocketServer();
    }
    private function startIoServer() {
        $this->bootWithBlacklist();

        return $this->bootIoServer();
    }
    private function bootIoServer() {
        $socket = new SocketServer($this->host.':'.$this->port, $this->getEventLoop());

        return new IoServer(
            $this->serverInstance,
            $socket,
            $this->getEventLoop()
        );
    }
    private function bootZmqConnection() {
        $this->info(sprintf('Starting ZMQ listener on: %s:%s', config('ratchet.zmq.host'), config('ratchet.zmq.port')));

        $context = new ZMQContext($this->getEventLoop());
        $socket = $context->getSocket(config('ratchet.zmq.method', \ZMQ::SOCKET_PULL));
        $socket->bind(sprintf('tcp://%s:%d', config('ratchet.zmq.host', '127.0.0.1'), config('ratchet.zmq.port', 5555)));

        $socket->on('messages', function ($messages) {
            $this->ratchetServer->onEntry($messages);
        });

        $socket->on('message', function ($message) {
            $this->ratchetServer->onEntry($message);
        });
    }
    private function getEventLoop() {
        if ( ! $this->eventLoop instanceof LoopInterface ) $this->eventLoop = EventLoop::create();

        return $this->eventLoop;
    }
}
