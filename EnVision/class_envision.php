<?php // 7.4.24
//vim:ts=2:sts=2:nocompatible:ruler:ai
//try {
//	if (empty($_SERVER['ENV_MYIP'])) throw new Exception ("_SERVER['ENV_MYIP'] is empty",1);
//} catch (Exception $myipEmpty) {
//	try {
//		if (empty($_SERVER['ENV_MYIP'] = trim(shell_exec('/env/setup/bin/myIP')))) throw new Exception ("_SERVER['ENV_MYIP'] is empty",1);
//	} catch (Exception $myipEmpty) {
//		try {
//			if (empty($_SERVER = getenv('ENV_MYIP'))) throw new Exception ("_SERVER['ENV_MYIP'] is empty",1);
//		}	catch (Exception $myipEmpty) {
//			$_SERVER['ENV_MYIP'] = "127.0.0.1";
//		}
//	}
//}

$vDir = getenv("ENV_VISIONDIR");
$vFile = getenv("ENV_VISIONFILE");

define("DEFAULT_VISIONDIR", getenv("ENV_VISIONDIR"));
define("DEFAULT_VISIONROOT", getenv("ENV_VISIONROOT"));
define("DEFAULT_VISIONFILE", getenv("ENV_VISIONFILE"));

//$env_myip = trim(shell_exec("/env/scripts/myIP"));
//if (!empty($env_myip)) define ("ENV_MYIP",$env_myip);
//$if (isset($_SERVER["ENV_VISIONDIR"])) define ("DEFAULT_VISIONDIR",$_SERVER['ENV_VISIONDIR']);


//if (!defined("DEFAULT_VISIONDIR")) 
//	define("DEFAULT_VISIONDIR",(getenv('ENV_VISIONDIR') ?? $_SERVER['ENV_VISIONDIR'] ?? "/env/vision/".$_SERVER['ENV_MYIP']."/apache"));
//if (!defined("INVALID_ENVISIONFILE"))
//	define ("INVALID_ENVISIONFILE",0x1);
//if (!defined("DEFAULT_ENVISIONFILE"))
//	define ("DEFAULT_ENVISIONFILE","/usr/share/EnVision/default.conf");



//if (!defined("DEFAULT_VISIONDIR")) {
//	if (defined("ENV_MYIP")) {
//		define ("DEFAULT_VISIONDIR","/env/vision/".ENV_MYIP."/apache");
//	}
//}
//if (!file_exists(DEFAULT_ENVISIONFILE)) exit(INVALID_ENVISIONFILE);
// implements Serializable
class EnVision
{
	public string $EnVisionDir;
	public string $EnVisionFile; // path leading to cereal

	public int    $commands_executed;

	public int		$usage_statements;
	public int		$files_included;
	public int		$files_opened;
	public int		$files_closed;
	public int		$file_bytes_written;
	public int		$file_bytes_read;
	public int		$files_created;
	public int		$files_deleted;

	public string	$last_exception; // $EnVision->last_exception="db->query-fail __FILE__:__LINE__";
	public int		$last_exception_code;
	public int		$exceptions_thrown;
	public int		$exceptions_caught;

	// when a user uses new or unset or objects go out of scope and obliterate or a program ends, started
	// etc. 
	public int		$objects_instantiated;
	public int		$objects_invoked;
	public int		$objects_destroyed;
	public int		$objects_cloned;
	public int		$objects_loaded;
	public int		$objects_saved;
	public int		$objects_serialized;
	public int		$objects_unserialized;
	public int		$objects_put_to_sleep;
	public int		$objects_awoken;
	public int		$objects_extended;

	/// these only make sense to increment inside the logger class or if there were logs using a different method
	//  and they are incremented for completeness.
	public int		$logger_objects_instantiated;
	public int		$logger_objects_destroyed;
	public int		$log_files_created;
	public int		$log_files_opened;
	public int		$log_files_closed;
	public int		$log_files_rotated;
	public int		$log_lines_written;
	public int		$log_lines_in_color;
	public int		$log_lines_in_monochrome;
	public int		$log_bytes_written;
	public int		$log_init_lines;
	public int		$log_unclassified_lines;
	public int		$log_warning_lines;
	public int		$log_attention_lines;
	public int		$log_debug_functions_entered;
	public int		$log_debug_functions_inout;
	public int		$log_debug_functions_left;
	public int		$log_debug_warnings;
	public int		$log_debug_hilights;
	public int		$log_debug_variables;
	public int		$log_debug_sql_queries;
	public int		$log_debug_lines_logged;
	public int		$log_stats_lines;
	public int		$log_stop_lines;
	public int		$log_info_lines;
	public int		$log_error_lines=0;
	public int		$log_emergency_lines;
	public int		$log_alert_lines;
	public int		$log_crit_lines;
	public int		$log_notice_lines;

	public int		$functions_entered;	// user created functions entered
	public int		$functions_left;			// user created functions exited

