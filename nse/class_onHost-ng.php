<?php // 7.3.0

require_once __DIR__ . "/logger/class_logger.php";

class OnHost {
	
	private $userName;		// the username to use for the connections (defaults to $LOGNAME from the environment)
	private $nodeFile;		// text file containing: id:address[:port]
	private $nodeList;		// array containing hosts to operate on
	private $onlyNodes;		// only operate on specified nodes in the list
	private $identityFile;	// ~$userName/.ssh/id_<type> || /path/to/identity_file
	private $parallelExec;	// do parallel execution?
	private $command;
	public $output;

	private $allNodes;

	public $numNodes;

	private $logging;
	private $log;

	public function __construct (string $hostList, string $command, string $onlyNodes = null, bool $parallel = null, string $identityFile = null, Logger $log = null)
	{
		$this->initializeObject();

		if (($log !== null) || (!is_a($log,"Logger")))
		{
			$logDir = $_SERVER['ENV_LOGROOT'] . "/" . $_SERVER['ENV_MYIP'] . "/onHost";
			$logFile = $logDir . "/onHost.log";
			if (!is_dir($logDir)) if (!mkdir($logDir,0644)) return false;
			$this->log = new Logger ($logFile,true);
			$this->logging = true;
		}
		else
		{
			$this->log =& $log;
			$this->logging = true;
		}

		// look for and parse the user portion of the hostList definition [ user@host || user@nodeFile ]
		if (preg_match("/@/",$hostList))
		{
			list ($this->userName,$hostList) = explode("@",$hostList);
		}
		else
		{
			$this->userName = $_SERVER['LOGNAME'];
		}
		// if $hostList was a filename, parse that
		if ((file_exists($hostList)) && (is_readable($hostList)))
		{
			$this->parseNodeList($hostList);
		}
		else
		{
			// otherwise, try to determine if port was provided
			if (preg_match("/:/", $hostList))
			{
				list($this->nodeList[0]['address'],$this->nodeList[0]['port']) = explode(":",$hostList);
			}
			else
			{
				// it is just a host, deal with it
				$this->nodeList[0]['address'] = $hostList;
			}
		}
		if ($parallel === true) $this->parallel = true;
		// we aren't going to validate this much right now .. just a quick split by ',' or add the string to the first element of this->onlyNodes
		if ($onlyNodes !== null)
		{
			if (preg_match("/,/", $onlyNodes))
			{
				$onlyNodes = explode(",", $onlyNodes); // make array from csv node list
			}
			else
			{
				if (preg_match("/^all$/", $onlyNodes))
				{
					$this->allNodes = true;
				}
				else
				{
					$this->allNodes = false;
					$onlyNodes[] = $onlyNodes;
				}
			}
		}
		// if we have an identity file, it exists and we can read it, use it, otherwise show usage and quit(1);
		if ($identityFile !== null)
		{
			if ((file_exists($identityFile)) && (is_readable($identityFile)))
			{
				$this->identityFile = $identityFile;
			}
			else
			{
				$this->showUsage("Unable to open specified identity file");
			}
		}
		foreach ($this->nodeList as $id => $prop)
		{
			if (!isset($prop['port']))
			{
				$this->nodeList[$id]['port'] = 22;
			}
			if (!isset($prop['weight']))
			{
				$this->determineNodeWeight($prop['address']);
			}
		}
		$this->numNodes = count($this->nodeList);
		$this->command = $command;
		$this->health ();
		$this->processCommand();
		$this->showOutput ();
	}
	
	public function __destruct ()
	{
		$this->cleanExit(0);
	}
	
	private function initializeObject () : void
	{
		$this->log = null;
		$this->logging = true;
		$this->numNodes = 0;
		$this->userName = $_SERVER['LOGNAME'];
		$this->nodeFile = null;
		$this->nodeList = null;		
//		$this->nodeList = array('address' => null,
//				        'port' => null,
//				        'weight' => null,
//				        'isAlive' => null,
//				        'pid' => null,
//				        'tmpFile' => null,
//				        'output' => null
//					);
		$this->identityFile = null;
		$this->parallel = false;
		$this->allNodes = true;
	}
	
	public function cleanExit (int $code = null) : int
	{
		return $code;
	}

