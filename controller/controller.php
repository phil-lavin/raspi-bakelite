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
use \EV\EventLoop;

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
	$eventLoop = (new EventLoop($log))
		->addEventInput('HANG', $hangerFile)
		->addEventInput('TRIG', $triggerFile)
		->addEventInput('DIAL', $diallerFile);

	$phone = new Phone($log, $timerManager, $ringer, $eventLoop);

	// Listen for and handle various BareSIP events
	$bareSip->addEventListener('CALL_INCOMING', function($event) use ($bareSip, $phone) {
		if ($phone->isOffHook()) return;

		$phone->stopRinging();
		$phone->ring();
	});

	$bareSip->addEventListener('CALL_CLOSED', function ($event) use ($bareSip, $phone) {
		$phone->stopRinging();
	});

	// Listen for and handle various Phone events
	$phone->addEventListener('HANG', function($event) use ($bareSip, $phone) {
		// Picked up
		if ($event['value']) {
			// We are currently ringing, thus this is us answering the call
			if ($phone->isRinging()) {
				$phone->stopRinging();
				$bareSip->sendCommand('accept');
			}
		}
		// Hung up
		else {
			// Assume we're on a call and send hangup. We might not be but it doesn't matter
			$bareSip->sendCommand('hangup');
		}
	});

	// Event loop runner
	$eventLoop = new InfinateRunner(500);
	$eventLoop->addRunnable($bareSip);
	$eventLoop->addRunnable($timerManager);
	$eventLoop->addRunnable($phone);

	// Run the event loop
	$eventLoop->run();
}
catch (\RuntimeException $e) {
	$log->error($e->getMessage());
}
catch (\ErrorException $e) {
	$log->error($e->getMessage());
	die(1);
}
