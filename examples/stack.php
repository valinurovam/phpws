<?php

require_once("../vendor/autoload.php");

// Run from command prompt > php demo.php
use Devristo\Phpws\Messaging\JsonMessage;
use Devristo\Phpws\Protocol\StackTransport;
use Devristo\Phpws\Protocol\JsonTransport;
use Devristo\Phpws\Protocol\ServerProtocolStack;
use Devristo\Phpws\Protocol\TransportInterface;
use Devristo\Phpws\Protocol\WebSocketTransportInterface;
use Devristo\Phpws\Server\WebSocketServer;


class StackHandler extends \Devristo\Phpws\Server\UriHandler\WebSocketUriHandler{
    protected $loop;

    public function __construct(\React\EventLoop\LoopInterface $loop, $logger){
        parent::__construct($logger);
        $this->loop = $loop;
    }

    /**
     * Notify everyone when a user has joined the chat
     *
     * @param StackTransport $stackTransport
     */
    public function onConnect(WebSocketTransportInterface $transport){
        /**
         * @var $stackTransport StackTransport
         * @var $jsonTransport JsonTransport
         */
        $logger = $this->logger;
        $loop = $this->loop;
        $stackTransport = StackTransport::create($transport, array(function(TransportInterface $carrier) use($loop, $logger){
            return new JsonTransport($carrier, $loop, $logger);
        }));

        $jsonTransport = $stackTransport->getTopTransport();

        $server = $transport->getHandshakeResponse()->getHeaders()->get('X-WebSocket-Server')->getFieldValue();

        $jsonTransport->whenResponseTo("hello world from $server!", 0.1)->then(function(JsonMessage $result) use ($logger, $server){
            $logger->notice(sprintf("Got '%s' in response to 'hello world from $server!'", $result->getData()));
        });
    }
}

$loop = \React\EventLoop\Factory::create();

// Create a logger which writes everything to the STDOUT
$logger = new \Zend\Log\Logger();
$writer = new \Zend\Log\Writer\Stream("php://output");
$logger->addWriter($writer);

$server = new WebSocketServer("tcp://0.0.0.0:12345", $loop, $logger);
$server->bind();

$server->on("handshake", function(\Devristo\Phpws\Protocol\WebSocketTransportInterface $transport, \Devristo\Phpws\Protocol\Handshake $handshake){
    $handshake->getResponse()->getHeaders()->addHeaderLine("X-WebSocket-Server", "phpws");
});

$router = new \Devristo\Phpws\Server\UriHandler\ClientRouter($server, $logger);
$router->addRoute('#^/stack#i', new StackHandler($loop, $logger));

// Start the event loop
$loop->run();