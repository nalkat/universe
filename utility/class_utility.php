<?php // 7.3.0

// @REQ defines.php
// @REQ:sockets
// @REQ:pcre
// @REQ:fileinfo
// @REQ:EnVision 
require_once __DIR__ . "/EnVision/class_envision.php";

// if these values are not defined elsewhere, define them here
if (!defined("L_NULL"))    define ("L_NULL", 0x0);
if (!defined("L_ERROR"))   define ("L_ERROR",0x1);
if (!defined("L_ACCESS"))  define ("L_ACCESS",0x2);
if (!defined("L_CONSOLE")) define ("L_CONSOLE",0x4);
if (!defined("L_DEBUG"))   define ("L_DEBUG",0x8);
if (!defined("L_ALL"))     define ("L_ALL",0xF);

final class Utility
{
	private static $address = null;
	private static $socket = null;
	private static $aLog = null;
	private static $cLog = null;
	private static $dLog = null;
	private static $eLog = null;
	private static $domain = null;
	public  static $EnVision = null;

	public static function init () : void // attempt to import the logger objects if they already exist
	{
		if (!isset($GLOBALS['EnVision']) || (!is_a($GLOBALS['EnVision'],"EnVision"))) {
			$GLOBALS['EnVision'] = new EnVision();
		}
		self::$EnVision =& $GLOBALS['EnVision'];
		self::$EnVision->objects_instantiated++;
		self::$EnVision->functions_entered++;
		self::$domain = $_SERVER['SERVER_NAME'] ?? getenv("ENV_HOSTNAME") ?? getenv("HOSTNAME") ?? "the.observer";
		global $accessLog, $consoleLog, $debugLog, $errorLog;
		if (($accessLog !== null) && (is_a($accessLog,"Logger"))) {
			self::$aLog =& $accessLog;
			self::$EnVision->files_opened++;
		}
		if (($consoleLog !== null) && (is_a($consoleLog,"Logger"))) {
			self::$cLog =& $consoleLog;
			self::$EnVision->files_opened++;
		}
		if (($debugLog !== null) && (is_a($debugLog,"Logger"))) {
			self::$dLog =& $debugLog;
			self::$EnVision->files_opened++;
		}
		if (($errorLog !== null) && (is_a($errorLog,"Logger"))) {
			self::$eLog =& $errorLog;
			self::$EnVision->files_opened++;
		}
		self::$address = null;
		self::$socket = null;
		self::$EnVision->functions_left++;
	}

	// a bash clone of the same name
	// usage:
	// while getopts ("a:bcd:efg:",["happy:","hap","hello"])
	// {
	//   do something
	// }
	//
	public static function getopts (string $optStr, array $longOptStr = null) : mixed
	{
		self::$EnVision->functions_entered++;
		self::$EnVision->functions_left++;
	  return (empty($longOptStr) ? getopt($optStr) : getopt($optStr, $longOptStr));
	}

	public static function getDomain () : string
	{	
		return (self::$domain);
	}

  function counter () : int {
		if (!class_exists("db")) require_once "class_db.php";
		global $domain;
  	try
	  {
		  if (!$db = new db ())
			{
			  $GLOBALS['EnVision']->excptions_thrown++;
			  throw new Exception ("Unable to connect to the database", 0);
		  }
		/*
	    if (!($escDomain = $db->escapeLiteral($domain))===false)
			{
					$db->sqlstr = "INSERT INTO page_hits (domain_id, page_hits) values ((select id from domains where name='". $domain ."'), 1) ON CONFLICT (domain_id) DO UPDATE SET page_hits = page_hits.page_hits + 1 RETURNING page_hits.page_hits;";
		}
			else
			{
				return (0);
			}
		*/
      if (($escDomain = $db->escapeLiteral($domain)) !== false)
      {
        $db->sqlstr = "
          WITH domain_lookup AS (
            SELECT id FROM domains WHERE name = {$escDomain}
          )
          INSERT INTO page_hits (domain_id, page_hits)
          SELECT id, 1 FROM domain_lookup
          ON CONFLICT (domain_id)
          DO UPDATE SET page_hits = page_hits.page_hits + 1
          RETURNING page_hits;
        ";
      }
      else
      {
  			echo "<p>$domain</p>" . PHP_EOL;
        return 0;
      }

  		if (!$db->query())
  		{
  			$GLOBALS['EnVision']->exceptions_thrown++;
  			throw new Exception ("Unable to query the database (".$db->sqlstr.")",0);
  		}
  		return ($db->row()['page_hits']);
  	}
  	catch (Exception $e)
  	{
  		$GLOBALS['EnVision']->exceptions_caught++;
  		$GLOBALS['EnVision']->exceptions_last_message = $e->getMessage();
  		$GLOBALS['EnVision']->exceptions_last_code = $e->getCode();
  		return ($e->getCode());
  	}
  }
	public static function isMTU (int $mtu) : bool
	{
		self::$EnVision->functions_entered++;
		$valid = false;
		if (!empty($mtu))
		{
			if ($mtu % 8 === 0) {
				$valid = true;
			} else {
				self::$EnVision->functions_left++;
				self::$EnVision->exceptions_thrown++;
				throw new Exception ("invalid mtu '$mtu', try (" . ($mtu - ($mtu % 8)) . ") or (" . ($mtu + (8 - ($mtu % 8))) . ")", 1);
			}	
		} else {
			// MTU is empty(false, null or zero) all are bad
			self::$EnVision->functions_left++;
			self::$EnVision->exceptions_thrown++;
			throw new Exception ("MTU '$mtu' cannot be 0", 1);
		}
		self::$EnVision->functions_left++;
		return ($valid);
	}
 
