<?php // 7.3.0

require_once __DIR__ . "../common.php";
require_once __DIR__ . "../db/class_db.php";
require_once __DIR__ . "../logger/class_logger.php";
require_once __DIR__ . "../host/class_host.php";
require_once __DIR__ . "/class_onHost.php";
require_once __DIR__ . "../timer/class_timer.php";

/**
 * This class is an interface to the shared environment cluster
 */
class SharedEnvironment {
	private $hostId;
	private $hostName;
	private $machineUUID;
	private $myIP;
	private $httpdUser;
	private $httpdGroup;
	private $pgUser;
	private $pgGroup;

	public $installed;			// this really can't be empty/false due to the 1st line of the definition
	public $sharedRoot;			//	/env  						* top level directory
	public $cephRoot;			//	|==> /ceph					* ceph stuff
	public $clusterRoot;		//	|==> /clusterdata			* clusterwide data
	public $pgRoot;				//	|==> /data					* where pg database related stuff is stored
	public $pgLayout;			//	|	|==> /layout			* layout of db tables
	public $pgHome;				//	|	|==> /pgsql/${ENV_MYIP}	* Where postgresql runs from
	public $pgData;				//	|	|==> /pgsql/${ENV_MYIP}	
	public $envRoot;			//	|==> /environment			* root for shared environmental data
	public $sharedConfRoot;		//	|	|==> /etc				* configs that are common for all un*x types
	public $httpRoot;			//	|	|==> /httpd				* where HTTPD runs from
	public $httpDir;			//  |   |==> /httpd/${ENV_MYIP} * host's httpd root
	public $osName;				// ($osType/$osArch/$osDist) a.k.a. 'getClientVersion'
	public $osArch;				// x64	|	|
	public $osDist;				// Fedora27	|
	public $osType;				// Linux|	|	|
////////////////////////////////////////////////|////////\==> /Linux (no assigned var)
	public $osCommon;			//	|	\==> /common			* directory for common $osType information is stored
	public $osRoot;				//	|		\==> Fedora27 ($osType/$osArch/$osDist = $osName))	distribution specific root (for 'configure')
	public $binRoot;			//	|	 		|==> /bin		* binaries
	public $confRoot;			//	|	 		|==> /etc		* configs
	public $hostsRoot;		//	|	 		|==> /hosts		* dynamic host data
	public $libRoot;			//	|	 		|==> /lib		* library files
	public $libexecRoot;	//	|	 		|==> /libexec	* modules
	public $shareRoot;		//	|	 		|==> /share		* share directory (not shared data)
	public $infoRoot;			//	|	 		|	|==> /info	* info files
	public $manRoot;			//	|	 		|	\==> /man	* man files
	public $sbinRoot;			//	|	 		\==> /sbin		* privileged binaries
	public $logRoot;			//	|==> /log					* clusterwide log root
	public $logDir;				//  |==> /log/${ENV_MYIP}/		* host's log root
	public $repoRoot;			//	|==> /repo					* repository for packages/revision control
	public $runRoot;			//	|==> /run					* clusterwide run root
	public $runDir;				//  |==> /run/${ENV_MYIP}/		* host's run root
	public $scriptRoot;		//	|==> /scripts				* common clusterwide scripts directory
	public $phpRoot;			//	|	 \==> /lib				* where all the php libraries live
	public $setupRoot;		//	|==> /setup					* common clusterwide setup scripts/info
	public $tmpRoot;			//	|==> /tmp					* clusterwide tmp directories
	public $tmpDir;				//  |==> /tmp/${ENV_MYIP}		* host's tmp root
	public $userRoot;			//	|==> /users				* the home directory root for users using the system
	public $userDir;			//	| \==> /user_name  * the individual user's root for per/os home directory
  //										//	|   |==> /common		* this is where common user files are stored (os/arch independent .. like .basrc, .ssh directory...
	//										//  |   \==> /OS Name	  * this is where the OS names are found Linux, Windows, HPUX, AIX, etc.
	//										//	|     \==> /OS Architecture  * This is where the architecture type is found x86, x64, ppc, mips, mips64, arm, arm64, s390, s390x, etc...
	//										//	|				\==> / OS Distribution * this is where the distribution-specific files are. e.g. Fedora39, Fedora40, Ubuntu_2204, Ubuntu_2404, etc...
	public $visionRoot;		//  |==>/vision/${ENV_MYIP}	* host's EnVision root directory
	public $visionDir;		//	\==>/vision/${ENV_MYIP}/${LOGNAME}  * The data directory used for the current user.

