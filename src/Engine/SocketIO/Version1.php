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

namespace Dleno\SocketIOClient\Engine\SocketIO;


use InvalidArgumentException;
use Dleno\SocketIOClient\EngineInterface;
use Dleno\SocketIOClient\Payload\Encoder;
use Dleno\SocketIOClient\Engine\AbstractSocketIO;
use Dleno\SocketIOClient\Exception\SocketException;
use Dleno\SocketIOClient\Exception\ServerConnectionFailureException;

/**
 * Implements the dialog with Socket.IO version 1.x
 *
 * @author dleno <dleno@126.com>
 */
class Version1 extends AbstractSocketIO
{
    const KEEP_ALIVE_TYPE_HYPERF = 1;
    const KEEP_ALIVE_TYPE_SWOOLE = 2;
    const TRANSPORT_WEBSOCKET    = 'websocket';
    const VERSION                = '13';
    const KEY                    = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    protected $isHandShake = false;
    protected $isHyperf    = false;

    protected $multiHeader = [
        'Set-Cookie',
    ];

    /** {@inheritDoc} */
    public function connect()
    {
        if (is_resource($this->stream)) {
            return;
        }

        $protocol = 'http';
        $errors   = [
            null,
            null
        ];
        $host     = sprintf('%s:%d', $this->url['host'], $this->url['port']);

        if (true === $this->url['secured']) {
            $protocol = 'ssl';
            $host     = 'ssl://' . $host;
        }

        // add custom headers
        if (isset($this->options['headers'])) {
            $headers                            = isset($this->context[$protocol]['header']) ? $this->context[$protocol]['header'] : [];
            $this->context[$protocol]['header'] = array_merge($headers, $this->options['headers']);
        }

        $this->stream = stream_socket_client(
            $host,
            $errors[0],
            $errors[1],
            $this->options['timeout'],
            STREAM_CLIENT_CONNECT,
            stream_context_create($this->context)
        );
        if (!is_resource($this->stream)) {
            throw new SocketException($errors[0], $errors[1]);
        }
        stream_set_timeout($this->stream, $this->options['timeout']);

        $this->handShake();
    }

    /** {@inheritDoc} */
    public function keepAlive()
    {
        $callBack = function () {
            while (true) {
                if ($this->isHandShake && $this->session instanceof Session) {
                    \Swoole\Coroutine::sleep($this->session->interval);
                    $this->write(EngineInterface::PING);
                } else {
                    break;
                }
            }
        };
        if ($this->options['keepAliveType'] == static::KEEP_ALIVE_TYPE_HYPERF) {
            \Hyperf\Utils\Coroutine::create($callBack);
        } else {
            \Swoole\Coroutine::create($callBack);
        }
    }

    /** {@inheritDoc} */
    public function close()
    {
        $this->isHandShake = false;
        if (!is_resource($this->stream)) {
            return;
        }
        //支持的关闭协议不一样
        if ($this->isHyperf) {
            $this->write(EngineInterface::MESSAGE, EngineInterface::CLOSE);
        } else {
            $this->write(EngineInterface::CLOSE);
        }

        fclose($this->stream);
        $this->stream  = null;
        $this->session = null;
        $this->cookies = [];

    }

    /** {@inheritDoc} */
    public function emit($event, $args)
    {
        $namespace = $this->getNameSpace();
        return $this->write(EngineInterface::MESSAGE, static::EVENT . $namespace . json_encode([
                $event,
                $args
            ]));
    }

    /** {@inheritDoc} */
    public function of($namespace)
    {
        parent::of($namespace);
        $namespace = $this->getNameSpace($this->url['query']);

        $this->write(EngineInterface::MESSAGE, static::CONNECT . $namespace);
        $this->read();//40
        $result = $this->read();
        if ($result != EngineInterface::MESSAGE . static::CONNECT . $this->getNameSpace()) {
            throw new ServerConnectionFailureException(sprintf('NameSpace is Error, Accept: "%s"', $result));
        }
    }

    /** {@inheritDoc} */
    public function write($code, $message = null)
    {
        if (!is_resource($this->stream)) {
            return;
        }

        if (!is_int($code) || static::CONNECT > $code || static::BINARY_ACK < $code) {
            throw new InvalidArgumentException('Wrong message type when trying to write on the socket');
        }

        $payload = new Encoder($code . $message, Encoder::OPCODE_TEXT, true);
        $bytes   = fwrite($this->stream, (string)$payload);
        // wait a little bit of time after this message was sent
        usleep((int)$this->options['wait']);

        return $bytes;
    }

