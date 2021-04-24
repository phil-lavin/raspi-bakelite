<?php

namespace Async;

class Timeout {
	protected $start;
	protected $timeout;

	public function __construct($timeout) {
		$this->start = microtime(true);
		$this->timeout = $timeout / 1000000;
	}

	public function check() {
		return $this->timeout && microtime(true) >= $this->start + $this->timeout;
	}
}