	private $debug;				// debugging output flag
	private $host;				// external host object
	
	// creates/populates an object with the shared environment variables
	public function __construct (Host $host = null, bool $debug = false) {	
		$this->initializeObject ();
		$this->debug = $debug;
		if (($host !== null) && (is_a($host,"Host")))
		{
			$this->host =& $host;
		}
		else
		{
			$this->host = new Host ($this->debug, true);
		}
	}

	public function cleanExit (int $code = null) : void
	{
		if ($code === null) return;
		if ($this->hasTempNodeList === true)
		{
			foreach ($this->rmTempFiles as $tempFile)
			{
				if (file_exists($tempFile))
				{
					unlink($tempFile);
				}
			}
		}
		exit ($code);
	}

	// cycle through all of the environment variables and populate the object
	private function initializeObject () : void
	{
		// convert all of the environment variables to php variables and attempt to set their value (or reset as necessary)
		foreach ($_SERVER as $var => $val)
		{
			switch($var) {
				case 'ENV_INSTALLED':
					intval($val) == 1 ? $this->installed = true : $this->installed = false;
					break;
				case 'ENV_SHARED':
					(is_dir($val)) ? $this->sharedRoot = $val : $this->sharedRoot = null;
					break;
				case 'ENV_CEPHROOT':
					(is_dir($val)) ? $this->cephRoot = $val : $this->cephRoot = null;
					break;
				case 'ENV_CLUSTERDATA':
					(is_dir($val)) ? $this->clusterRoot = $val : $this->clusterRoot = null;
					break;
				case 'ENV_PGROOT':
					(is_dir($val)) ? $this->pgRoot = $val : $this->pgRoot = null;
					break;
				case 'ENV_PGDATA':
					(is_dir($val)) ? $this->pgData = $val : $this->pgData = null;
					break;
				case 'ENV_PGHOME':
					(is_dir($val)) ? $this->pgHome = $val : $this->pgHome = null;
					break;
				case 'ENV_PGLAYOUT':
					(is_dir($val)) ? $this->pgLayout = $val : $this->pgLayout = null;
					break;
				case 'ENV_ROOT':
					(is_dir($val)) ? $this->envRoot = $val : $this->envRoot = null;
					break;
				case 'ENV_SHARED_CONFROOT':
					(is_dir($val)) ? $this->sharedConfRoot = $val : $this->sharedConfRoot = null;
					break;
				case 'ENV_HTTP_ROOT':
					(is_dir($val)) ? $this->httpRoot = $val : $this->httpRoot = null;
					break;
				case 'ENV_OSCOMMON':
					(is_dir($val)) ? $this->osCommon = $val : $this->osCommon = null;
					break;
				case 'ENV_OSROOT':
					(is_dir($val)) ? $this->osRoot = $val : $this->osRoot = null;
					break;
				case 'ENV_BINROOT':
					(is_dir($val)) ? $this->binRoot = $val : $this->binRoot = null;
					break;
				case 'ENV_CONFROOT':
					(is_dir($val)) ? $this->confRoot = $val : $this->confRoot = null;
					break;
				case 'ENV_HOSTSROOT':
					(is_dir($val)) ? $this->hostsRoot = $val : $this->hostsRoot = null;
					break;
				case 'ENV_LIBROOT':
					(is_dir($val)) ? $this->libRoot = $val : $this->libRoot = null;
					break;
				case 'ENV_LIBEXECROOT':
					(is_dir($val)) ? $this->libexecRoot = $val : $this->libexecRoot = null;
					break;
				case 'ENV_SBINROOT':
					(is_dir($val)) ? $this->sbinRoot = $val : $this->sbinRoot = null;
					break;
				case 'ENV_SHAREROOT':
					(is_dir($val)) ? $this->shareRoot = $val : $this->shareRoot = null;
					break;
				case 'ENV_INFOROOT':
					(is_dir($val)) ? $this->infoRoot = $val : $this->infoRoot = null;
					break;
				case 'ENV_MANROOT':
					(is_dir($val)) ? $this->manRoot = $val : $this->manRoot = null;
					break;
				case 'ENV_LOGROOT':
					(is_dir($val)) ? $this->logRoot = $val : $this->logRoot = null;
					break;
				case 'ENV_REPOROOT':
					(is_dir($val)) ? $this->repoRoot = $val : $this->repoRoot = null;
					break;
				case 'ENV_RUNROOT':
					(is_dir($val)) ? $this->runRoot = $val : $this->runRoot = null;
					break;
				case 'ENV_SETUPROOT':
					(is_dir($val)) ? $this->setupRoot = $val : $this->setupRoot = null;
					break;
				case 'ENV_SCRIPTROOT':
					(is_dir($val)) ? $this->scriptRoot = $val : $this->scriptRoot = null;
					break;
				case 'ENV_PHPROOT':
					(is_dir($val)) ? $this->phpRoot = $val : $this->phpRoot = null;
					break;
				case 'ENV_TEMPROOT':
					(is_dir($val)) ? $this->tmpRoot = $val : $this->tmpRoot = null;
					break;
				case 'ENV_MACHINE_UUID':
					(!empty($val)) ? $this->machineUUID = $val : $this->machineUUID = null;
					break;
				case 'ENV_MYIP':
					(!empty($val)) ? $this->myIP = $val : $this->myIP = null;
					break;
				case 'ENV_PGUINF':
					(preg_match('/:/',$val)) ? list($this->pgUser,$this->pgGroup) = explode(":", $val) : $this->pgUser = null && $this->pgGroup = null;
					break;
				case 'ENV_HTTP_USER':
					(preg_match('/:/',$val)) ? list($this->httpdUser,$this->httpdGroup) = explode(":", $val) : $this->httpdUser = null && $this->httpdGroup = null;
					break;
				case 'ENV_OSNAME':
					(!empty($val)) ? $this->osName = $val : $this->osName = null;
					break;
				case 'ENV_OSARCH':
					(!empty($val)) ? $this->osArch = $val : $this->osArch = null;
					break;
				case 'ENV_OSDIST':
					(!empty($val)) ? $this->osDist = $val : $this->osDist = null;
					break;
				case 'ENV_OSTYPE':
					(!empty($val)) ? $this->osType = $val : $this->osType = null;
					break;
				case 'ENV_VISIONROOT':
					(!empty($val)) ? $this->visionRoot = $val : $this->visionRoot = null;
					break;
				case 'ENV_VISIONDIR':
					(!empty($val)) ? $this->visionDir = $val : $this->visionDir = null;
					break;
				default:
					break;
			}
		}
	}
	
