<?php

require_once 'vendor/autoload.php';

require_once 'config.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use Async\Timer\TimerManager;
use Async\Runner\InfinateRunner;
use Async\Runner\TimedRunner;

use EV\EventLoop;

use Bakelite\BareSip;
use Bakelite\Phone;
use Bakelite\Ringer;
use Bakelite\DialPlan\UKDialPlan;
use Bakelite\DialPlan\ExtensionDialPlan;
use Bakelite\DialString;
use Bakelite\Digit;

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

	// Build a Ringer object from config
	$ringer = new Ringer($log, $timerManager, $ringerFile);

	foreach (array_values($ringPattern) as $k=>$interval) {
		// Odd is 'off'
		if ($k % 2) $ringer->addOffInterval($interval);
		// Even is 'on'
		else $ringer->addOnInterval($interval);
	}

	// Set up interface to the phone's hardware
	$eventLoop = (new EventLoop($log))
		->addEventInput('HANG', $hangerFile)
		->addEventInput('TRIG', $triggerFile)
		->addEventInput('DIAL', $diallerFile);

	$phone = new Phone($log, $timerManager, $ringer, $eventLoop);

	// Set up the dial plans - we want a UK Dial Plan with Extensions support
	$dialPlan = (new ExtensionDialPlan($log, new UKDialPlan($log)));

	foreach ($extensions as $extension) {
		$dialPlan->addExtensionRange(...$extension);
	}

	// This object records the number currently being dialled
	$dialString = new DialString($log, $timerManager, $dialPlan);

	// This object records the digit currently being dialled
	$digit = new Digit();

	// Listen for and handle various BareSIP events
	$bareSip->addEventListener('CALL_INCOMING', function($event) use ($bareSip, $phone) {
		// One at a time, please
		if ($phone->isOffHook() || $phone->isRinging()) return;

		$phone->ring();
	});

	$bareSip->addEventListener('CALL_CLOSED', function ($event) use ($bareSip, $phone) {
		$phone->stopRinging();
	});

	// Listen for and handle various Phone events
	$phone->addEventListener('RECEIVER_UP', function($event) use ($bareSip, $phone, $dialString) {
		// We are currently ringing, thus this is us answering the call
		if ($phone->isRinging()) {
			$phone->stopRinging();
			$bareSip->sendCommand('accept');
		}
	});

	$phone->addEventListener('RECEIVER_DOWN', function($event) use ($bareSip, $phone, $dialString) {
		// Assume we're on a call and send hangup. We might not be, but it doesn't matter
		$bareSip->sendCommand('hangup');

		// Clear the dial string when we hang up on the off chance we're in the middle of dialling
		$dialString->reset();
	});

	$phone->addEventListener('TRIG', function ($event) use ($bareSip, $phone, $digit, $dialString) {
		// Only when the phone is off the hook
		if ( ! $phone->isOffHook()) return;

		// If this is the start, begin counting pulses on the Digit
		if ($event['value']) {
			$digit->start();
		}
		// End of a digit - get it and add it to the string
		else {
			$dialString->addDigit($digit->stop());
		}
	});

	$phone->addEventListener('DIAL', function ($event) use ($digit, $phone) {
		// Only when the phone is off the hook
		if ( ! $phone->isOffHook()) return;

		// Only count the 'on' part of the pulses
		if ( ! $event['value']) return;

		// Count the pulse
		$digit->pulse();
	});

	// Listen for and handle DialString events
	$dialString->addEventListener('COMPLETE', function ($event) use ($dialString, $bareSip) {
		// Dial the number when the DialString is complete
		$bareSip->call($dialString);
	});

	// Event loop runner
	$eventLoop = new InfinateRunner(500);
	$eventLoop->addRunnable($bareSip);
	$eventLoop->addRunnable($timerManager);
	$eventLoop->addRunnable($phone);
	$eventLoop->addRunnable($dialString);

	// Run the event loop
	try {
		$eventLoop->run();
	}
	catch (\RuntimeException $e) {
		$log->error($e->getMessage());
	}
}
catch (\RuntimeException $e) {
	$log->error($e->getMessage());
	die(2);
}
catch (\ErrorException $e) {
	$log->error($e->getMessage());
	die(1);
}
