<?php

namespace Bakelite;

use Monolog\Logger;
use Async\Timer\TimerManager;
use Async\Timer;
use Async\Runnable;
use Async\Runner\CallbackRunner;
use Async\Runner\TimedRunner;

class BareSip implements Runnable {
	use \Async\Eventer;

	protected $log;
	protected $timerManager;
	protected $port;
	protected $ip;

	protected $sock;

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

	// Adds callbacks to the stack for responses. The callback on the top of the stack is popped (i.e. removed) and used once
	public function addResponseListener(callable $callback) {
		$this->responseCallbacks->push($callback);
	}

	// Reads a complete JSON message from the socket and returns it as an array
	protected function readMessage() {
		if ( ! $this->sock) $this->connect();

		// Read the message length, up to a :
		$len = '';

		while (($chr = socket_read($this->sock, 1)) != ':') {
			if ($chr === false && !$len) return;
			elseif ($chr !== false) $len .= $chr;
		}

		if (!is_numeric($len)) throw new \RuntimeException("The socket message didn't start with a numeric length ({$len})");

		// Read and parse the message
		$event = socket_read($this->sock, $len);
		if ( ! ($parsed = json_decode($event, true))) throw new \RuntimeException("The socket message wasn't properly formed JSON ({$event})");

		// Discard the ,
		if (socket_read($this->sock, 1) !== ',') throw new \RuntimeException("Event wasn't followed with a , separator");

		return $parsed;
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
	protected function readAndFire() {
		try {
			$message = $this->readMessage();
		}
		catch (\RuntimeException $e) {
			$this->log->error("Error reading message from socket: {$e->getMessage()}");
		}

		if ($message) {
			$this->log->debug("Got message: " . print_r($message, true));

			if (isset($message['event']) && $message['event'] == 'true') {
				$this->log->info("Got event of type {$message['type']}");
				$this->fireEvents($message['type'], $message);
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

		// Bind a response listener to listen for the response to the 'ping'
		$this->addResponseListener(function($response) use (&$gotResponse) {
			if (strpos($response, 'main loop') !== false)
				$gotResponse = true;
		});

		// Use a TimedRunner to wait for the response for $timeout uSeconds
		$cR = (new CallbackRunner())->addCallback(function() use (&$gotResponse) {
			if ( ! $gotResponse) $this->readAndFire();
		});
		$tR = (new TimedRunner($timeout))->addRunnable($cR);
		$tR->run();

		return $gotResponse;
	}

	// Poll for messages
	public function run() {
		// Read a message and fire callback(s)
		$this->readAndFire();
	}
}
