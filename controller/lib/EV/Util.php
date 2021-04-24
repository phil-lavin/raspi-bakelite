<?php

namespace EV;

class Util {
	// Opens a file handle to a given file, doing some sanity checks and turning them into exceptions
	public static function openHandle(string $file, string $mode) {
		if ( ! file_exists($file)) {
			throw new \ErrorException("Cannot open {$file}: File does not exist");
		}

		if ( ! ($handle = fopen($file, $mode))) {
			throw new \ErrorException("Cannot open {$file}");
		}

		return $handle;
	}
}
