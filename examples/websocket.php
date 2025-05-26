<?php

require __DIR__ . '/../vendor/autoload.php';

use ReactphpX\WebsocketGroup\WebsocketGroupComponent;
use ReactphpX\WebsocketGroup\WebsocketGroupMiddleware;
use ReactphpX\ConnectionGroup\ConnectionGroup;
use ReactphpX\ConnectionGroup\SingleConnectionGroup;
use ReactphpX\WebsocketMiddleware\WebsocketMiddleware;

use ReactphpX\RegisterCenter\Master;
use ReactphpX\RegisterCenter\ServiceRegistry;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;



$logger = new Logger('master');
$handler = new StreamHandler('php://stdout', Level::Debug);
$handler->setFormatter(new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    null,
    true,
    true
));
$logger->pushHandler($handler);



$connectionGroup = new ConnectionGroup;

ServiceRegistry::register('websocket', $connectionGroup, [
    'env' => 'prod',
]);
$master = new Master(
    logger: $logger
);
$master->connectViaConnector('127.0.0.1', getenv('REGISTER_CENTER_PORT') ?: 8010);

$master->on('connect', function ($tunnelStream) use ($master) {
    $tunnelStream->write([
        'cmd' => 'auth',
        'token' => 'register-center-token-2024'
    ]);
    $tunnelStream->on('cmd', function ($cmd, $message) use ($tunnelStream, $master) {
        echo "Received command: $cmd\n";
        echo "Message: " . json_encode($message) . "\n";
        if ($cmd === 'register') {
            $registers = $message['registers'];
            foreach ($registers as $register) {
                $master->connectViaConnector($register['host'], $register['port']);
            }
        } elseif ($cmd === 'remove') {
            $registers = $message['registers'];
            foreach ($registers as $register) {
                $master->removeConnection($register['host'], $register['port']);
            }
        }
    });
});

$connectionGroup->on('open', function ($conn, $request) use ($connectionGroup) {
    $connectionGroup->sendMessageTo_id($conn->_id, json_encode([
        'cmd' => 'open',
        '_id' => $conn->_id,
    ]));
});

$connectionGroup->on('message', function ($from, $msg) use ($connectionGroup) {
    if ($msg == 'ping') {
        $connectionGroup->sendMessageTo_id($from->_id, json_encode([
            'cmd' => 'open',
            '_id' => $from->_id,
        ]));
    }
});

$connectionGroup->on('close', function ($conn, $reason) {
    var_dump('close', $conn->_id, $reason);
});


$websocketGroupMiddleware = new WebsocketGroupMiddleware($connectionGroup);
$token = getenv('TOKEN');
if ($token) {
    $token = explode(',', $token);
    $websocketGroupMiddleware->setTokens($token);
}


$http = new React\Http\HttpServer(
    $websocketGroupMiddleware,
    new WebsocketMiddleware(new WebsocketGroupComponent($connectionGroup))
);
$socket = new React\Socket\SocketServer('0.0.0.0:' . getenv('PORT') ?: 8090);
echo 'Server running at ' . (getenv('PORT') ?: 8090) . PHP_EOL;
$http->listen($socket);