	private function parseNodeList (string $nodeFile = null) : bool
	{
		if ($nodeFile === null) return false;
		if ((file_exists($nodeFile)) && (is_readable($nodeFile)))
		{
			if ($this->logging === true) $this->log->writeLog("Parsing node file $nodeFile",LOG_INIT);
			$this->nodeFile = $nodeFile;
		}
		$nodes = array();
		if ($fp = fopen($this->nodeFile,"r+"))
		{
			while (!feof($fp)) $nodes[] = rtrim(fgets($fp));
		}
		else
		{
			return false;
		}
		fclose ($fp);
		foreach($nodes as $nodeItem)
		{
			// this probably needs some refinement
			if (preg_match('/ /',$nodeItem)) continue; // skip if blanks
			if (preg_match('/^$/',$nodeItem)) continue; // skip if no line item
			if (preg_match('/[\\`!~@#$%^&*\)\(\]\[\}\{,\/]/',$nodeItem)) continue; // skip if these are present
			@list ($nID,$nAddr,$nPort,$nWeight) = explode(":",$nodeItem);
			if ($this->logging === true) $this->log->writeLog("Found node: $nAddr ($nID)",LOG_INFO);
			if ((isset($nID)) && (isset($nAddr)) && ($nID !== null) && (!empty($nAddr)))
			{
				$this->nodeList[$nID]['address'] = $nAddr;
				if ((isset($nPort)) && (is_numeric($nPort)))
				{
					$this->nodeList[$nID]['port'] = $nPort;
				}
				else
				{
					$this->nodeList[$nID]['port'] = 22;
				}
				if ((isset($nWeight)) && (is_numeric($nWeight)))
				{
					$this->nodeList[$nID]['weight'] = $nWeight;
				}
				else
				{
					if ($this->determineNodeWeight($nID) === false) $this->nodeList[$nID]['weight'] = 75000;
				}
				$this->nodeList[$nID]['isAlive'] = $this->isAlive($nID);
			}
			else
			{
				continue;
			}
//			if (is_writable($nodeFile)) {
//				file_put_contents($nodeFile,implode(":",$this->nodeList[$nID]));
//			}
		}
		$this->writeNodeFile();
		return true;
	}

	private function writeNodeFile () : void
	{
		if ($this->logging === true) $this->log->writeLog("Writing node information to " . $this->nodeFile,LOG_INFO);
		$fp = fopen($this->nodeFile,"w+");
		foreach($this->nodeList as $nID => $prop)
		{
			$line = sprintf("%s:%s:%s:%s\n",$nID,$prop['address'],$prop['port'],$prop['weight']);
			fwrite($fp,$line);
		}
		fclose($fp);
	}

	private function getNodeKey (string $node = null)
	{
		if ($node === null) return false;
		$key = null;
		// accept either node id, node name or node address
		if (isset($this->nodeList[$node]))
		{
			// a node id was pssed
			$key = $node;
		}
		elseif (($key = array_search($node, $this->nodeList)) === false)
		{
			foreach ($this->nodeList as $id => $prop)
			{
				if ((strcmp(strtolower($node),strtolower($prop['address']))) !== 0)
				{
					continue;
				}
				else
				{
					$key = $id;
				}
			}
		}
		return $key;
	}

	public function determineNodeWeight (string $node = null) : int
	{
		if ($node === null) return false;
		if (($key = $this->getNodeKey($node)) === false) return false;
		$iterations = 0;
		$trips = array();
		$low = null;
		$high = null;
		$average = null;
		$pkt = "\x08\x00\x7d\x4b\x00\x00\x00\x00PingHost";
		$iterations++;
		for ($i = 0; $i < 25; $i++)
		{
			$averages[$i] = null;
			$qs = socket_create(AF_INET,SOCK_RAW,1);
			// max timeout = 1 s
			socket_set_option($qs,SOL_SOCKET,SO_RCVTIMEO,array('sec'=>1,'usec'=>0));
			if (@socket_connect($qs,$this->nodeList[$key]['address'],null) === false) break;
			$ts = microtime(true);
			socket_send($qs,$pkt,strLen($pkt),0);
			if (socket_read($qs,255))
			{
				$trips[$i] = microtime(true) - $ts;
			}
		}
		// determine highest trip, lowest trip and average
		foreach ($trips as $length)
		{
			$length = intval($length * 1000000);
			$low ?? $low = $length;
			$high ?? $high = $length;
			$average += $length;
			if ($length < $low)
			{
				$low = $length;
			}
			if ($length > $high)
			{
				$high = $length;
			}
		}
		$this->nodeList[$key]['weight'] = intval(($average / count($trips)) + ($high - $low));
		return $this->nodeList[$key]['weight'];
	}

