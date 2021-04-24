<?php

require_once 'vendor/autoload.php';

require_once 'config.php';

use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;
use \Async\Timer\TimerManager;
use \Bakelite\BareSip;
use \Bakelite\Phone;
use \Bakelite\Ringer;
use \Async\Runner\InfinateRunner;
use \Async\Runner\TimedRunner;

// Monolog Logger
$log = new Logger('bakelite');
$log->pushHandler(new StreamHandler($logFile, $logLevel));

// Clean up when we shut down
register_shutdown_function(function() use ($log) {
	$log->info("Shutdown detected. Cleaning up");

	// Should cause the destructor to be run
	if (isset($phone))
		unset($phone);
});

// It seems that PHP doesn't trigger the shutdown function when you send SIGINT (ctrl+c)
pcntl_async_signals(true);
pcntl_signal(SIGINT, function() {
	exit();
});

try {
	// Set up a new TimerManager to be used for co-ordinating async tasks
	$timerManager = new TimerManager();

	// Set up connection to BareSIP
	$bareSip = new BareSip($log, $timerManager);

	// Build a Ringer object
	$ringer = new Ringer($log, $timerManager, $ringerFile);

	foreach (array_values($ringPattern) as $k=>$interval) {
		// Even is 'on'
		if ($k % 2 == 0)
			$ringer->addOnInterval($interval);
		// Odd is 'off'
		else
			$ringer->addOffInterval($interval);
	}

	// Set up interface to the phone's hardware
	$phone = new Phone($log, $timerManager, $ringer, $hangerFile, $triggerFile, $diallerFile);

	// Listen for and handle various BareSIP events
	$bareSip->addEventListener('CALL_INCOMING', function($event) use ($bareSip, $phone) {
		if ($phone->isOffHook()) return;

		var_dump($event);
		//$bareSip->sendCommand('accept');

		$phone->stopRinging();
		$phone->ring();
	});

	// Bare sip timeout runner
	$bareSipRunner = new TimedRunner(50000, 5000);
	$bareSipRunner->addRunnable($bareSip);

	// Event loop runner
	$eventLoop = new InfinateRunner(1);
	$eventLoop->addRunnable($bareSipRunner);
	$eventLoop->addRunnable($timerManager);

	// Run the event loop
	$eventLoop->run();

	// Event loop
	/*while (1) {
		// Poll for messages from BareSIP
		$bareSip->run(50000); // 0.05 second timeout

		// Run any timers which are due
		$timerManager->run();

		// Detect phone being off the hook
		if ($phone->isOffHook()) {
			var_dump('Phone is off the hook');
			sleep(1);
		}
	}*/
}
catch (\RuntimeException $e) {
	$log->error($e->getMessage());
}
catch (\ErrorException $e) {
	$log->error($e->getMessage());
	die(1);
}
