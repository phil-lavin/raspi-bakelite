<?php

namespace Bakelite;

require_once __DIR__.'/../Async/Timeout.php';
require_once __DIR__.'/../Async/Timer.php';
require_once __DIR__.'/../Async/Runnable.php';

use Monolog\Logger;
use Async\Timer\TimerManager;
use Async\Timer;
use Async\Timeout;
use Async\Runnable;

class BareSip implements Runnable {
	protected $log;
	protected $timerManager;
	protected $port;
	protected $ip;

	protected $sock;

	protected $eventCallbacks = [];
	protected $responseCallbacks;

	protected $pingTimer;

	public function __construct(Logger $logger, TimerManager $timerManager, int $port = 4444, string $ip = '127.0.0.1') {
		$this->log = $logger;
		$this->timerManager = $timerManager;
		$this->port = $port;
		$this->ip = $ip;

		$this->responseCallbacks = new \SplStack();
	}

	// Connects to BareSIP JSON interface
	public function connect() {
		if ($this->sock) return;

		$this->sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		if ( ! @socket_connect($this->sock, $this->ip, $this->port)) {
			$this->disconnect();
			throw new \ErrorException("Cannot connect to BareSIP on {$this->ip}:{$this->port}");
		}

		$this->log->info("Connected to BareSIP on {$this->ip}:{$this->port}");

		socket_set_nonblock($this->sock);

		// Set up a timer which tries to reconnect if ping fails
		$this->pingTimer = new Timer(1000000, function() {
			if ( ! $this->ping()) {
				$this->log->error('Ping to BareSIP failed. Trying to reconnect');
				$this->reconnect();
			}
			else {
				$this->log->debug("Pong received from BareSIP");
			}
		});

		// Add pingTimer to the TimerManager
		$this->timerManager->addTimer($this->pingTimer);

		// Bind to EXIT event and try to reconnect
		$this->addEventListener('EXIT', function() {
			$this->log->error('BareSIP exited. Trying to reconnect');
			sleep(5);
			$this->reconnect();
		});

		return true;
	}

	// Disconnect and clean up socket
	public function disconnect() {
		if ( ! $this->sock) return;

		socket_close($this->sock);
		$this->sock = NULL;
	}

	// Try to connect to the socket until it succeeds
	public function reconnect($interval = 5) {
		$this->disconnect();

		while (1) {
			try {
				return $this->connect();
			}
			catch (\ErrorException $e) {
				$this->log->error($e->getMessage() . ". Trying again in {$interval} seconds");
				sleep($interval);
			}
		}
	}

	// Adds callbacks for particular types of events. These are persistent
	public function addEventListener(string $type, callable $callback) {
		$type = strtoupper($type);

		$this->eventCallbacks[$type][] = $callback;
	}

	// Adds callbacks to the stack for responses. The callback on the top of the stack is popped (i.e. removed) and used once
	public function addResponseListener(callable $callback) {
		$this->responseCallbacks->push($callback);
	}

	// Reads a complete JSON message from the socket and returns it as an array
	protected function readMessage(int $timeout) {
		$to = new Timeout($timeout);

		if ( ! $this->sock) $this->connect();

		// Read the message length, up to a :
		$len = '';

		while (($chr = socket_read($this->sock, 1)) != ':') {
			if ($chr === false) usleep($timeout / 3);
			else $len .= $chr;

			// Check if we've hit the configured timeout and we're not part way through reading a number
			if (!$len && $to->check()) return;
		}

		if (!is_numeric($len)) throw new \RuntimeException("The socket message didn't start with a numeric length ({$len})");

		// Read and parse the message
		$event = socket_read($this->sock, $len);
		if ( ! ($parsed = json_decode($event, true))) throw new \RuntimeException("The socket message wasn't properly formed JSON ({$event})");

		// Discard the ,
		if (socket_read($this->sock, 1) !== ',') throw new \RuntimeException("Event wasn't followed with a , separator");

		return $parsed;
	}

	// Fires event callbacks
	protected function fireEvents(array $event) {
		$type = $event['type'];

		if (isset($this->eventCallbacks[$type])) {
			foreach ($this->eventCallbacks[$type] as $callback) {
				$callback($event);
			}
		}
	}

	// Fires response callback
	protected function fireResponse(array $response) {
		try {
			$callback = $this->responseCallbacks->pop();
		}
		catch (\RuntimeException $e) {
			return;
		}

		$callback($response['data']);
	}

	// Reads a JSON message from the socket and fires the appropriate callback(s)
	protected function readAndFire(int $timeout) {
		try {
			$message = $this->readMessage($timeout);
		}
		catch (\RuntimeException $e) {
			$this->log->error("Error reading message from socket: {$e->getMessage()}");
		}

		if ($message) {
			$this->log->debug("Got message: " . print_r($message, true));

			if (isset($message['event']) && $message['event'] == 'true') {
				$this->log->info("Got event of type {$message['type']}");
				$this->fireEvents($message);
			}
			elseif (isset($message['response']) && $message['response'] == 'true') {
				$this->log->debug("Got response: {$message['data']}");
				$this->fireResponse($message);
			}
		}
	}

	// Sends a command JSON message to BareSIP
	public function sendCommand(string $command, array $params = []) {
		$data = [
			'command' => $command,
		];

		$data += $params;

		// Format is LEN:JSON,
		$data = json_encode($data);
		$dataStr = strlen($data) . ':' . $data . ',';

		@socket_write($this->sock, $dataStr);
	}

	// Checks if BareSIP is alive by sending a ping type message
	protected function ping($timeout = 250000) {
		$this->sendCommand('main'); // Closest I could find to a 'ping'

		$gotResponse = false;

		$this->addResponseListener(function($response) use (&$gotResponse) {
			if (strpos($response, 'main loop') !== false)
				$gotResponse = true;
		});

		// Read and fire up to 20 times before the timeout in case there's events waiting on the socket
		$to = new Timeout($timeout);
		while ( ! $gotResponse) {
			$this->readAndFire($timeout / 20);

			if ($to->check()) break;
		}

		return $gotResponse;
	}

	// Poll for messages
	public function run(int $timeout = 0) {
		$to = new Timeout($timeout);

		while (1) {
			// Read a message and fire callback(s)
			$this->readAndFire($timeout / 3);

			// Try to run any timers which are due
			$this->timerManager->run();

			// Check if we've hit the configured timeout
			if ($to->check()) return;
		}
	}
}
