<?php // 7.3.0
// vim: ts=2:sts=2:nocp:ruler:ai
define("LOG_INIT",-1);
define("LOG_UNKN",-2); // only used for assigning colors
define("LOG_WARN",4);
define("LOG_ATTN",5);
define("LOG_DEBUG_INOUT",11);
define("LOG_DEBUG_WARN",12);
define("LOG_DEBUG_HILITE",13);
define("LOG_DEBUG_VAR",14);
define("LOG_DEBUG_QUERY",15);
define("LOG_STATS",254);
define("LOG_STOP",255);

//////////////////////////globally predefined
// define("LOG_EMERG",0);  THIS IS ALREADY PREDEFINED AS "0"
// define("LOG_ALERT",1);  THIS IS ALREADY PREDEFINED AS "1"
// define("LOG_CRIT",2);   THIS IS ALREADY PREDEFIMED AS "2"
// define("LOG_ERR",3);    THIS IS ALREADY PREDEFINED AS "3"
// define("LOG_WARNING",4);   THIS IS ALREADY PREDEFINED AS "4"
// define("LOG_NOTICE",5); THIS IS ALREADY PREDEFINED AS "6"
// define("LOG_INFO",6);   THIS IS ALREADY PREDEFINED AS "5"
// define("LOG_DEBUG",7);  THIS IS ALREADY PREDEFINED AS "7"

class Logger {

	private $isEnVisioned;
	public EnVision $EnVision;

	private $instanceId;

	private $logfp;
	private $logfile;
	private $loggerVersion;

	private $indentLevel;
	private $coloredLogs;

// custom debugging messages (message color)
	private $colorDEBUG_INOUT;	// Blue
	private $colorDEBUG_WARN;	// Yellow on Red
	private $colorDEBUG_HILITE;	// 
	private $colorDEBUG_QUERY;	// Red on Black
	private $colorDEBUG_VAR;	// White on Blue

// predefined constants (type color)
	private $colorEMERG;		// 0 Emergency Information	(Black on Red?)
	private $colorCRITICAL;		// 1 Critical/Fatal Errors	(???)
	private $colorALERT;		// 2 System Alert Messages	(???)
	private $colorERR;		// 3 Error Information		(Red)
	private $colorWARNING;		// 4 Informational Warnings	(Yellow) 
	private $colorNOTICE;		// 5 Informational Notices	(???)
	private $colorINFO;		// 6 Informational Messages	(White) 
	private $colorDEBUG;		// 7 Debugging Messages		(Magenta)
// custom log messages (type color)
	private $colorINIT;		//-1 Initialization Messages	(Cyan)

// cusstom log messages (message color)
	private $colorATTN;		//
	private $colorSTATS;		// Intense Green
	private $colorSTOP;		// Green
	private $colorOKAY;		// this exists to allow for an extra indicator when thing happened as expected
	private $colorUNKNOWN;

	private $colorEND;

	public function __construct (string $logfile = STDOUT,bool $coloredLogs = false, bool $initMessage = true)
	{
		$this->initializeObject();
			// attempt to rescope the object into the logger's view. test now... save time
		if (isset($GLOBALS['EnVision'])) {
			$this->EnVision =& $GLOBALS['EnVision'];
		} else {
			$this->EnVision = new EnVision();
		}
		$this->isEnVisioned = true;
		$this->EnVision->functions_entered+=2;
		$this->EnVision->functions_left++;
		$this->EnVision->objects_instantiated++;
//		} else {
//			$this->isEnVisioned = false;
//		}
		try
		{
			if (($this->openLog ($logfile))===false) {
//				if ($this->isEnVisioned === true) {
				$this->EnVision->exceptions_thrown++;
				$this->EnVision->last_exception="Failed to open logfile   __CLASS__ : __LINE__";
				throw new Exception ("Failed to open '$logFile' for writing", false);
			}
			$this->EnVision->files_opened++;
			$this->EnVision->log_files_opened++;
		//	} //else {
//				if ($this->isEnVisioned === true) {
//					$this->EnVision->files_opened++;
//				}
///			}
		}
		catch (Exception $openFail)
		{
			$this->EnVision->exceptions_caught++;
			$this->EnVision->exceptions_thrown++;
			$this->EnVision->last_exception="Failed to open '$logFile'";
			throw new Exception ("Failed to open '$logFile': $openFail->getMessage()" . PHP_EOL, 1);
		}
		$this->coloredLogs = $coloredLogs;
		if ($this->coloredLogs == false) $this->unsetColors();
		if ($initMessage === true) $this->writeLog("Logger Version " . $this->getLoggerVersion() . " started for $logfile", LOG_INIT);
		$this->EnVision->functions_left++;
	}

