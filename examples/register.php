<?php

require __DIR__ . '/../vendor/autoload.php';

use React\EventLoop\Loop;
use ReactphpX\RegisterCenter\Register;
use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use ReactphpX\WebsocketCenter\RegisterMiddleware;

// Create event loop
$loop = Loop::get();

// Create logger
$logger = new Logger('registration-center');
$handler = new StreamHandler('php://stdout', Level::Debug);
$handler->setFormatter(new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    null,
    true,
    true
));
$logger->pushHandler($handler);



// Create and start registration center with logger
$center = new Register(getenv('REGISTER_CENTER_PORT') ?: 8010, $loop, $logger);
$center->on('master-authenticated', function ($masterId, $tunnelStream) use ($center) {
    echo "Master authenticated: " . $masterId . PHP_EOL;
    // todo 同步其它注册中心信息
    if (getenv("OTHER_REGISTER_CENTER_PORT")) {
        $ports = explode(',', getenv("OTHER_REGISTER_CENTER_PORT"));
        foreach ($ports as $port) {
            $center->writeRawMessageToAllMasters([
                'cmd' => 'register',
                'registers' => [
                    [
                        'host' => '127.0.0.1',
                        'port' => (int) $port,
                    ]
                ]
            ]);
        }
    }
    
});
$center->start();




Loop::addPeriodicTimer(5, function () use ($center) {
    $services = $center->getServicesMaster();
    echo "Services: " . json_encode($services) . "\n";
});

$registerMiddleware = new RegisterMiddleware($center);

if (getenv('TOKEN')) {
    $registerMiddleware->setTokens(explode(',', getenv('TOKEN')));
}

$http = new React\Http\HttpServer(
    function ($request, $next)  {
        $withHeaders = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => '*',
            'Access-Control-Allow-Headers' => '*',
            'Access-Control-Expose-Headers' => '*',
        ];

        if ($request->getMethod() == 'OPTIONS') {
            return new \React\Http\Message\Response(204, $withHeaders);
        }

        return \React\Promise\resolve($next($request))->then(
            function ($response) use ($withHeaders) {
                foreach ($withHeaders as $key => $value) {
                    if (!$response->hasHeader($key)) {
                        $response = $response->withHeader($key, $value);
                    }
                }
                return $response;
            }
        );
    },
    $registerMiddleware
);
$socket = new React\Socket\SocketServer('0.0.0.0:' . getenv('HTTP_PORT') ?: 8011);
echo 'Server running at ' . (getenv('HTTP_PORT') ?: 8011) . PHP_EOL;
$http->listen($socket);

// Run the loop
$loop->run(); 