	// get $len random printable characters and return
	// them for use in a filename -- needs to reject
	// characters which cannot be used in a pathspec. If
	// $len is omitted or is negative, then this function
	// will return 8 characters.
	public static function mktmpnam (int $len=0) : string
	{
		self::$EnVision->functions_entered++;
		// we're using 0-inclusive values so $len becomes ($len -1)
		if ($len <= 0) $len = 8;
		$i = 0;
		$chararr = array();
		// generate 8 random alphanum characters
		for ($i=0; count($chararr) <= ($len-1); $i++)
		{
			// chars 32-126 are printable, but not all are in pathspec
			// chars from 32-47 can be used if they are escaped, but let's skip them
			// 48-57 should be included, 58-64 should be skipped, then the rest are 
			// fairgame
			$return_list = array(48,49,50,51,52,53,54,55,56,57,61,62,63,64,65,66,67,68,
												69,70,71,72,73,74,75,76,77,78,79,80,81,82,83,84,85,86,
												87,88,89,90,91,92,93,94,95,96,97,98,99,100,101,102,103,
												104,105,106,107,108,109,110,111,112,113,114,115,116,117,
												118,119,120,121,122,123,124,125,126);
			$bytestr="";
			$ok = false;
			while($ok === false)
			{
				$byte=random_bytes(1);
				if ((in_array(ord($byte),$return_list,true))===false)
					continue;
				else
					$chararr[$i] = $byte; // add this to our 
				if (count($chararr) === ($len-1)) $ok = true;
				if (count($chararr) >= $len)
				{
					echo "something really bad happened, resetting chararr" . PHP_EOL;
					$chararr = array();
				}
				// there is no supposing in this function. it looks solid.
			}
		}
		self::$EnVision->functions_left++;
		// now push all of the elements into a string and return that to our calling function;
		return (implode('',$chararr));
	}

	public static function isIPv4 (string $ipAddress) : bool
	{
		//self::$EnVision->functions_entered++;
		if (($ipAddress = filter_var($ipAddress,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_RES_RANGE)) === false)
		{
			//self::$EnVision->functions_left++;
			return false;
		}
		else
		{
			//self::$EnVision->functions_left++;
			return true;
		}
		//self::$EnVision->functions_left++;
	}

	public static function isIPv6 (string $ipAddress) : bool
	{
		//self::$EnVision->functions_entered++;
		if (($ipAddress = filter_var($ipAddress,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6|FILTER_FLAG_NO_RES_RANGE)) === 0)
		{
			//self::$EnVision->functions_left++;
			return false;
		}
		else
		{
			//self::$EnVision->functions_left++;
			return true;
		}
		//self::$EnVision->functions_left++;
	}

//	public static function getDomain (string $hostname = "") : string
//	{
//		self::$EnVision->functions_entered++;
//		if ((!empty($hostname)) && (preg_match('/[^.]+\.[^.]+$/', $hostname, $matches))) {
//			self::$EnVision->functions_left++;
//			return ($matches[0]);
//		}
//		self::$EnVision->functions_left++;
//		return NULL;;
//	}

