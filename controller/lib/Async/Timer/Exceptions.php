<?php

namespace Async\Timer;

class TimerNormalException extends \RuntimeException {}
class NotTimeYetException extends TimerNormalException {}
class MaxRunsExceededException extends TimerNormalException {}
