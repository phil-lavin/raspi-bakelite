<?php

namespace Bakelite;

require_once 'Timer.php';

class TimerManager {
	protected $timers;

	public function addTimer(Timer $timer, string $name = NULL) {
		if ($name === NULL)
			$name = spl_object_id($timer);

		if (isset($this->timers[$name])) {
			throw new RuntimeException("Timer with name {$name} already exists");
		}

		$this->timers[$name] = $timer;
	}

	public function removeTimer(Timer $timer) {
		foreach ($this->timers as $k=>$t) {
			if ($t == $timer) {
				unset($this->timers[$k]);
				return true;
			}
		}

		return false;
	}

	public function removeTimerByName(string $name) {
		if ( ! isset($this->timers[$name])) return false;

		unset($this->timers[$name]);
		return true;
	}

	public function run() {
		$count = 0;

		foreach ($this->timers as $timer) {
			try {
				$timer->run();
				$count++; // Increment count if timer->run() didn't throw a NotTimeYetException
			}
			catch (NotTimeYetException $e) {}
		}

		return $count;
	}
}
