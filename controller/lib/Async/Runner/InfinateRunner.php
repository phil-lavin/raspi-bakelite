<?php

namespace Async\Runner;

use Async\Runner;

class InfinateRunner extends Runner {
	protected $interval;

	// timeout is the time this Runner should run in useconds
	public function __construct($interval = 5000) {
		$this->interval = $interval;
	}

	public function run() {
		while (1) {
			parent::run();

			usleep($this->interval);
		}
	}
}
