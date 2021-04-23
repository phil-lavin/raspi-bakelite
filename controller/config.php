<?php

$logFile = STDOUT;
$logLevel = \Monolog\Logger::INFO;

$ringer = '/sys/class/leds/ringer/brightness';
$trigger = '/dev/input/by-path/platform-soc:trig-event';
$hanger = '/dev/input/by-path/platform-soc:hang-event';
$dialler = '/dev/input/by-path/platform-soc:dial-event';
