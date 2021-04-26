<?php

require_once 'vendor/autoload.php';

require_once 'config.php';

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use Async\Timer\TimerManager;
use Async\Runner\InfinateRunner;

use EV\EventLoop;

use Bakelite\BareSip;
use Bakelite\Phone;
use Bakelite\Ringer;
use Bakelite\DialPlan\UKDialPlan;
use Bakelite\DialPlan\ExtensionDialPlan;
use Bakelite\DialString;
use Bakelite\Dialler;

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
	$ringer = Ringer::createFromPatternArray($log, $timerManager, $ringerFile, $ringPattern);

	// Set up interface to the phone's hardware
	$phoneEventLoop = (new EventLoop($log))
		->addEventInput('HANG', $hangerFile)
		->addEventInput('TRIG', $triggerFile)
		->addEventInput('DIAL', $diallerFile);

	$phone = new Phone($log, $timerManager, $ringer, $phoneEventLoop);

	// Set up the dial plans - we want a UK Dial Plan with Extensions support
	$dialPlan = ExtensionDialPlan::createFromRangeArray($log, new UKDialPlan($log), $extensions);

	// This object records the number currently being dialled
	$dialString = new DialString($log, $timerManager, $dialPlan);

	// Create a Dialler to handle detection and collation of rotary events
	$dialler = new Dialler($log, $phone, $dialString);

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
		// Only when we're ringing - this is the user answering a ringing call
		if ( ! $phone->isRinging()) return;

		$phone->stopRinging();
		$bareSip->sendCommand('accept');
	});

	$phone->addEventListener('RECEIVER_DOWN', function($event) use ($bareSip, $phone, $dialString) {
		// Assume we're on a call and send hangup. We might not be, but it doesn't matter
		$bareSip->sendCommand('hangup');

		// Clear the dial string when we hang up on the off chance we're in the middle of dialling
		$dialString->reset();
	});

	// Listen for and handle Dialler events
	$dialler->addEventListener('COMPLETE', function ($event) use ($bareSip, $phone) {
		// Only when we're off the hook
		if ( ! $phone->isOffHook()) return;

		// Dial the number when the DialString is complete
		$bareSip->call($event['dialString']);
	});

	$dialler->addEventListener('NEW_DIGIT', function ($event) use ($bareSip, $phone) {
		// Only when we're off the hook
		if ( ! $phone->isOffHook()) return;

		$bareSip->dtmf($event['digit']);
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
	die(1);
}
catch (\ErrorException $e) {
	$log->error($e->getMessage());
	die(2);
}
