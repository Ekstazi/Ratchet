<?php
namespace Ratchet\Server;
use Ratchet\ConnectionInterface;
use Amp\Socket\ServerSocket as AmpConn;

/**
 * {@inheritdoc}
 */
class IoConnection implements ConnectionInterface {
    /**
     * @var AmpConn
     */
    protected $conn;


    /**
     * @param AmpConn $conn
     */
    public function __construct(AmpConn $conn) {
        $this->conn = $conn;
    }

    /**
     * {@inheritdoc}
     */
    public function send($data) {
        $this->conn->write($data);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function close() {
        $this->conn->end();
    }

    public function id()
	{
		return (int) $this->conn->getResource();
	}

	public function getRemoteAddress()
	{
		return $this->conn->getRemoteAddress();
	}


}
