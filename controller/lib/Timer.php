<?php

namespace Bakelite;

class Timer {
	protected $interval;
	protected $lastRun;
	protected $callback;

	public function __construct(int $interval, callable $callback) {
		$this->lastRun = microtime(true);
		$this->interval = $interval / 1000000;
		$this->callback = $callback;
	}

	public function run() {
		if (microtime(true) < $this->lastRun + $this->interval) return;

		$this->lastRun = microtime(true);

		$callback = $this->callback;
		$callback();
	}
}
