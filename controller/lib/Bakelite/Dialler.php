<?php

namespace Bakelite;

use Monolog\Logger;
use Async\EventerInterface;

// Aggregates dialling events from the phone and turns them into digits on the dial plan
class Dialler implements EventerInterface {
	use \Async\Eventer;

	protected $log;
	protected $phone;
	protected $dialString;

	public function __construct(Logger $log, Phone $phone, DialString $dialString) {
		$this->log = $log;
		$this->phone = $phone;
		$this->dialString = $dialString;

		$this->init();
	}

	protected function init() {
		$digit = new Digit();

		// Listen for and handle relevant Phone events
		$this->getPhone()->addEventListener('TRIG', function ($event) use ($digit) {
			// Only when the phone is off the hook
			if ( ! $this->getPhone()->isOffHook()) return;

			// If this is the start, begin counting pulses on the Digit
			if ($event['value']) {
				$digit->start();
			}
			// End of a digit - get it and add it to the string
			else {
				$this->dialString->addDigit($digit->stop());
			}
		});

		$this->getPhone()->addEventListener('DIAL', function ($event) use ($digit) {
			// Only when the phone is off the hook
			if ( ! $this->getPhone()->isOffHook()) return;

			// Only count the 'on' part of the pulses
			if ( ! $event['value']) return;

			// Count the pulse
			$digit->pulse();
		});

		// Proxy the DialPlan events to our own listeners
		$this->getDialString()->addEventListener(true, function ($event, $type) {
			$this->fireEvents($type, $event);
		});
	}

	public function getPhone() {
		return $this->phone;
	}

	public function getDialString() {
		return $this->dialString;
	}
}
