<?php // 7.3.0

// simple timer
ini_set('precision',24);
class Timer {

	public $startTime;
	public $stopTime;
	public $totalTime;

	public function __construct () {
		$this->initializeObject ();
		$this->start();
	}

	private function cleanExit() {
		unset ($this->startTime);
		unset ($this->stopTime);
		unset ($this->totalTime);
	}

	public function __destruct () {
		unset ($this->startTime);
		unset ($this->stopTime);
	}

	private function initializeObject () {
		$this->startTime = 0;
		$this->stopTime = 0;
		$this->totalTime = 0;
	}

	// start the timer and clear any previous saved stops
	public function start () : float {
		$this->startTime = microtime(true);
		$this->stopTime = 0;
		return $this->startTime;
	}

	// stop the timer and calculate elapsed time
	public function stop () : float {
		$this->totalTime = ($this->stopTime = microtime(true)) - $this->startTime;
		$this->startTime = 0;
		return $this->totalTime;
	}

	// set currently elapsed time without stopping the timer
	public function read () : float {
		// if the timer wasn't stopped, return the currently elapsed time and save it to totalTime
		if (empty($this->stopTime)) {
			return (sprintf("%-24.24f",($this->totalTime = ((microtime(true)) - $this->startTime))));
		} else {
			return (sprintf("%-24.24f",$this->totalTime));
		}
	}

	// completely restart the timer
	public function reset () {
		self::__construct();
	}
}
?>