	public int		$db_objects_instantiated;
	public int		$db_objects_destroyed;
	public array	$db_types = array();
	public array	$db_names = array();
	public array	$db_users = array();
	public array	$db_hosts = array();
	public array	$db_ports = array();
	public int		$db_disconnections;
	public int		$db_connections;
	public int		$db_queries;
	public int		$db_rows;
	public int		$db_fields;
	public int		$db_failed_queries;
	public int		$db_successful_queries;
	public int		$db_creates;
	public int		$db_inserts;
	public int		$db_updates;
	public int		$db_selects;
	public int		$db_querytime;
	public int		$db_query_bytes_in;
	public int		$db_query_bytes_out;

	public int    $ads_served;

	private bool  $marked_for_destruction; // intention: if it is not empty, the object will vaporize (unset/zeroed/removed/cleared)

	// user_registrations = number of times the registration routine has been executed for the user ()
	// user_logins = number of logins by this user
	// user_logins_today = number of logins by this user today
	// user_last_login = date('ddMonYYY HH:MM:SS');
	// user_logouts = number of times this user logged out
	// user_logouts_today = number times this user logged out today
	// user_last_logout = date('ddMonYYYY HH:MM:SS');
	// user_last = /usr/bin/logname
	public int		$user_registrations;
	public int		$user_logins;
	public int		$user_logins_today;
	public int		$user_last_login;
	public int		$user_logouts;
	public int		$user_logouts_today;
	public int		$user_last_logout;
	public string	$user_last;
	// added 07May2023
/*	public int              $user_objects_instantiated;
	public int              $user_objects_destroyed;
	public int              $user_object_bytes_written;
	public int              $user_object_bytes_read;
	public int              $user_object_db_selects;
	public int              $user_object_db_upserts;
*/
/*
	public array	$user_data = array("id", 
															"regdata" => array ("regdate", "regip"),
															"logindata" => array ("firstlogin", "lastlogin", "numlogins", "lastlogout"),
															"pagedata" => array ("pagename","last_view","times_viewed","ads_served","ads_clicked")

												 );
*/
	// if this class is autoloaded, and there is a directory assigned in the environment
	// try to locate the file which describes a saved EnVision object.  If it doesn't 
	// exist or is undefined, initialize a new object.
	public function __autoload ()
	{
		$this->initializeObject();
		if (!$this->eatCereal(getenv("ENV_VISIONDIR"). "/objects/cereal"))
		{
			$this->readDataFiles();
			$this->objects_instantiated++;
			$this->functions_entered++;
			$this->functions_left++;
			$this->writeDataFiles();
		}
		return;
	}

	// if we are provided a file, try to use it... if we were told to unserialize, then ONLY try to do that
	// if we were provided nothing, look for the "cereal" file in $ENV_VISIONDIR/objects and load it if it is
	// there, otherwise initialize a new one and save it there and return.  Do nothing beyond that, that's what
	// the other methods are for
	public function __construct (string $EnVisionFile="", bool $cerealize=false) 
	{
		$this->initializeObject();
		$this->initEnVisionFile();
		A:
		$cerealize = $cerealize ?? false;
		$functions_entered=0;
		$functions_left=0;
		$objects_instantianted=0;
		$objects_unserialized=0;
		$files_opened=0;
		$files_closed=0;
		$file_bytes_read=0;
		$file_bytes_written=0;
		$this->EnVisionDir = getenv("ENV_VISIONDIR");
		if ($cerealize === true)
		{
			// this will never be executed?
			if (isset($EnVisionFile))
			{
				if ((empty($EnVisionFile)) || (!file_exists($EnVisionFile)))
				{
					unset ($EnvisionFile);
					unset ($cerealize);
					goto A;
				}
				else
				{
					$this->readDataFiles();
//				$this->unserialize($this->eatCereal());
					$this->files_opened++;
					$this->files_closed++;
					$this->functions_entered+=3;
					$this->functions_left+=3;
					$this->objects_serialized++;
					$this->objects_unserialized++;
					$this->files_opened++;
					$this->files_closed++;
					$this->functions_entered+=3;
					$this->functions_left+=3;
					$this->objects_serialized++;
					$this->objects_unserialized++;
					return;
				}
			}
			else
			{
				unset ($EnVisionFile);
				unset ($Cerealize);
				goto A;
			}
		}
		$functions_entered++;
		// this will never be executed?
    if ((!empty($EnVisionFile)) && ($EnVisionFile!==null))
    {
      // if the user passed a value here and it is not empty, null, false or equiv, use it instead of discovery 
      $this->EnVisionFile = $EnVisionFile;
    }
		else
		{	// we will just not use the $_ENV form and use getenv instead
//			if (!isset($_ENV)) $_ENV = getenv();
			// if envvar ENV_VISIONDIR is not set, 
			if (getenv("ENV_VISIONDIR")===NULL) 
			{	
				// if envvar ENV_VISIONDIR is null or false, we have to set it to something
				// or we will die horribly.... use the following as the default
				$this->EnVisionFile = getenv("ENV_SHARED") . "/vision/" . getenv("ENV_MYIP") . "/objects/cereal";
			}
			else
			{
				// if the value in the environment is not null, use it as the default
				$this->EnVisionFile = getenv("ENV_VISIONDIR") . "/objects/cereal";
			}
		}
		// we should have a valid "Cereal"ized file now? let's try to eat it.
		if ((file_exists($this->EnVisionFile)) && (is_file($this->EnVisionFile)))
		{
			$this->readDataFiles();
//			if (!($sData = file_get_contents($this->EnVisionFile)) === false) {
//				$this->file_bytes_read += strlen($sData);
//				$this->files_opened++;
//				$this->files_closed++;
//				$this->unserialize($sData);
//			}
		}
//		$this->writeDataFiles();
		$this->functions_left++;
	}

