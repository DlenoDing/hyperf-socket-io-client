# Introduction

hyperf-socket-io-client

# example

```
use Dleno\SocketIOClient\Client;
use Dleno\SocketIOClient\Engine\SocketIO\Version1;

$client = new Client(new Version1("ws://169.1.0.251:9502/?cdk=sss&room=1", [
    'headers' => [
        'cdk: websocket cdk',
        'Authorization: Bearer 12b3c4d5e6f7g8h9i'
    ],
]));
$client->initialize(true);//keepAlive
$client->of('/spread');
$client->emit('event', 'hello, hyperf');

$client->emit('join-room', 'room1');

$client->emit('say', '{"room":"room1", "message":"Hello Hyperf!!!"}');

$client->close();
```
