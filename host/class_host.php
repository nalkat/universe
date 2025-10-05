<?php // 7.4.24
/**
 * This is the interface to the hosts database table. It is responsible for
 * discovering information about hosts and updating the associated table in
 * the database.
 *
 * @author Scott W. Griffith <whytigr@gmail.com>
 * @version 1.0
 * @package TAS
 * @subpackage Host
 */
// REQ:pcre
// REQ@Docker
// REQ@Utility

class Host
{

	private $id;

	private Telemetry $Telemetry;

	// hal-device /org/freedesktop/Hal/devices/computer 
	private string $manufacturer;		// dmidecode -s system-manufacturer
	private string $modelNumber;		// dmidecode -s system-product-name
	private string $serialNumber;		// dmidecode -s system-serial-number
	private string $biosVersion;		// dmidecode -s system-version
	private string $uuid;			// dmidecode -s system-uuid ##/etc/machine_uuid

//	private $firmwareVendor;	// hal.firmware.vendor
//	private $firmwareVersion;	// hal.firmware.version
//	private $firmwareDate;		// hal.firmware.release_date

	// in bytes
	private int $memoryTotal;		// proc/meminfo | grep MemTotal
	private int $memoryFree;		// proc/meminfo | grep MemFree
	private int $swapTotal;		// proc/meminfo | grep SwapTotal
	private int $swapFree;		// proc/meminfo | grep SwapFree
	private int $storageTotal;
	private int $storageFree;

	// hal-device /org/freedesktop/Hal/devices/acpi_CPU0
	private string $cpuMfgr;			// dmidecode -s processor-manufacturer | uniq
	private string $cpuType;			// dmidecode -s processor-family | uniq
	private string $cpuVersion;			// dmidecode -s processor-version | uniq
	private int $cpuFrequency;			// dmidecode -s processor-frequency | uniq
	private string $cpuArch;
	private int $numCPUs;			// lscpu | grep CPU(s)

	private array $interfaces = array();
	private array $hostNames = array();
	private array $ip4Addresses = array();
	private array $ip4NetMasks = array();
	private array $ip6Addresses = array();
	private array $ip6NetMasks = array ();
	private array $macAddresses = array();
	private string $osType;
	private string $osName;
	private string $osVersion;
	private string $osRevision;

	private string $buildingLocation;
	private string $labLocation;
	private string $gridLocation;
	private int $contactID;
	private int $assignedUserID;
	private string $lastServiceDate;
	private string $comments;

	// Virtualization
	private bool $isVirtualized;
	private bool $virtualizationType;

	// Docker
	private bool $isDockerHost;

	// Containers
	private bool $isContainer;
	private string $containerType;
	private int $containerHost;
	private string $containerApplication;

	private string $lastScanTime;			// last date scanned

	private $docker;

	private bool $debug;
	private string $lastError;
	public $dbHost;
	