	// attempt to unserialize the previously written object
	// from the given filename or return false on failure
	public function eatCereal (string $filename="") : bool
	{
//		$filename ?? $filename = $this->EnVisionFile ?? return (false);
		if (empty($filename))
		{
			if (empty($this->EnVisionFile))
			{
				return false;
			}
			else
			{
				if (file_exists($this->EnVisionFile))
				{
					$data = file_get_contents($this->EnVisionFile);
					$this->file_bytes_read += strlen($data);
//					$obj = unserialize($data);
				}
			}
		} 
		else
		{
			if (!file_exists($filename))
			{
				return false;
			}
			else
			{
				$data = file_get_contents($filename);
				$this->file_bytes_read += strlen($data);
			}
		}
		if (!empty($data))
		{
			// unserialize the data we just read from $filename/EnVisionFile
			$obj = $this->unserialize($data);
			$this->file_bytes_read+=strlen($data);
			$this->files_opened++;
			$this->files_closed++;
			$this->functions_entered+=3;
			$this->functions_left+=3;
			$this->objects_serialized++;
			$this->objects_unserialized++;
			return true;
		}
		return false;
	}


	public function unserialize ($data) : bool
	{
		// first order of business... verify that the incoming data has not
		// been tampered with.
		$this->initializeObject();
		if ($dat = (unserialize($data,array('EnVision'))) === false)
		{
			return false;
		}
		if ((is_object($dat)) && (is_a($dat,"EnVision")))
		{
			$this->objects_unserialized++;
			$this->file_bytes_read+=strlen($data);
		}
		return true;
	}

	public function __invoke(object &$obj)
	{
		// why not just make it write out the datafiles instead of all this bullshit
		$data = $this->serialize($obj);
		$this->writeOut_Envision();
	
//		$this->unserialize($data,array("EnVision"));
//		$this->readDataFiles();
//		$this->writeDataFiles();
		return;


		// if we invoke, it should be a deserialized object that may or may not be
		// the right class as I have been reading on php.net.. So let's do our best
		// to guarantee that it is the correct type.  Invoking wil serve the purpose
		// of an automatic merge facility....

		$tOBJ = new EnVision($this->EnVisionFile);
		
		$OKAY=true;
		$same_field_nums = false;
		$same_class_name = false;
		$same_called_class = false;
		$same_parent_class = false;
		$same_subclass = false;
		$same_method_nums = false;
		$same_method_names = false;
		$tOBJ_methods = null;
		$obj_methods = null;
		// Let's do the simple test first.... did it maintain cohesion? did we receive
		// some other kind of object from someone?
		if (!is_a($obj, "EnVision"))
		{
			// we're not an EnVision object, but do we have compatible fields?
			// 1] do we have the same class names??
			if (get_class($tOBJ) === get_class($obj))
			{
				$same_class_name = true;
			}
			// 2] do we have the same amount of fields?
			if (sizeof($obj) === sizeof($tOBJ))
			{
				// they do have the same number of fields
				$same_field_nums = true;
			}
			// 3] do they have the same parent?
			if ((get_parent_class($tOBJ)) === (get_parent_class($obj)))
			{
				$same_parent_class = true;
			}
			if ((get_called_class($tOBJ)) === (get_called_class($obj)))
			{
				$same_called_class = true;
			}
			$tOBJ_methods = get_class_methods($tOBJ);
			$obj_methods = get_class_methods($obj);
			// this next one takes care of 2 questions simultaneously...
			// if the following is true, they have the same method names amd
			// the same number of methods.
			if ($tOBJ_methods === $obj_methods)
			{
				$same_method_names = true;
				$same_method_nums = true;
			}
			$tProps = get_class_vars($tOBJ);
			$oProps = get_class_vars($obj);
			if ($tProps === $oProp)
			{
				$same_var_names = true;
				$same_var_nums = true;
				// attempt apples:apples test
				for ($i=0; $i<=((sizeof($tProps)+sizeof($oProps))/2); $i++)
				{
					if (!((gettype($tProps)) === (gettype($oProps))))
					{
//						echo "anomaly detected: " . $tProps[$i] .":" . gettype($tProps[$i]). " and ". $oProps[$i] . ":" . gettype($oProps[$i]) ." are different." . PHP_EOL;
					}
					else
					{
						// starting to think these really are identical.
						$same_prop_types = true;
					}
				}
			}
		} 
	}

