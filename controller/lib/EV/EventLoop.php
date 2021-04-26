<?php

namespace EV;

use Monolog\Logger;
use Async\Runnable;
use Async\EventerInterface;

class EventLoop implements Runnable, EventerInterface {
	use \Async\Eventer;

	protected $log;
	protected $eventSize;
	protected $format;

	protected $handles;

	public function __construct(Logger $log, int $eventSize = 32, string $format = 'LtimeSec/LtimeUSec/Stype/Scode/Lvalue') {
		$this->log = $log;
		$this->eventSize = $eventSize;
		$this->format = $format;
	}

	public function addEventInput(string $name, string $file) {
		$this->handles[$name] = $this->openHandle($file, 'rb');

		return $this;
	}

	// Opens a file handle to a given file, doing some sanity checks and turning them into exceptions
	protected function openHandle(string $file, string $mode) {
		return Util::openHandle($file, $mode);
	}

	// Reads and parses events from /dev/input/* streams, firing event(s) as a result
	protected function readAndFireEvents() {
		$read = $this->handles;
		$write = [];
		$except = [];

		if (stream_select($read, $write, $except, 0, 0)) {
			foreach ($read as $k=>$handle) {
				$data = fread($handle, $this->eventSize);
				$data = unpack($this->format, $data);

				$this->fireEvents($k, $data);
			}
		}

		return;
	}

	// Checks for events and fires them
	public function run() {
		return $this->readAndFireEvents();
	}
}
