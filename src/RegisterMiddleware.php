<?php

namespace ReactphpX\WebsocketCenter;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use React\Promise\Deferred;
use WyriHaximus\React\Stream\Json\JsonStream;
use ReactphpX\RegisterCenter\Register;

class RegisterMiddleware
{
    protected $register;
    protected $tokens = [];

    public function __construct(Register $register)
    {
        $this->register = $register;
    }


    public function setTokens($tokens)
    {
        $this->tokens = $tokens;
    }

    public function __invoke(ServerRequestInterface $request, $next = null)
    {

        $params = [];
        if ($request->getMethod() === 'POST') {
            $params = json_decode((string) $request->getBody(), true);
            $params = $params ?: [];
        }
        $params = $params + $request->getQueryParams();


        if ($this->tokens) {
            $token = $params['token'] ?? '';
            if (!in_array($token, $this->tokens)) {
                return Response::json([
                    'code' => 1,
                    'msg' => 'token error',
                    'data' => []
                ]);
            }
        }

        $event = $params['event'] ?? '';

        if (!$event || !is_string($event)) {
            return Response::json([
                'code' => 1,
                'msg' => 'event error',
                'data' => []
            ]);
        }
        
        $events = explode(',', $event);
        $extra = [];
        $methodToPromises = [];
        foreach ($events as $method) {
            $_params = $params[$method] ?? [];
            $methodToPromises[$method] = $this->getJsonPromiseFromStreams($this->register->runOnAllMasters(function ($stream) use ($method, $_params) {
               return $stream->end(\ReactphpX\RegisterCenter\ServiceRegistry::execute('websocket', $method, $_params));
            }));
        }

        if (empty($methodToPromises)) {
            return Response::json([
                'code' => 1,
                'msg' => 'event error1',
                'data' => []
            ]);
        }


        return $this->getJsonPromise($methodToPromises)->then(function ($data) use ($extra) {
            return Response::json([
                'code' => 0,
                'msg' => 'ok',
                'extra' => $extra,
                'master_ids' => $this->register->getConnectedMasters(),
                'data' => $data
            ]);
        }, function ($e) {
            return Response::json([
                'code' => 1,
                'msg' => $e->getMessage(),
                'data' => []
            ]);
        }, function ($e) {
            return Response::json([
                'code' => 1,
                'msg' => $e->getMessage(),
                'data' => []
            ]);
        });
    }

    public function getJsonPromise($array = [])
    {

        $deferred = new Deferred();
        $buffer = '';
        $jsonStream = new JsonStream();
        $jsonStream->on('data', function ($data) use (&$buffer) {
            $buffer .= $data;
        });

        $jsonStream->on('end', function () use (&$buffer, $deferred) {
            $deferred->resolve(json_decode($buffer, true));
            $buffer = '';
        });

        $jsonStream->end($array);
        return $deferred->promise();
    }

    public function getJsonPromiseFromStreams($streams = [])
    {
        $promises = [];
        foreach ($streams as $masterId => $stream) {
            $deferred = new Deferred();
            $stream->on('data', function ($data) use ($deferred) {
                $deferred->resolve($data);
            });
            $stream->on('error', function ($e) use ($deferred) {
                $deferred->resolve([
                    'code' => 1,
                    'msg' => $e->getMessage(),
                    'data' => []
                ]);
            });
            $stream->on('close', function () use ($deferred) {
                $deferred->resolve(0);
            });
            $promises[$masterId] = $deferred->promise();
        }
        return \React\Promise\all($promises);
    }
}
