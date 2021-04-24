<?php

namespace Bakelite;

use Monolog\Logger;

abstract class DialPlan {
	protected $log;

	protected $rules;

	public function __construct(Logger $log) {
		$this->log = $log;

		$this->init();
	}

	// Force child classes to define a timeout value
	public abstract function getTimeout();

	// Can be used by child classes to initialize stuff
	public function init() {}

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

		return false;
	}
}