	public function serialize (EnVision $eObj) : string
	{ 
		$this->functions_entered++;
		// let's do some clean up before we become transmittable
		flush();
		$this->functions_entered++;
		$this->functions_left++;
		$content = serialize($eObj);
		if (!empty($content))
		{
			$this->objects_serialized++;
			$this->functions_entered++;
			$this->functions_left++;
		}
		($this->EnVisionFile ?? $this->EnVisionFile = "/tmp/.EnVisionFile");
		if (is_writable($this->EnVisionFile))
		{
			$this->functions_entered++;
			$this->functions_left++;
//			unlink ($this->EnVisionFile);
//			$this->files_deleted++;
			$this->writeDataFiles();
			$bytes_written = file_put_contents($this->EnVisionFile,$content,LOCK_EX);
			$this->functions_entered++;
			$this->functions_left++;
			if ($bytes_written !== false)
				$this->file_bytes_written += $bytes_written;
			$this->files_opened++;
			$this->files_closed++;
		}
		$this->functions_left++;
		return strval($content);
	}
//	file_put_contents("/env/vision/172.20.21.2/objects/envision",serialize($this),LOCK_EX);
//		$fp = fopen ("/env/vision/172.20.21.2/objects/envision","w+");
		// since we don't know how big our object is in bytes before writing,
		// we are going to have to ascertain this in the unserialize event and add it there.
//		$contents = serialize($this);
//		if (fwrite($fp,$contents,strlen($contents)) === false) {
//			echo "FATAL: There was an error while attempting to write serialized data" . PHP_EOL;
//			fclose ($fp);
//			exit(-1);
//		}
//		fclose($fp);
//		echo "successfully serialized";
		// we are going to also have to add the functions_left from this action to the unserialze
		// event to maintain accuracy as we will not be able to track what happens to us when we
		// become serialized.

	public function __destruct ()
	{
		$this->functions_entered++;
		if ($this->marked_for_destruction===false)
		{
		// if we are not going to kill the object off, serialize it
			($this->EnVisionFile ?? $this->EnVisionFile = "/tmp/.EnVisionFile");
//			if (is_writable($this->EnVisionFile)) $this->file_bytes_written += file_put_contents ($this->EnVisionFile,$this->serialize($this),LOCK_EX);
			$this->functions_left++;
			$this->objects_destroyed++;
			$this->writeDataFiles();
			return;
		}
		else
		{
			true;
		}
		$this->functions_left++;
		$this->objects_destroyed++;
		$this->writeDataFiles();
		return;
	}

	private function initializeObject() : void
	{
		// general data types and stats
//		if (is_dir(getenv("ENV_VISIONDIR"))) $this->EnVisionDir = getenv("ENV_VISIONDIR");
//		if (getenv("ENV_VISIONDIR") . "/objects/cereal")
//		{
//			$this->EnVisionFile = getenv("ENV_VISIONDIR") . "/objects/cereal";
//		}
//		else
//		{
//			$this->EnVisionFile = "";
//		}
		// file objects
		$this->commands_executed = intval(0);
		$this->usage_statements = intval(0);
		$this->files_included = intval(0);
		$this->files_opened = intval(0);
		$this->files_closed = intval(0);
		$this->file_bytes_written = intval(0);
	 	$this->file_bytes_read = intval(0);
		$this->files_created = intval(0);
		$this->files_deleted = intval(0);
		$this->last_exception = ""; // $EnVision->last_exception="db-query-fail __FILE__:__LINE__";
		$this->last_exception_code = intval(0);
		$this->exceptions_thrown = intval(0);
		$this->exceptions_caught = intval(0);
		// general - generic objects
		$this->objects_instantiated = intval(0);
		$this->objects_invoked = intval(0);
		$this->objects_destroyed = intval(0);
		$this->objects_cloned = intval(0);
		$this->objects_loaded = intval(0);
		$this->objects_saved = intval(0);
		$this->objects_serialized = intval(0);
		$this->objects_unserialized = intval(0);
		$this->objects_put_to_sleep = intval(0);
		$this->objects_awoken = intval(0);
		$this->objects_extended = intval(0);
		$this->objects_extended = intval(0);
		// logger module specific
		$this->logger_objects_instantiated = intval(0);
		$this->logger_objects_destroyed = intval(0);
		$this->log_files_created = intval(0);
		$this->log_files_opened = intval(0);
		$this->log_files_closed = intval(0);
		$this->log_files_rotated = intval(0);
		$this->log_lines_written = intval(0);
		$this->log_lines_in_color = intval(0);
		$this->log_lines_in_monochrome = intval(0);
		$this->log_bytes_written = intval(0);
		$this->log_init_lines = intval(0);
		$this->log_unclassified_lines = intval(0);
		$this->log_warning_lines = intval(0);
		$this->log_attention_lines = intval(0);
		$this->log_debug_functions_entered = intval(0);
		$this->log_debug_functions_inout = intval(0);
		$this->log_debug_functions_left = intval(0);
		$this->log_debug_warnings = intval(0);
		$this->log_debug_hilights = intval(0);
		$this->log_debug_variables = intval(0);
		$this->log_debug_sql_queries = intval(0);
		$this->log_debug_lines_logged = intval(0);
		$this->log_stats_lines = intval(0);
		$this->log_stop_lines = intval(0);
		$this->log_info_lines = intval(0);
		$this->log_error_lines = intval(0);
		$this->log_emergency_lines = intval(0);
		$this->log_alert_lines = intval(0);
		$this->log_crit_lines = intval(0);
		$this->log_notice_lines = intval(0);
		$this->functions_entered = intval(0);
		$this->functions_left = intval(0);
		$this->EnVisionFile = "";
		$this->EnVisionDir = "";
		$this->initEnVisionFile();
		// database object
		$this->db_objects_instantiated = intval(0);
		$this->db_objects_destroyed = intval(0);
		$this->db_types = array(null);
		$this->db_names = array(null);
		$this->db_users = array(null);
		$this->db_hosts = array(null);
		$this->db_ports = array(null);
		$this->db_disconnections = intval(0);
		$this->db_connections = intval(0);
		$this->db_queries = intval(0);
		$this->db_rows = intval(0);
		$this->db_fields = intval(0);
		$this->db_failed_queries = intval(0);
		$this->db_successful_queries = intval(0);
		$this->db_creates = intval(0);
		$this->db_inserts = intval(0);
		$this->db_updates = intval(0);
		$this->db_selects = intval(0);
		$this->db_querytime = intval(0);
		$this->db_query_bytes_in = intval(0);
		$this->db_query_bytes_out = intval(0);
		$this->ads_served = intval(0);
		// should the data be destroyed?
		$this->marked_for_destruction = false;
		// add as much as we can extract here

		// The NSE class will need to be integrated and added

		// The Host class will need to be more tightly integrated
		/// it currently causes massive lag on load for some reason
		/// it needs to be looked at.
		// This was largely done 10Oct2021-11Oct2021.
	
		// The Client/Server classes will need to be added

		// The Reaper class will need to be added

		// The Signal class will need to be added
//		$this->signal_objects_created;
//		$this->signals_sent;
//		$this->signals_caught;
//		$this->signal_parents_created;
//		$this->signal_objects_destroyed;

		// The User class will need to be added
		$this->user_registrations = intval(0);
		$this->user_logins = intval(0);
		$this->user_logins_today = intval(0);
		$this->user_last_login = intval(0);
		$this->user_logouts = intval(0);
		$this->user_logouts_today = intval(0);
		$this->user_last_logout = intval(0);
		$this->user_last = "";
/*
		$this->user_objects_instantiated;
		$this->user_objects_destroyed;
		$this->user_object_bytes_written;
		$this->user_object_bytes_read;
		$this->user_object_db_selects;
		$this->user_object_db_upserts;
*/
// The Utility class will need to be added
  // added 07May2023
	// The Timer class will need to be added
  // $this->timers_instantiated;
  // $this->timers_started;
  // $this->timers_stopped;
  // $this->timers_destroyed;
	//** The User class might need to be added
		
	//** The Display class might need to be added

	// The Bind class will need to be completed and then added

	} // end of initializeObject ()
	