	public function __construct (bool $debug = false, bool $scan = true, ?Telemetry $Telemetry = null)
	{
		date_default_timezone_set('America/Los_Angeles');
		$this->debug = $debug;
		if ($this->debug === true)
		{
			Utility::ncWrite("Enabling verbose debugging in class Host", LOG_INIT, L_CONSOLE);
			Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		}
		$this->initializeObject();
		if ((empty($Telemetry)) || (!is_a($Telemetry,"Telemetry"))) {
			if ((isset($GLOBALS['Telemetry'])) && (is_a($GLOBALS['Telemetry'],"Telemetry"))) {
				$this->Telemetry =& $GLOBALS['Telemetry'];
			} else {
				$this->Telemetry = new Telemetry($_ENV['UNIVERSE_TELEMETRY_DIR']."/objects/cereal");
				$this->Telemetry->objects_instantiated++;
				$this->Telemetry->objects_unserialized++;
			}
		} else {
			$this->Telemetry =& $Telemetry;
			$this->Telemetry->objects_instantiated++;
		}
		$this->Telemetry->objects_instantiated++;
		$this->Telemetry->log_lines_written+=2;
		$this->Telemetry->log_lines_in_color+=2;
		$this->Telemetry->log_debug_lines_logged++;
		$this->Telemetry->log_init_lines++;
		$this->Telemetry->log_bytes_written+=strlen("Enabling verbose debugging in class Host");
		$this->Telemetry->log_bytes_written+=strlen(__METHOD__ . " Entering function");
		$this->Telemetry->files_opened+=2;
		$this->Telemetry->files_created++;
		$this->Telemetry->functions_entered+=3;
		if ($scan === true)
		{
			$this->loadModules ();	// load external class definitions
			Utility::ncWrite("Performing initial host scan and updating the database", LOG_INIT, L_CONSOLE);
			$this->Telemetry->log_lines_written++;
			$this->Telemetry->log_lines_in_color++;
			$this->gatherHostInfo ();
			$this->dbUpdate ();
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	// if we really want to exit, do this instead
	private function cleanExit (int $code = null) : int
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		unset ($this->id);
		unset ($this->manufacturer);
		unset ($this->modelNumber);
		unset ($this->serialNumber);
		unset ($this->biosVersion);
		unset ($this->uuid);
		unset ($this->memoryTotal);
		unset ($this->memoryFree);
		unset ($this->swapTotal);
		unset ($this->swapFree);
		unset ($this->storageTotal);
		unset ($this->storageFree);
		unset ($this->cpuMfgr);
		unset ($this->cpuType);
		unset ($this->cpuArch);
		unset ($this->numCPUs);
		unset ($this->interfaces);
		unset ($this->hostNames);
		unset ($this->ip4Addresses);
		unset ($this->ip4NetMasks);
		unset ($this->ip6Addresses);
		unset ($this->ip6NetMasks);
		unset ($this->macAddresses);
		unset ($this->osType);
		unset ($this->osName);
		unset ($this->osVersion);
		unset ($this->osRevision);
		unset ($this->buildingLocation);
		unset ($this->labLocation);
		unset ($this->gridLocation);
		unset ($this->contactID);
		unset ($this->assignedUserID);
		unset ($this->lastServiceDate);
		unset ($this->comments);
		unset ($this->isVirtualized);
		unset ($this->virtualizationType);
		unset ($this->isDockerHost);
		unset ($this->isContainer);
		unset ($this->containerType);
		unset ($this->containerHost);
		unset ($this->containerApplication);
		unset ($this->lastScanTime);
		unset ($this->lastError);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Turning off debugging and leaving function", LOG_DEBUG_INOUT, L_DEBUG);
		unset ($this->debug);
		return $code;
	}

	private function initializeObject () : void
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function and setting default values", LOG_DEBUG_INOUT, L_DEBUG);
		$this->Telemetry = new Telemetry();
		$this->dbHost = null; // object pointer?
		$this->id = 0;
		$this->manufacturer = "";
		$this->modelNumber = "";
		$this->serialNumber = "";
		$this->biosVersion = "";
		$this->uuid = "";
		$this->memoryTotal = 0;
		$this->memoryFree = 0;
		$this->swapTotal = 0;
		$this->swapFree = 0;
		$this->storageTotal = 0;
		$this->storageFree = 0;
		$this->cpuType = "";
		$this->cpuArch = "";
		$this->cpuVersion = "";
		$this->cpuFrequency = 0;
		$this->numCPUs = 0;
		$this->interfaces = array();
		$this->hostNames = array();
		$this->ip4Addresses = array();
		$this->ip4NetMasks = array();
		$this->ip6Addresses = array();
		$this->ip6NetMasks = array();
		$this->macAddresses = array();
		$this->osType = "";
		$this->osName = "";
		$this->osVersion = "";
		$this->osRevision = "";
		$this->buildingLocation = "";
		$this->labLocation = "";
		$this->gridLocation = "";
		$this->contactID = 0;
		$this->assignedUserID = 0;
		$this->lastServiceDate = '2001-01-01 01:01:01';
		$this->comments = "";
		$this->lastScanTime = '2001-01-01 01:01:01';			// last date scanned
		$this->isDockerHost = false;
		$this->isVirtualized = false;
		$this->virtualizationType = "";
		$this->isContainer = false;
		$this->containerApplication = "";
		$this->docker = null;
		$this->lastError = "";
		$this->packages = array(); // item_id, name, epoch, version, filename, release, arch // This is for the /var/lib/dnf/history.sqlite database
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function loadModules () : void
	{
		//if (empty($phproot)) die ("it's just fucked");
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
///// this really needs to be rewritten ////	
		if (!class_exists ("Docker", false))
		{
//			if (file_exists($phproot . "class_docker.php"))
//				{
	//			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Including file: " . $phproot . "/docker/class_docker.php", LOG_DEBUG_HILITE, L_DEBUG);
				require_once "class_docker.php";
///			}
		}
/////////////////////////////////////////////
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function gatherHostInfo () : void
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		// determine if we are a docker host
		Utility::ncWrite("Gathering information about the host", LOG_INFO, L_CONSOLE);

///////// this really needs to be rewritten
//		if (class_exists("Docker",false))
//		{
//			if (($this->docker === null) || (!is_a($this->docker,"Docker")))
//			{
//				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " No current docker object found, instantiating a new one", LOG_DEBUG_HILITE, L_DEBUG);
//				$this->docker = new Docker ();
//			}

		$this->docker = new Docker ();
		$this->isDockerHost = $this->docker->getHasDocker();
//		}
////////////////////////
		$this->setContainer ();
		$this->setUUID ();
		$this->setOSType ();
		$this->determineOS ();
		$this->determineCPUArch();
//		if (!$this->determineCPUArch ()) {
//			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Unable to determine CPU architecture, leaving function", LOG_DEBUG_WARN, L_DEBUG);
//			Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " FATAL: Unable to determine CPU architecture", LOG_ERR, L_CONSOLE|L_ERROR);
//			$this->cleanExit(1);			
////			return (false);
//		}
		$this->determineCPUManufacturer ();
		$this->determineCPUType ();
		$this->determineCPUVersion();
		$this->determineCPUFrequency();
		$this->determineNumberCPUs ();
		$this->determineManufacturer ();
		$this->determineModelNumber ();
		$this->determineSerialNumber ();
		$this->determineMemoryTotal ();
		$this->determineMemoryFree ();
		$this->determineSwapTotal ();
		$this->determineSwapFree ();
		$this->findInterfaces ();
		$this->findMACs ();
		$this->findIP4s ();
		$this->findHostNames ();
		microtime (true) && $this->setLastScanTime(date("Y-m-d H:i:s",time()));
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	// this is a temporary function -- this will move to "class Docker" soon.
	private function setRunningContainers () : void
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ($this->isDockerHost === true)
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Obtaining list of running containers", LOG_DEBUG_HILITE, L_DEBUG);
			$cmd = "docker ps --format '{{.Names}}'";
//			$containers = preg_split("/\n/",rtrim(`docker ps --format {{.Names}}`));
			$pp = popen($cmd,"r");
			while (!feof($pp))
			{
				$containers[] = trim(fgets($pp));
			}
			pclose($pp);
			if (!empty($containers))
			{
				$this->runningContainers = $containers;
			}
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function setContainer () : void
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
                $this->isContainer = false;
                if (isset($_SERVER["CONTAINER_APP"]))
                {
                        $this->containerApplication = trim($_SERVER["CONTAINER_APP"]);
                        $this->isContainer = ($this->containerApplication !== '');
                }
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
		return;
	}

	private function setUUID () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (file_exists("/etc/machine_uuid"))
		{
			$this->uuid = rtrim(file_get_contents("/etc/machine_uuid"));
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " /etc/machine_uuid doesn't exist, resorting to uuidgen -t", LOG_DEBUG_WARN, L_DEBUG);
		$cmd = "uuidgen -t";
		$pp = popen($cmd,"r");
		$this->uuid = strtoupper(trim(fgets($pp)));
		pclose($pp);
		if (!empty($this->uuid))
		{
			if ($this->debug === true)
			{
				Utility::ncWrite(__METHOD__ . " set uuid to " . $this->uuid, LOG_DEBUG_VAR, L_DEBUG);
				Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			}
			return true;
		}
		else
		{
			$this->uuid = "12345678-1234-1234-1234-12345678";
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " failed to obtain unique id, leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
		// this should not execute.
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function determineOS ()
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
			if (file_exists ("/etc/os-release"))
			{
				foreach (preg_split("/\n/", file_get_contents("/etc/os-release")) as $idx => $property)
				{	
					$osParts = preg_split ("/=/", $property);
					switch (strtolower($osParts[0]))
					{
						case 'name':
							$this->setOSName($osParts[1]);
							break;
						case 'version':
							break;
						case 'id':
							break;
						case 'version_id':
							$this->setOSVersion($osParts[1]);
							$revision = preg_split("/./",$osParts[1]);
							if (!empty($revision[1])) {
								$this->setOSRevision($revision[1]);
							}
							break;
						case 'version_codename':
							break;
						case 'platform_id':
							break;
						case 'pretty_name':
							break;
						case 'ansi_color':
							break;
						case 'logo':
							break;
						case 'cpe_name':
							break;
						case 'default_hostname':
							break;
						case 'home_url':
							break;
						case 'documentation_url':
							break;
						case 'support_url':
							break;
						case 'bug_report_url':
							break;
						case 'redhat_bugzilla_product':
							break;
						case 'redhat_bugzilla_product_version':
							break;
						case 'redhat_support_product':
							break;
						case 'redhat_support_product_version':
							break;
						case 'support_end':
							break;
						case 'variant':
							break;
						case 'variant_id':
							break;
						default:
							break;
					}
				}
			}
			// SuSE SLES
			if (file_exists ("/etc/SuSE-release"))
			{
				// read in the file and break it into an array from \n's
				$osParts = preg_split ("/\n/", file_get_contents ("/etc/SuSE-release"));
				// get the name string from the first line and further break it by spaces.
				$osNameParts = preg_split ("/ /", $osParts[0]);
				// the first element is our OS Name
				$this->setOSName ($osNameParts[0]);
				// the 6th element is the archtype, remove "(" and ")" and set it as our arch.
				$osNameParts[5] = preg_replace ("/\(/", "", $osNameParts[5]);
				$osNameParts[5] = preg_replace ("/\)/", "", $osNameParts[5]);
				$this->setCPUArch ($osNameParts[5]);
				// the version number is the second line of the file, break it up by spaces and take the 3rd element.
				$osVersionParts = preg_split ( "/ /", $osParts[1]);
				$this->setOSVersion ($osVersionParts[2]);
				// the revision number is the third line of the file, break it up by spaces and take the 3rd element.
				$osRevisionParts = preg_split ( "/ /", $osParts[2]);
				$this->setOSRevision ($osRevisionParts[2]);
			}
			// Ubuntu
			if (file_exists ("/etc/lsb-release"))
			{
				if ((preg_match ("/Ubuntu/", file_get_contents ("/etc/lsb-release"))) > 0)
				{
					// read in the file and break it into an array from \n's
					$osParts = preg_split ("/\n/", file_get_contents ("/etc/lsb-release"));
					// get the name string from the first line and further break it by '=' characters.
					$osNameParts = preg_split ("/=/", $osParts[0]);
					// the second element is the name.
					$this->setOSName ($osNameParts[1]);
					// get the version and release number from the second line of the file and split it by '=' characters.
					$osVersion = preg_split ("/=/", $osParts[1]);
					// split it again to get the version and revision elements using the '.' character
					$osVersionParts = preg_split ("/\./", $osVersion[1]);
					// set the osVersion from the first element.
					$this->setOSVersion ("".$osVersionParts[0]);
					// set the osRevision from the second element.
					$this->setOSRevision ("".$osVersionParts[1]);
				}
			}
			// Red Hat 
			if (file_exists ("/etc/redhat-release"))
			{
				$osParts = preg_split ("/ /", file_get_contents ("/etc/redhat-release"));
				switch ($osParts[0])
				{
					case 'CentOS':
						$ver = NULL;
						$rev = NULL;
						// don't trust that osParts[0] will continue to hold "CentOS".. send hardcode instead to be safe.
						$this->setOSName ("CentOS");
						// take the 3rd token and split it by the period to get major/minor revision info
						list ($ver,$rev) = preg_split ("/\./", $osParts[2]);
						if ( ((is_string($ver)) || (is_numeric($ver))) && (!empty($ver)) )
						{
							if (!($this->setOSVersion ("$ver")))
							{
								if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
								return false;
							}
						}
						else
						{
							if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
							return false;
						}
						if (!empty($rev))
						{
							if (!($this->setOSRevision ("$rev")))
							{
								if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
								return false;
							}
						}
						else
						{
							if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
							return false;
						}
						unset ($ver);
						unset ($rev);
						if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
						return true;
					case 'Fedora':
						// same as above.. don't trust the variable to be "Fedora", send hard-code instead to be safe.
						$this->setOSName ("Fedora");
						// Fedora uses an integer for it's revision instead of major/minor like the others.
						if (!($this->setOSVersion("$osParts[2]")))
						{
							if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
							return false;
						}
						// Set the OS Revision to NULL because setOSRevision() will not accept NULL and we don't need it.
						$this->osRevision = "";
						if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
						return true;
				}
			}
			// we arent rhel/suse or debian based...
			// i don't have a case for this at the moment
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Unable to get OS information as we are not linux, leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function setVirtualized (string $virtType) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		$this->virtualizationType = $virtType;
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
		return true;
	}

	private function determineVirtualized () : bool
	{
		$isVirt = false;
		if ((isset($this->Telemetry)) && (is_a($this->Telemetry,"Telemetry"))) $this->Telemetry->functions_entered++;
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (($this->osType == "Linux") && (file_exists("/sbin/virt-what")))
		{
			$pp = popen("virt-what","r");
			while (!feof($pp)) $virt = trim(fgets($pp));
//			$virt = rtrim (`virt-what`);
			
      $pstatus = pclose($pp);
			if ($pstatus != -1) {
				if (pcntl_wifexited($pstatus)) {
					$ec = pcntl_wexitstatus($pstatus);
				} else {
					$ec = $pstatus;
				}
			}
			
		}
		if (!empty($virt))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning the result of setVirtualized($virt) and leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			$this->isVirtualized = true;
			$this->setVirtualized($virt);
			return true;
		}
		else
		{
			$this->virtualizationType = null;
            $this->isVirtualized = boolval($isVirt);
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Potential error: Leaving function", LOG_DEBUG_WARN, L_DEBUG);
		return false;
	}

	private function determineCPUArch () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ($this->osType == "Linux")
		{
			$pp = popen("uname -m","r");
			$arch = trim(fgets($pp));
			pclose($pp);
			if (!empty($arch))
			{
				$this->setCPUArch ($arch);
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return false;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}
	private function determineNumberCPUs () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
//			$cmd = "lscpu | grep '^CPU(s):' | sed 's/  //g' | cut -f2 -d ':'";
			$cmd = "cat /proc/cpuinfo | grep ^processor | wc -l";
			$pp = popen($cmd,"r");
			$this->setNumberCPUs (intval(trim(fgets($pp))));
			fclose($pp);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			// we are not linux, i don't have a case for this
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function determineManufacturer () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
			$cmd = "dmidecode -s system-manufacturer";
			$pp = popen($cmd,"r");
			$mfgr = trim(fgets($pp));
			pclose($pp);
			if (!$this->setManufacturer ($mfgr))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Failed to set the manufacturer for this host, leaving function", LOG_DEBUG_WARN, L_DEBUG);
				return false;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function determineModelNumber () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
			$cmd = "dmidecode -s system-product-name";
			$pp = popen($cmd,"r");
			$model = trim(fgets($pp));
			pclose($pp);
			if (!$this->setModelNumber ($model))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Failed to set the model number for this host, leaving function", LOG_DEBUG_WARN, L_DEBUG);
				return false;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function determineSerialNumber () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
			$cmd = "dmidecode -s system-serial-number";
			$pp = popen($cmd,"r");
			$serial = trim(fgets($pp));
			pclose($pp);
			if (!$this->setSerialNumber ($serial))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Failed to set the serial number for this host, leaving function", LOG_DEBUG_WARN, L_DEBUG);
				return false;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function determineCPUManufacturer () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
			$cmd = "dmidecode -s processor-manufacturer | uniq";
			$pp = popen($cmd,"r");
			$mfgr = trim(fgets($pp));
			pclose($pp);
			if (!$this->setCPUMfgr ($mfgr))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Failed to determine the CPU manufacturer for this host, leaving function", LOG_DEBUG_WARN, L_DEBUG);
				return false;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function determineCPUType () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{ 
			$cmd = "dmidecode -s processor-family | uniq";
			$pp = popen($cmd,"r");
			$type = trim(fgets($pp));
			pclose($pp);
			if (!$this->setCPUType ($type))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Failed to determine the cpu type for this host, leaving function", LOG_DEBUG_WARN, L_DEBUG);
				return false;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function determineCPUVersion () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
			$cmd = "dmidecode -s processor-version | uniq";
			$pp = popen($cmd,"r");
			$version = trim(fgets($pp));
			pclose($pp);
			if (!$this->setCPUVersion($version))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " failed to determime the cpu version for this host", LOG_DEBUG_INOUT, L_DEBUG);
				return false;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	// this will only take the first value for now... going to have to add multi-socket support later
	private function determineCPUFrequency () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
			$cmd = "echo $(echo $(dmidecode -s processor-frequency) | uniq) | cut -f1 -d' '";
			$pp = popen($cmd,"r");
			$freq = trim(fgets($pp));
			pclose($pp);
			if (!$this->setCPUFrequency($freq))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Failed to determine the cpu frequency, leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return false;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function determineMemoryTotal () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
//			$cmd = "cat /proc/meminfo | grep MemTotal | sed 's/  / /g' | cut -f 2 -d ':' | sed 's/^ *//g' | cut -f1 -d ' '";
			$cmd = "cat /proc/meminfo | grep ^MemTotal | sed 's/[^0-9]//g'"; // this number is in kB
			//$cmd = "cat /proc/meminfo | grep ^MemTotal | sed ''s@.* \(\[0-9\]*\) .*@\1@g''"; // this number is in kB
			$pp = popen($cmd,"r");
			//while (!feof($pp)) 
			$total = trim(fgets($pp));
			pclose($pp);
			if (!empty($total) && (is_numeric($total) || is_int($total))) {
				$total = intval($total * 1024); // now it is number of bytes
			} else {
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Failed to determine total memory, leaving function", LOG_DEBUG_WARN, L_DEBUG);
				Throw new Exception ("cat /proc/meminfo | grep ^MemTotal | sed 's/[^0-9]//g' did not produce a valid result", 1);
				return false;
			}
			if (!$this->setMemoryTotal ($total))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Failed to determine total memory, leaving function", LOG_DEBUG_WARN, L_DEBUG);
				return false;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}
	
	private function determineMemoryFree () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
			$cmd = "cat /proc/meminfo | grep ^MemFree | sed 's/[^0-9]//g'"; // this number is in kB
			$pp = popen($cmd,"r");
			$free = intval(trim(fgets($pp)) * 1024); // now it is number of bytes
			pclose($pp);
			if (!$this->setMemoryFree ($free))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Failed to determine free memory, leaving function", LOG_DEBUG_WARN, L_DEBUG);
				return false;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function determineSwapTotal () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
			$cmd = "cat /proc/meminfo | grep ^SwapTotal | sed 's/[^0-9]//g'"; // this number is in kB
			$pp = popen($cmd,"r");
			$total = intval(trim(fgets($pp)) * 1024); // now it is in bytes
			pclose($pp);
			if (!$this->setSwapTotal ($total))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Failed to determine total swap memory, leaving function", LOG_DEBUG_WARN, L_DEBUG);
				return false;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function determineSwapFree () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (PHP_OS == "Linux")
		{
			$cmd = "cat /proc/meminfo | grep ^SwapFree | sed 's/[^0-9]//g'"; // this number is in kB
			$pp = popen($cmd,"r");
			$free = intval(trim(fgets($pp)) * 1024); // now it is in bytes
			pclose($pp);	
			if (!$this->setSwapFree ($free))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Failed to determine free swap memory, leaving function", LOG_DEBUG_WARN, L_DEBUG);
				return false;
			}
			else
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
				return true;
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " we are not Linux, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	/* somewhere, this is being executed twice.
	 * It may be better to have an interfaces array with the following properties:
	 *      $interfaces[$mac_address] = array("name" => $ifaceName, "hostname"[] => $hostmame, "ipv4_Address"[] => $ipv4_address, "ipv6_Address"[] => $ipv6_address);
	 * or maybe even it's own class
	 *      class NetworkInterface {
	 *         public $name;
	 *         public $mac;
	 *         public $ipv4_address;
	 *         public $ipv6_address;
	 *         public isBridgeMaster;
	 *         public isBridgeMember;
	 *         public isBonded;
	 *         public isBondMaster;
	 *         public isBondSlave;
	 *         public isPhysical;
	 *         public isVirtual;
	 *	   ...
	 * array ([$mac_address] => array ("inter
	 * array ($interface_name => array ("mac_addr" => mac, "hostname" => $hostname, "ip_address" => array(
	 */
//	private function findInterfaces ()
//	{
//		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
//		if ($this->osType == "Linux")
//		{
//			$interfaces = array();
//			$macs = array();
			// get a list of the interfaces which have a MAC address associated with them.
			/// I bet this failed becasuse it should be a file, not just a command.
//			$cmd = "echo $(Z=0; Y= ; for X in $(ip link | grep -B1 ether | sed -e 's/^[0-9]*: //g' -e 's/: <.*$//g' -e 's/@.*$//g' -e 's/^ *link\/ether //g' -e 's/ brd.*$//g' -e 'y/:/-/' -e '/^--/d'); do (( Z = 0 )) && { echo -n \"\$X \"; Z=1; } || { echo \"\$X\"; Z=0; }; done)";
//			$cmd = "Z=0; Y= ; for X in $(ip link | grep -B1 ether | sed -e 's/^[0-9]*: //g' -e 's/: <.*$//g' -e 's/@.*$//g' -e 's/^ *link\/ether //g' -e 's/ brd.*$//g' -e 'y/:/-/' -e '/^--/d'); do [ \"\$Z\" -eq 0 ] && {   echo -n \"\$X \"; Z=1; } || { echo \"\$X\"; Z=0; }; done";
//			echo "potential problem: ". PHP_EOL . "$cmd" . PHP_EOL;
//			$cmd = "ls -1 /dev/class/net"
//			if (!($pp = popen($cmd,"r")) === FALSE) {
//				while (!feof($pp)) $items[] = trim(fgets($pp));
//				pclose($pp);
//			} else {
//				echo "something went wrong $pp" . PHP_EOL;
//				return false;
//			}
//			var_dump($items);
//			exit;
//			// for each interface, assign it to the array
//			foreach ($items as $item)
///			{
	//			if (!empty($item))
	//			{
//					list($interfaces[],$macs[]) = explode(" ",$item);
//				}
//			}
			// set both the interface name and the mac address simultaneously
//			foreach ($interfaces as $idx => $interface)
//			{
//				$this->addInterface ($interface);
//				$this->setMACAddress ($interface,strtolower($macs[$idx]));
	//		}
//		}
//		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
//	}

	  private function findInterfaces ()
  {
    if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
    if ($this->osType == "Linux")
    {
      $interfaces = array();
      $macs = array();
      // get a list of the interfaces which have a MAC address associated with them.
      $cmd = "Z=0; Y= ; for X in $(ip link | grep -B1 ether | sed -e 's/^[0-9]*: //g' -e 's/: <.*$//g' -e 's/@.*$//g' -e 's/^ *link\/ether //g' -e 's/ brd.*$//g' -e 'y/:/-/' -e '/^--/d'); do [ \"\$Z\" -eq 0 ] && { echo -n \"\$X \"; Z=1; } || { echo \"\$X\"; Z=0; } done";
      $pp = popen($cmd,"r");
      while (!feof($pp)) $items[] = trim(fgets($pp));
      pclose($pp);
      // for each interface, assign it to the array
      foreach ($items as $item)
      {
        if (!empty($item))
        {
          list($interfaces[],$macs[]) = explode(" ",$item);
        }
      }
      // set both the interface name and the mac address simultaneously
      foreach ($interfaces as $idx => $interface)
      {
        $this->addInterface ($interface);
        $this->setMACAddress ($interface,strtolower($macs[$idx]));
      }
    }
    if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
  }

	private function findMACs ()
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		true;
//		if ($this->osType == "Linux") {
//			$cmd = "for ITEM in \$(ifconfig -a | grep -B3 -E \"(ether|HWaddr)\" | sed 's/  */ /g' | grep ^[a-zA-Z] | cut -f1 -d':'); do echo \$ITEM \$(ifconfig \$ITEM | grep -E \"(ether|HWaddr)\" | sed -e 's/  */ /g' -e 'y/:/-/' | awk -F ' ' '{print $2}'); done";
//	$cmd = "ifconfig -a | grep -E \"(ether|HWaddr)\" | sed 's/  */ /g' | sed 's/:/-/g' | awk -F ' ' '{print $2}'";
//			$cmd = "ifconfig -a | grep -B3 -E \"(ether|HWaddr)\" | sed 's/  */ /g' | sed 's/:/-/g' | awk -F ' ' '{print $2}'";
//			$cmd = "ifconfig -a | grep HWaddr | sed 's/  */ /g' | sed 's/:/-/g' | awk -F ' ' '{print $1 \" \" $5}'";
//			$pp = popen($cmd,"r");
//			while (!feof($pp)) $macs[] = trim(fgets($pp));
//			pclose($pp);
//			$result = rtrim (`$cmd`);
//			if (empty($result)) {
//				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Potential error: Leaving function early", LOG_DEBUG_INOUT, L_DEBUG);
//				return;
//			}
//			$macs = array_unique(preg_split ("/\n/", $result));
//			$macs = preg_split("/\n/", $result);
//			foreach ($macs as $mac) {
//				list ($interface, $address) = preg_split ("/ /", $mac);
//				$this->setMACAddress ($interface, strtolower($address));
//			}
//		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	private function findIP4s ()
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		// for all of the interfaces installed in this system, attempt to find an IP address associated and then put it in the array
		foreach ($this->interfaces as $interface)
		{
			$cmd = "ifconfig -a $interface | grep 'inet ' | sed -e 's/^ *//g' -e 's/  / /g' | cut -f2 -d' '";
			$ip = rtrim(`$cmd`);
			if (!empty ($ip))
			{
				$this->setIP4Address ($interface, $ip);
			} else continue;
			$cmd = "ifconfig -a $interface | grep 'inet ' | sed -e 's/^ *//g' -e 's/  / /g' | cut -f4 -d' '";
			$mask = rtrim(`$cmd`);
			if (!empty ($mask))
			{
				$this->setIP4NetMask ($interface, $mask);
			}
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

// FUTURE SUPPORT FOR IPv6 NETWORK ADDRESSES
//	private function findIP6s () {
//		// for all of the interfaces installed in thgis system, attempt to find an IP address associated and then put it in the array
//		foreach ($this->interfaces as $interface) {
//			$cmd = "ifconfig -a eth0 | grep 'inet6 addr' | sed 's/^ *//g' | sed 's/^inet6 addr: //g' | sed 's/ Scope:Link*$//g'";
//			// whack the trailing whitespace
//			$ip = rtrim (`$cmd`);
//			// if we have an IP, assign it to the appropriate interface
//			if (!empty ($ip)) {
//				$this->setIP6Address ($interface, $ip);
//			}
//		}
//	}

	private function findHostNames () : void
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		// for all of the interfaces installed in this system, attempt to find an IP address associated and then the host name of that.
		foreach ($this->ip4Addresses as $interface => $ip)
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " interface $interface = $ip", LOG_DEBUG_VAR, L_DEBUG);
			if ($ip != null)
			{
				// get the ip address from "host" but drop invalid '3(NXDOMAIN)' results
				// also remove the trailing '.' if there is one
				$cmd = "host $ip | cut -f5 -d' ' | sed -e '/^[0-9]\([A-Z]*\)/d' -e 's/\.$//g'";
				// whack the trailing whitespace.
				$hostname = rtrim(`$cmd`);
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " found hostname $hostname", LOG_DEBUG_VAR, L_DEBUG);
				// if we have something in our string, assign it!
				if (!empty($hostname))
				{
					$this->setHostName ($hostname);
				}
			}
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " finished processing interface/ip addresses", LOG_DEBUG_HILITE, L_DEBUG);
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	// attempt to set an error message.
	private function setLastError (string $message) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (is_string($message))
		{
			$this->lastError = $message  . PHP_EOL;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			Utility::ncWrite("Error setting the error message ($message).", LOG_ERR, L_CONSOLE|L_ERROR);
			Utility::ncWrite("The message was either empty or was not a string.", L_CONSOLE|LOG_ERR);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setManufacturer (string $manufacturer) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($manufacturer))
		{
			$this->manufacturer = $manufacturer;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Potential error: Leaving function", LOG_DEBUG_WARN, L_DEBUG);
	}

	private function setModelNumber (string $model) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function",LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($model))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Setting this->modelNumber to $model", LOG_DEBUG_VAR, L_DEBUG);
			$this->modelNumber = $model;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	// void
	private function setSerialNumber (string $serial) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function",LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($serial))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Setting this->serialNumber to $serial", LOG_DEBUG_VAR, L_DEBUG);
			$this->serialNumber = $serial;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	// void
	private function setCPUMfgr (string $cpuManufacturer) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function",LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty($cpuManufacturer))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Setting this->cpuMfgr to $cpuManufacturer", LOG_DEBUG_VAR, L_DEBUG);
			$this->cpuMfgr = $cpuManufacturer;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	// void
	private function setCPUType (string $cpuType) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($cpuType))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Setting this->cpuType to $cpuType", LOG_DEBUG_VAR, L_DEBUG);
			$this->cpuType = $cpuType;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	// void
	private function setCPUVersion (string $cpuVersion) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty($cpuVersion))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Setting this->cpuVersion to $cpuVersion", LOG_DEBUG_VAR, L_DEBUG);
			$this->cpuVersion = $cpuVersion;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setCPUFrequency (string $cpuFrequency) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty($cpuFrequency))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Setting this->cpuFrequency to $cpuFrequency", LOG_DEBUG_VAR, L_DEBUG);
			$this->cpuFrequency = $cpuFrequency;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	// this should be changed to number CPU cores ... 
	private function setNumberCPUs (int $cpus) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ($cpus > 0)
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Setting this->numCPUs to $cpus", LOG_DEBUG_VAR, L_DEBUG);
			$this->numCPUs = $cpus;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setMemoryTotal (int $total) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ($total > 0)
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Setting this->memoryTotal to $total", LOG_DEBUG_VAR, L_DEBUG);
			$this->memoryTotal = $total;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setMemoryFree (int $free) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ($free > 0)
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Setting this->memoryFree to $free", LOG_DEBUG_VAR, L_DEBUG);
			$this->memoryFree = $free;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setSwapTotal (int $total) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ($total > 0)
		{
			$this->swapTotal = intval ($total);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setSwapFree (int $free) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ($free > 0)
		{
			$this->swapFree = intval ($free);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setHostName (string $hostname) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($hostname))
		{
			$this->hostNames[] = $hostname;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			Utility::ncWrite("Failed to assign host name ($hostname) to the list of names for this system.", LOG_WARN, L_CONSOLE|L_ERROR);
			echo $this->getLastError ();
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setIP4Address (string $interface, string $ipAddress) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (empty ($interface))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function early", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if (preg_match('/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/',$ipAddress))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " $ipAddress is a valid IPv4 Address on $interface", LOG_DEBUG_VAR, L_DEBUG);
			$this->ip4Addresses[$interface] = $ipAddress;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setIP4NetMask (string $interface, string $netMask) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (empty ($interface))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " interface ($interface) is invald, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if (preg_match('/^((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/',$netMask))
		{
			$this->ip4NetMasks[$interface] = $netMask;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " $interface has a valid netmask of $netMask", LOG_DEBUG_VAR, L_DEBUG);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setMACAddress (string $interface, string $macAddress) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (empty ($interface))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " interface ($interface) is invalid, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		if (preg_match('/^([a-f0-9]{2}[:-]){5}[a-f0-9]{2}$/i',$macAddress))
		{
			$macAddress = preg_replace('/:/','-',$macAddress);
			$this->macAddresses[$interface] = $macAddress;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " $interface has MAC address of $macAddress", LOG_DEBUG_VAR, L_DEBUG);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setOSType () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		$this->osType = PHP_OS;
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Set OS type to " . $this->osType, LOG_DEBUG_VAR, L_DEBUG);
		if (empty($this->osType))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
	}

	private function setOSName (string $osname) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($osname))
		{
			$this->osName = $osname;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Set this->osName to " . $this->osName, LOG_DEBUG_VAR, L_DEBUG);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Unable to determine OS name", LOG_DEBUG_WARN, L_DEBUG);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setOSVersion (string $osver) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($osver))
		{
			$this->osVersion = $osver;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Set this->osVersion to " . $this->osVersion, LOG_DEBUG_VAR, L_DEBUG);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " unable to determine osVersion", LOG_DEBUG_WARN, L_DEBUG);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setOSRevision (string $osrev) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($osrev))
		{
			$this->osRevision = $osrev;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Set this->osRevision to " . $this->osRevision . ", leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return true;
		}
		elseif ((empty($osrev)) && ($osrev != null))
		{
			$this->osRevision = "0";
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Set this->osRevision to " . $this->osRevision . ", leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Unable to determine osRevision, leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function setCPUArch (string $arch) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty($arch))
		{
			$this->cpuArch = trim($arch);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Set this->cpuArch to " . $this->cpuArch . ", leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Unable to determine this->cpuArch, leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	private function addInterface (string $interface) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($interface))
		{
			$this->interfaces[] = $interface;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Added interface ($interface) to this->interfaces[], leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Unable to add interface ($interface) to this->interfaces[], leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return false;
		}
	}

	private function setLastScanTime (string $scandate) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty($scandate))
		{
			$this->lastScanTime = $scandate;
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Set this->lastScanTime to " . $this->lastScanTime . ", leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Unable to set this->lastScanTime to $scandate, Leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
	}

	public function dbUpdate()
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ((isset($this->dbHost) && is_string($this->dbHost) && !empty($this->dbHost)) && 
			(isset($this->dbPort) && is_int($this->dbPort) && $this->dbPort !== 0) &&
			(isset($this->dbUser) && is_string($this->dbUser) && !empty($this->dbUser))
			(isset($this->dbPass) && is_string($this->dbHost) && !empty($this->dbHost)) &&
			(isset($this->dbName)))
		{
			try {
				if (!($db = new db ())) //($this->dbHost,$this->dbPort,$this->dbUser,$this->dbPass,$this->dbName)))
				{
					throw new Exception ("Unable to connect to the database. Check credentials and try again",1);
				}
			} catch (Exception $dbFail)
			{
				if ($dbFail->getCode() !== 0)
				{
					throw $dbFail;
				}
			}
		} 
		else 
		{
			try
			{
				if (!$db = new db()) throw new Exception("failed to create a db connection",1);
			} 
			catch (Exception $dbConn)
			{
				if ($dbConn->getCode() !== 0)
				{
					throw new Exception ("failed to create a database connection",1);
				}
			}
		}