	public function __destruct ()
	{
		$this->EnVision->functions_entered++;
		$this->EnVision->functions_left++;
		return;
	}

	public function __clone ()
	{
		if (is_resource($this->logfp))
		{
			fclose ($this->logfp);
		}
		$this->EnVision->files_closed++;
		$this->EnVision->log_files_closed++;
		$this->EnVision->objects_cloned++;
		$this->EnVision->objects_instantiated++;
// I don't think this is what I want
//		$this->instanceId = posix_getpid();
//		do
//		{
//			if (!preg_match('/\.\d*$/', $this->logfile))
//			{
//				$curlog = $this->logfile . ".client.{$this->instanceId}";
//			}
//			else
//			{
//				$curlog = preg_replace('/\.\d+$/',".client.{$this->instanceId}",$this->logfile);
//			}
//		} while (file_exists($curlog));
		$this->openLog ($this->logfile);
		$this->EnVision->files_created++;
		$this->EnVision->files_opened++;
		$this->EnVision->log_files_created++;
		$this->EnVision->log_files_opened++;
		$this->writeLog ("Successfully cloned " . $this->logfile,LOG_NOTICE);
		$this->EnVision->functions_left++;
	}

	public function close (int $code = null)
	{
		$this->EnVision->functions_entered++;
		if ($code === null)
		{
			$this->EnVision->functions_left++;
			return false;
		}
		$this->writeLog("Logging ended",LOG_STOP);
		$this->EnVision->files_closed++;
		$this->EnVision->log_files_closed++;
		fclose ($this->logfp);
		unset ($this->logfile);
		unset ($this->coloredLogs);
		// predefined levels
		unset ($this->colorEMERG);	// LOG_EMERG
		unset ($this->colorALERT);	// LOG_ALERT
		unset ($this->colorCRITICAL);	// LOG_CRIT
		unset ($this->colorERR);	// LOG_ERR
		unset ($this->colorWARNING);	// LOG_WARN
		unset ($this->colorNOTICE);	// LOG_NOTICE
		unset ($this->colorINFO);	// LOG_INFO
		unset ($this->colorDEBUG);	// LOG_DEBUG
		// custom levels below
		unset ($this->colorINIT);
		unset ($this->colorSTATS);
		unset ($this->colorSTOP);
//		unset ($this->colorOKAY);
		unset ($this->colorDEBUG_INOUT);
		unset ($this->colorDEBUG_WARN);
		unset ($this->colorDEBUG_HILITE);
		unset ($this->colorDEBUG_QUERY);
		unset ($this->colorDEBUG_VAR);
		unset ($this->customColors);
		$this->EnVision->functions_left++;
		return $code;
	}

