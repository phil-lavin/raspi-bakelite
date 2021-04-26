<?php

namespace Bakelite;

use Monolog\Logger;
use Async\Timer;
use Async\Timer\TimerManager;
use Async\EventerInterface;
use Async\Runnable;
use Bakelite\DialPlan;

// Represents a dial string currently being constructed
class DialString implements Runnable, EventerInterface {
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

	protected function _fireEvents(string $type, array $extraData = []) {
		$data = [
			'dialString' => $this->getDialString()
		];
		$data += $extraData;

		$this->fireEvents($type, $data);
	}

	public function addDigit(string $digit) {
		// Reset timeout timer
		$this->timerManager->removeTimerByName('dialstring');
		$this->timerManager->addTimer(new Timer($this->getDialPlan()->getTimeout() * 1000000, function() {
			$this->reset();
			$this->log->info("Dialling timeout exceeded");

			$this->_fireEvents('TIMEOUT');
		}), 'dialstring');

		// Append digit to dial string
		$this->dialString .= $digit;

		$this->log->info("Dial string is currently {$this->getDialString()}");

		// Fire a NEW_DIGIT event
		$this->_fireEvents('NEW_DIGIT', ['digit'=>$digit]);
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

	// Checks the Dial Plan for completeness and throws an event if it's complete
	public function run() {
		if ( ! $this->isComplete()) return;

		$this->_fireEvents('COMPLETE');

		// Reset myself after firing the events
		$this->reset();
	}
}
