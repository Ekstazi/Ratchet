<?php
namespace Ratchet\Server;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use Amp\Loop\Driver as LoopInterface;
use Amp\Socket\Server as ServerInterface;
use Amp\Loop\DriverFactory as LoopFactory;
use Amp\Socket\ServerSocket as Connection;
/**
 * Creates an open-ended socket to listen on a port for incoming connections.
 * Events are delegated through this to attached applications
 */
class IoServer
{
	/**
	 * @var LoopInterface
	 */
	public $loop;

	/**
	 * @var \Ratchet\MessageComponentInterface
	 */
	public $app;

	/**
	 * The socket server the Ratchet Application is run off of
	 * @var \React\Socket\ServerInterface
	 */
	public $socket;

	/**
	 * @param \Ratchet\MessageComponentInterface $app The Ratchet application stack to host
	 * @param ServerInterface $socket The React socket server to run the Ratchet application off of
	 * @param LoopInterface|null $loop The React looper to run the Ratchet application off of
	 */
	public function __construct(MessageComponentInterface $app, ServerInterface $socket, LoopInterface $loop = null)
	{
		if (false === strpos(PHP_VERSION, "hiphop")) {
			gc_enable();
		}

		set_time_limit(0);
		ob_implicit_flush();

		$this->loop = $loop;
		$this->app = $app;
		$this->socket = $socket;


		//$socket->on('connection', array($this, 'handleConnect'));
	}

	/**
	 * @param  \Ratchet\MessageComponentInterface $component The application that I/O will call when events are received
	 * @param  int $port The port to server sockets on
	 * @param  string $address The address to receive sockets on (0.0.0.0 means receive connections from any)
	 * @return IoServer
	 */
	public static function factory(MessageComponentInterface $component, $port = 80, $address = '0.0.0.0')
	{
		$loop = (new LoopFactory)->create();
		$socket = \Amp\Socket\listen($address . ':' . $port);

		return new static($component, $socket, $loop);
	}

	/**
	 * Run the application by entering the event loop
	 * @throws \RuntimeException If a loop was not previously specified
	 */
	public function run()
	{
		if (null === $this->loop) {
			throw new \RuntimeException("A React Loop was not provided during instantiation");
		}

		$this->loop->defer(function(){
			while($connection = yield $this->socket->accept()) {
				\Amp\asyncCall([$this, 'handleConnect'], $connection);
			}
		});

		// @codeCoverageIgnoreStart
		$this->loop->run();
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Triggered when a new connection is received from React
	 * @param Connection $conn
	 */
	public function handleConnect($conn)
	{
		$decorated = new IoConnection($conn);

		$this->app->onOpen($decorated);

		try {
			while ($data = yield $conn->read()) {
				$this->handleData($data, $decorated);
			}
			$this->handleEnd($decorated);
		} catch (\Throwable $e) {
			$this->handleError($e, $decorated);
		}
	}

	/**
	 * Data has been received from React
	 * @param string $data
	 * @param ConnectionInterface $conn
	 */
	public function handleData($data, $conn)
	{
		try {
			$this->app->onMessage($conn, $data);
		} catch (\Exception $e) {
			$this->handleError($e, $conn);
		}
	}

	/**
	 * An error has occurred, let the listening application know
	 * @param \Throwable $e
	 * @param ConnectionInterface $conn
	 */
	public function handleError(\Throwable $e, $conn)
	{
		$this->app->onError($conn, $e);
	}

	/**
	 * A connection has been closed by React
	 * @param ConnectionInterface $conn
	 */
	public function handleEnd($conn)
	{
		try {
			$this->app->onClose($conn);
		} catch (\Exception $e) {
			$this->handleError($e, $conn);
		}

		unset($conn);
	}
}