	private function initializeObject () : void
	{
		$this->instanceId			= 0;
		$this->logfp				= null;
		$this->logfile				= null;
		$this->loggerVersion		= "1.3";
		$this->coloredLogs			= false;
		$this->indentLevel			= 0;
		$this->colorDEBUG			= "[1;35m";		// intense magenta
		$this->colorDEBUG_INOUT		= "[1;34m";		// intense blue
		$this->colorDEBUG_WARN		= "[33;41m";		// red background / yellow characters
		$this->colorDEBUG_HILITE	= "[30;42m";		// green background / black characters
		$this->colorDEBUG_QUERY		= "[4;31m";		// underlined red
		$this->colorDEBUG_VAR		= "[3;34;47m";	// blue background / white characters
		$this->colorINIT			= "[1;36m";		// intense cyan
		$this->colorEMERG			= "[30;41m";		// red background / black characters
		$this->colorCRITICAL		= "[30;45m";
		$this->colorWARNING			= "[30;43m";		// black letters / yellow background
		$this->colorALERT			= "[30;46m";
		$this->colorINFO			= "[1;37m";		// intense white
		$this->colorNOTICE			= "[1;33m";		// intense yellow
		$this->colorERR				= "[1;31m";		// intense red
		$this->colorATTN			= "[4;32m";		// underlined green
		$this->colorSTATS			= "[1;32m";		// intense green
		$this->colorSTOP			= "[0;32m";		// green
		$this->colorOKAY			= "[1;32m";		// intense green
//		$this->colorUNKNOWN			= "[1;30m";		// intense black
		$this->colorEND				= "[0m";			// color off
	}

	private function openLog ($logfile = "/dev/stdout") : bool
	{
		try
		{
			$this->logfp = fopen($logfile,"a+");
			if ($this->logfp === false) {
//				if ($this->isEnVisioned === true) {
					$this->EnVision->last_exception = "Failed to open $logfile in append mode " . __FILE__ .":". __LINE__;
					$this->EnVision->exceptions_thrown++;
//				}
				throw new Exception ("Failed open|create $logfile in append mode", $this->logfp);
			}
		}
		catch (Exception $openFail)
		{
//			if ($this->isEnVisioned === true) {
			$this->EnVision->exceptions_caught++;
//			}
			$this->EnVision->functions_left++;
			return false;
		}
		if (is_resource($this->logfp))
		{
//			if ($this->isEnVisioned === true) {
				$this->EnVision->files_opened++;
				$this->EnVision->log_files_created++;
//			}
			$this->logfile = $logfile;
			unset($logfile);
			$this->EnVision->functions_left++;
			return true;
		} // I don't believe that this code will ever be reached unless in the nano second between the open and then check
		  // for resource status the file somehow gets closed (possible FS link failure) Don't underestimate the impossible
		  // though.  It *COULD* happen. This would likely save the day in that situation.
		unset($logfile);
		$this->EnVision->functions_left++;
		return false;
	}

	// attempts to rotate the currently opened log file
	public function rotate () : bool
	{
//		$rotate_date = date('Y_m_d_H_i_s_u', microtime(true));
		$rdate = new DateTime("now");
		$rotate_date = $rdate->format('Y_m_d_H_i_s_u');
		// check if the log file pointer is still a resource
		if (is_resource($this->logfp))
		{
			$this->writeLog("Rotating log to {$this->logfile}.$rotate_date", LOG_INFO);
			// if it is, try to close it
			try
			{
				if (fclose($this->logfp) === false) {
					$this->EnVision->exceptions_thrown++;
					$this->EnVision->last_exception="Failed to close resource associated with {$this->logfile}";
					throw new Exception ("Failed to close resource associated with {$this->logfile}",1);
				}
			}
			// if we failed, print the message and return false
			catch (Exception $logEx)
			{
				$this->EnVision->exceptions_caught++;
				echo $logEx->getMessage() . PHP_EOL;
				$this->EnVision->functions_left++;
				return false;
			}
			$this->EnVision->log_files_closed++;
		}
		// the file pointer is invalid.. throw an exception to the caller
		else
		{
			$this->EnVision->functions_left++;
			return $this->writeLog ("Unable to rotate invalid resource associated with {$this->logfile}",1);
		}

		// attempt to rename the log file to {logfile}.{rotate_date}
		try
		{
			$new_name = "{$this->logfile}.{$rotate_date}";
			// if renaming the file fails, throw an exception
			if (file_exists($new_name)) throw new Exception ("Refusing to overwrite $new_name",1);
			if (rename($this->logfile, $new_name) === false)
			{
				$this->writeLog("Attempt to rotate log file to $new_name failed", LOG_ERR);
				$this->EnVision->exceptions_thrown++;
				$this->EnVision->last_exception="Unable to rename {$this->logfile} to $new_name";
				throw new Exception ("Unable to rename {$this->logfile} to $new_name",1);
			}
		}
		// renaming failed, print a message and return false
		catch (Exception $logEx)
		{
			$this->EnVision->exceptions_caught++;
			echo $logEx->getMessage() . PHP_EOL;
			$this->EnVision->functions_left++;
			return false;
		}

		// try to reopen a new version of the log file
		try
		{
			// if reopening fails, throw an exception otherwise return true
			if (!$this->openLog($this->logfile))
			{
				$this->EnVision->exceptions_thrown++;
        $this->EnVision->last_exception="Failed to reopen {$this->logfile}";
				throw new Exception ("Failed to reopen {$this->logfile}", 1);
			}
			$this->EnVision->log_files_created++;
			$this->EnVision->log_files_opened++;
			// attempt to write a message to the new log file
			$rdate = new DateTime("now");
			$this->EnVision->functions_left++;
			return ($this->writeLog("New log started on " . $rdate->format('Y-m-d H:i:s-u'), LOG_INIT));
		}
		catch (Exception $logEx)
		{
			$this->EnVision->exceptions_caught++;
			echo $logEx->getMessage() . PHP_EOL;
			$this->EnVision->functions_left++;
			return false;
		}
	}

