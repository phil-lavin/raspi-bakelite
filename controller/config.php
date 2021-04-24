<?php

$logFile = STDOUT;
$logLevel = \Monolog\Logger::INFO;

$ringerFile = '/sys/class/leds/ringer/brightness';
$triggerFile = '/dev/input/by-path/platform-soc:trig-event';
$hangerFile = '/dev/input/by-path/platform-soc:hang-event';
$diallerFile = '/dev/input/by-path/platform-soc:dial-event';
