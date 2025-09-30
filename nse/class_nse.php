<?php // 7.4.19

//require_once "{$_SERVER['ENV_PHPROOT']}/common.php";
//require_once "{$_SERVER['ENV_PHPROOT']}/db/class_db-static.php";
//require_once "{$_SERVER['ENV_PHPROOT']}/logger/class_logger.php";
//require_once "{$_SERVER['ENV_PHPROOT']}/host/class_host.php";
//require_once "{$_SERVER['ENV_PHPROOT']}/sharedEnvironment/class_onHost.php";
//require_once "{$_SERVER['ENV_PHPROOT']}/timer/class_timer.php";


// Error code constant definitions.  These can be reassigned values as long
// as nothing directly calls them by their value.
define("ENV_MISSING_CLUSTERDATA_BIN", 0xA0);
define("ENV_MISSING_BUILDDIR", 0xA1);
define("ENV_MISSING_BINROOT", 0xA2);
define("ENV_MISSING_SBINROOT", 0xA3);
define("ENV_MISSING_LIBROOT", 0xA4);
define("ENV_MISSING_LIBEXECROOT", 0xA5);
define("ENV_MISSING_CONFROOT", 0xA6);

define("ENV_MISSING_SHAREROOT", 0xB0);
define("ENV_MISSING_DOCROOT", 0xB1);
define("ENV_MISSING DVIROOT", 0xB2 );
define("ENV_MISSING_INFOROOT", 0xB3);
define("ENV_MISSING_LOCALEROOT", 0xB4);
define("ENV_MISSING_MANROOT",0xB5);
define("ENV_MISSING_PDFROOT",0xB6);
define("ENV_MISSING_PSROOT",0xB7);

define("ENV_MISSING_CLUSTERDATA", 0xF0);
define("ENV_MISSING_DATA", 0xF1);
define("ENV_MISSING_ENVIRONMENT", 0xF2);
define("ENV_MISSING_LOGROOT", 0xF3);
define("ENV_MISSING_LOGDIR", 0xF4);
define("ENV_MISSING_REPODIR", 0xF5);
define("ENV_MISSING_RUNROOT", 0xF6);
define("ENV_MISSING_RUNDIR", 0xF7);
define("ENV_MISSING_SCRIPTDIR", 0xF8);
define("ENV_MISSING_SETUPDIR", 0xF9);
define("ENV_MISSING_TMPROOT", 0xFA);
define("ENV_MISSING_TMPDIR", 0xFB);
define("ENV_MISSING_USERDIR", 0xFC);
define("ENV_MISSING_TELEMETRY", 0xFD);
define("ENV_MISSING_ROOT", 0xFE);
// not installed error code
define("ENV_NOT_INSTALLED", 0xFF);

/**
 * This class is an interface to the shared network environment and participating host systems
 */
class nse {
	private $lastError;		// if there was an error, this field will contain the hex value
	private $hostId;			// 
	private $hostName;		// 
	private $machineUUID;	// 
	private $myIP;				// 
	private $httpdUser;		// 
	private $httpdGroup;	// 
	private $pgUser;			// 
	private $pgGroup;			// 

	public $installed;		// this really can't be empty/false due to the 1st line of the definition
	public $sharedRoot;		//	/env  						* top level directory
	public $cephRoot;			//	|==> /ceph					* ceph stuff
	public $clusterRoot;	//	|==> /clusterdata			* clusterwide data
	public $clusterBin;		//	| |==> /bin					* bin directory for programs designed for cluster actions
	public $pgRoot;				//	|==> /data					* where pg database related stuff is stored
	public $pgLayout;			//	|	|==> /layout			* layout of db tables
	public $pgHome;				//	|	|==> /pgsql/${ENV_MYIP}	* Where postgresql runs from
	public $pgData;				//	|	|==> /pgsql/${ENV_MYIP}	
	public $envRoot;			//	|==> /environment			* root for shared environmental data
	public $httpRoot;			//	|	|==> /httpd				* where HTTPD runs from
	public $httpDir;			//  |   |==> /httpd/${ENV_MYIP} * host's httpd root
	public $httpSiteRoot; //
	public $htLogs;				// log directory for httpd
	public $htSiteLogs;		// log directory for httpd's VirtualHost files
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
	public $setupBin;
	public $tmpRoot;			//	|==> /tmp					* clusterwide tmp directories
	public $tmpDir;				//  \==> /tmp/${ENV_MYIP}		* host's tmp root