	public static function cleanExit (int $code = 0) : int
	{
		self::$EnVision->functions_entered++;
		if (is_resource(self::$aLog)) {
			if (!fclose (self::$aLog)) {
				self::$EnVision->exceptions_thrown++;
				throw new Exception ("FATAL: Failed to close the accessLog",250);
			}
			self::$EnVision->files_closed++;
		}
		if (is_resource(self::$cLog)) {
			if (!fclose (self::$cLog)) {
				self::$EnVision->exceptions_thrown++;
				throw new Exception ("FATAL: Failed to close the consoleLog",251);
			}
			self::$EnVision->files_closed++;
		}
		if (is_resource(self::$dLog)) {
			if (!fclose (self::$dLog)) {
				self::$EnVision->exceptions_thrown++;
				throw new Exception ("FATAL: Failed to close the debugLog",252);
			}
			self::$EnVision->files_closed++;
		}
		if (is_resource(self::$eLog)) {
			if (!fclose (self::$eLog)) {
				self::$EnVision->exceptions_thrown++;
				throw new Exception ("FATAL: Failed to close the accessLog",253);
			}
			self::$EnVision->files_closed++;
		}
		if (self::is_connected(self::$socket)) socket_close(self::$socket);
		self::$EnVision->functions_left++;
		exit ($code);
	}

	// determine if an imported socket resource is active
	public static function is_connected () : bool
	{
		self::$EnVision->functions_entered++;
		if ((!is_resource(self::$socket)) || ((strcmp(strtolower(get_resource_type(self::$socket)), "socket")) !== 0)) {
			self::$EnVision->functions_left++;
			return false;
		} else {
			self::$EnVision->functions_left++;
			return true;
		}
		self::$EnVision->functions_left++;
	}

	// import the socket resource $socket
	public static function setSocket (Socket &$socket) : bool
	{
		self::$EnVision->functions_entered++;
		if ((!is_resource($socket)) || ((strcmp(strtolower(get_resource_type($socket)), "socket")) !== 0)) {
			self::$EnVision->functions_left++;
			return false;
		}
		self::$socket =& $socket;
		self::$EnVision->functions_left++;
		return (socket_getpeername($socket, self::$address));
	}


	public static function reslen (file &$res) : int 
	{
		self::$EnVision->functions_entered++;
		if ($devnull = fopen("/dev/null", "w") === false) { // failed ...
			self::$EnVision->functions_left++;
			return (-1);
		}
		$nBytes = 0;
		if (($nBytes += intval(stream_copy_to_stream ($this->result_set, $devnull))) === false) {
			self::$EnVision->functions_left++;
			return (-1);
		} else {
			self::$EnVision->functions_left++;
			return $nBytes;
		}
		self::$EnVision->functions_left++;
	}

	// import a log file resource that we can use for output
	public static function setLog (Logger &$log, int $oFlag) : bool
	{
		self::$EnVision->functions_entered++;
		if (!is_a($log,"Logger")) {
			self::$EnVision->functions_left++;
			return false;
		}
		$ret = false;
		if ($oFlag & L_ACCESS)
		{
			self::$aLog =& $log;
			self::write('access log started', LOG_INFO, L_ACCESS);
			$ret = true;
		}
		if ($oFlag & L_CONSOLE)
		{
			self::$cLog =& $log;
			$ret = true;
		}
		if ($oFlag & L_DEBUG)
		{
			self::$dLog =& $log;
			$ret = true;
		}
		if ($oFlag & L_ERROR)
		{
			self::$eLog =& $log;
			$ret = true;
		}
		self::$EnVision->functions_left++;
		return $ret;
	}

	// converts a string containing a time value into corresponding number of seconds
	public static function getDuration (string $t) : int
	{
		self::$EnVision->functions_entered++;
		if (empty($t)) {
			self::$EnVision->functions_left++;
			return 0;
		}
		if (preg_match('/^[0-9]*[ ]{0,}[dhms]$/', $t))
		{
			$t = preg_replace('/ */', '', $t);
			$t = preg_replace('/s/', '*1', $t);
			$t = preg_replace('/d/', '*86400', $t);
			$t = preg_replace('/w/', '*604800', $t);
			$t = preg_replace('/m/', '*60', $t);
			$t = preg_replace('/h/', '*3600', $t);
			$t = explode('*', $t);
			if (isset($t[0]) && isset($t[1])) $t = intval( $t[0] * $t[1]);
			else $t = intval($t[0]);
		}
		elseif (preg_match('/^[0-9]*$/', $t))
		{
			$t = intval($t);
		}
		else
		{
			self::$EnVision->functions_left++;
			return 0;
		}
		self::$EnVision->functions_left++;
		return $t;
	}