//		catch (Exception $dbFail)
//		{
//			if ($dbFail->getCode() !== 0)
//			{
//				return ($dbFail);
//			}
//		}
		// determine whether to insert a new host or update an existing one.
		$db->sqlstr = "SELECT id,mac_address FROM hosts";
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Attempting db query: ". $db->sqlstr, LOG_DEBUG_QUERY, L_DEBUG);
		if (!$db->query())
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " db query failed, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		while ($hostInfo = $db->row())
		{
			$mac = preg_replace ('/[}{]/','',$hostInfo['mac_address']);
			$mac = preg_replace ("/:/","-",$mac);
			$mac = explode (",",$mac);
			foreach ($mac as $macaddr)
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Attempting to find MAC $macaddr", LOG_DEBUG_HILITE, L_DEBUG);
			}
			if (array_intersect($this->macAddresses,$mac))
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " setting this->id to " . $hostInfo['id'], LOG_DEBUG_VAR, L_DEBUG);
				$this->id = $hostInfo['id'];
			}
		}
		// if the id in the result_set is > 0, we should update
		if ($this->id > 0)
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " attempting a record update:", LOG_DEBUG, L_DEBUG);
			$res = $this->doUpdate();
			if ($res === false)
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " update failed, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			}
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " attempting a record insert:", LOG_DEBUG, L_DEBUG);
			$res = $this->doInsert();
			if ($res === false)
			{
				if ($this->debug === true) Utility::ncWrite(__METHOD__ . " insert failed, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			}
		}
		return $res;
	}

	private function doUpdate() : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		try
		{
//			$db = new db($this->dbHost->dbHost,$this->dbHost->dbPort,$this->dbHost->dbUser,$this->dbHost->dbPass,$this->dbHost->dbName);
			$db = new db();
			$hostnames="";
		}
		catch (Exception $dbException)
		{
			if ($dbException->getCode() !== 0)
			{
				return false;
			}
		}
		if ( (is_array($this->interfaces)) && (!empty($this->interfaces)) )
		{
			$interfaces = implode (",",$this->interfaces);
		}
		if ( (is_array($this->macAddresses)) && (!empty($this->macAddresses)) )
		{
			$macs = implode (",",$this->macAddresses);
		}
		if (isset($this->hostNames)) {
			if (!empty($this->hostnames)) {
				if (is_array($this->hostNames))
				{
					$hostnames = implode (",",$this->hostNames);
				}
				else
				{
					$hostnames = strval($this->hostNames);
				}
			}
			else
			{
				$pp = popen("hostname -s","r");
				while (!feof($pp)) $hostnames = trim(fgets($pp));
				pclose($pp);
			}
		}
		else
		{
			$hostnames = 'The hostName property is unset';
		}
		if ( (is_array($this->ip4Addresses)) && (!empty($this->ip4Addresses)) )
		{
			$ip4s = implode (",",$this->ip4Addresses);
		}
		else
		{
			$ipv4s = "127.0.0.1";
		}
		if ((is_array($this->ip6Addresses)) && (!empty($this->ip6Addresses)))
		{
			$ip6s = implode (",",$this->ip6Addresses);
		}
		else
		{
			$ip6s = "::1/128";
		}
		($this->isDockerHost === true) ? $isDockerHost='t' : $isDockerHost='f';
		($this->isVirtualized === true) ? $virtualized='t' : $virtualized='f';
		($this->isContainer === true) ? $container='t' : $container='f';
		$db->sqlstr = "UPDATE hosts SET " 					.		
			"manufacturer='{$this->manufacturer}'," 			.
			"model_number='{$this->modelNumber}',"  			.
			"serial_number='{$this->serialNumber}',"			.
			"uuid='{$this->uuid}'," 					.
			"memory={$this->memoryTotal}," 					.
			"swap={$this->swapTotal}," 					.
			"storage_capacity={$this->storageTotal}," 			.
			"numcpus={$this->numCPUs}," 					.
			"cpu_mfgr='{$this->cpuMfgr}'," 					.
			"cpu_type='{$this->cpuType}'," 					.
			"cpu_architecture='{$this->cpuArch}'," 				.
			"os_distribution='{$this->osName}'," 				.
			"os_version='{$this->osVersion}'," 				.
			"os_revision='{$this->osRevision}'," 				.
			"interface='{" . $interfaces . "}'::text[]," 			.
			"mac_address='{" . $macs . "}'::macaddr[]," 			.
			"host_name='{" . $hostnames . "}'::text[]," 			.
			"ipv4_address='{" . $ip4s . "}'::cidr[]," 			.
			"ipv6_address='{" . $ip6s . "}'::cidr[]," 	.
			"building_location='{$this->buildingLocation}'," 		.
			"lab_location='{$this->labLocation}'," 				.
			"grid_location='{$this->gridLocation}'," 			.
			"contact_id={$this->contactID}," 				.
			"assigned_user_id={$this->assignedUserID}," 			.
			"last_scan_date='{$this->lastScanTime}'::timestamp," 		.
			"last_service_date='{$this->lastServiceDate}'::timestamp," 	.
			"is_dockerhost='{$isDockerHost}'," 				.
			"is_virtualized='{$virtualized}'," 				.
			"virtualization_type='{$this->virtualizationType}'," 		.
			"is_container='{$container}'," 					.
			"container_application='{$this->containerApplication}'," 	.
			"comments='Updated by class_host.php' " 			.
			"WHERE id={$this->id}";
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Attempting database query: " . $db->sqlstr, LOG_DEBUG_QUERY, L_DEBUG);
		if ($db->query())
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Update successful, leaving function", LOG_DEBUG_HILITE, L_DEBUG);
			$res = pg_affected_rows($db->result_set);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function ", LOG_DEBUG_INOUT, L_DEBUG);
			return false;
		}
	}

	//// PROGRESS!! Implemented the forever amazing UPSERT.  Now there will only be 1 function neeed to insert and update
	/// the update function can now be commented and eventually removed but now how will we attract women to come into our
	/// villages? Before they were FORCED to walk passed the insert village and make a choice about upgrading or not... Now
	/// they can only insert.  Oh well... Poor updaters.... That is what Google is trying to force on us too with their chromebooks
	/// that expire after 6 years.... They are causing environmental issues by causing the precious minerals required to make 
	/// some of the chips we use in computers to have to be mined continuously instead of recycling more. /shrug
	private function doInsert() : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		// check if we have a valid db connection (something may have happened)
		try
		{
			$db = new db(); //($this->dbHost,$this->dbPort,$this->dbUser,$this->dbPass,$this->dbName);
		}
		catch (Exception $dbException)
		{
			if ($dbException->getCode() !== 0)
			{
				return false;
			}
		}
		// inserts a host device into the database

		if ( (is_array($this->interfaces)) && (!empty($this->interfaces)) )
		{
			$interfaces = implode (",",$this->interfaces);
		}
		if ( (is_array($this->macAddresses)) && (!empty($this->macAddresses)) )
		{
			$macs = implode (",",$this->macAddresses);
		}

		if ( (is_array($this->hostNames)) && (!empty($this->hostNames)) )
		{
			$hostnames = implode (",",$this->hostNames);
		}
		else
		{
			$hostnames = trim(shell_exec('hostname'));
		}

		if ( (is_array($this->ip4Addresses)) && (!empty($this->ip4Addresses)) )
		{
			$ip4s = implode (",",$this->ip4Addresses);
		}
		//var_dump($this->ip6Addresses);
		if ( (is_array($this->ip6Addresses)) && (!empty($this->ip6Addresses)) )
		{
			$ip6s = implode(",",$this->ip6Addresses);
		} else {
			$ip6s = "::1";
		}
		($this->isDockerHost === true) ? $isDockerHost='t' : $isDockerHost='f';
		($this->isVirtualized === true) ? $virtualized='t' : $virtualized='f';
		($this->isContainer === true) ? $container='t' : $container='f';		
		$db->sqlstr = "INSERT INTO hosts VALUES ("											.
			"nextval('public.hosts_id_seq'::text),"												.
			"'{$this->manufacturer}',"																		.
			"'{$this->modelNumber}',"																			.
			"'{$this->serialNumber}',"																		.
			"'{$this->uuid}',"																						.
			"{$this->memoryTotal},"																				.
			"{$this->swapTotal},"																					.
			"{$this->storageTotal},"																			.
			"{$this->numCPUs},"																						.
			"'{$this->cpuMfgr}',"																					.
			"'{$this->cpuType}',"																					.
			"'{$this->cpuArch}',"																					.
			"'{$this->osName}',"																					.
			"'{$this->osVersion}',"																				.
			"'{$this->osRevision}',"																			.
			"'{" . $interfaces . "}'::text[],"														.
			"'{" . $macs . "}'::macaddr[],"																.
			"'{" . $hostnames . "}'::text[],"															.
			"'{" . $ip4s . "}'::cidr[],"																	.
			"'{" . $ip6s . "}'::cidr[],"																	.
			"'{$this->buildingLocation}',"																.
			"'{$this->labLocation}',"																			.
			"'{$this->gridLocation}',"																		.
			"{$this->contactID},"																					.
			"{$this->assignedUserID},"																		.
			"'{$this->lastScanTime}'::timestamp,"													.
			"'{$this->lastServiceDate}'::timestamp,"											.
			"'$isDockerHost'::boolean,"																		.
			"'$virtualized'::boolean,"																		.
			"'{$this->virtualizationType}'::text,"												.
			"'$container'::boolean,"																			.
			"'{$this->containerApplication}'::text,"											.
			"'automatically inserted by discovery') ON CONFLICT (id) ".
			"DO UPDATE SET "				       																.
			"manufacturer=EXCLUDED.manufacturer,"													.
			"model_number=EXCLUDED.model_number,"													. 
			"serial_number=EXCLUDED.serial_number,"												. 
			"uuid=EXCLUDED.uuid,"																					. 
			"memory=EXCLUDED.memory,"																			.
			"swap=EXCLUDED.swap,"																					.
			"storage_capacity=EXCLUDED.storage_capacity,"									.
			"numcpus=EXCLUDED.numcpus,"																		.
			"cpu_mfgr=EXCLUDED.cpu_mfgr,"																	.
			"cpu_type=EXCLUDED.cpu_type,"																	.
			"cpu_architecture=EXCLUDED.cpu_architecture,"									.
			"os_distribution=EXCLUDED.os_distribution,"										.
			"os_version=EXCLUDED.os_version,"															.
			"os_revision=EXCLUDED.os_revision,"														.
			"interface=EXCLUDED.interface,"																.
			"mac_address=EXCLUDED.mac_address,"														.
			"host_name=EXCLUDED.host_name,"																.
			"ipv4_address=EXCLUDED.ipv4_address,"													.
			"ipv6_address=EXCLUDED.ipv6_address,"													.
			"building_location=EXCLUDED.building_location,"								.
			"lab_location=EXCLUDED.lab_location,"													.
			"grid_location=EXCLUDED.grid_location,"												.
			"contact_id=EXCLUDED.contact_id,"															.
			"assigned_user_id=EXCLUDED.assigned_user_id,"									.
			"last_scan_date=EXCLUDED.last_scan_date,"											.
			"last_service_date=EXCLUDED.last_service_date,"								.
			"is_dockerhost=EXCLUDED.is_dockerhost,"												.
			"is_virtualized=EXCLUDED.is_virtualized,"											.
			"virtualization_type=EXCLUDED.virtualization_type,"						.
			"is_container=EXCLUDED.is_container,"												.
			"container_application=EXCLUDED.container_application,"			.
			"comments='Upserts brought to you by Tigr by class_host.php' ".
			"WHERE hosts.id=EXCLUDED.id RETURNING id";
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Attempting database query: " . $db->sqlstr, LOG_DEBUG_QUERY, L_DEBUG);
		if ($db->query())
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Insert successful", LOG_DEBUG_HILITE, L_DEBUG);
			// the following assignment for $this->id is a temporary test
			$this->id = $db->row()['id'];
			if (empty($this->id))
			{
				$this->id = pg_fetch_assoc($db->result_set)['id'];
			}
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " setting this->id to " . $this->id, LOG_DEBUG_VAR, L_DEBUG);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function ", LOG_DEBUG_INOUT, L_DEBUG);
			return true;
		}
		else
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " insert failed, Leaving function ", LOG_DEBUG_WARN, L_DEBUG);
			Utility::ncWrite("FATAL: Unable to insert the host object into the database", LOG_CRIT, L_CONSOLE|L_ERROR);
			return false;
		}
	}

	public function loadObject (int $id) : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ($id <= 0)
		{
			Utility::ncWrite(__METHOD__ . " ID ($id) must be greater than 0, unable to load the object", LOG_ERR, L_CONSOLE|L_ERROR);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Invalid id parameter, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		try
		{
			$db = new db();//($this->dbHost,$this->dbPort,$this->dbUser,$this->dbPass,$this->dbName);
		}
		catch (Exception $dbException)
		{
			if ($dbException->getCode() !== 0)
			{
				return false;
			}
		}
		$db->sqlstr = "SELECT * FROM hosts WHERE id=$id";
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Attempting sql query: " . $db->sqlstr, LOG_DEBUG_QUERY, L_DEBUG);
		if (!$this->db->query ())
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Query failed: " . $db->sqlstr, LOG_ERR, L_CONSOLE|L_ERROR);
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " query failed, leaving function", LOG_DEBUG_WARN, L_DEBUG);
			return false;
		}
		$inf = $db->row();
		$this->id = $inf['id'];
		$this->manufacturer = $inf['manufacturer'];
		$this->modelNumber = $inf['model_number'];
		$this->serialNumber = $inf['serial_number'];
		$this->memoryTotal = $inf['memory'];
		$this->swapTotal = $inf['swap'];
		$this->storageCapacity = $inf['storage_capacity'];
		$this->numCPUs = $inf['numcpus'];
		$this->cputMfgr = $inf['cpu_mfgr'];
		$this->cpuType = $inf['cpu_type'];
		$this->cpuArch = $inf['cpu_architecture'];
		$this->osName = $inf['os_distribution'];
		$this->osVersion = $inf['os_version'];
		$this->osRevision = $inf['os_revision'];
		$this->interfaces = preg_replace ("/{/","",$inf['interface']);
		$this->interfaces = preg_replace ("/}/","",$this->interfaces);
		$this->interfaces = explode (",",$this->interfaces);
		$this->macAddresses = preg_replace ("/{/","",$inf['mac_address']);
		$this->macAddresses = preg_replace ("/}/","",$this->macAddresses);
		$this->macAddresses = explode (",",$this->macAddresses);
		// assign the mac addresses to the appropriate interface (hopefully?)