    /** {@inheritDoc} */
    public function getName()
    {
        return 'SocketIO Client 1.0';
    }

    /** {@inheritDoc} */
    protected function getDefaultOptions()
    {
        $defaults                  = parent::getDefaultOptions();
        $defaults['version']       = 3;
        $defaults['transport']     = static::TRANSPORT_WEBSOCKET;
        $defaults['keepAliveType'] = static::KEEP_ALIVE_TYPE_HYPERF;

        return $defaults;
    }

    /**
     * get url
     *
     * @return string
     */
    protected function getUrl($sid = false)
    {
        $query = [
            'EIO'       => $this->options['version'],
            'transport' => $this->options['transport'] ?? static::TRANSPORT_WEBSOCKET,
        ];
        if ($sid) {
            $query['sid'] = $this->session->id;
        }
        if (isset($this->url['query'])) {
            $query = array_replace($query, $this->url['query']);
        }
        $url = sprintf('/%s/?%s', trim($this->url['path'], '/'), http_build_query($query));

        return $url;
    }

    /**
     * get Origin
     *
     * @return array
     */
    protected function getHeaders()
    {
        $protocol = true === $this->url['secured'] ? 'ssl' : 'http';
        $headers  = $this->context[$protocol]['header'] ?? [];

        return $headers;
    }

    /**
     * generateKey
     *
     * @return string
     */
    protected function generateKey()
    {
        $hash = sha1(uniqid(mt_rand(), true), true);
        $hash = substr($hash, 0, 16);
        $key  = base64_encode($hash);

        return $key;
    }

    /**
     * handShake to WebSocket
     */
    protected function handShake()
    {
        $key = $this->generateKey();
        $url = $this->getUrl();

        $request = "GET {$url} HTTP/1.1\r\n"
            . "Host: {$this->url['host']}:{$this->url['port']}\r\n"
            . "Upgrade: WebSocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: {$key}\r\n"
            . "Sec-WebSocket-Version: 13\r\n";

        $headers = $this->getHeaders();
        $origin  = '*';
        foreach ($headers as $header) {
            if (preg_match('`^Origin:\s*(.+?)$`', $header, $matches)) {
                $origin = $matches[1];
            } else {
                $request .= $header . "\r\n";
            }
        }
        $request .= "Origin: {$origin}\r\n";
        $request .= "\r\n";
        fwrite($this->stream, $request);

        //检查HTTP状态
        $http = fgets($this->stream);
        $http = explode(' ', $http);
        if ('HTTP/1.1' !== $http[0] || '101' !== $http[1]) {
            throw new ServerConnectionFailureException(sprintf('Http status is Error, Accept: "%s"', join(' ', $http)));
        }
        //获取header头
        $acceptHeaders = $cookies = [];
        while (true) {
            $result = fgets($this->stream);
            if (!$result || $result == "\r\n") {
                break;
            }
            if (preg_match('/^Set-Cookie:\s*([^;]*)/i', $result, $matches)) {
                $cookies[] = $matches[1];
            }
            $result = explode(': ', trim($result, "\r\n"));
            if ($result[0] == 'Sec-Websocket-Accept') {//正常协议是：Sec-WebSocket-Accept
                $this->isHyperf = true;
            }
            $result[0] = strtolower($result[0]);//hyperf和其他有大小写不一致问题
            if (in_array($result[0], $this->multiHeader)) {
                $acceptHeaders[$result[0]][] = $result[1];
            } else {
                $acceptHeaders[$result[0]] = $result[1];
            }
        }

        //验证Sec-Websocket-Accept
        $expectedResonse = base64_encode(pack('H*', sha1($key . static::KEY)));
        if ($acceptHeaders['sec-websocket-accept'] !== $expectedResonse) {
            throw new ServerConnectionFailureException('sec-websocket-accept is Error!');
        }
        //验证Sec-WebSocket-Version
        if (isset($acceptHeaders['sec-websocket-version']) && $acceptHeaders['sec-websocket-version'] != static::VERSION) {
            throw new ServerConnectionFailureException('sec-websocket-version is Error!');
        }

        //获取cookie
        $this->cookies = $cookies;

        //读取握手返回信息
        $handShake = $this->read();
        if ($handShake[0] != self::CONNECT) {
            throw new ServerConnectionFailureException('HandShake Error!');
        }
        $handShake     = json_decode(substr($handShake, 1), true);
        $this->session = new Session($handShake['sid'], $handShake['pingInterval'], $handShake['pingTimeout'], $handShake['upgrades']);
        //标记握手成功
        $this->isHandShake = true;
    }
}

