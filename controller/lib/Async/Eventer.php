<?php

namespace Async;

trait Eventer {
	protected $eventCallbacks = [];

	// Adds callbacks for particular types of events. These are persistent
	public function addEventListener(string $type, callable $callback) {
		$type = strtoupper($type);

		$this->eventCallbacks[$type][] = $callback;
	}

	// Fires event callbacks
	protected function fireEvents(string $type, array $event) {
		if (isset($this->eventCallbacks[$type])) {
			foreach ($this->eventCallbacks[$type] as $callback) {
				$callback($event);
			}
		}
	}
}
