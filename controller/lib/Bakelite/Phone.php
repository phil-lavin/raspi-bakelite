<?php

namespace Bakelite;

require_once 'Util.php';

use Monolog\Logger;
use Async\Timer;
use Async\Timer\TimerManager;

class Phone {
	protected $log;
	protected $timerManager;

	protected $ringer;
	protected $hanger;
	protected $trigger;
	protected $dialler;

	protected $offHook = false;

	public function __construct(Logger $log, TimerManager $timerManager, Ringer $ringer, string $hanger, string $trigger, string $dialler) {
		$this->log = $log;
		$this->timerManager = $timerManager;

		$this->ringer = $ringer;
		$this->hanger = $this->openHandle($hanger, 'rb');
		$this->trigger = $this->openHandle($trigger, 'rb');
		$this->dialler = $this->openHandle($dialler, 'rb');
	}

	public function __destruct() {
		// Stop ringing when the code exits or the bell may get stuck on
		$this->stopRinging();
	}

	// Opens a file handle to a given file, doing some sanity checks and turning them into exceptions
	protected function openHandle(string $file, string $mode) {
		return Util::openHandle($file, $mode);
	}

	// Reads and parses an event from /dev/input/* streams
	protected function readEvent($handle) {
		$read = [$handle];
		$write = [];
		$except = [];

		if (stream_select($read, $write, $except, 0, 0)) {
			$data = fread($handle, 32);
			return unpack('LtimeSec/LtimeUSec/Stype/Scode/Lvalue', $data);
		}

		return;
	}

	// Returns true if the handset is currently off the hook
	public function isOffHook() {
		// Read events from the input, if any exist, to sync the current state
		while ($event = $this->readEvent($this->hanger)) {
			$this->offHook = (bool)$event['value'];
		}

		return $this->offHook;
	}

	// Gets the Ringer instance of this Phone
	public function getRinger() {
		return $this->ringer;
	}

	// Rings the ringer
	public function ring() {
		return $this->getRinger()->ring();
	}

	// Stops the ringer ringing
	public function stopRinging() {
		return $this->getRinger()->stop();
	}
}