	private function readDataFiles () : int
	{	
		$this->functions_entered++;
		$totalBytes = 0;
		$count = 0;
		foreach (get_class_vars("EnVision") as $varname => $varval)
		{
			if (empty($varname)) continue;
			$numBytes = 0;
	$fname = ($this->EnVisionDir ?? (getenv("ENV_VISIONDIR") ?? ("/env/vision/" . (getenv("LOGNAME")) ?? "/env/vision/apache"))) . "/objects/$varname";
	//		var_dump($fname,$varval	);
			if (!file_exists($fname))
			{
	//			echo "$fname not found, skipping.." . PHP_EOL;
				continue;
			}
//			if (is_array($this->$varname))
//			{
//$this->$varname		if (!empty($this->$varname))
//				{
//					array_push($this->$varname,file_get_contents($fname));
//					$numBytes = strlen(file_get_contents($fname));
//				}
//			}
//			else
//			
			$vartype = gettype ($this->$varname);
			switch (strtolower($vartype))
			{
				case "boolean":
				case "bool":
					$this->$varname = boolval(file_get_contents($fname));
					break;
				case "integer":
				case "int":
					$this->$varname = intval(file_get_contents($fname));
					break;
				case "double": //(for historical reasons "double" is returned in case of a float, and not simply "float")
					break;
				case "EnVision - string":
				case "string":
					$this->$varname = strval(file_get_contents($fname));
					break;
				case "array":
					if (!empty($this->$varname))
					{
						array_push($this->$varname,file_get_contents($fname));
						$this->$varname = array_unique($this->$varname, SORT_STRING);
						$numBytes = strlen(file_get_contents($fname));
					}
					break;
				case "object":
					break;
				case "resource":
					break;
				case "resource (closed)": // as of PHP 7.2.0
					break;
				case "NULL":
					break;
				case "unknown type":
					break;
				default:
					break;
			}
/*
			if (!is_array($this->$varname)) {
				if (is_int($this->$varname)) {
					$this->$varname = intval(file_get_contents($fname));
				} elseif (is_bool($this->$varname)) {
					$this->$varname = boolval(file_get_contents($fname));
				} elseif (is_string($this-$varname)) {
					$this->$varname = strval(file_get_contents($fname));
				} else {
					continue;
				}
			}
*/
//			$numBytes = strlen($this->{$varname});
		}
		if ($numBytes <= 0)
		{
			$numBytes += 2;
		}
		$this->files_opened++;
		$this->files_closed++;
		$this->file_bytes_read += $numBytes;
		$totalBytes += $numBytes;
		$this->functions_left++;
		return intval($totalBytes);
	}

