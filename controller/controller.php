<?php

require_once 'vendor/autoload.php';
require_once 'lib/BareSip.php';

require_once 'config.php';

$log = new \Monolog\Logger('bakelite');
$log->pushHandler(new \Monolog\Handler\StreamHandler($logFile, $logLevel));

try {
	$bareSip = new \Bakelite\BareSip($log);

	$bareSip->addEventListener('CALL_INCOMING', function($event) use ($bareSip) {
		var_dump($event);
		$bareSip->sendCommand('accept');
	});

	while (1) {
		$bareSip->run(50000);
	}
}
catch (\RuntimeException $e) {
	$log->error($e->getMessage());
}
catch (\ErrorException $e) {
	$log->error($e->getMessage());
	die(1);
}
