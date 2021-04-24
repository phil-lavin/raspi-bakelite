<?php

namespace Async\Runner;

use Async\Runner;
use Async\Timeout;

// Runs the runnables until a timeout value is hit
// It will guarantee that every runnable is run at least once, regardless of whether the timeout is hit before they all complete
class TimedRunner extends Runner {
	protected $timeout;
	protected $interval;

	// timeout is the time this Runner should run in useconds
	public function __construct($timeout, $interval = 5000) {
		$this->timeout = new Timeout($timeout);
		$this->interval = $interval;
	}

	public function run() {
		while (1) {
			parent::run();

			if ($this->timeout->check()) return;

			usleep($this->interval);
		}
	}
}