	public function writeDataFiles () : int
	{
		$this->functions_entered++;
		$numBytes = 0;
		$count = 0;
		$result = null;
		$fbytes = 0;
		if (!$this->validateVisionLocation())
		{
			$this->functions_left++;
			var_dump($this->EnVisionDir);
			return 0;
		}
		if (empty($this->EnVisionDir) || !is_dir($this->EnVisionDir))
		{
			if (empty($this->EnVisionDir))
			{
				// build $this->EnVisionDir ...
				if (!is_null(getenv("ENV_SHARED")))
				{
					$shared = $_ENV["ENV_SHARED"] ?? getenv("ENV_SHARED");
				}
				else
				{
					$shared = "/env";
				}
				$logname = null;
				$logname = trim(shell_exec("id -u -n")) ?? $_ENV["UID"] ?? getenv("UID") ?? "apache";
				$shared = $shared ?? "/env";
				//$myip = getenv("ENV_MYIP") ?? shell_exec("myIP") ?? shell_exec("echo $(echo $(ip -br addr show dev br) | cut -f3 -d' ' | cut -f1 -d '/')") ?? '127.0.0.1';
				$myip = getenv("ENV_MYIP") ?? shell_exec("myIP") ?? shell_exec("echo \"$(echo \"$(ip -br addr show dev br)\" | cut -f3 -d\" \" | cut -f1 -d \"/\")\"") ?? "127.0.0.1";
				//$logname = shell_exec("logname") ?? shell_exec("id ". getenv("$UID") . " | cut -f2 -d '(' | cut -f1 -d ')'") ?? 'apache';
				if (strcmp("no user name",$logname)===0) $logname = "nse";
				$this->EnVisionDir = ($shared ?? "/env") . "/vision/" . $myip . "/" . ($logname ?? "nse") . "/objects";
				if (!is_dir($this->EnVisionDir)) {
					$this->EnVisionDir = "/env/vision/172.20.20.254/apache";
				}
				shell_exec("logger -t EnVision " . $this->EnVisionDir);
			}
			if (!is_dir($this->EnVisionDir))
			{
				shell_exec("mkdir -p " . $this->EnVisionDir);
			}
			if (!$this->WriteOut_EnVision())
			{
				//shell_exec("logger -t EnVision 'EnVision->writeDataFiles has failed to write out its files'");
				$a = true;
			}
		}
		//foreach (get_class_vars("EnVision") as $varname => $varval)
		foreach (get_class_vars("EnVision") as $varname => $varval)
		{
			if (empty($this->$varname)) continue;
			//if (empty($this->EnVisionDir))
			//{
			//	$this->EnVisionDir = DEFAULT_VISIONDIR;
			//}
			$fname = $this->EnVisionDir . "/objects/" .  $varname;
			// $fname = $this->EnVisionDir;
			if (!file_exists($fname)) 
			{
				$this->files_created++;
			}
			if (is_array($this->$varname))
			{
				// join the array values by a comma and write that glom to the file
				if (empty(array_unique($this->$varname))) {
					$this->functions_left++;
					return (0);
				}
				else
				{
					$strrep = array_unique(explode('/,/',join(',', $this->$varname)));
				}
				if (empty($strrep))
				{
					$this->functions_left++;
					return (0);
				}
				$fbytes = file_put_contents($fname, $strrep, LOCK_EX);
			}
			elseif (is_string($this->$varname))
			{
				$fbytes = file_put_contents($fname, $this->$varname, LOCK_EX);
			}
			if (!$fbytes) continue;
			$this->files_opened++;
			$this->files_closed++;
			$this->file_bytes_written += $fbytes;
			$numBytes += $fbytes;
			//if ($fbytes === false)
			//{
			//	$this->functions_left++;
			//	return $numBytes;
			//}
			$count++;
		}
		$this->functions_left++;
		return $numBytes;
	}