	public static function isLog(Logger $resource = null) : bool
	{
		self::$EnVision->functions_entered++;
		if (is_a($resource, "Logger")) {
			self::$EnVision->functions_left++;
			return true; 
		} else {
			self::$EnVision->functions_left++;
			return false;
		}
		self::$EnVision->functions_left++;
	}

	// return bytes written if writing to a socket, false on error, or true if successfully written to a log
	public static function write (string $what = null, int $logLevel = null, int $oFlags = null)
	{
		self::$EnVision->functions_entered++;
		try 
		{
			if ($what === null) $what = "";
			$ret = false;
			if (!self::is_connected()) return $ret;
			if ($logLevel === null || $oFlags === null)
			{
				# bytes or false
				self::$EnVision->functions_left++;
				return ((socket_write(self::$socket, $what . PHP_EOL)) === false);
			}
			else
			{
				if ($oFlags & L_ACCESS)
				{
					if (!self::isLog(self::$aLog)) {
						throw new Exception ("Attempting to write log data to nonLog objects", 1);
					}
					if (!self::is_connected()) {
						self::$EnVision->functions_left++;
						self::$EnVision->exceptions_thrown++;
						throw new Exception ("Attempting to write disconnected socket", 2);
					}
					if (($ret = self::$aLog->writeLog($what, $logLevel))>0) {
						self::$EnVision->file_bytes_written += intval($ret);
					}
				}
				if ($oFlags & L_CONSOLE)
				{
					if (!self::isLog(self::$cLog)) {
						//self::$EnVision->functions_left++;
						////self::$EnVision->exceptions_thrown++;
						throw new Exception ("Attempting to write log data to nonLog objects", 1);
					}
					if (($ret = self::$cLog->writeLog($what, $logLevel))>0) {
						self::$EnVision->file_bytes_written += intval($ret);
					}
				}
				if ($oFlags & L_DEBUG)
				{
					if (!self::isLog(self::$dlog)) {
						//self::$EnVision->functions_left++;
						////self::$EnVision->exceptions_thrown++;
						throw new Exception ("Attempting to write log data to nonLog objects", 1);
					}
					if (($ret = self::$dLog->debug($what, $logLevel))>0) {
						self::$EnVision->file_bytes_written += intval($ret);
					}
				}
				if ($oFlags & L_ERROR)
				{
					if (!self::isLog(self::$eLog)) {
						self::$EnVision->functions_left++;
						self::$EnVision->exceptions_thrown++;
						throw new Exception ("Attempting to write log data to nonLog objects", 1);
					}
					if (($ret = self::$eLog->writeLog($what, $logLevel))>0) {
						self::$EnVision->file_bytes_written += intval($ret);
					}
				}
			}
		} catch (Exception $logFail) {
			echo $logFail->getMessage(). PHP_EOL;
			self::$EnVision->functions_left++;
			return ($logFail->getCode());
		}
	}

