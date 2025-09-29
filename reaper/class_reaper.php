<?php // 7.3.0
#v:123432123
pcntl_async_signals(1);
require_once __DIR__ . "/timer/class_timer.php";
require_once __DIR__ . "/logger/class_logger.php";

define ("POLL_REALTIME",0);
define ("POLL_ACTIVE",1);
define ("POLL_INACTIVE",2);

class Reaper {

	private $pid;
	private $pgid;
	public  $pollActiveTime = 10000;
	public  $pollInactiveTime = 300000;
	public  $pollMode;
	public  $pollTime;
	private $reapList;
	public  $statusInterval;
	private $statusLastUpdate;
	private $statusTimer;

	public  $socket;
	public  $addr;
	public  $port;

	public  $log;
	public  $console;

	public function __construct() {
		$this->initializeObject();
		$this->log = new Logger("reaper.log",true);
		$this->console = new Logger("/dev/stderr",true);
//		$this->log->writeLog("Initializing the reaper",LOG_INIT);
		$this->outputMessage("Initializeing the reaper", LOG_INIT);
		$this->pid = posix_getpid();
		$this->pgid = posix_getpgid($this->pid);
//		$this->log->writeLog("PID ({$this->pid}), PGID ({$this->pgid})", LOG_INIT);
		$this->outputMessage("PID ({$this->pid}), PGID ({$this->pgid})", LOG_INIT);
		// temp
		$this->socket = socket_create_listen(0);
		socket_getsockname($this->socket,$this->addr, $this->port);
//		$this->register($this->pid);
	}

	public function outputMessage (string $what, int $level) {
		$this->log->writeLog($what,$level);
		$this->console->writeLog($what,$level);
	}

	private function initializeObject() {
		$this->pid = 0;
		$this->pgid = 0;
		$this->pollActivetime = 10000;
		$this->pollInactiveTime = 500000;
		$this->pollMode = POLL_ACTIVE;
		$this->pollTime = $this->pollActiveTime;
		$this->reapList = array();
		$this->statusInterval = 120;
		$this->statusLastUpdate = 0;
		$this->statusTimer = new Timer();
		$this->log = null;
		$this->addr="";
		$this->port="1414";
	}

	public function register (int $pid) : bool {
		// attempt to switch into active mode due to activity?
		if (!posix_getpgid($pid) === $this->pgid) return false;
		if ($this->pollMode !== POLL_REALTIME) $this->pollMode = POLL_ACTIVE;
//		$this->log->writeLog("Attempting to register pid $pid with pgid {$this->pgid}", LOG_INFO);
		$this->outputMessage("Attempting to register pid $pid with pgid {$this->pgid}", LOG_INFO);
		$this->reapList[$pid]['pid'] = $pid;
		if (($this->reapList[$pid]['pgid'] = posix_getpgid($pid)) !== $this->pgid) {
//			$this->log->writeLog("The pgid for the process didn't match {$this->pgid}, attempting to set it",LOG_WARNING);
			$this->outputMessage("The pgid for the process didn't match {$this->pgid}, attempting to set it",LOG_WARNING);
			if (!posix_setpgid($pid, $this->pgid)) {
//				$this->log->writeLog("Unable to set the pgid for process $pid",LOG_ERR);
				$this->outputMessage("Unable to set the pgid for process $pid",LOG_ERR);
				$result = false;
			}
		} else {
			$result = true;
		}
		if ($result === true) {
//			$this->log->writeLog("Successfully added $pid to the reapList", LOG_INFO);
			$this->outputMessage("Successfully added $pid to the reapList", LOG_INFO);
		}
//		var_dump($this->reapList);
		return $result;
	}

	public function deregister (int $pid) : bool {
		if ((isset($this->reapList[$pid])) && (!empty($this->reapList[$pid]))) {
			unset($this->reapList[$pid]);
//			$this->log->writeLog("Reaped PID $pid",LOG_INFO);
			$this->outputMessage("Reaped PID $pid",LOG_INFO);
			return true;
		}
//		$this->log->writeLog("Failed to locate $pid in my reap list",LOG_WARNING);
		$this->outputMessage("Failed to locate $pid in my reap list",LOG_WARNING);
		return false;
	}

	public function get_pgid() : int {
		return $this->pgid;
	}