	private function validateVisionLocation () : bool
	{
		$this->functions_entered++;
		if (!empty($this->EnVisionDir))
		{
			if (is_dir($this->EnVisionDir))
			{
				return true;
			}
		}
		else // $this->EnVisionDir is empty for some reason
		{
			if (is_dir(DEFAULT_VISIONDIR))
			{
				$this->EnVisionDir = DEFAULT_VISIONDIR;
				return true;
			}
			else
				$this->EnVisionDir = getenv("ENV_VISIONROOT") . "/" . (getenv("ENV_MYIP") ?? "172.20.21.1") . "/" . getenv("ENV_WEBADMIN");
			try
			{
				if (getenv("ENV_VISIONROOT") === null)
				{
					$this->exceptions_thrown++;
					throw new Exception ("Environment variable ENV_VISIONROOT is not set",false);
				}
				if (getenv("ENV_VISIONDIR") === null)
				{
					$this->exceptions_thrown++;
					throw new Exception ("Environment variable ENV_VISIONDIR is not set",false);
				}
				$this->EnVisionDir = ((getenv("ENV_VISIONDIR")) ?? ("/env/vision/" . (getenv("ENV_MYIP") ?? "172.20.21.1") ."/". (getenv("LOGNAME") ?? "apache")));
//				echo $this->EnVisionDir . PHP_EOL;
//				$this->EnVisionDir = "/env/vision/172.20.21.1/apache";
			}
			catch (Exception $unsetVISION)
			{
				$this->exceptions_caught++;
				$this->last_exception = $unsetVISION->getMessage();
				$this->last_eception_code = $unsetVISION->getCode();
				$this->EnVisionDir = "/env/vision/" . (getenv("ENV_MYIP") ?? "172.20.21.1") . "/apache";
			}
		}
		if (empty($this->EnVisionFile))
		{
			try
			{
				if (is_dir($this->EnVisionDir . "/objects"))
				{
		      $this->EnVisionFile = $this->EnVisionDir . "/objects/cereal";
		    } else {
		      $this->exceptions_thrown++;
		      throw new Exception ("EnVision objects directory does not exist: " . $this->EnVisionDir,false);
		    }
		  }
			catch (Exception $unsetVISION)
			{
				$this->exceptions_caught++;
				$this->last_exception = $unsetVISION->getMessage();
				$this->last_exception_code = $unsetVISION->getCode();
				$this->functions_left++;
				$ip = getenv("ENV_MYIP");
				if (empty($ip)) $ip = "172.20.21.1";
				$this->EnVisionDir = "/env/vision/$ip/apache";
				$this->EnVisionFile = $this->EnVisionDir . "/objects/cereal";
				return ($unsetVISION->getCode());
			}
		}
		$this->functions_left++;
		return (true);
	}

	private function initEnVisionFile() : bool
	{
		$this->functions_entered++;
		try
		{
			if (empty($this->EnVisionFile))
			{
				if (!$this->validateVisionLocation())
				{
					if (getenv("ENV_SHARED") === null) throw new Exception("Environment ENV_SHARED is not set",false);
					if (getenv("ENV_VISIONDIR") === null) throw new Exception("Environment ENV_VISIONDIR is not set", false);
					if (@!mkdir($this->EnVisionDir."/objects",0755,false)) throw new Exception("Failed to create directory: ".$this->EnVisionDir."/objects", false);
					if (@!unlink($this->EnVisionDir."/objects/cereal")) throw new Exception("Failed to erase cereal file",false);
					if (!touch($this->EnVisionDir."/objects/cereal",null,null)) throw new Exception("Failed to touch: ".$this->EnVisionDir."/objects/cereal",false);
					$this->EnvisionFile = $this->EnVisionDir."/objects/cereal";
					$this->functions_left++;
					return (true);
				}
			}
		}
		catch (Exception $initFail)
		{
			$this->exceptions_caught++;
			$this->last_exception = $initFail->getMessage();
			$this->last_exception_code = $initFail->getCode();
			$this->functions_left++;
			return ($initFail->getCode());
		}
		$this->functions_left++;
		return (true);
	}

