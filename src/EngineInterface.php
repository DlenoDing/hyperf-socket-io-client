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

namespace Dleno\SocketIOClient;

/**
 * Represents an engine used within Dleno\SocketIOClient to send / receive messages from
 * a websocket real time server
 *
 * @author dleno <dleno@126.com>
 */
interface EngineInterface
{
    const OPEN    = 0;
    const CLOSE   = 1;
    const PING    = 2;
    const PONG    = 3;
    const MESSAGE = 4;
    const UPGRADE = 5;
    const NOOP    = 6;

    /** Connect to the targeted server */
    public function connect();

    /** Closes the connection to the websocket */
    public function close();

    /**
     * Read data from the socket
     *
     * @return string Data read from the socket
     */
    public function read();

    /**
     * Emits a message through the websocket
     *
     * @param string $event Event to emit
     * @param string  $args  Arguments to send
     */
    public function emit($event, $args);

    /** Keeps alive the connection */
    public function keepAlive();

    /** Gets the name of the engine */
    public function getName();

    /** 
     * Sets the namespace for the next messages
     *
     * @param string $namespace the namespace
     */
    public function of($namespace);
}