	// show the size in bytes that a directory occupies
	public function du (string $directory)
	{
		$command = "du -xsb $directory";		
		$result = $this->onHost($command);
		return ($result);
	}

/*
 *  This can be run through onHost
 */
/*	public function duDirTree (string $directory) 
	{
		$command = "/bin/du -xsb";
		// we don't have a node list
		if (empty($this->nodeList))
		{
			// a node list was not specified
			if ($nodes === null)
			{
				// directory exists on this host
				if (is_dir($directory))
				{
					// get the size of the tree
					$pp = popen("$command $directory","r");
					$dirSize = explode(" ",trim(fgets($pp)));
					pclose($pp);
					return ($dirSize);
				}
				else
				{
					return (false);
				}
			}
			else
			{
				if (file_exists($nodes))
				{
					$this->setNodeList($nodes);
				}
				else
				{
					// let's make a temp node list
					$this->mkTempNodeList($nodes);
				}
			}
			$result = $this->onHost("$command $directory");
		}
		else
		{
			$result = $this->onHost("$command $directory");
		}
		return ($result);
	}
*/	
	public function compressSystemLogs (string $directory = null, string $nodes = null)
	{
		// if $directory is unspecified, set it to /var/log
		if (($directory === null) && (is_dir("/var/log")))
		{
			$directory = "/var/log";
		}
		// create shell fragment to compress previously rotated log files that are not already compressed with xz, run it in a subshell to avoid modifying actual environment
		$command = "(cd $directory ; export XZ_OPTS=-T0 ; for FILE in $(ls -1 *-* | grep -v \.xz$); do echo $FILE && xz $FILE; done)";
		if ($nodes === null)
		{
			// run the command and store results in an array split by \n
			$pp = popen ("$command","r");
			while (!feof($pp))
			{
				$result[] = fgets($pp);
			}
			pclose($pp);
		} 
	}
}
?>
