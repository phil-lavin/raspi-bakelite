<?php

namespace Bakelite;

require_once 'Util.php';

// Represents the ringer bell of the phone
class Ringer {
	protected $log;
	protected $timerManager;

	protected $ringer;
	protected $intervals;

	protected $ringing = false;

	public function __construct(\Monolog\Logger $logger, TimerManager $timerManager, $ringerFile) {
		$this->log = $logger;
		$this->timerManager = $timerManager;
		$this->ringer = $this->openHandle($ringerFile, 'w');
	}

	// Opens a file handle to a given file, doing some sanity checks and turning them into exceptions
	protected function openHandle(string $file, string $mode) {
		return Util::openHandle($file, $mode);
	}

	// Adds a period of ringing 'on' for $time useconds
	public function addOnInterval($time) {
		return $this->addInterval(true, $time);
	}

	// Adds a period of ringing 'off' for $time useconds
	public function addOffInterval($time) {
		return $this->addInterval(false, $time);
	}

	// Adds an interval of a given type for $time useconds
	protected function addInterval($state, $time) {
		// Build interval with time in decimal seconds
		$this->intervals[] = [
			'state' => $state,
			'time' => $time / 1000000,
		];

		return $this;
	}
}