////////////////$this->macAddresses = array_combine ($this->interfaces, $this->macAddresses);
		$this->hostNames = preg_replace ("/{/","",$inf['host_name']);
		$this->hostNames = preg_replace ("/}/","",$this->hostNames);
		$this->hostNames = explode (",",$this->hostNames);
		$this->ip4Addresses = preg_replace ("/{/","",$inf['ipv4_address']);
		$this->ip4Addresses = preg_replace ("/}/","",$this->ip4Addresses);
		$this->ip4Addresses = explode (",",$this->ip4Addresses);
////////////////$this->ip4Addresses = array_combine ($this->interfaces, $this->ip4Addresses);
		$this->buildingLocation = $inf['building_location'];
		$this->labLocation = $inf['lab_location'];
		$this->gridLocation = $inf['grid_location'];
		$this->contactID = $inf['contact_id'];
		$this->assignedUserID = $inf['assigned_user_id'];
		$this->lastScanTime = $inf['last_scan_date'];
		$this->lastServiceDate = $inf['last_service_date'];
		var_export ($inf['is_dockerhost'],$inf['is_virtualized'],$inf['is_container']);
		if ($inf['is_dockerhost'] === 't') $this->isDockerHost = true; else $this->isDockerHost = false;
		if ($inf['is_virtualized'] === 't') $this->isVirtualized = true; else $this->isVirtualized = false;
		$this->virtualizationType = $inf['virtualization_type'];
		if ($inf['is_container'] === 't') $this->isContainer = true; else $this->isContainer = false;
		$this->containerApplication = $inf['container_application'];
		$this->comments = $inf['comments'];
		switch (strtolower($this->osName)) {
			case 'redhat':
			case 'fedora':
			case 'scientific':
			case 'centos':
				$this->packages = $this->queryRHPackages();
				break;
			default:
				break;
		}
		$this->packages = $this->queryPackages();
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
		return true;
	}

	public function getLastError () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function",LOG_DEBUG_INOUT, L_DEBUG);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function",LOG_DEBUG_INOUT, L_DEBUG);
		return $this->lastError;
	}

	public function getId () : int
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->id))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->id " . $this->id . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->id;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->id was empty or 0", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
		return 0;
	}

	public function getManufacturer () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->manufacturer))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Returning this->manufacturer " . $this->manufacturer . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->manufacturer;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->manufacturer was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getModelNumber () : string
	{
		if ($this->debug === true) Utility::ncWrite("Entering function " . __METHOD__, LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->modelNumber))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->modelNumber " . $this->modelNumber . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->modelNumber;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->modelNumber was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getSerialNumber () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->serialNumber))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->serialNumber " . $this->serialNumber . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->serialNumber;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->serialNumber was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getMemoryTotal () : int
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->memoryTotal))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->memoryTotal " . $this->memoryTotal . " in bytes and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->memoryTotal;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->memoryTotal was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getMemoryFree () : int
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (is_numeric ($this->memoryFree))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->memoryFree " . $this->memoryFree . " in bytes and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->memoryFree;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->memoryFree was empty or not numeric", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getSwapTotal () : int
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (is_numeric ($this->swapTotal))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->swapTotal " . $this->swapTotal . " in bytes and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->swapTotal;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->swapTotal was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getSwapFree () : int 
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->swapFree))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->swapFree " . $this->swapFree . " in bytes and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->swapFree;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->swapFree was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getNumCPUs () : int
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->numCPUs))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->numCPUs " . $this->numCPUs . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->numCPUs;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->numCPUs was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getCPUArch () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->cpuArch))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->cpuArch " . $this->cpuArch . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->cpuArch;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->cpuArch was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getCPUMfgr () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . "Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->cpuMfgr))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->cpuMfgr " . $this->cpuMfgr . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->cpuMfgr;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->cpuMfgr was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getCPUType () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->cpuType))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->cpuType " . $this->cpuType . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->cpuType;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->cpuType was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getCPUVersion () : string  
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty($this->cpuVersion))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return $this->cpuVersion;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getCPUFrequency () : int 
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty($this->cpuFrequency))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return $this->cpuFrequency;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}
	public function getOSName () : string 
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->osName))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->osName " . $this->osName . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->osName;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->osName was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getOSVersion () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (is_numeric ($this->osVersion))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->osVersion " . $this->osVersion . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->osVersion;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->osVersion was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getOSRevision () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (is_numeric ($this->osRevision))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->osRevision " . $this->osRevision . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->osRevision;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->osRevision was empty", LOG_WARN, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	// returns an array of all hostnames assigned to this system
	public function getHostNames () : array
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ((is_array($this->hostNames)) || (!empty($this->hostNames)))
		{
			if ($this->debug === true)
			{
				foreach ($this->hostNames as $hostName)
				{
					Utility::ncWrite(__METHOD__ . " hostNames[] contains $hostName", LOG_DEBUG_VAR, L_DEBUG);
				}
				Utility::ncWrite(__METHOD__ . " returning hostNames array and leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			}
			return (array_unique($this->hostNames));
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->hostNames was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	// returns an array of all IP4 addresses assigned to this system
	public function getIP4Addresses () : array
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ((is_array($this->ip4Addresses)) || (!empty($this->ip4Addresses)))
		{
			if ($this->debug === true)
			{
				foreach ($this->ipAddresses as $ipAddress)
				{
					Utility::ncWrite(__METHOD__ . " ip4Addresses[] contains $ipAddress", LOG_DEBUG_VAR, L_DEBUG);
				}
				Utility::ncWrite(__METHOD__ . " Returning ip4Addresses array and leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			}
			return (array_unique($this->ip4Addresses));
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->ip4Addresses was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	// returns an array of all IP6 addresses assigned to this system
	public function getIP6Addresses () : array
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ((is_array($this->ip6Addresses)) || (!empty($this->ip6Addresses)))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning ip6Addresses " . $this->ip6Addresses . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return ($this->ip6Addresses);
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->ip6Addresses was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	// returns an array of all MAC addresses present on this system
	public function getMACAddresses () : array
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ((is_array($this->macAddresses)) || (!empty($this->macAddresses)))
		{
			if ($this->debug === true)
			{
				foreach ($this->macAddresses as $macaddr)
				{
					Utility::ncWrite(__METHOD__ . " macAddresses[] contains $macaddr", LOG_DEBUG_VAR, L_DEBUG);
				}
				Utility::ncWrite(__METHOD__ . " Returning macAddresses array and leaving", LOG_DEBUG_INOUT, L_DEBUG);
			}
			return (array_unique($this->macAddresses));
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->macAddresses was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getInterfaces () : array
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ((is_array($this->interfaces)) || (!empty($this->interfaces)))
		{
			if ($this->debug === true)
			{
				foreach ($this->interfaces as $interface)
				{
					Utility::ncWrite(__METHOD__ . " Interfaces[] contains $interface", LOG_DEBUG_VAR, L_DEBUG);
				}
				Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			}
			return (array_unique($this->interfaces));
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->interfaces was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getPackageInfo () : array
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ((is_array($this->packages)) || (!empty($this->packages)))
		{
			if ($this->debug === true)
			{
				foreach ($this->packages as $package)
				{
					Utility::ncWrite(__METHOD__ . " packages[] contains $package", LOG_DEBUG_VAR, L_DEBUG);
				}
				Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			}
			return (array_unique($this->packages));
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->packages was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getSystemUUID () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->uuid))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->uuid " . $this->uuid . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->uuid;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->uuid was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getBuildingLocation () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->buildingLocation))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->buildingLocation " . $this->buildingLocation . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->buildingLocation;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->buildingLocation was empty", LOG_WARN, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getLabLocation () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->labLocation))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->labLocation " . $this->labLocation . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->labLocation;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->labLocation was empty", LOG_WARN, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getGridLocation () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->gridLocation))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->gridLocation " . $this->gridLocation . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->gridLocation;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->gridLocation was empty", LOG_WARN, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getContactID () : int
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->contactID))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->contactID " . $this->contactID . " and leaving function", LOG_DEBUG_VAR, L_CONSOLE|L_ERROR);
			return $this->contactID;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->contactID was empty", LOG_WARN, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getAssignedUserID () : int
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->assignedUserID))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->assignedUserID " . $this->assignedUserID . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->assignedUserID;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->assignedUserID was empty", LOG_WARN, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getLastScanTime () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->lastScanTime))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->lastScanTime " . $this->lastScanTime . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->lastScanTime;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->lastScanTime was empty", LOG_WARN, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getLastServiceDate () : string 
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->lastServiceDate))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->lastServiceDate " . $this->lastServiceDate . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->lastServiceDate;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->lastServiceDate was empty", LOG_WARN, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getIsVirtualized () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (is_bool($this->isVirtualized))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " returning this->isVirtualized and leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return $this->isVirtualized;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->isVirtualized was not a boolean value", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getVirtualizationType () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->virtualizationType))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->virtualizationType " . $this->virtualizationType . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->virtualizationType;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->vitrualizationType was empty", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getIsContainer () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (is_bool($this->isContainer))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " returning this->isContainer and leaving function", LOG_DEBUG_INOUT, L_DEBUG);
			return $this->isContainer;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->isContainer was not a boolean value", LOG_ERR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getContainerApplication () : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->containerApplication))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->containerApplication " . $this->containerApplication . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->containerApplication;
		}
		// Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->containerApplication was empty", LOG_WARN, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getComments () : string // not sure if this is correct.
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty ($this->comments))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning this->comments " . $this->comments . " and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->comments;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->comments was empty", LOG_WARN, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getIsDockerHost () : bool
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (is_bool($this->isDockerHost))
		{
			if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Returning value of this->isDockerHost (".$this->isDockerHost.") and leaving function", LOG_DEBUG_VAR, L_DEBUG);
			return $this->isDockerHost;
		}
		Utility::ncWrite(__LINE__ . ":" . __METHOD__ . " this->isDockerHost was not a boolean value", LOG_ERROR, L_CONSOLE|L_ERROR);
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
	}

	public function getDataSize ($size = null) : string
	{
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if ($size === null) return "";
		if (!is_numeric($size)) return "";
		$pow = 0;
		while (($size / pow(1024,$pow)) >= 1024) $pow++;
		$output = $size/pow(1024,$pow);
		switch ($pow)
		{
			case 0:
				$output .= " B";
				break;
			case 1:
				$output .= " KB";
				break;
			case 2:
				$output .= " MB";
				break;
			case 3:
				$output .= " GB";
				break;
			case 4:
				$output .= " TB";
				break;
			case 5:
				$output .= " EB";
				break;
			case 6:
				$output .= " PB";
				break;
			case 7:
				$output .= " YB";
				break;
			case 8:
				$output .= " ZB";
				break;
			default:
				break;
		}
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
		return $output;
	}

	public function hostInfo () : string
	{
		$this->Telemetry->functions_entered++;
		$infoString = "";
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Entering function", LOG_DEBUG_INOUT, L_DEBUG);
		if (!empty($this->id) || $this->id == 0)		$infoString =  "Host ID      : " . $this->id . PHP_EOL;
		if (!empty($this->manufacturer))			$infoString .= "Manufacturer : " . $this->manufacturer . PHP_EOL;
		if (!empty($this->modelNumber))				$infoString .= "Model Number : " . $this->modelNumber . PHP_EOL;
		if (!empty($this->serialNumber))			$infoString .= "Serial Number: " . $this->serialNumber . PHP_EOL;
		if (!empty($this->uuid))				$infoString .= "System UUID  : " . $this->uuid . PHP_EOL;
		// report memoryTotal in human readable format
		if (!empty($this->memoryTotal))				$infoString .= "Memory Total : " . $this->getDataSize($this->memoryTotal) . PHP_EOL;
		if (!empty($this->memoryFree)) 				$infoString .= "Memory Free  : " . $this->getDataSize($this->memoryFree) . PHP_EOL;
		if (!empty($this->swapTotal)) 				$infoString .= "Swap Total   : " . $this->getDataSize($this->swapTotal) . PHP_EOL;
		if (!empty($this->swapFree)) 				$infoString .= "Swap Free    : " . $this->getDataSize($this->swapFree) . PHP_EOL;
		if (!empty($this->storageTotal)) 			$infoString .= "Storage Total: " . $this->getDataSize($this->storageTotal) . PHP_EOL;
		if (!empty($this->storageFree)) 			$infoString .= "Storage Free : " . $this->getDataSize($this->storageFree) . PHP_EOL;
		if (!empty($this->numCPUs)) 				$infoString .= "Number CPU(s): " . $this->numCPUs . PHP_EOL;
		if (!empty($this->cpuMfgr)) 				$infoString .= "CPU Mfgr     : " . $this->cpuMfgr . PHP_EOL;
		if (!empty($this->cpuType)) 				$infoString .= "CPU Type     : " . $this->cpuType . PHP_EOL;
		if (!empty($this->cpuVersion)) 				$infoString .= "CPU Version  : " . $this->cpuVersion . PHP_EOL;
		if (!empty($this->cpuFrequency)) 			$infoString .= "CPU Frequency: " . $this->cpuFrequency . PHP_EOL;
		if (!empty($this->cpuArch)) 				$infoString .= "CPU Archtype : " . $this->cpuArch . PHP_EOL;
		if (!empty($this->osType)) 					$infoString .= "OS Type      : " . $this->osType . PHP_EOL;
		if (!empty($this->osName)) 					$infoString .= "OS Name      : " . $this->osName . PHP_EOL;
		if (!empty($this->osVersion)) 				$infoString .= "OS Version   : " . $this->osVersion . PHP_EOL;
		if (!empty($this->osRevision)) 				$infoString .= "OS Revision  : " . $this->osRevision . PHP_EOL;
		$infoString .= "Hostname(s)  : " . implode (",", $this->getHostNames ())  . PHP_EOL;
		$infoString .= "Interfaces (" . count($this->interfaces) . ")" . PHP_EOL;
		foreach ($this->getInterfaces () as $interface)
		{
			if (!empty($interface))
			{
				$infoString .= "  $interface" . PHP_EOL;
				if (!empty($this->ip4Addresses[$interface]))
				{
					$infoString .= "  IP : " . $this->ip4Addresses[$interface]  . PHP_EOL;
				}
				if (!empty($this->macAddresses[$interface]))
				{
					$infoString .= "  MAC: " . $this->macAddresses[$interface]  . PHP_EOL;
				}
			}
			else continue;
		}
		if (!empty($this->buildingLocation))		$infoString .= "BuildingLOC  : " . $this->buildingLocation . PHP_EOL;
		if (!empty($this->labLocation))				$infoString .= "Lab Location : " . $this->getLabLocation . PHP_EOL;
		if (!empty($this->gridLocation))			$infoString .= "Grid Location: " . $this->getGridLocation . PHP_EOL;
		if (!empty($this->contactID))				$infoString .= "Contact ID   : " . $this->getContactID . PHP_EOL;
		if (!empty($this->assignedUserID))			$infoString .= "Assigned User: " . $this->assignedUserID . PHP_EOL;
		if (!empty($this->lastScanTime))			$infoString .= "Last Scanned : " . $this->lastScanTime . PHP_EOL;
		if (!empty($this->lastServiceDate))			$infoString .= "Last Serviced: " . $this->lastServiceDate . PHP_EOL;
		$infoString .= "Is Virtual   : " . ($this->isVirtualized ? "true" : "false") . PHP_EOL;
		if (!empty($this->virtualizationType))		$infoString .= "Virt Type    : " . $this->virtualizationType . PHP_EOL;
		if ($this->isContainer === true)			$infoString .= "Is Container : true" . PHP_EOL;
		if (!empty($this->containerApplication))	$infoString .= "Container App: " . $this->containerApplication . PHP_EOL;
		if (!empty($this->comments))				$infoString .= "Comments     : " . $this->comments . PHP_EOL;
		if ($this->debug === true) Utility::ncWrite(__METHOD__ . " Leaving function", LOG_DEBUG_INOUT, L_DEBUG);
		$this->Telemetry->functions_left++;
		return $infoString;
	}

