<?php

namespace Async;

class Timer implements Runnable {
	protected $interval;
	protected $lastRun;
	protected $callback;

	public function __construct(int $interval, callable $callback) {
		$this->lastRun = microtime(true);
		$this->interval = $interval / 1000000;
		$this->callback = $callback;
	}

	public function run() {
		if (microtime(true) < $this->lastRun + $this->interval)
			throw new Timer\NotTimeYetException();

		$this->lastRun = microtime(true);

		$callback = $this->callback;
		return $callback();
	}
}
