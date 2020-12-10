<?php
/**
 * This file is part of the Elephant.io package
 *
 * For the full copyright and license information, please view the LICENSE file
 * that was distributed with this source code.
 *
 * @copyright Dleno
 * @license   http://www.opensource.org/licenses/MIT-License MIT License
 */

use Dleno\SocketIOClient\Client;
use Dleno\SocketIOClient\Engine\SocketIO\Version1;

require __DIR__ . '/../../../../vendor/autoload.php';

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