<?php

namespace Async;

require_once 'Timer/Exceptions.php';

class Timer {
	protected $interval;
	protected $lastRun;
	protected $callback;
	protected $maxRuns;

	protected $runCount = 0;

	public function __construct(int $interval, callable $callback, int $maxRuns = -1) {
		$this->lastRun = microtime(true);
		$this->interval = $interval / 1000000;
		$this->callback = $callback;
		$this->maxRuns = $maxRuns;
	}

	public function run() {
		if ($this->maxRunsExceeded())
			throw new Timer\MaxRunsExceededException();

		if (microtime(true) < $this->lastRun + $this->interval)
			throw new Timer\NotTimeYetException();

		$this->lastRun = microtime(true);
		$this->runCount++;

		$callback = $this->callback;
		return $callback();
	}

	public function getRunCount() {
		return $this->runCount;
	}

	public function maxRunsExceeded() {
		return $this->maxRuns >= 0 && $this->runCount >= $this->maxRuns;
	}
}
