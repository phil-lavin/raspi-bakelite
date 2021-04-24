<?php

namespace Async\Runner;

require_once __DIR__.'/../Runnable.php';

use Async\Runnable;

class CallbackRunner implements Runnable {
	protected $callbacks = [];

	public function addCallback(Callable $callback) {
		$this->callbacks[] = $callback;

		return $this;
	}

	public function removeCallback(Callable $callback) {
		foreach ($this->callbacks as $k=>$r) {
			if ($callback === $r) {
				unset($this->callbacks[$k]);
			}
		}

		return $this;
	}

	public function run() {
		foreach ($this->callbacks as $callback) {
			$callback();
		}
	}
}
