<?php

namespace Bakelite;

// Used to count pulses from the rotary encoder and turn them into a digit
class Digit {
	protected $count = 0;

	public function getDigit() {
		return $this->count == 10 ? 0 : $this->count;
	}

	public function start() {
		$this->count = 0;
	}

	public function stop() {
		return $this->getDigit();
	}

	public function pulse() {
		$this->count++;
	}
}
