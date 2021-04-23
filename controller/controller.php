<?php

require_once 'vendor/autoload.php';
require_once 'lib/BareSip.php';
require_once 'lib/Phone.php';

require_once 'config.php';

$log = new \Monolog\Logger('bakelite');
$log->pushHandler(new \Monolog\Handler\StreamHandler($logFile, $logLevel));

try {
	// Set up connection to BareSIP
	$bareSip = new \Bakelite\BareSip($log);

	// Set up interface to the phone's hardware
	$phone = new \Bakelite\Phone($log, $ringer, $hanger, $trigger, $dialler);

	// Listen for and handle various BareSIP events
	$bareSip->addEventListener('CALL_INCOMING', function($event) use ($bareSip, $phone) {
		if ($phone->isOffHook()) return;

		var_dump($event);
		$bareSip->sendCommand('accept');
	});

	// Event loop
	while (1) {
		// Poll for messages from BareSIP
		$bareSip->run(50000); // 0.05 second timeout

		// Detect phone being off the hook
		if ($phone->isOffHook()) {
			var_dump('Phone is off the hook');
			sleep(1);
		}
	}
}
catch (\RuntimeException $e) {
	$log->error($e->getMessage());
}
catch (\ErrorException $e) {
	$log->error($e->getMessage());
	die(1);
}