	// this is already being done in cereal, is this necessary? It might be?
	// it seems to be... unserialize actually returns an array instance which is definitely
	// not what I want. Going to need to figure out how to correct that.
	public function WriteOut_EnVision () : bool
	{
		//	we need to write out the data we are collecting to the $ENV_VISIONDIR/objects files, 
		//	this method will do just that.
		try 
		{
			$envroot = null;
			$this->functions_entered++;
			if (!$this->validateVisionLocation())	throw new Exception ("Unable to validate EnVisionFile",false);
		}
		catch (Exception $envFail)
		{
			$this->exceptions_caught++;
			$this->last_exception = $envFail->getMessage();
			$this->last_exception_code = $envFail->getCode();
			$this->functions_left++;
			return ($envFail->getCode());
		}
		// what is the point here... we just set it up there
		try
		{
			if (empty($this->EnVisionFile))
			{
				if (is_dir(getenv("ENV_SHARED")))
				{
					if (is_dir(getenv("ENV_VISIONDIR") . "/" . getenv("ENV_MYIP")))
					{
							true;
//						$this->EnVisionDir = getenv("ENV_VISIONROOT") . "/" . getenv("ENV_MYIP") . "/" . getenv("ENV_WEBADMIN");
//						if (is_dir($this->EnVisionDir . "/objects"))
//						{
//							$this->EnVisionFile = $this->EnVisionDir . "/objects/cereal";
//						} else {
//							$this->functions_left++;
//							return false;
//						}
					} // the environment is not setup
					else
					{
						if (is_dir(getenv("ENV_VISIONROOT") . "/". getenv("ENV_MYIP") . "/" . getenv("ENV_WEBADMIN")))
						{
							$this->EnVisionDir = getenv("ENV_SHARED")."/".getenv("ENV_MYIP");
							if (file_exists($this->EnVisionDir."/objects/cereal"))
							{
								$this->EnVisionFile = $this->EnVisionDir."/objects/cereal";
							}
							else
							{
								$this->exceptions_thrown++;
								$this->functions_left++;
								throw new Exception ("blah",false);
							}
						}
						else
						{
							$this->exceptions_thrown++;
							$this->functions_left++;
							throw new Exception ("write out failed",false);
						}
					}
				}
				else
				{
					$this->exceptions_thrown++;
					$this->functions_left++;
					throw new Exception("write out failed",false);
				}
			}
		}
		catch (Exception $failWrite)
		{
			$this->exceptions_caught++;
			$this->last_exception = $failWrite->getMessage();
			$this->last_exception_code = $failWrite->getCode();
			$this->functions_left++;
			return boolval($failWrite->getCode());
		}
		
		// this is cereal
		try
		{
			if ((!file_exists($this->EnVisionFile)) ||  (empty($this->EnVisionFile)))
			{
				$this->exceptions_thrown++;
				throw new Exception ("INVALID_ENVISIONFILE",false);
			}
		}
		catch (Exception $cerealFail)
		{
			$this->exceptions_caught++;
			$this->last_exception = $cerealFail->getMessage();
			$this->last_exception_code = $cerealFail->getCode();
			$this->functions_left++;
			return ($cerealFail->getCode());
		}
		try
		{
		// this is ENV_VISIONDIR
			if ((!is_dir($this->EnVisionDir)) || (empty($this->EnVisionDir)))
			{
				throw new Exception ("INVALID_ENVISIONDIR",false);
			}
		}
		catch (Exception $dirFail)
		{
			$this->exceptions_caught++;
			$this->last_exception = $dirFail->getMessage();
			$this->last_exception_code = $dirFail->getCode();
			$this->functions_left++;
			return ($dirFail->getCode());
		}
		// home of cereal
		if (!is_dir(getenv("ENV_VISIONDIR") . "/objects"))
		{
			if (mkdir(getenv("ENV_VISIONDIR") . "/objects",0700, true) === false)
			{
				$this->functions_left++;
				return false;
			}
		}
		$count = 0;
		// iterate through each variable in the class
		foreach (get_class_vars("EnVision") as $varname => $varval)
		{
			if (empty($varname)) continue;
			$fname = $this->EnVisionDir . "/objects/$varname";
//			echo "Setting $fname to $varval" . PHP_EOL;
			$fbytes = file_put_contents($fname, $varval, LOCK_EX);
			if ($fbytes === false)
			{
				$this->functions_left++;
				return false;
			}
			else
			{
				$this->file_bytes_written+=$fbytes;
				$this->files_created++;
				$this->files_opened++;
				$this->files_closed++;
				$count++;
			}
	// $this->log_bytes_written += $log->writeLog("Wrote $count files for a total of $bytecount bytes",L_CONSOLE,LOG_INFD);
			$this->functions_left++;
			return true;
		}
	}
}
// Main loader begins here
//require_once "../nse/class_nse.php";
// I don't care about this next line.  Even if I want to rely on the ENV being
// installed, at this point in the game, it is not super beneficial to go searching and
// and patching.  Let bashrc do its thing and we will do ours.  If it's there, great!, if not
// no big deal, just have to employ another discovery method. I don't know if I am going to
// incorporate NSE into this analytics engine or not.. Probably will eventually.
// if (($_ENV['ENV_INSTALLED'] === "1") && (!empty($_ENV['ENV_PHPROOT']))) {
//  require_once $_ENV["ENV_PHPROOT"] . "/nse/class_nse.php";
/*
if (empty(getenv()) || empty(getenv("ENV_VISIONDIR"))) require __FILE__.".inc";
// if we don't have an EnVision object, let's try to find one or create one.
if ((!isset($GLOBALS['EnVision'])) || (!is_a($GLOBALS['EnVision'], "EnVision"))) {
	// if the file exists, try to unserialize it in to an object
	if (file_exists(getenv("ENV_VISIONDIR") . "/objects/cereal")) {
		$GLOBALS['EnVision'] = new EnVision(getenv("ENV_VISIONDIR") . "/objects/cereal",true);
		$GLOBALS['EnVision']->objects_unserialized++;
	} else {
		// failed, just make a new one
		$GLOBALS['EnVision'] = new EnVision();
	}
	// since we're done, let's increment everything and save it for the next call.
	$GLOBALS['EnVision']->objects_instantiated++;
	$GLOBALS['EnVision']->files_opened++;
	$GLOBALS['EnVision']->objects_serialized++;
	$GLOBALS['EnVision']->functions_entered++;
	$GLOBALS['EnVision']->functions_left++;
	// the serialize function uses file locking to write out the data in an attempt to 
	// avoid coliding with any other processes which may be running at the same time.
	$GLOBALS['EnVision']->serialize($GLOBALS['EnVision']);
//var_export($GLOBALS['EnVision']);
	// not sure if exit is appropriate here due to the fact that it could interfere with
	// the web services too...  ANYTHING php is going to be running this, so we have to 
	// think about that type of thing in our design.  Minimally invasive, but collect as
	// much data as feasibly possible.
	return (0);
} else {
//var_export($GLOBALS['EnVision']);
	$GLOBALS['EnVision']->functions_entered++;
	$GLOBALS['EnVision']->functions_left++;
	$GLOBALS['EnVision']->objects_serialized++;
	$GLOBALS['EnVision']->serialize($GLOBALS['EnVision']);
	return (0);
}
*/
?>
