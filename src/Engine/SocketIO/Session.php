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

/**
 * Represents the data for a Session
 *
 * @author dleno <dleno@126.com>
 */
class Session
{
    /** @var integer session's id */
    public $id;

    /** @var integer session's last heartbeat */
    public $heartbeat;

    /** @var integer session's and heartbeat's timeout */
    public $timeout;

    /** @var integer session's and heartbeat's interval */
    public $interval;

    /** @var string[] supported upgrades */
    public $upgrades;

    public function __construct($id, $interval, $timeout, array $upgrades)
    {
        $this->id        = $id;
        $this->upgrades  = $upgrades;
        $this->heartbeat = time();
        $this->interval  = intval($interval/1000);
        $this->timeout   = intval($timeout/1000);
    }

}