	private function unsetColors () : void
	{
		$this->EnVision->functions_entered++;
		$this->colorDEBUG			= "";  // 7
		$this->colorDEBUG_INOUT		= "";
		$this->colorDEBUG_WARN		= "";
		$this->colorDEBUG_HILITE	= "";
		$this->colorDEBUG_QUERY		= "";
		$this->colorDEBUG_VAR		= "";
		$this->colorINIT			= "";
		$this->colorINFO			= "";  // 6
		$this->colorWARNING			= "";  // 4
		$this->colorERR				= "";  // 3
		$this->colorALERT			= "";  // 1
		$this->colorNOTICE			= "";  // 5
		$this->colorEMERG			= "";	// 0
		$this->colorCRITICAL		= "";  // 2
		$this->colorATTN			= "";
		$this->colorSTATS			= "";
		$this->colorSTOP			= "";
		$this->colorUNKNOWN			= "";
		$this->colorOKAY			= "";
		$this->EnVision->functions_left++;
	}

	public static function printLevels () : void
	{
		$this->EnVision->functions_entered++;
		echo "Available log levels:" . PHP_EOL;
		echo "LOG_INIT         |-1" . PHP_EOL;
		echo "LOG_EMERG        | 0" . PHP_EOL;
		echo "LOG_ALERT        | 1" . PHP_EOL;
		echo "LOG_CRIT         | 2" . PHP_EOL;
		echo "LOG_ERR          | 3" . PHP_EOL;
		echo "LOG_WARNING      | 4" . PHP_EOL;
		echo "LOG_NOTICE       | 5" . PHP_EOL;
		echo "LOG_INFO         | 6" . PHP_EOL;
		echo "LOG_DEBUG        | 7" . PHP_EOL;
		echo "LOG_DEBUG_INOUT  | 11" . PHP_EOL;
		echo "LOG_DEBUG_WARN   | 12" . PHP_EOL;
		echo "LOG_DEBUG_HILITE | 13" . PHP_EOL;
		echo "LOG_DEBUG_VAR    | 14" . PHP_EOL;
		echo "LOG_DEBUG_QUERY  | 15" . PHP_EOL;
		echo "LOG_STATS        | 254" . PHP_EOL;
		echo "LOG_STOP         | 255" . PHP_EOL;
		echo PHP_EOL;
		$this->EnVision->functions_left++;
	}
	
	public function getLoggerVersion () : string
	{
		$this->EnVision->functions_entered++;
		$this->EnVision->functions_left++;
		return $this->loggerVersion;
	}

	public function getLogFileLocation () : string
	{
		$this->EnVision->functions_entered++;
		$this->EnVision->functions_left++;
		return $this->logfile;
	}

