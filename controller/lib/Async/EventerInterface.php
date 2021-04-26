<?php

namespace Async;

interface EventerInterface {
	// Adds a callback which is called when an event of a specific type is fired
	public function addEventListener(string $type, callable $callback);
}
