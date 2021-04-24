<?php

namespace Bakelite;

use Monolog\Logger;
use Async\Runnable;
use Async\Timer;
use Async\Timer\TimerManager;
use EV\EventLoop;

class Phone implements Runnable {
	protected $log;
	protected $timerManager;

	protected $ringer;
	protected $eventLoop;

	protected $offHook = false;
	protected $dialling = false;

	public function __construct(Logger $log, TimerManager $timerManager, Ringer $ringer, EventLoop $eventLoop) {
		$this->log = $log;
		$this->timerManager = $timerManager;

		$this->ringer = $ringer;
		$this->eventLoop = $eventLoop;

		$this->registerInputEvents();
	}

	public function __destruct() {
		// Stop ringing when the code exits or the bell may get stuck on
		$this->stopRinging();
	}

	// Registers internal handlers for the input events so we can update our own state
	protected function registerInputEvents() {
		$this->getEventLoop()->addEventListener('HANG', function($event) {
			$this->offHook = (bool)$event['value'];
		});

		$this->getEventLoop()->addEventListener('TRIG', function($event) {
			$this->dialling = (bool)$event['value'];
		});
	}

	// Returns true if the handset is currently off the hook
	public function isOffHook() {
		return $this->offHook;
	}

	// Returns true if the handset is currently dialling a digit (i.e. the rotary encoder is not at its resting point)
	public function isDialling() {
		return $this->dialling;
	}

	// Gets the Ringer instance of this Phone
	public function getRinger() {
		return $this->ringer;
	}

	// Gets the EventLoop instance of this Phone
	public function getEventLoop() {
		return $this->eventLoop;
	}

	// Rings the ringer
	public function ring() {
		return $this->getRinger()->ring();
	}

	// Stops the ringer ringing
	public function stopRinging() {
		return $this->getRinger()->stop();
	}

	// Triggers the event loop
	public function run() {
		return $this->getEventLoop()->run();
	}
}