	private $debug;				// debugging output flag
	private $host;				// external host object
	
	// creates/populates an object with the shared environment variables
	public function __construct (Host $host = null, bool $debug = false)
	{	
		if(!$this->initializeObject ()) return;
		$this->debug = $debug;
		// if NSE isnt installed yet, we should skip pulling in the host object. It will
		// just slow things down and doesn't provide anything useful while not fully done
		// installing.
		$host ?? $host = new host($debug,true); // there is no manual scan method for host. rectify.
		if (($GLOBALS['Telemetry'] ?? false ) !== false) {
			$GLOBALS['Telemetry']->objects_instantiated++;
			$GLOBALS['Telemetry']->functions_entered++;
		}
		if (($_ENV["ENV_INSTALLED"] ?? false) === false)
		{
		
			if (($GLOBALS['Telemetry'] ?? false) !== false) {
				$GLOBALS['Telemetry']->functions_left++;
			}
			return ;
		} else {
			$this->host =& $host;
			if (($GLOBALS['Telemetry'] ?? false) !== false) {
				$GLOBALS['Telemetry']->functions_left++;
			}
			return;
		}
	}

	public function __destruct () {
		if (($GLOBALS['Telemetry'] ?? false) !== false) {
			$GLOBALS['Telemetry']->functions_entered++;
			$GLOBALS['Telemetry']->objects_destroyed++;
			$GLOBALS['Telemetry']->functions_left++;
		}
	}

