<?php

namespace Async\Timer;

use \Async\Timer;

class MaxRunTimer extends Timer {
	protected $maxRuns;

	protected $runCount = 0;

	public function __construct(int $interval, callable $callback, int $maxRuns) {
		$this->maxRuns = $maxRuns;

		return parent::__construct($interval, $callback);
	}

	public function run() {
		if ($this->maxRunsExceeded())
			throw new Timer\MaxRunsExceededException();

		$result = parent::run();

		$this->runCount++;

		return $result;
	}

	public function getRunCount() {
		return $this->runCount;
	}

	public function maxRunsExceeded() {
		return $this->runCount >= $this->maxRuns;
	}
}
