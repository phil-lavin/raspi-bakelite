<?php

namespace Bakelite;

use Monolog\Logger;
use Async\Timer;
use Async\Timer\TimerManager;
use Bakelite\DialPlan;

// Represents a dial string currently being constructed
class DialString {
	use \Async\Eventer;

	protected $log;
	protected $timerManager;
	protected $dialPlan;

	protected $dialString = '';

	public function __construct(Logger $log, TimerManager $timerManager, DialPlan $dialPlan) {
		$this->log = $log;
		$this->timerManager = $timerManager;
		$this->dialPlan = $dialPlan;
	}

	public function addDigit(string $digit) {
		// Reset timeout timer
		$this->timerManager->removeTimerByName('dialstring');
		$this->timerManager->addTimer(new Timer($this->getDialPlan()->getTimeout() * 1000000, function() {
			$this->reset();
			$this->log->info("Dialling timeout exceeded");

			$this->fireEvents('TIMEOUT', ['dialString'=>$this->getDialString()]);
		}), 'dialstring');

		// Append digit to dial string
		$this->dialString .= $digit;

		$this->log->info("Dial string is currently {$this->getDialString()}");
	}

	public function reset() {
		if ( ! $this->getDialString()) return;

		$this->timerManager->removeTimerByName('dialstring');
		$this->dialString = '';

		$this->log->info('Dial string reset');
	}

	public function getDialPlan() {
		return $this->dialPlan;
	}

	public function getDialString() {
		return $this->dialString;
	}

	public function __toString() {
		return $this->getDialString();
	}

	// Check the Dial Plan for completeness
	public function isComplete() {
		return $this->getDialPlan()->check($this->dialString);
	}
}
