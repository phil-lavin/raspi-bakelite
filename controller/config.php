<?php

$logFile = STDOUT;
$logLevel = \Monolog\Logger::INFO;

$ringerFile = '/sys/class/leds/ringer/brightness';
$triggerFile = '/dev/input/by-path/platform-soc:trig-event';
$hangerFile = '/dev/input/by-path/platform-soc:hang-event';
$diallerFile = '/dev/input/by-path/platform-soc:dial-event';

// Array of times in useconds which represent one ring
// First entry is ringer on, second is off, etc.
$ringPattern = [400000, 200000, 400000, 2000000];

// Array of own internal extension ranges to add to the dial plan
$extensions = [
	[201,205],
];