	// return bytes written if writing to a socket, false on error, or true if successfully written to a log
	public static function ncWrite (string $what, int $logLevel = null, int $oFlags = null) 
	{
		self::$EnVision->functions_entered++;
		try
		{
			$ret = false;
			if ($logLevel === null || $oFlags === null) 
			{
				self::$EnVision->functions_left++;
				self::$EnVision->exceptions_thrown++;
				throw new Exception (debug_backtrace()[1]['function'] . " invalid parameters passed to ncWrite", 1);
			} else {
				if ($oFlags & L_ACCESS)
				{
					if (!self::is_connected()) $ret = false;;
					// don't even try to write to non-logger objects...
					if (self::isLog(self::$aLog)) 
					{
						if (($ret = self::$aLog->writeLog($what, $logLevel))>0) {
							self::$EnVision->file_bytes_written+=intval($ret);
						}
					} 
					else
					{
						self::$EnVision->functions_left++;
						self::$EnVision->exceptions_thrown++;
						throw new Exception ("Attempting to write log data to nonLog objects", 1);
					}
				}
				if ($oFlags & L_CONSOLE)
				{
					if (self::isLog(self::$cLog))
					{
						if (($ret = self::$cLog->writeLog($what, $logLevel)) > 0) {
							self::$EnVision->file_bytes_written+=intval($ret);
						}
					}
					else
					{
						self::$EnVision->functions_left++;
						self::$EnVision->exceptions_thrown++;
						throw new Exception ("Attempting to write log data to nonLog objects", 1);
					}
				}
				if ($oFlags & L_DEBUG)
				{
					$ret = self::$dLog->writeLog($what, $logLevel);
					if (self::isLog(self::$dLog))
					{
						if (($ret = self::$dLog->debug($what, $logLevel))>0) {
							self::$EnVision->file_bytes_written+=intval($ret);
						}
					}
					else
					{
						self::$EnVision->functions_left++;
						self::$EnVision->exceptions_thrown++;
						throw new Exception ("Attempting to write log data to nonLog objects", 1);
					}
				}
				if ($oFlags & L_ERROR)
				{
					if (self::isLog(self::$eLog))
					{
						if (($ret = self::$eLog->writeLog($what, $logLevel))>0) {
							self::$EnVision->file_bytes_written+=intval($ret);
						}
					}
					else
					{
						self::$EnVision->exceptions_thrown++;
						self::$EnVision->functions_left++;
						throw new Exception ("Attempting to write log data to nonLog objects", 1);
					}
				}
			}
		} catch (Exception $logFail) {
			echo $logFail->getMessage() . PHP_EOL;
			self::$EnVision->functions_left++;
			return ($logFail->getCode());
		}
	}

	// converts a string into asterisks for displaying potentially sensitive information
	// [possible issue]: displaying actual number of characters could provide some clue as to the information
	// 		     container within... :-/
	public static function string2stars (string $input) : string
	{
		self::$EnVision->functions_entered++;
		$ret = "";
		for ($i = 0; $i != strlen($input); $i++)
		{
			$ret .= "*";
		}
		self::$EnVision->functions_left++;
		return $ret;
	}

	public static function cleanse_csv (string $input) : string
	{
		self::$EnVision->functions_entered++;
		self::$EnVision->functions_left++;
		return ($input = preg_replace('/[^[:alnum:][:space:]_\-,:@\.\<\>\(\)]/u','', $input));
	}

	// strip out anything that might be potentially harmful
	public static function cleanse_string (string $input) : string
	{
		self::$EnVision->functions_entered++;
		self::$EnVision->functions_left++;
		return ($input = preg_replace('/[^[:alnum:][:space:]_\-@]/u','', $input));
	}

	// checks for characters which are not 'a-z', 'A-Z', '0-9' or '_'
	// return empty string on fail or unmodified string on success
	public static function query_alnumu (string $query) : string
	{
		self::$EnVision->functions_entered++;
		// search for any characters that are not 'a-z', 'A-Z', '0-9' or '_'
		if (preg_match('/[^a-zA-Z_]/', $query)) {
			self::$EnVision->functions_left++;
			return "";
		} else {
			self::$EnVision->functions_left++;
			return "$query";
		}
		self::$EnVision->functions_left++;
	}

	// checks for characters which are not 'a-z', 'A-Z' or '0-9'
	// return empty string on fail or unmodified string on success
	public static function query_alnum (string $query) : string
	{
		self::$EnVision->functions_entered++;
		// search for any characters that are not 'a-z', 'A-Z' or '0-9'
		if (preg_match('/[^a-zA-Z0-9]/', $query)) {
			self::$EnVision->functions_left++;
			return "";
		}
		else
		{
			self::$EnVision->functions_left++;
			return "$query";
		}
		self::$EnVision->functions_left++;
	}

	// make sure the input string only contains characters 0-9 - . + , % $ ( )
	public static function query_numeric (string $query) : string
	{
		self::$EnVision->functions_entered++;
		if (preg_match('/[^0-9\-\.\+,%\$^\(\)]/', $query)) {
			self::$EnVision->functions_left++;
			return "";
		} else {
			self::$EnVision->functions_left++;
			return "$query";
		}
		self::$EnVision->functions_left++;
	}

	// make sure the input string only containe a-z and A-Z characters
	public static function query_alpha (string $query) : string
	{
		self::$EnVision->functions_entered++;
		if (preg_match('/[^a-zA-Z]/', $query)) {
			self::$EnVision->functions_left++;
			return "";
		} else {
			self::$EnVision->functions_left++;
			return "$query";
		}
		self::$EnVision->functions_left++;
	}