	public function weighAll () : void
	{
		if ($this->logging === true) $this->log->writeLog("Determining node weight for all hosts",LOG_INFO);
		foreach ($this->nodeList as $key => $prop)
		{
			$this->determineNodeWeight($key);
		}
		$this->writeNodeFile();
	}

	public function setWeight (string $node = null, int $weight = null) : bool
	{
		if ($node === null) return false;
		if ($weight === null) $weight = 75000;
		if (($key = $this->getNodeKey($node)) === false) return false;
		if (($weight > 1) && ($weight <= 100000))
		{
			$this->nodeList[$key]['weight'] = $weight;
		}
		else
		{
			return false;
		}
		return true;
	}

	public function getUserName () : string
	{
		return $this->userName;
	}

	// this will check to see if a host (node) is responding on the network
	public function isAlive (string $node = null) : bool
	{
		if ($node === null) return false;
		if (($key = $this->getNodeKey($node)) === false) return false;
		if ($this->logging === true) $this->log->writeLog("Determining if " . $this->nodeList[$key]['address'] . " is online",LOG_INFO);
		// choose a default value of .75 sec if weight isn't set
		$this->nodeList[$key]['weight'] ?? $this->nodeList[$key]['weight'] = 75000;
		$ret = false;
		$res = array();
		// get the host's key from the nodeList
		$pkt = "\x08\x00\x7d\x4b\x00\x00\x00\x00PingHost";
		$qs  = socket_create(AF_INET, SOCK_RAW, 1);
		// 3/4 sec pings
		$secs = intval($this->nodeList[$key]['weight'] / 10000);
		$usec = intval($this->nodeList[$key]['weight'] - ($secs*10000));
		socket_set_option($qs, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $secs, 'usec' => $usec));
		if (@socket_connect($qs, $this->nodeList[$key]['address'], null) === false)
		{
			return false;				
		}
		$ts = microtime(true);
		if (@socket_send($qs, $pkt, strLen($pkt), 0) === false)
		{
			return false;
		}
		if (socket_read($qs, 255))
		{
			$ret = true;
		}
		socket_close($qs);
		$this->nodeList[$key]['isAlive'] = $ret;
		return $ret;
	}

	public function getNodeAddress (string $node = null) : string
	{
		if ($node === null) throw new Exception ("Cannot retrieve address of unknown node '$node'",1);
		if (($key = $this->getNodeKey($node)) === false) throw new Exception ("Cannot retrieve address of unknown node '$node'",1);
		return $this->nodeList[$key]['address'];
	}

	public function showNodes (bool $detail = null) : void
	{
		foreach ($this->nodeList as $key => $prop)
		{
			echo "[$key] = " . $prop['address'] . PHP_EOL;
			if ($detail !== false)
			{
				echo "  port:   " . $prop['port'] . PHP_EOL;
				echo "  weight: " . $prop['weight'] . PHP_EOL;
				if ($prop['isAlive'] === true)
				{
					echo "  alive?  true" . PHP_EOL;
				}
				else
				{
					echo "  alive?  false" . PHP_EOL;
				}
			}
		}
	}

	// determine the state of the hosts from the given nodeList
	public function health () : bool
	{
		$warn = false;
		foreach ($this->nodeList as $key => $prop)
		{
			if (!$this->isAlive($key))
			{
				if ($this->logging === true) $this->log->writeLog($prop['address'] . " is not responding",LOG_WARN);
				echo "[WARN] ${prop['address']} is unresponsive" . PHP_EOL;
				$warn = true;
			}
		}
//		if ($warn === false) {
//			echo "All hosts from " . $this->nodeFile . " are ready" . PHP_EOL;
//		}
		return $warn;
	}

	public function setUserName (string $userName = null) : bool
	{
		if ($userName === null) throw new Exception ("Cannot set empty user name '$userName'",1);
		if (preg_match('^[a-zA-Z0-9]$',$userName))
		{
			$this->userName = $userName;
			return true;
		}
		return false;
	}

	public function showUsage(string $reason = null)
	{
		if ($reason !== null) echo "[Error] $reason" . PHP_EOL .PHP_EOL;
		$message = <<<DONE
onHost [-p] <-h host|host.lst> [-n [#[,#] | all] ] [-i 'identity-file'] 'command'
=================================================================================
-n #,# or -n all          specify one or more nodes
Nodes are hostnames prefixed with a number followed by a colon in the host.lst
=================================================================================
-p                        process commands in parallel (output will mangle)
-i <identity-file>        the ssh identity file use for authorization
-h <user@host>            the user name and host used to run the command
<commands>                the command-line and arguments to run on the system

Example: Perform an upgrade of multiple CentOS/RHEL systems in parallel:
onHost -p -i ~/.ssh/id_dsa -h root@host.file yum -y upgrade

Example: Rediscover FC devices on multiple systems (without identity file):
onHost -h root@host.file multipath -F ; rmmod qla2xxx ; modprobe qla2xxx

DONE;

		$this->cleanExit(1);
	}
	
	private function processCommand ()
	{
		if (($this->command === null) || (empty($this->command)))
		{
			return false;
		}
		if (($this->nodeList === null) || (empty($this->nodeList)))
		{
			return false;
		}
		$nodeList = null;
		// determine whether we are working on a subset of nodes from the list ...
		if (($this->allNodes === false) && ($this->onlyNodes !== null))
		{
			foreach ($this->onlyNodes as $nodeKey)
			{
				if (($key = $this->getNodeKey($nodeKey)) !== false)
				{
					$nodeList[$key] = &$this->nodeList[$key];
				}
			}
		// either onlyNodes was empty or this->allNodes is true.. use them all ...
		}
		else
		{
			$nodeList =& $this->nodeList;
		}
		$cmd = "ssh ";
		if ($this->identityFile !== null)
		{
			$cmd .= " -i " . $this->identityFile . " ";
		}
		$cmd .= $this->userName . "@";
		// so far: cmd = "ssh [-i $identityFile] userName@"
		$finished = 0;
		$started = 0;
		if ($this->parallel === true)
		{
			echo "Parallel output cannot be displayed." . PHP_EOL;
			foreach ($nodeList as $key => &$prop)
			{
				$started++;
				$prop['tmpFile'] = "/tmp/" . $key . ".tmp";
				$prop['pid'] = pcntl_fork ();
				if ($prop['pid'] == -1)
				{
					$this->cleanExit(-1);
				}
				elseif ($prop['pid'] > 0)
				{
//					while ($finished != $started)
//					{
						pcntl_wait($status,WNOHANG);
//							echo "child " . $prop['pid'] . " exited" . PHP_EOL;
//							unset ($prop['pid']);
//							$finished++;
//						}
//						else
//						{
//							continue;
//						}
//					}
					echo "output is being saved to " . $prop['tmpFile'] . PHP_EOL;
					//exit;
				}
				else
				{
					// child process
					$sshCmd = $cmd . $prop['address'] . " '" . $this->command . "'";
					$prop['output'] = $this->runCommand($sshCmd);
					$f = fopen($prop['tmpFile'],"w+");
					fwrite($f,$prop['output']);
					fclose($f);
					exit(0);
				}
			}
		}
		else
		{
			// we are doing one host at a time
			foreach ($nodeList as $key => &$prop)
			{
				$prop['tmpFile'] = "/tmp/" . $key . ".tmp";
				$sshCmd = $cmd . $prop['address'] . " '" . $this->command . "'" . PHP_EOL;
				if ($this->logging === true) $this->log->writeLog("Attempting to execute ". $this->command ." on ". $prop['address'],LOG_INFO);
				$prop['output'] = $this->runCommand($sshCmd);
				$this->output[$prop['address']] = $prop['output'];
				$f = fopen($prop['tmpFile'],"w+");
				fwrite($f,$prop['output']);
				fclose($f);
			}
		}
	}

	private function showOutput ()
	{
		foreach ($this->nodeList as $key => &$prop)
		{
			if ($this->parallel === true)
			{
				return;
			}
			$output = array();
			$fileName = "/tmp/" . $key . ".tmp";
			$fp = fopen($fileName,"r");
			if ($fp === false) return $fp;

			while (feof($fp) !== false) {
				$output[] = trim(fgets($fp));
			}
			fclose($fp);
			echo implode("\n",$output);
		}
	}

	// need to rewrite this to return boolean value and to store output in $this->output[$node] instead of returning text
	private function runCommand (string $command = null)
	{
		if ($command === null) return false;
		$pp = popen($command,"r");
		while (!feof($pp))
		{
			$output[] = trim(fgets($pp));
		}
		pclose($pp);
		if ($output !== null)
		{
			return (implode("\n",$output));
		}
		else
		{
			return false;
		}
	}
}
?>