	public function cleanExit (int $code = null) : void
	{
		if (($GLOBALS['Telemetry'] ?? false) !== false) {
			$GLOBALS['Telemetry']->functions_entered++;
			$GLOBALS['Telemetry']->functions_left++;
		}
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
	// this is the exception to the general rule where initializeData doesn't return a status.
	// we can't really initialize/populate this object until we are installed.
	//
	// This DOES NOT validate the environment, only items which are set.  It DOES NOT check
	// for missing environment variables.... need to implement validate_environment function.
	private function initializeObject () : bool
	{
		$errors = array();
		// we are not installed yet, this is going to be a waste of time... return false
		if (strcmp($_ENV["ENV_INSTALLED"],"1") !== 0) {
			return false;
		}
		// convert all of the environment variables to php variables and attempt to set their value (or reset as necessary)
		foreach ($_SERVER as $var => $val)
		{
			switch($var) {
				case 'ENV_ADMINUSER':
					if ((!empty($val)) && (is_string($val)) && (!(posix_getpwnam($val))===false)) { 
						$this->adminUser = strval($val);
					} else {
						echo "Admin User ($val) is invalid." . PHP_EOL;
						$errors[] = ENV_INVALID_ADMINISTRATOR;
					}
					break;
				case 'ENV_BINROOT':
					(is_dir($val)) ? $this->binRoot = $val : $this->binRoot = null;
					if ($this->binRoot === null) $errors[] = ENV_MISSING_BINROOT;
					break;
				case 'ENV_BUILDDIR':
					(is_dir($val)) ? $this->buildDir = $val : $this->buildDir = null;
					if ($this->buildDir === null) $errors[] = ENV_MISSING_BUILDDIR;
					break;
        case 'ENV_CLADMIN':
					if ((!empty($val)) && (is_string($val)) && (!(posix_getpwnam($val))===false)) {
						$this->clAdminUser = strval($val);
					} else {
						echo "Cluster Admin User ($val) is invalid." . PHP_EOL;
						$errors[] = ENV_INVALID_CLUSTER_ADMINISTRATOR;
          }
					break;
				case 'ENV_CLUSTERDATA':
					(is_dir($val)) ? $this->clusterRoot = $val : $this->clusterRoot = null;
					if ($this->clusterRoot === null) $errors[] = ENV_MISSING_CLUSTERDATA;
					break;
        case 'ENV_CLUSTERDATABIN':
					(is_dir($val)) ? $this->clusterBin = $val : $this->clusterBin = null;
					if ($this->clusterBin === null) $errors[] = ENV_MISSING_CLUSTERDATA_BIN;
					break;
				case 'ENV_CONFIG':
					(file_exists($val)) ? $this->configFile = $val : $this->configFile = null;
					break;
				case 'ENV_CONFROOT':
					(is_dir($val)) ? $this->confRoot = $val : $this->confRoot = null;
					break;
				case 'ENV_DATAROOT':
          (is_dir($val)) ? $this->dataRoot = $val : $this->dataRoot = null;
          break;
				case 'ENV_DBGROUP':
					(is_string($val)) ? $this->dbGroup = $val : $this->dbGroup = null;
					break;
				case 'ENV_DBHOST':
					if ((is_string($val)) && (checkdnsrr($val,"ANY"))) {
						$this->dbHost = $val;
					} else $this->dbHost = null;
					break;
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
				case 'ENV_HTCONF':
					(is_dir($val)) ? $this->htConfRoot = $val : $this->htConfRoot = null;
					break;
				case 'ENV_HTLOGS':
					(is_dir($val)) ? $this->htLogs = $val : $this->htLogs = null;
					break;
				case 'ENV_HTTPDIR':
					(is_dir($val)) ? $this->httpDir = $val : $this->httpDir = null;
					break;
				case 'ENV_HTTPROOT':
					(is_dir($val)) ? $this->httpRoot = $val : $this->httpRoot = null;
					break;
				case 'ENV_HTTPSITELOGS':
					(is_dir($val)) ? $this->htSiteLogs = $val : $this->htSiteLogs = null;
					break;
				case 'ENV_HTTPSITEROOT':
					(is_dir($val)) ? $this->httpSiteRoot = $val : $this->httpSiteRoot = null;
					break;
				case 'ENV_OSCOMMON':
					(is_dir($val)) ? $this->osCommon = $val : $this->osCommon = null;
					break;
				case 'ENV_OSROOT':
					(is_dir($val)) ? $this->osRoot = $val : $this->osRoot = null;
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
				case 'ENV_LOGDIR':
					(is_dir($val)) ? $this->logDir = $val : $this->logDir = null;
					break;
				case 'ENV_REPOROOT':
					(is_dir($val)) ? $this->repoRoot = $val : $this->repoRoot = null;
					break;
				case 'ENV_RUNROOT':
					(is_dir($val)) ? $this->runRoot = $val : $this->runRoot = null;
					break;
				case 'ENV_RUNDIR':
					(is_dir($val)) ? $this->runDir = $val : $this->runDir = null;
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
				case 'ENV_TMPROOT':
					(is_dir($val)) ? $this->tmpRoot = $val : $this->tmpRoot = null;
					break;
				case 'ENV_TMPDIR':
					(is_dir($val)) ? $this->tmpDir = $val : $this->tmpDir = null;
					break;
				case 'ENV_MACHINE_UUID':
					(!empty($val)) ? $this->machineUUID = $val : $this->getMachineUUID ();
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
				default:
					break;
			}
		}
		return true;
	}

	private function validate_directories () : bool
	{
		return false;
	}

	private function isOSSupported () : bool
	{
		$this->Telemetry->functions_entered++;
		// if any of the following variables don the value 'unsupported', then
		// this function will return false; These values are filled using the
		// getClientVersion script and should depict a picture of what hardware
		// and operating systems NSE should work with (untested).
		// This is probably an overly simple test for now as we don't currently 
		// have a real matrix for support. Bash and PHP work on almost everything 
		// that Linux does, so that is our (lofty?) goal as well.
		$this->osName ?? 'unsupported';
		$this->osType ?? 'unsupported';
		$this->osArch ?? 'unsupported';
		$this->osDist ?? 'unsupported';
		// if any are true, return false.
		if (
				(strcmp($this->osName,"unsupported") === true) ||
				(strcmp($this->osType,"unsupported") === true) ||
				(strcmp($this->osArch,"unsupported") === true) ||
				(strcmp($this->osDist,"unsupported") === true)
			 )
		{
			$this->Telemetry->functions_left++;
			return false;
		} else {
			$this->Telemetry->functions_left++;
			return true;
		}
	}

	private function validate_environment () : bool
	{
		$this->Telemetry->functions_entered++;
		// to validate the environment, we need to look for the following:
			// valid directory structure
			// valid ENV_OSNAME variable (simple check finished)
			// valid .NSEROOT which is set immutable in $ENV_SHARED
			//  There is no direct way to check extended attributes on system files, so 
			//	I am going to need to use lsattr for the task
			try {
				if (file_exists("/bin/lsattr")) throw new Exception ("/bin/lsattr",true);
				if (file_exists("/usr/bin/lsattr")) throw new Exception ("/usr/bin/lsattr", true);
				if (file_exists("/usr/local/bin/lsattr")) throw new Exception ("/usr/local/bin/lsattr", true);
				throw new Exception ("not found in /bin,/usr/bin or /usr/local/bin",false);
			} catch (Exception $found) {
				if ($found->getCode === true) $lsattr = $found->getMessage();
				else {
					echo $found->getMessage() . PHP_EOL;
					$this->Telemetry->functions_left++;
					return ($found->getCode());
				}
			}
			$cmd = $lsattr . " " . $this->sharedRoot . "/.NSEROOT | cut -c5 -d' '";
			$pp = popen($cmd,"r+");
			$ret = fgetc($pp);
			pclose ($pp);
//			while (!feof($pp)) $ret = trim(fgets($pp));
			if (empty($ret)) {
				$this->lastError = ENV_MISSING_NSEROOT;
				$this->Telemetry->functions_left++;
				return (false);
			} else {
				if ($ret !== 'i') {
					var_export ($ret);
					$this->Telemetry->functions_left++;
					return false;
				}
			}
			// $ENV_SHARED/.NSEROOT is immutable
			if (($this->validate_directories()) === false)
			{
				$this->lastError = ENV_INVALID_DIRECTORY_STRUCTURE;
				$this->Telemetry->functions_left++;
				return false;
			}
			// Directory structure is valid.
			if ($this->isOSSupported() === false) {
				$this->lastError = ENV_OS_IS_UNSUPPORTED;
				$this->Telemetry->functions_left++;				
				return false;
			}
			// Passed the 3 criteria..... return true
			$this->Telemetry->functions_left++;
			return true;
	}

	// test to see if the environment is installed... no output
	public function is_envInstalled() : bool
  {
    try
    {
			if (($_ENV["ENV_INSTALLED"] ?? false) === false) throw new Exception ("Cannot continue as the environment is not installed.",false);
      if (!empty($_ENV["BASH_FUNC_envInstalled%%"]))
      {
        if (($pp=popen("envInstalled ;echo $?","r"))===FALSE) throw new Exception ("unable to find evidence of installed environment",false);
        $ret = trim(fgets($pp));
        pclose($pp);
        if (strcmp($ret,"0")===0) {
          throw new Exception ("Great, now that wasn't so hard was it?", true);
        }
      } else {
				// envInstatlled is not a function
				echo  "${_ENV["BASH_FUNC_envInstalled"]}" . PHP_EOL;
				var_dump($_ENV);
				return false;
			}
			// last qualifying event... this would likely be the first thing manipulated in order to bypass whatever a user is trying to avoid (login tracking?), so we will test it last.
			if (strcmp($_ENV["ENV_INSTALLED"],"1") === 0) throw new Exception ("Thank you for your compliance, hae a nice day.", true);
    } catch (Exception $EnV) {
			echo $EnV->getMessage() . PHP_EOL;
      return $EnV->getCode();
    }
		return true;
  }

	private function getMachineUUID () : bool
	{
		if (is_readable('/etc/machine-id')) {
			$this->machineUUID = file_get_contents('/etc/machine-id');
		} else {
		return false;
		}	
	}

	// show the size in bytes that a directory occupies
	public function du (string $directory)
	{
		$command = "du -xsb $directory";		
		$result = $this->onHost($command);
		return ($result);
	}
	//-- run it through onHost --
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
