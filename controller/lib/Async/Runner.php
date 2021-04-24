<?php

namespace Async;

require_once 'Runnable.php';

use Async\Runnable;

class Runner implements Runnable {
	protected $runnables = [];

	public function addRunnable(Runnable $runnable) {
		$this->runnables[] = $runnable;
		return $this;
	}

	public function removeRunnable(Runnable $runnable) {
		foreach ($this->runnables as $k=>$r) {
			if ($runnable === $r) {
				unset($this->runnables[$k]);
			}
		}

		return $this;
	}

	public function run() {
		foreach ($this->runnables as $runnable) {
			$runnable->run();
		}
	}
}
