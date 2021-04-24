<?php

namespace Bakelite\DialPlan;

use Bakelite\DialPlan;

class UKDialPlan extends DialPlan {
	protected $timeout = 10;

	public function init() {
		// Standard 11 digit dialling with 0 prefix
		$this->addRegex('/0[0-9]{10}/');
	}

	public function getTimeout() {
		return $this->timeout;
	}
}