	public function debug (string $what, int $debugLevel = LOG_DEBUG)
	{
		$this->EnVision->functions_entered++;
		if (empty($what)) {
			$this->EnVision->functions_left++;
			return false;
		}
		$logMessage = "$what";
		if (preg_match('/[Ee]ntering [Ff]unction/',$what)) $this->indentLevel++;
		if ((preg_match('/[Ll]eaving [Ff]unction/',$what)) && ($this->indentLevel > 0)) $this->indentLevel--;
		$this->writeLog ($logMessage,$debugLevel);
		$this->EnVision->functions_left++;
	}

	public function write (string $what, int $logLevel = LOG_INFO) : bool
	{
		$this->EnVision->functions_entered++;
		$this->EnVision->functions_left++;
		return ($this->writeLog ($what, $logLevel));
	}

	public function writeLog (string $what, int $logLevel = LOG_INFO) : bool
	{
		$this->EnVision->functions_entered++;
		if ((!is_string($what)) || (empty($what)))
		{
			return false;
		}
		$isDebug = false;
		$logMessage = null;
		$indent = null;
		if ($this->indentLevel > 0)
		{
			$prefix = "|";
		}
		else
		{
			$prefix = null;
		}
		$indent .= $prefix;
		// if we are entering a function, increase the indent level
		for ($i = 1; $i <= $this->indentLevel; $i++)
		{
			if ($this->indentLevel <= 0)
			{
				$this->indentLevel = 0;
				break;
			}
			// only print if the indent level is greater than zero
			if ($this->indentLevel >= 1)
			{
				$indent .= "--";
			}
		}
		if ($this->coloredLogs == true)
		{
			$this->EnVision->log_lines_in_color++;
			switch ($logLevel)
			{
				case LOG_DEBUG:
					$isDebug = true;
					$this->EnVision->log_debug_lines_logged++;
					$logMessage = "[" . $this->colorDEBUG . "DBUG". $this->colorEND ."] ";// . "$what";// . $this->colorEND . PHP_EOL;
					$color = $this->colorDEBUG;
					break;
				case LOG_DEBUG_VAR:
					$isDebug = true;
					$this->EnVision->log_debug_variables++;
					$logMessage = "[" . $this->colorDEBUG . "DBUG" . $this->colorEND . "] ";// . "$what".  $this->colorEND . PHP_EOL;
					$color = $this->colorDEBUG_VAR;
					break;
				case LOG_DEBUG_INOUT:
					$isDebug = true;
					$this->EnVision->log_debug_functions_inout++;
					$logMessage = "[" . $this->colorDEBUG . "DBUG" . $this->colorEND . "] ";// . "$what".  $this->colorEND . PHP_EOL;
					$color = $this->colorDEBUG_INOUT;
					break;
				case LOG_DEBUG_WARN:
					$isDebug = true;
					$this->EnVision->log_debug_warnings++;
					$logMessage = "[" . $this->colorDEBUG . "DBUG" . $this->colorEND . "] ";// . "$what".  $this->colorEND . PHP_EOL;
					$color = $this->colorDEBUG_WARN;
					break;
				case LOG_DEBUG_HILITE:
					$isDebug = true;
					$this->EnVision->log_debug_hilights++;
					$logMessage = "[" . $this->colorDEBUG . "DBUG" . $this->colorEND . "] ";// . "$what".  $this->colorEND . PHP_EOL;
					$color = $this->colorDEBUG_HILITE;
					break;
				case LOG_DEBUG_QUERY:
					$isDebug = true;
					$this->EnVision->log_debug_sql_queries++;
					$logMessage = "[" . $this->colorDEBUG . "DBUG" . $this->colorEND . "] ";// . "$what".  $this->colorEND . PHP_EOL;
					$color = $this->colorDEBUG_QUERY;
					break;
				case LOG_INIT:
					$this->EnVision->log_init_lines++;
					$logMessage = "[" . $this->colorINIT . "INIT" . $this->colorEND . "] ";// . $what . PHP_EOL;
					break;
				case LOG_INFO:
					$this->EnVision->log_info_lines++;
					$logMessage = "[" . $this->colorINFO . "INFO" . $this->colorEND . "] ";// . $what . PHP_EOL;
					break;
				case LOG_WARNING:
				case LOG_WARN:
					$this->EnVision->log_warning_lines++;
					$logMessage = "[" . $this->colorWARNING . "WARN" . $this->colorEND . "] " . $this->colorWARNING;// . $what . PHP_EOL;
					break;
				case LOG_EMERG:
					$this->EnVision->log_emergency_lines++;
					$logMessage = "[" . $this->colorEMERG . "EMRG" . $this->colorEND . "] " . $this->colorEMERG;// . $what . PHP_EOL;
					break;
				case LOG_ALERT:
					$this->EnVision->log_alert_lines++;
					$logMessage = "[" . $this->colorALERT . "ALRT" . $this->colorEND . "] ";// . $what . PHP_EOL;
					break;
				case LOG_CRIT:
					$this->EnVision->log_crit_lines++;
					$logMessage = "[" . $this->colorCRITICAL . "CRIT" . $this->colorEND . "] "; // . $what . PHP_EOL;
					break;
				case LOG_NOTICE:
					$this->EnVision->log_notice_lines++;
					$logMessage = "[" . $this->colorNOTICE . "NOTE" . $this->colorEND . "] " . $this->colorNOTICE;// . $what . PHP_EOL;
					break;	
				case LOG_ERR:
					$this->EnVision->log_error_lines++;
					$logMessage = "[" . $this->colorERR  . "ERR " . $this->colorEND . "] " . $this->colorERR;// . "$what" . $this->colorEND . PHP_EOL;
					break;
				case LOG_ATTN:
					$this->EnVision->log_attention_lines++;
					$logMessage = "[" . $this->colorATTN . "ATTN" . $this->colorEND . "] " . $this->colorATTN;// . "$what" . $this->colorEND . PHP_EOL;
					break;
				case LOG_STATS:
					$this->EnVision->log_stats_lines++;
					$logMessage = "[" . $this->colorSTATS . "STAT" . $this->colorEND . "] " . $this->colorSTATS;// . "$what" . $this->colorEND . PHP_EOL;
					break;
				case LOG_STOP:
					$this->EnVision->log_stop_lines++;
					$logMessage = "[" . $this->colorSTOP . "STOP" . $this->colorEND . "] ";// . $what . PHP_EOL;
					break;
				default:
					$this->EnVision->log_unclassified_lines++;
					$logMessage = "[" . $this->colorUNKNOWN . "----" . $this->colorEND . "] ";// . $what . PHP_EOL;
					break;
			}
			if (isset($_SERVER['ENV_MYIP']))
			{
				$logMessage .= "[" . $_SERVER['ENV_MYIP'] . "] ";
			}
			if (($isDebug == true) && ($this->indentLevel > 0))
			{
				$logMessage .= $this->colorEND . $indent . $this->colorDEBUG . "[" . $this->indentLevel . "] " . $color;
			}
			$logMessage .= "$what" . $this->colorEND . PHP_EOL;
		}
		else
		{
			$this->EnVision->log_lines_in_monochrome++;
			switch ($logLevel)
			{
				case LOG_INIT:
					$this->EnVision->log_init_lines++;
					$logMessage = "[INIT] ";
					break;
				case LOG_DEBUG:
					$this->EnVision->log_debug_lines_logged++;
					$logMessage = "[DBUG] ";
					break;
				case LOG_DEBUG_VAR:
					$this->EnVision->log_debug_variables++;
					$logMessage = "[DBUG][VARVAL] ";
					break;
				case LOG_DEBUG_INOUT:
					$this->EnVision->log_debug_functions_inout++;
					$logMessage = "[DBUG][INOUT] ";
					break;
				case LOG_DEBUG_WARN:
					$this->EnVision->log_debug_warnings++;
					$logMessage = "[DBUG][WARNING] ";
					break;
				case LOG_DEBUG_HILITE:
					$this->EnVision->log_debug_hilights++;
					$logMessage = "[DBUG][HILITED VALUE] ";
					break;
				case LOG_DEBUG_QUERY:
					$this->EnVision->log_debug_sql_queries++;
					$logMessage = "[DBUG][SQL_QUERY] ";
					break;
				case LOG_INFO:
					$this->EnVision->log_info_lines++;
					$logMessage = "[INFO] ";
					break;
				case LOG_WARNING:
				case LOG_WARN:
					$this->EnVision->log_warning_lines++;
					$logMessage = "[WARN] ";
					break;
				case LOG_ERR:
					$this->EnVision->log_error_lines++;
					$logMessage = "[ERR ] ";
					break;
				case LOG_NOTICE:
					$this->EnVision->log_notice_lines++;
					$logMessage = "[NOTE] ";
					break;
				case LOG_EMERG:
					$this->EnVision->log_emergency_lines++;
					$logMessage = "[EMRG] ";
					break;
				case LOG_ALERT:
					$this->EnVision->log_alert_lines++;
					$logMessage = "[ALRT] ";
					break;
				case LOG_CRIT:
					$this->EnVision->log_crit_lines++;
					$logMessage = "[CRIT] ";
					break;
				case LOG_ATTN:
					$this->EnVision->log_attention_lines++;
					$logMessage = "[ATTN] ";
					break;
				case LOG_STATS:
					$this->EnVision->log_stats_lines++;
					$logMessage = "[STAT] ";
					break;
				case LOG_STOP:
					$this->EnVision->log_stop_lines++;
					$logMessage = "[STOP] ";
					break;
				default:
					$this->EnVision->log_unclassified_lines++;
					$logMessage = "[----] ";
					break;
			}
			if (isset($_SERVER['ENV_MYIP']))
			{
				$logMessage .= "[" . $_SERVER['ENV_MYIP'] . "] ";
			}
			if (($isDebug == true) && ($this->indentLevel > 0))
			{
				$logMessage .= $indent . "[" . $this->indentLevel . "] ";
			}
			$logMessage .= "$what" . PHP_EOL;
		}
		$logMessage = "[" . date('M d H:i:s', microtime(true)) . "] " . $logMessage;
		$messageLen = strlen($logMessage);
		if (is_resource($this->logfp))
		{
			$lbw = fwrite($this->logfp, $logMessage);
			if ($lbw !== false) {
				$this->EnVision->log_lines_written++;
				$this->EnVision->log_bytes_written+=$lbw;
				$this->EnVision->functions_left++;
			}
			return true;
		}
		else
		{
			echo "ALERT: " . $logMessage;
			$this->EnVision->functions_left++;
			return false;
		}
	}

