<?php

namespace Bakelite;

class Phone {
	protected $log;

	protected $ringer;
	protected $hanger;
	protected $trigger;
	protected $dialler;

	protected $offHook = false;

	public function __construct(\Monolog\Logger $log, string $ringer, string $hanger, string $trigger, string $dialler) {
		$this->log = $log;

		$this->ringer = $this->openHandle($ringer, 'w');
		$this->hanger = $this->openHandle($hanger, 'rb');
		$this->trigger = $this->openHandle($trigger, 'rb');
		$this->dialler = $this->openHandle($dialler, 'rb');
	}

	// Opens a file handle to a given file, doing some sanity checks and turning them into exceptions
	protected function openHandle(string $file, string $mode) {
		if ( ! file_exists($file)) {
			throw new \ErrorException("Cannot open {$file}: File does not exist");
		}

		if ( ! ($handle = fopen($file, $mode))) {
			throw new \ErrorException("Cannot open {$file}");
		}

		return $handle;
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
}
