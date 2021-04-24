<?php

namespace Bakelite;

use Monolog\Logger;

abstract class DialPlan {
	protected $log;
	protected $decorator;

	protected $rules;

	// Implements the Decorator pattern. Optionally pass a DialPlan object to decorate its functionality
	public function __construct(Logger $log, DialPlan $decorator = NULL) {
		$this->log = $log;
		$this->decorator = $decorator;

		$this->init();
	}

	// Can be used by child classes to initialize stuff
	public function init() {}

	// Force child classes to define a timeout value
	protected abstract function timeout();

	// Public method to return the highest timeout from myself or my decoratee
	public function getTimeout() {
		return max($this->timeout(), $this->decorator ? $this->decorator->getTimeout() : 0);
	}

	// Adds a dialplan rule callback
	public function addRule(Callable $rule) {
		$this->rules[] = $rule;

		return $this;
	}

	// Adds a regex match. Actually just creates a basic callback to test it
	public function addRegex(string $pcre) {
		// Validate it
		if (preg_match($pcre, null) === false)
			throw new \RuntimeException("Regex Parsing Error: {$pcre} is not a valid PCRE");

		// Create the callback
		$this->addRule(function($number) use ($pcre) {
			return preg_match($pcre, $number);
		});
	}

	// Checks if a given number matches a rule
	public function check(string $number) {
		foreach ($this->rules as $rule) {
			if ($rule($number)) return true;
		}

		if ($this->decorator) return $this->decorator->check($number);

		return false;
	}
}
