<?php

namespace Bakelite\DialPlan;

use Monolog\Logger;
use Bakelite\DialPlan;

class ExtensionDialPlan extends DialPlan {
	protected $timeout = 10;
	protected $extensions = [];

	public function init() {
		// Rule to check extensions
		$this->addRule([$this, 'checkExtensions']);
	}

	// Add a range of extensions which we should match
	public function addExtensionRange(int $min, int $max) {
		$this->extensions[] = [$min,$max];

		return $this;
	}

	// Check list of extensions to see if any match
	protected function checkExtensions($number) {
		foreach ($this->extensions as $extension) {
			if ($number >= $extension[0] && $number <= $extension[1])
				return true;
		}

		return false;
	}

	protected function timeout() {
		return $this->timeout;
	}

	// Creates an instance of this object from an array of extension ranges
	public static function createFromRangeArray(Logger $log, DialPlan $decorator, array $ranges) {
		$dialPlan = new static($log, $decorator);

		foreach ($ranges as $range) {
			$dialPlan->addExtensionRange(...$range);
		}

		return $dialPlan;
	}
}
