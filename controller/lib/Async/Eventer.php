<?php

namespace Async;

trait Eventer {
	protected $eventCallbacks = [true => []];

	// Adds callbacks for particular types of events. These are persistent
	// If type === true then this callback will be called for all events
	public function addEventListener(string|bool $type, callable $callback) {
		$type = strtoupper($type);

		$this->eventCallbacks[$type][] = $callback;
	}

	// Fires event callbacks
	protected function fireEvents(string $type, array $event) {
		if (isset($this->eventCallbacks[$type])) {
			foreach ($this->eventCallbacks[$type] as $callback) {
				$callback($event, $type);
			}
		}

		// Callbacks with a type === true always get called
		foreach ($this->eventCallbacks[true] as $callback) {
			$callback($event, $type);
		}
	}
}
