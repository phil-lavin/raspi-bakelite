<?php

namespace Bakelite;

require_once 'Util.php';

use Monolog\Logger;
use Async\Timer\TimerManager;
use Async\Timer;

// Represents the ringer bell of the phone
class Ringer {
	protected $log;
	protected $timerManager;

	protected $ringer;
	protected $intervals;

	protected $ringing = false;
	protected $runningItervals = NULL;

	protected $timerName = 'currentBellInterval';

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
		$this->intervals[] = [
			'state' => $state,
			'time' => $time,
		];

		return $this;
	}

	// Returns true if this ringer is currently in the ringing state
	// Ringing doesn't mean the bell is on, just that it's going through a ring cycle currently
	public function isRinging() {
		return $this->ringing;
	}

	// Asyncronously ring the bell using timers
	public function ring() {
		// If we're not currently ringing, start
		if ( ! $this->isRinging()) {
			$this->runningIntervals = $this->intervals;
			$this->ringing = true;

			// Trigger the first interval
			return $this->ringNext();
		}

		// Otherwise treat this as a wrapper to run the timer manager timers
		return $this->timerManager->run();
	}

	// Turn off and reset the bell state
	public function stop() {
		$this->timerManager->removeTimerByName($this->timerName);
		$this->setBellState(false);
		$this->runningIntervals = NULL;
	}

	// Sets the on/off state of the bell
	protected function setBellState(bool $state) {
		$state = (string)(int)$state;

		$this->log->debug("Setting bell state to $state");
		fwrite($this->ringer, $state, 1);
	}

	protected function ringNext() {
		// Get the interval that we need to run now
		$interval = current($this->runningIntervals);

		// Toggle the bell as per the current interval
		$this->setBellState($interval['state']);

		// Set a timer to advance to the next state
		$timer = new Timer($interval['time'], function() {
			// Remove the current timer from the timer manager so we can add the new one
			$this->timerManager->removeTimerByName($this->timerName);
			// Advance the array pointer to either the next item or the first item if we hit the end
			next($this->runningIntervals) or reset($this->runningIntervals);

			// Trigger the next interval
			$this->ringNext();
		}, 1);

		// Add to the timer manager
		$this->timerManager->addTimer($timer, $this->timerName);
	}
}