	public function status () : int {
		if ((($now = $this->statusTimer->read()) - $this->lastStatusUpdate) >= $this->statusInterval) {
			$this->lastStatusUpdate = $now;
			if ($num = count($this->reapList) > 1) {
				$this->log->writeLog("I am currently waiting to reap " . ($num -1) ." processes", LOG_INFO);
				$this->outputMessage("I am currently waiting to reap " . ($num -1) ." processes", LOG_INFO);
				return ($num -1);
			} else {
				$this->log->writeLog("No registered processes", LOG_INFO);
				$this->outputMessage("No registered processes", LOG_INFO);
				return (0);
			}
		}
	}


// get the fork out
//		if (($pid = pcntl_fork()) < 0)
//			exit(1);
//		elseif ($pid != 0)
//			exit(0);
//
//		// attempt to enter the process group so we can reap things
//		if (!posix_setpgid(posix_getpid(),posix_getpgid(posix_getppid()))) 
//			exit (1);
//
//		$this->pid = posix_getpid();
//		$this->pgid = posix_getpgrp();
//		// start the loop

	private function pollWait () {
		if ($this->pollMode !== POLL_REALTIME) {
			if ((count($this->reapList) === 1) && (isset($this->reapList[$this->pid])) && ($this->reapList[$this->pid]['pid'] === $this->pid)) {
				if ($this->pollMode === POLL_ACTIVE) {
//					$this->log->writeLog("No processes to reap, switching to inactive mode", LOG_INFO);
					$this->outputMessage("No processes to reap, switching to inactive mode", LOG_INFO);
					$this->pollMode = POLL_INACTIVE;
					$this->pollTime = $this->pollInactiveTime;
				}
			} else {
				if ($this->pollMode === POLL_INACTIVE) {
//					$this->log->writeLog("Switching to active mode while waiting to reap" , LOG_INFO);
					$this->outputMessage("Switching to active mode while waiting to reap" , LOG_INFO);
					$this->pollMode = POLL_ACTIVE;
					$this->pollTime = $this->pollActiveTime;
				}
			}
			usleep($this->pollTime);
		}
	}


	public function shutdown () {
		foreach ($this->reapList as $pid => $props) {
			posix_kill(9,$pid);
		}
		exit (0);
	}

	public function reap() {
		$this->outputMessage("Reaping until there is nothing left to reap!", LOG_NOTICE);
		$write = array();
		$except = array();
		$lastReapTime = 0;
		do {
//			attempt to read the socket to get a pid to register
//			socket_set_nonblock($this->read);
//			$pid = intval(trim(socket_read($this->read, 10, PHP_BINARY_READ)));

			$status = null;
			// determine if signals have been received
			pcntl_signal_dispatch();

			// save resources ---
			$this->pollWait();

			// write the port number into pf
			$fp = fopen("/env/scripts/lib/reaper/pf","w");
			fwrite($fp,$this->port);
			fclose($fp);
			// accept a client
			$cInfo = array("addr"=>null,"port"=>null);
			$pidClient = socket_accept($this->socket);
			socket_getpeername($pidClient, $cInfo['addr'], $cInfo['port']);
			$this->port = $cInfo['port'];
			$this->outputMessage($cInfo['addr'] . " " . $cInfo['port'], LOG_ALERT);
			$pid = socket_read($this->socket,1024,PHP_BINARY_READ);
			if ($pid === '') {
				socket_close($this->socket);
			}
			$pid = intval(trim($pid));
			if ($pid > 0) {
				$this->register($pid);
			}

			// implement an interval action
			if ((($now = $this->statusTimer->read()) - $lastReapTime) >= $this->statusInterval) {
				if ($this->emitStatus === true) {
//					$this->log->writeLog("There are currently " . count($this->reapList) . " processes waiting to be reaped", LOG_NOTICE);
					$this->outputMessage("There are currently " . count($this->reapList) . " processes waiting to be reaped", LOG_NOTICE);
					$this->emitStatus = false;
				}
				$lastReapTime = $now;
			}
			// reap the process list
			foreach ($this->reapList as $pid => $props) {
				// set waitpid to handle any children in its process group (0)
				$cStatus = pcntl_waitpid(0, $status, WNOHANG);
//				echo $cStatus .PHP_EOL;
				if ((isset($this->reapList[$cStatus]['pid'])) && (!empty($this->reapList[$cStatus]['pid']))) {
//			if (($cStatus < 0) || ($cStatus > 0)) {
//					$this->log->writeLog("Reaping PID $cStatus",LOG_INFO);
					$this->outputMessage("Reaping PID $cStatus",LOG_INFO);
					$this->deregister($cStatus);
				}
			}
		} while (true);			
	}
}
?>