// end of class Host
}
// CHANGE LOG
/////////////
// 05/31/2011 Created initial version of this class
// 05/31/2011 added the ability to determine the OS, OSVER and OSREV of the host
// 05/31/2011 added the ability to determine what hardware architecture the host is running on
/////////////
// 06/01/2011 added the ability to determine interfaces
// 06/01/2011 added the ability to determine mac addresses for all interfaces
// 06/01/2011 added the ability to determine ip addresses for all configured interfaces
// 06/01/2011 added the ability to determine all host names for the configured ip addresses
// 06/01/2011 added the ability to determine the manufacturer/model/serial/cputype of the host
// 06/01/2011 added the ability to determine total memory/swap and free memory/swap
/////////////
// 06/02/2011 added the ability to insert and update the info gathered into the database
// 06/02/2011 added the ability to load info from the database into the object
// 06/02/2011 added the getId and hostInfo methods
/////////////
// 06/06/2011 fixed bug which attempted to use an undefined array index value while assigning host names
/////////////
// 06/22/2011 fixed a type that was preventing the loadObject method from completing properly
/////////////
// 01/25/2018 Added tests for virtualization and virtualization type
// 01/25/2018 Added tests for containers and container application
/////////////
// 02/12/2018 Changed object to create its own database object
// 02/12/2018 Changed object requirements from passing db object to passing log object
// 02/12/2018 Added debugging information
// 02/12/2018 Changed display of virtualization/container information from 1/0 to true/false
//            Added cpuFrequency variable/determineCPUFrequency/setCPUFrequency/getCPUFrequency
//            Added cpuVersion variable/determineCPUVersion/setCPUVersion/getCPUVersion
//            Rewrote hostInfo to only display information that is actually populated to
//                cut down on junk lines
//            Rewrote displays of memory/swap/storage totals to show TB/GB/MB/KB instead of
//                raw byte count
//            Added display of cpu version and frequency information
/////////////
// 04/10/2018 Modified all functions to conform with Logger debugging messages
//            Removed most of the parents surrounding return() results
//            Added some return type checking on some of the methods
//
/////////////
// 05/03/2018 Added Host::getDataSize($size) which returns a string with the proper suffixed data size (B,KB,MB,GB,TB,EB,PB.YB.ZB)
//            Modified hostInfo method to utilize the new function when it is displaying memory,swap,storage values
?>
