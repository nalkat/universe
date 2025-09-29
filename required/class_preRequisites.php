<?php // 7.3.0
require_once __DIR__ . "/defines.php";
class PreRequisites
{
	// vars
	private static $unmet_dependencies = array('reqext' => array(), 'optext' => array(), 'reqclass' => array(), 'optclass' => array());
	public static $loaded_extensions = array();
	public static $dynamic_extensions = array();

	// required extensions
	private static $required_extensions = array();
	private static $optional_extensions = array();

	// required objects and the path to their source
	private static $loaded_classes = array();
	private static $required_classes = array();
	private static $optional_classes = array();

	// methods
	public static function get_current_classes () : void { self::$loaded_classes = get_declared_classes(); }
	public static function get_current_extensions () : void { self::$loaded_extensions = get_loaded_extensions(); }
	
	//	public static function load_dynamic (string $extension_so) : bool { return dl($extension_so); }


// {{{
// The class function cannot operate the same as the modules... due to this, the add_class will just return false on failure instead of having a "check" function

	public static function load_classes() : void
	{
		foreach(self::getClassList() as $idx => $props)
		{
			if (!class_exists($props['name'], false)) {
				require_once $props['path'];
			}
		}
	}

	public static function getClassList() : array
	{
		return array_merge(self::$required_classes,self::$optional_classes);
	}

	// check if the given class name/file path is already listed; if not, assign it to the first flag type encountered
	public static function add_class (string $class_name, string $file_path, $flags = DEP_NULL) : bool
	{
		if ((!file_exists($file_path)) || (!is_readable($file_path)))
		{
			echo "Unable to locate the file $file_path or it is not readable" . PHP_EOL;
			return false;
		}
		if ((in_array($class_name,self::$required_classes)) || (in_array($class_name,self::$optional_classes)))
		{
			echo "The class $class_name has already been added" . PHP_EOL;
			return false;
		}
		if ((in_array($file_path,self::$required_classes)) || (in_array($file_path,self::$optional_classes)))
		{
			echo "The class $class_name has already been added" . PHP_EOL;
			return false;
		}
		$numReqs = count(self::$required_classes);
		$numOpts = count(self::$optional_classes);
		switch($flags)
		{
			default:
			case DEP_NULL:
				return false;
			case DEP_REQUIRED:
				self::$required_classes[$numReqs]['name']= $class_name;
				self::$required_classes[$numReqs]['path'] = $file_path;
				require_once "$file_path";
				return true;
			case  DEP_OPTIONAL:
				self::$optional_classes[$numOpts]['name'] = $class_name;
				self::$required_classes[$numOpts]['path'] = $file_path;
				include_once "$file_path";
				return true;
			case DEP_ALL:
				return false;
		}
		return false;
	}

	// remove a class from the list of dependencies
	public static function rm_class (string $name_or_path) : bool
	{
		foreach (self::$optional_classes as $key => $props)
		{
			if (in_array($name_or_path,$props))
			{
				unset (self::$optional_classes[$key]);
				return true;
			}
		}
		foreach (self::$required_classes as $key => $props)
		{
			if (in_array($name_or_path,$props))
			{
				unset(self::$required_classes[$key]);
				return true;
			}
		}
		return false;
	}

	// move a previously added class to the opposite requirement type
	public static function mv_class (string $class_name, string $file_path) : bool
	{
		foreach (self::$required_classes as $key => $props) {
			if (in_array($class_name,$props))
			{
				if (self::rm_class($class_name)) return self::add_class($class_name,$file_path,DEP_OPTIONAL);
				//unset(self::$required_classes[$key]);
				//return self::add_class($class_name, $file_path, DEP_OPTIONAL);
			}
			//unset(self::$required_classes['path'][$key]);
		}
		foreach (self::$optional_classes as $key => $props)
		{
			if (in_array($class_name,$props))
			{
				if (self::rm_class($class_name)) return self::add_class($class_name,$file_path,DEP_REQUIRED);
			}
		}
	}

	
	// searches through required/optional class lists and validates the values are in the loaded_classes property
	// if an item is not loaded, it is added to the unmet_dependencies list
	public static function check_classes () : void
	{
		self::get_current_classes();
		foreach (self::$required_classes as $idx => $props) {
			if ((empty($props['name'])) || (empty($props['path']))) continue;
			if (($key = array_search($props['name'],self::$loaded_classes)) === false)
			{
				self::$unmet_dependencies['reqclass'][] = $idx;
			}
		}
		foreach (self::$optional_classes as $idx => $props) {
			if ((empty($props['name'])) || (empty($props['path']))) continue;
			if (($key = array_search($props['name'],self::$loaded_classes)) === false)
			{
				self::$unmet_dependencies['optclass'][] = $idx;
			}
		}
	}