	// [0-7];3[0-7];4[0-7]m || [0-7];3[0-7]m || [0-7]m
	private function setColor (string $colorCode, int $logLevel) : void
	{
		$this->EnVision->functions_entered++;
		if ((preg_match("/^\[[0-7];3[0-7]m$/", $colorCode)) || (preg_match("/^\[;3[0-7]m$/",$colorCode)) || (preg_match("/^\[[0-7];3[0-7];4[0-7]m/",$colorCode)))
		{
			switch($logLevel)
			{
				case LOG_INIT:
					$this->colorINIT = $colorCode;
					break;
				case LOG_INFO:
					$this->colorINFO = $colorCode;
					break;
				case LOG_WARNING:
				case LOG_WARN:
					$this->colorWARNING = $colorCode;
					break;
				case LOG_EMERG:
					$this->colorEMERG = $colorCode;
					break;
				case LOG_ALERT:
					$this->colorALERT = $colorCode;
					break;
				case LOG_CRIT:
					$this->colorCRITICAL = $colorCode;
					break;
				case LOG_NOTICE:
					$this->colorNOTICE = $colorCode;
					break;
				case LOG_ERR:
					$this->colorERR = $colorCode;
					break;
				case LOG_ATTN:
					$this->colorATTN = $colorCode;
					break;
				case LOG_STATS:
					$this->colorSTATS = $colorCode;
					break;
				case LOG_STOP:
					$this->colorSTOP = $colorCode;
					break;
				case LOG_UNKN:
					$this->colorUNKNOWN = $colorCode;
					break;
				case LOG_DEBUG:
					$this->colorDEBUG = $colorCode;
					break;
				case LOG_DEBUG_INOUT:
					$this->colorDEBUG_INOUT = $colorCode;
					break;
				case LOG_DEBUG_WARN:
					$this->colorDEBUG_WARN = $colorCode;
					break;
				case LOG_DEBUG_HILITE:
					$this->colorDEBUG_HILITE = $colorCode;
					break;
				case LOG_DEBUG_QUERY:
					$this->colorDEBUG_QUERY = $colorCode;
					break;
				case LOG_DEBUG_VAR:
					$this->colorDEBUG_VAR = $colorCode;
					break;
				default:
					$this->writeLog("Unknown parameter ($logLevel) passed in Logger::setColor. Skipping.",LOG_WARN);
					break;
			}
		}
		else
		{
			// do not return possibly dangerous/unintended escape sequences to caller
			$this->writeLog("Invalid color code passed to Logger::setColor.",LOG_ERR);
		}
		$this->EnVision->functions_left++;
	}