	// convert a php array into a form that is compatible with postgres tables
	public static function db_array_convert (array $db_array = null) : string
	{
		self::$EnVision->functions_entered++;
		if ($db_array === null) {
			self::$EnVision->functions_left++;
			return "{}";
		}
		if (!is_array($db_array)) {
			self::$EnVision->functions_left++;
			return "{}";
		}
		foreach ($db_array as $idx => $val) if (empty($val)) unset ($db_array[$idx]);
		else $result = "{" . implode(',', $db_array) . "}";
		if (empty($result)) {
			self::$EnVision->functions_left++;
			return "{}";
		}
		self::$EnVision->functions_left++;
		return $result;
	}

	// convert an array retrieved from postgres into a usable for for php
	public static function pg_array_convert (string $pg_array) : array
	{
		self::$EnVision->functions_entered++;
		if (preg_match('/[}{]/', $pg_array))
		{
			$pg_array = preg_replace('/}/', '', preg_replace('/{/', '', $pg_array));
			if (preg_match('/,/', $pg_array))
			{
				$ret = explode(',', $pg_array);
			}
			else
			{
				$ret = array($pg_array);
			}
			foreach ($ret as $idx => $val) if (empty($val)) unset($ret[$idx]);
			self::$EnVision->functions_left++;
			return $ret;
		}
		else
		{
			self::$EnVision->functions_left++;
			return array();
		}
	}

	public static function extractPatch (string $patch_stream = null) : string
	{
		self::$EnVision->functions_entered++;
		if (is_null($patch_stream) || !is_string($patch_stream) || empty($patch_stream))
		{
			self::$EnVision->functions_left++;
			return "";
		}
		$f = new finfo();
		$junk = null;
		$patch = null;
		$ftype = $f->buffer($patch_stream);
		if (!((preg_match("/ascii/i",$ftype)) || (preg_match("/diff/i",$ftype)) || (preg_match("/c source/i",$ftype)) || (preg_match("/text/i",$ftype))))
		{
			self::$EnVision->exceptions_thrown++;
			self::$EnVision->functions_left++;
			throw new Exception ("The patch stream did not contain a valid patch",1);
		}
		$patch_stream= explode("\n",$patch_stream);
		while (!preg_match("/^---$/", $patch_stream[0]))
		{
			$junk[] = array_shift($patch_stream);
		}
		$junk[] = "@[ SNIP PATCH ]@" . PHP_EOL;
		if (empty($patch_stream[0]))
		{
			self::$EnVision->functions_left++;
			exit;
		}
		while (!preg_match('/^--[ ]{0,1}$/', $patch_stream[0]))
		{
			if ($patch_stream[0] === null) break;
			$patch[] = array_shift($patch_stream);
		}
		$patch[] = array_shift($patch_stream);
		// ditch '---'
		array_shift($patch);
		// ditch '--'
		array_pop($patch);
		$patch = implode("\n",$patch) . PHP_EOL;
		self::$EnVision->functions_left++;
		return $patch;
	}

	public static function extractPatchSetInfo (string $message_header = null) : array
	{
		self::$EnVision->functions_entered++;
		if (is_null($message_header) || !is_string($message_header) || is_numeric($message_header) || empty($message_header))
		{
			self::$EnVision->functions_left++;
			self::$EnVision->exceptions_thrown++;
			throw new Exception ("Invalid email message header detected while attempting to extract the subject line",1);
		}
		$patch_set_info = array("patch_number" => 0, "patches_in_set" => 0, "patch_subject" => "");
		$subject = null;
		$header_line = explode(PHP_EOL,$message_header);
		foreach ($header_line as $line_num => $line_text)
		{
			if ((preg_match('/^[sS]ubject:.*$/', $line_text, $matches)) === false) continue;
			if (empty($matches)) continue;
			foreach ($matches as $idx => $match)
			{
				if ((preg_match('/\d+\/\d+/', $match, $idxList)) === false) continue;
				if (!empty($idxList)) list($patch_set_info['patch_number'],$patch_set_info['patches_in_set']) = explode('/', $idxList[0]);
				$patch_set_info['patch_subject'] = $match;
				self::$EnVision->functions_left++;
				return $patch_set_info;
			}
		}
		self::$EnVision->functions_left++;
	}

	// temporary debugging function
	public static function dump () : void
	{
		self::$EnVision->functions_entered++;
		var_dump(self::$address);
		var_dump(self::$socket);
		var_dump(self::$aLog);
		self::$EnVision->functions_left++;
	}
}
?>