	// check if the given extension is already listed as either optional or required; if not, assign it to the first flag type encountered
	public static function add_extension (string $extension, $flags = DEP_REQUIRED) : bool
	{
		// if the extension is already present in either of these, fail
		if (in_array($extension,self::$required_extensions)) return false;
		if (in_array($extension,self::$optional_extensions)) return false;
		$numReqs = count(self::$required_extensions);
		$numOpts = count(self::$optional_extensions);
		switch($flags)
		{
			case DEP_NULL:
				return false;
			case DEP_REQUIRED:
				self::$required_extensions[$numReqs] = $extension;
				return true;
			case DEP_OPTIONAL:
				self::$optional_extensions[$numOpts] = $extension;
				return true;
			case DEP_ALL:
				return false;
		}
		return false;
	}

	// remove an extension from the list of dependencies
	public static function rm_extension (string $extension) : bool
	{
		if (($key = array_search($extension,self::$required_extensions)) !== false)
		{
			unset(self::$required_extensions[$key]);
			return true;
		}
		if (($key = array_search($extension,self::$optional_extensions)) !== false)
		{
			unset(self::$optional_extensions[$key]);
			return true;
		}
		return false;
	}

	// move a known dependency to the opposite requirement type or fail if it is not already known 
	public static function mv_extension (string $extension) : bool
	{
		if (($key = array_search($extension,self::$required_extensions)) !== false)
		{
			if (self::rm_extension($extension)) return self::add_extension($extension,DEP_OPTIONAL);
			//unset(self::$required_extensions[$key]);
			//return self::add_dep($extension,DEP_OPTIONAL);
		}
		elseif (($key = array_search($extension,self::$optional_extensions)) !== false)
		{
			if (self::rm_extension($extension)) return self::add_extension($extension,DEP_REQUIRED);
			//unset(self::$optional_extensions[$key]);
			//return self::add_dep($extension,DEP_REQUIRED);
		}
		else return false;
	}

	// searches through required/optional extension lists and validates the values are in the loaded_extensions property
	// if an item is not loaded, it is added to the unmet_dependencies list
	public static function check_extensions () : void
	{
		self::get_current_extensions();
		foreach (self::$required_extensions as $idx => $extension)
		{
			// search the loaded extensions array for the required extension
			if (($key = array_search($extension,self::$loaded_extensions)) === false)
			{
				self::$unmet_dependencies['reqext'][] = $idx;
			}
		}
		foreach (self::$optional_extensions as $idx => $extension)
		{
			// search the loaded extensions array for the optional extension
			if (($key = array_search($extension,self::$loaded_extensions)) === false)
			{
				self::$unmet_dependencies['optext'][] = $idx;
			}
		}
	}

	public static function check() : void
	{
		$pass = true;
		$err = 0;
		$warn = 0;
//		self::check_classes();
		self::check_extensions();
		if (!empty(self::$unmet_dependencies))
		{
			foreach (self::$unmet_dependencies as $type => $idx)
			{
				switch (strtolower($type))
				{
					case 'reqext':
						foreach ($idx as $val)
						{
							if (empty(self::$required_extensions[$val])) continue; 
							else
							{
								echo "ERROR: Required extension dependency not found: " . self::$required_extensions[$val] . PHP_EOL;
								$pass = false;
								$err++;
							}
						}
						break;
					case 'optext':
						foreach ($idx as $val)
						{
							if (empty(self::$optional_extensions[$val])) continue;
							else
							{
								echo "WARNING: Optional extension dependency not found: " . self::$optional_extensions[$val] . PHP_EOL;
								echo "* Some functionality may not be present" . PHP_EOL;
								$warn++;
							}
						}
						$pass = true;
						break;
//					case 'reqclass':
//						foreach ($idx as $val)
//						{
//							if (empty(self::$required_classes[$val]['name'])) continue;
//							else
//							{
//								echo "ERROR: Required class dependency not found: " . self::$required_classes[$val]['name'] . PHP_EOL;
//								echo "Please ensure that the definition can be found at: " . self::$required_classes[$val]['path'] . PHP_EOL;
//								$pass = false;
//								$err++;
//							}
//						}
//						break;
//					case 'optclass':
//						foreach ($idx as $val)
//						{
//							if (empty(self::$optional_classes[$val]['name'])) continue;
//							else
//							{
//								echo "WARNING: Optional class dependency not found: " . self::$optional_classes[$idx]['name'] . PHP_EOL;
//								echo "* Some functionality may not be present" . PHP_EOL;
//								echo "If you feel this is an errant statement, please verify definition file is at: " . self::$optional_classes[$idx]['path'] . PHP_EOL;
//								$warn++;
//							}
//						}
//						$pass = true;
//						break;
					default:
						break;
				}
			}
		}
		if ($warn > 0) echo "$warn warnings were found while processing prerequisites" . PHP_EOL;
		if ($err > 0) echo "$err errors were found while processing prerequisites" . PHP_EOL;
		if (!$pass)
		{
			echo "Unable to continue, please fix the errors above" . PHP_EOL;
			exit (1);
		}
	}

	public static function get_extensions() : array
	{
		$extensions['required'] = self::get_required_extensions();
		$extensions['optional'] = self::get_optional_extensions();
		return $extensions;
	}
	public static function get_required_extensions () : array { return self::$required_extensions; }
	public static function get_optional_extensions () : array { return self::$optional_extensions; }
}
?>