	// array consists of: array (LOG_INIT => "[1;36m", LOG_INFO => "[1;37m", ...)
	// levels are listed above in the defines.
	public function setCustomColors (array $colorArray):bool
	{
		$this->EnVision->functions_entered++;
		if (empty($colorArray))
		{
			$this->writeLog ("Empty array passed to Logger::setCustomColors",LOG_ERR);
			$this->EnVision->functions_left++;
			return (false);
		}
		foreach ($colorArray as $level => $color)
		{
			switch ($level)
			{
				case LOG_DEBUG:
				case LOG_DEBUG_INOUT:
				case LOG_DEBUG_WARN:
				case LOG_DEBUG_HILITE:
				case LOG_DEBUG_QUERY:
				case LOG_DEBUG_VAR:
				case LOG_INIT:
				case LOG_INFO:
				case LOG_WARNING:
				case LOG_WARN:
				case LOG_ERR:
				case LOG_ATTN:
				case LOG_STATS:
				case LOG_STOP:
				case LOG_UNKN:
					$this->setColor($color, $level);
					break;
				default:
					$this->writeLog ("Invalid parameter ($level) passed to Logger::setCustomColors. Skipping.",LOG_WARN);
					break;
			}
		}
		$this->EnVision->functions_left++;
		return (true);
	}
}
// CHANGE LOG //
////////////////
// 2018-02-12 // Added colorization to the log object to make it easier to read
//            // (This makes the logs unable to be viewed easily in VIM or others, but works for cat)
// 2018-02-13 // In order to further make visual separations, I added the following constants:
//            //     LOG_DEBUG_INOUT	to mark entry/exit in functions
//            //     LOG_DEBUG_WARN	to mark potentially undesireables
//            //     LOG_DEBUG_HILITE	to abstract things of potential interest
//            //     LOG_DEBUG_VAR	to show the values of variable assignments
// 2018-02-13 // Added the change log to the file for tracking changes made
// 2018-04-10 // Added LOG_DEBUG_QUERY to delineate database queries instead of using LOG_DEBUG_VAR
// 2018-04-10 // Modified writeLog to check this->coloredLogs and output different values depending
//            // on whether this is true or false.
// 2018-04-10 // Added class variable this->indentLevel
//            // added check in writeLog which performs a preg_match on the message string when it
//            // contains the value "[eE]ntering [fF]unction" or "[lL]eaving [fF]unction". Should
//            // it match, the indentLevel class variable will increment or decrement respectively
// 2018-04-10 // Added code to print the indent level and  '-->' for each level of indent greater
//            // greater than zero
// 2018-04-10 // Added class variable LOG_STATS and associated output and set color to green 
//////////////////////
//            // Plan to extend this by implementing the ability to compress log entries...
//            // not sure how easy that will be, but php supports the functionality so it should be
//            // fairly trivial.
////////////////
?>
