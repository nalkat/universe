<?php // 7.3.0-dev

class Universe
{
	// static properties
	private static $max_x;
	private static $max_y;
	private static $max_z;
	private static $maxSize;

	private static $current_x;
	private static $current_y;
	private static $current_z;
	private static $currentSize;		// x * y * z

	private static $expansion_rate;
	private static $rotation_speed;		// number of ticks required for a revolution
	private static $movement_speed;
	private static $movement_direction;
	private static $last_location;
	private static $current_location;	// is this different than the instance variable?

	public static $numGalaxies;		// only count galaxies
	public static $numObjects;		// all objects which reside in this universe
	public static $objectList;		// pointer to all actual objects

	private static $age;			// age in full revolutions

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////	
	// instance properties
	private $name;

	private $galaxies;			// array of galaxy objects
	private $objects;			// array of objects in this scope which are not galaxies

	// empty space will need to also be an array (xLeft, yLeft, zLeft)
	private $empty_space;			// self::$maxSize - (self::$current_x - self::$current_y - self::$current_z)
	private $used_space;			// self::$current_x + self::$current_y + self::$current_z
	private $fill_factor;			// for x, y, z
	private $location;

	private $ticks;				// float value as % of ($this->rotationTimer->read() - $this->rotationStart)
	private $tickEvent;		// boolean value indicating if a tickEvent will occur
	private $tickEvents;			// array of actions to perform on a given tick (kind of like cron/at)

	private $randomEventChance;

	// timed events
	private $rotationStart;
	private $rotationTime;			// duration until a single rotation occurs
	private $rotationTimer;			// timer object to track rotations
	private $eventTime;			// duration until an event occurs
	private $eventTimer;			// timer object to track events
	private $createTime;			// duration until a creation event ocurrs
	private $createTimer;			// timer object to track creations

	public static function init () : void
	{
		self::$max_x = floatval(0);
		self::$max_y = floatval(0);
		self::$max_z = floatval(0);
		self::$maxSize = floatval(0);
		self::$current_x = floatval(0);
		self::$current_y = floatval(0);
		self::$current_z = floatval(0);
		self::$currentSize = floatval(0);
		self::$expansion_rate = array ('x' => floatval(0), 'y' => floatval(0), 'z' => floatval(0));
		self::$rotation_speed = floatval(0);
		self::$movement_speed = array ('x' => floatval(0), 'y' => floatval(0), 'z' => floatval(0));
		self::$movement_direction = array ('x' => floatval(0), 'y' => floatval(0), 'z' => floatval(0));
		self::$numGalaxies = 0;
		self::$numObjects = 0;
		self::$age = floatval(0);
		self::$last_location = array('x' => floatval(0), 'y' => floatval(0), 'z' => floatval(0));
		self::$current_location = array('x' => floatval(0), 'y' => floatval(0), 'z' => floatval(0));
	}

	// set upper bounds of the x-plane (|)
	public static function setMaxX (float $x) : void
	{
		self::$max_x = $x;
	}

	public static function getMaxX () : float
	{
		return self::$max_x;
	}

	public static function setCurrentX (float $x) : void
	{
		if (($x > 0) && ($x <= self::$max_x)) self::$current_x = $x;
	}

	public static function getCurrentX () : float
	{
		return self::$current_x;
	}

	// set upper bounds of the y-plane (-)
	public static function setMaxY (float $y) : void
	{
		self::$max_y = $y;
	}

	public static function getMaxY () : float
	{
		return self::$max_y;
	}

	public static function setCurrentY (float $y) : void
	{
		if (($y > 0) && ($y <= self::$max_y)) self::$current_y = $y;
	}

	public static function getCurrentY () : float
	{
		return self::$current_y;
	}

	// set upper bounds of the z-plane (/)
	public static function setMaxZ (float $z) : void
	{
		self::$max_z = $z;
	}

	public static function getMaxZ () : float
	{
		return self::$max_z;
	}

	public static function setCurrentZ (float $z) : void
	{
		if (($z > 0) && ($z <= self::$max_z)) self::$current_z = $z;
	}

	public static function getCurrentZ () : float
	{
		return self::$current_z;
	}

	public static function getMaxSize () : float
	{
		return (self::$max_x * self::$max_y * self::$max_z);
	}

	public static function getCurrentSize () : float
	{

		return floatval (self::$current_x * self::$current_y * self::$current_x);
	}

	public static function setExpansionRate (float $x, float $y, float $z) : void
	{
		self::$expansion_rate['x'] = $x;
		self::$expansion_rate['y'] = $y;
		self::$expansion_rate['z'] = $z;
	}

	public static function getExpansionRate () : array
	{
		return self::$expansion_rate;
	}

	public static function setRotationSpeed (float $ticks) : void
	{
		self::$rotation_speed = $ticks;
	}

	public static function getRotationSpeed () : float
	{
		return self::$rotation_speed;
	}

	// set speed for each of the planes
	public static function setMovementSpeed (float $x, float $y, float $z) : void
	{
		self::$movement_speed['x'] = $x;
		self::$movement_speed['y'] = $y;
		self::$movement_speed['z'] = $z;
	}

	public static function getMovementSpeed () : array
	{
		return self::$movement_speed;
	}

	// set a direction of movement: #units-x, #units-y, #units-z
	public static function setMovementDirection(float $x, float $y, float $z)
	{
		self::$movement_direction['x'] = $x;
		self::$movement_direction['y'] = $y;
		self::$movement_direction['z'] = $z;
	}

	public static function getMovementDirection () : array
	{
		if ((empty(self::$movement_direction['x'])) && (empty(self::$movement_direction['y'])) && (empty(self::$movement_direction['z'])))
		{
			Utility::write("The universe is at a dead stand still", LOG_NOTICE, L_CONSOLE);
		}
		return self::$movement_direction;
	}

	// set the location of the universe center (yes, it also has space in which to move)
	public static function setLocation (float $x, float $y, float $z) : bool
	{
		// this needs to be redone, however, I am just going to leave it for now.
		if (($x >= floatval(self::$max_x / 2)) || ($y >= floatval(self::$max_y / 2)) || ($z >= floatval(self::$max_z / 2)))
		{
			Utility::write("Attempt to set the center of the Universe to the invalid coordinates ($x, $y, $z) failed", LOG_ERR, L_CONSOLE);
			return false;
		}
		else
		{
			Utility::write("Successfully set the center of the Universe to ($x, $y, $z)", LOG_NOTICE, L_CONSOLE);
			return true;
		}
	}

	public static function getLastLocation () : array
	{
		return self::$last_location;
	}

	public static function getLocation () : array
	{
		return self::$current_location;
	}

	public static function getAge () : float
	{
		return self::$age;
	}

	///////////////// INSTANCE METHODS //////////////////

	public function __construct (string $name, float $max_x, float $max_y, float $max_z)
	{
		$this->initializeObject();
		$this->name = Utility::cleanse_string($name);
		self::setMaxX ($max_x);
		self::setMaxY ($max_y);
		self::setMaxZ ($max_z);
		self::tick();
	}
	
	public function __destruct ()
	{
		true;
	}

	public function initializeObject () : void
	{
		$this->galaxies = array();
		$this->objects = array();
		$this->empty_space = array('x' => null, 'y' => null, 'z' => null);
		$this->used_space = array('x' => null, 'y' => null, 'z' => null);
		$this->fill_factor = array('x' => null, 'y' => null, 'z' => null);
		$this->location = array('x' => null, 'y' => null, 'z' => null);
		$this->rotationTime = 0;
		$this->rotationTimer = new Timer();
		$this->eventTime = 0;
		$this->eventTimer = new Timer();
		$this->createTime = 0;
		$this->createTimer = new Timer;
		$this->ticks = 0;
		$this->tickEvent = false;
		$this->tickEvents = array();
	}

	public function getTicks() : float {
		return (floatval($this->ticks));
	}

	// this is responsible for adjusting the spin rate of the universe object
	public function spin(float $adjust = 0) : void
	{
		// if the universe was not rotating and the adjustment is positive:
		if ((self::$rotation_speed === 0) && ((self::$rotation_speed + $adjust) > 0))
		{
			Utility::write("The Universe has started rotating in a positive direction", LOG_INFO, L_CONSOLE);
		}
		// if the universe was not rotating and the adjustment is negative:
		if ((self::$rotation_speed === 0) && ((self::$rotation_speed + $adjust) < 0))
		{
			Utility::write("The Universe has started rotating in a negative direction", LOG_INFO, L_CONSOLE);
		}
		// if the adjustment to the universe rotation rate becomes 0:
		if ((self::$rotation_speed + $adjust) === 0)
		{
			Utility::write("The Universe has stopped rotating", LOG_INFO, L_CONSOLE);
		}
		// slow/reverse rotation rate:
		if ($adjust <= 0)
		{
			// if the rotation rate was positive and the adjustment brought it to negative:
			if ((self::$rotation_speed > 0) && ((self::$rotation_speed + $adjust) < 0))
			{
				Utility::write("The Universe has started rotating in reverse (-)", LOG_INFO, L_CONSOLE);
			}
			elseif ((self::$rotation_speed < 0) && ((self::$rotation_speed + $adjust) > 0))
			{
				Utility::write("The Universe has started rotating in reverse (+)", LOG_INFO, L_CONSOLE);
			}
			else
			{
				Utility::write("Rotation slowing by factor of $adjust", LOG_INFO, L_CONSOLE);
			}
		}
		elseif ($adjust === 0)
		{
			Utility::write("The rotation adjustment had no affect", LOG_INFO, L_CONSOLE);
		}
		// increase rotation rate:
		else
		{
			if ((self::$rotation_speed > 0) && ((self::$rotation_speed + $adjust) < 0))
			{
				Utility::write("The Universe has started rotating in reverse (-)", LOG_INFO, L_CONSOLE);
			}
			elseif ((self::$rotation_speed < 0) && ((self::$rotation_speed + $adjust) > 0))
			{
				Utility::write("The Universe has started rotating in reverse (+)", LOG_INFO, L_CONSOLE);
			}
			else
			{
				Utility::write("Rotation increased by factor of $adjust", LOG_INFO, L_CONSOLE);
			}
		}
		self::$rotation_speed += $adjust;
	}

	private function randomEvent () : void
	{
		// randomly roll for the object which will be the only one guaranteed to be affected by this event
		$object = random_int(1,intval(count(Universe::$objectList)));
		$objIDs[] = $object;
		if ((random_int(1,394823) % 2) === 0) {
			// if the value picked from the random pool is even, set the multiObject flag
			$multiObject = true;
		} else {
			$multiObject = false;
		}
		if ($multiObject === true)
		{
			$allAffected = false;
			$numAffected = intval(random_int(0,intval(log((1.5*(3/7))-25)))); // find how many objects are affected by the event
			if ($numAffected > self::$numObjects)
			{
				// if the number of affected objects is greater than the number of objects
				$allAfected = true;
			}
			while (($allAffected !== true) && (intval($numAffected) > 1)) 
			{ // if the number of objects to choose > 1, iterate loop code
				if (count($objIDs) <= $numAffected)
				{
					// as long as we don't have more objects in the affected pool, grab and stuff another id
					// we will allow duplicated ids in the pool. A duplicate means that the object with multiple entries is affected multiple times
					$objIDs[] = random_int(1,self::$numObjects); // get a random object id and stuff it in the array with the first object
				}
				$numAffected--;
			}
		}
		$verb = random_int(1,4);
		switch($verb)
		{
			case 1:
				// something happened
				// echo "the Universe {$this->name} expanded by .01 in all directions" . PHP_EOL;
				$this->grow(.01,.01,.01);
				break;
			case 2:
				// something happened
				// echo "the Universe {$this->name} shrunk by .01 in all directions" . PHP_EOL;
				$this->shrink(.01,.01,.01);
				break;
			case 3:
				// something happened
				Utility::write("Something may or may not have happened",LOG_INFO, L_CONSOLE);
				break;
			case 4:
				// something happened
				Utility::write("Not sure what happened, if anything...", LOG_INFO, L_CONSOLE);
				break;
			default :
				break;
		}
	}

	public function tick() : void
	{
		// check for the occurrance of a random event
		$this->randomEventChance = random_int(1,100);	// get the random percentage
		if ($this->randomEventChance > 0) // if the number of rolls > 0, which it will always be...
		{
			// find the number to hit
			$eventRollHit = random_int(1,1000); // get a number, that when "hit", will trigger an event
			for ($rollNum = 1; $rollNum <= $this->randomEventChance; $rollNum++) // if rollNum is <= the randomEventChance we rolled earlier start loop
			{
				// get an integer between 1 and 1000
				$randomEventRoll = random_int(1,1000); // roll random between 1 and 1000.
				if ($randomEventRoll === $eventRollHit) // if it matches the static number we set before the looper
				{
					$this->randomEvent(); // trigger a random event
				}
			}
		}
		$randomEventRoll = random_int(1,1000);
		// if we are not rotating, we cannot perform methods affecting rotation
		if (self::$rotation_speed === 0) return;
		if ($this->ticks === self::$rotation_speed) // new day
		{
			self::newDay();
			return;
		}
		else
		{
			// advance tick count
			$this->ticks++;
			if (!empty($this->tickEvents[$this->ticks])) $this->tickEvent = true;
			// if there is a tickEvent for this tick, perform the action
			if ($this->tickEvent === true)
			{
				$this->doTickEvent ();
			}
		}

	} // end of tick

	private function doTickEvent () : void
	{
		// tickEvents are not yet implemented
		return;
	}

	// grow only x-plane
	public function growX (int $x) : bool
	{
		self::$max_x += $x;
		$this->free_space['x'] = self::$max_x - self::$current_x;
		Utility::write("The Universe has expanded its x-plane by $x units", LOG_INFO, L_CONSOLE);
	}

	// expand only y-plane
	public function growY (float $y) : void
	{
		self::$max_y += $y;
		$this->free_space['y'] = self::$max_y - self::$current_y;
		Utility::write("The Universe has expanded its y-plane by $y units", LOG_INFO, L_CONSOLE);
	}

	// expand only z-plane
	public function growZ (float $z) : void
	{
		self::$max_z += $z;
		$this->free_space['z'] = self::$max_z - self::$current_z;
		Utility::write("The Universe has expanded its z-plane by $z units", LOG_INFO, L_CONSOLE);
	}

	// grow by cubic volume -- this operates on max_x/y/z, not current.
	public function grow (float $x, float $y, float $z) : void
	{
		self::$max_x += $x;
		self::$max_y += $y;
		self::$max_z += $z;
		Utility::write("The Universe has expanded by: $x,$y,$z (" . ($x * $y * $z) . ") units", LOG_INFO, L_CONSOLE);
	}

	public function shrinkX (float $x) : bool
	{
		$newMaxX = self::$max_x - $x;
		if (self::$current_x > $newMaxX)
		{
			Utility::write("Shrinking the x-plane by $x units would cause the new size to be smaller than the currently used size", LOG_ERROR, L_CONSOLE);
			return false;
		}
		if ($newMaxX >= 1)
		{
			self::$max_x -= $newMaxX;
			$this->empty_space['x'] = $newMaxX - self::$current_x;
			$this->used_space['x'] = self::$current_x;
			Utility::write("The Universe has contracted its x-plane by $x units", LOG_INFO, L_CONSOLE);
			if ($this->empty_space['x'] === 1) Utility::write("The universe has reached the minimum x-plane. Further contraction is not possible.", LOG_WARNING, L_CONSOLE);
			return true;
		}
		else
		{
			if ($newMaxX <= 0)
			{
				$xSizeViolation = (self::$current_x - $newMaxX);
				Utility::write("Contraction of the x-plane by $x would cause the universe to lose its x-plane ($xSizeViolation)", LOG_WARNING, L_CONSOLE);
			}
		}
		return false;
	}

	public function shrinkY (float $y) : bool
	{
		$newMaxY = self::$max_y - $y;
		if (self::$current_y > $newMaxY)
		{
			Utility::write("Shrinking the y-plane by $y units would cause the new size to be greater than the currently used size", LOG_ERROR, L_CONSOLE);
			return false;
		}
		if ($newMaxY >= 1)
		{
			self::$max_y = $newMaxY;
			$this->empty_space['y'] = $newMaxY - self::$current_y;
			$this->used_space['y'] = self::$current_z;
			Utility::write("The Universe has contracted its y-plane by $y units", LOG_INFO, L_CONSOLE);
			if ($this->empty_space['y'] === 1) Utility::write("The universe has reached the minimum y-plane. Further contraction is not possible.", LOG_WARNING, L_CONSOLE);
			return true;
		}
		else
		{
			if ($newMaxY <= 0)
			{
				$ySizeViolation = (self::$current_y - $newMaxY);
				Utility::write("Contraction of the y-plane by $y would cause the universe to lose its y-plane ($ySizeViolation)", LOG_WARNING, L_CONSOLE);
			}
		}
		return false;
	}

	public function shrinkZ (float $z) : bool
	{
		// to shrink, we need to ensure that current_z is <= (max_z -z)
		$newMaxZ = self::$max_z - $z;
		if (self::$current_z > $newMaxZ)
		{
			Utility::write("Shrinking the z-plane by $z units would cause the new size to be smaller than the currently used size", LOG_ERROR, L_CONSOLE);
			return false;
		}
		// if the result of the shrink is 1 or more units....
		if ($newMaxZ >= 1)
		{
			self::$max_z = $newMaxZ;
			// recalculate used & free space on the z-plane
			$this->empty_space['z'] = $newMaxZ - self::$current_z;
			$this->used_space['z'] = self::$current_z;
			Utility::write("The Universe has contracted its z-plane by $z units", LOG_INFO, L_CONSOLE);
			if ($this->empty_space['z'] === 1) Utility::write("The universe has reached the minimum z-plane. Further contraction is not possible.", LOG_WARNING, L_CONSOLE);
			return true;
		}
		// otherwise, if the result of the shrink is 0 or less units...
		else
		{
			if ($newMaxZ <= 0)
			{
				$zSizeViolation = (self::$current_z - $newMaxZ);
				Utility::write("Contraction of the z-plane by $z would cause the universe to lose its z-plane ($zSizeViolation)", LOG_WARNING, L_CONSOLE);
			}
		}
		return false;
	}

	public function shrink (int $x = 0, int $y = 0, int $z = 0) : bool
	{
		try {
			if (!$this->shrinkX($x)) throw new Exception ("Failed to shrink the x-plane by $x",1);
			if (!$this->shrinkY($y)) throw new Exception ("Failed to shrink the y-plane by $y",2);
			if (!$this->shrinkZ($z)) throw new Exception ("Failed to shrink the z-plane by $z",3);
			//Utility::write("The Universe has contracted by: $x,$y,$z (" . ($x * $y * $z) . ") units", LOG_INFO, L_CONSOLE);	
		}
		catch (Exception $e)
		{
			switch ($e->getCode())
			{
				case 1:
					$x = 0;
					break;
				case 2:
					$y = 0;
					break;
				case 3:
					$z = 0;
					break;
			}
		}
		finally
		{
			//$units = (($x <= 0 ? 1 : $x) * ($y <= 0 ? 1 : $y) * ($z <= 0 ? 1 : $z));
			Utility::write("The Universe has contracted by: $x, $y, $z (" . (($x <= 0 ? 1 : $x) * ($y <= 0 ? 1 : $y) * ($z <= 0 ? 1 : $z)) . ") units", LOG_INFO, L_CONSOLE);
		  return true;
		}
		return false;
	}

	public function getFreeX () : float
	{
		$this->empty_space['x'] = self::$max_x - self::$current_x;
		return $this->empty_space['x'];
	}

	public function getFreeY () : float
	{
		$this->empty_space['y'] = self::$max_y - self::$current_x;
		return $this->empty_space['y'];
	}

	public function getFreeZ () : float
	{
		$this->empty_space['z'] = self::$max_z - self::$current_z;
		return $this->empty_space['z'];
	}

	public function getFreeSpace () : array
	{
		$this->getFreeX();
		$this->getFreeY();
		$this->getFreeZ();
		return $this->empty_space;
	}

	
        public function registerGalaxy (Galaxy $galaxy) : void
        {
                $name = $galaxy->name;
                $this->galaxies[$name] =& $galaxy;
                $found = false;
                foreach (self::$objectList as $index => $object)
                {
                        if (($object instanceof Galaxy) && ($object->name === $name))
                        {
                                self::$objectList[$index] =& $this->galaxies[$name];
                                $found = true;
                                break;
                        }
                }
                if ($found === false)
                {
                        self::$objectList[] =& $this->galaxies[$name];
                }
                self::$numGalaxies = count($this->galaxies);
        }

        public function getGalaxies () : array
        {
                return $this->galaxies;
        }

        public function getGalaxy (string $name) : ?Galaxy
        {
                $cleanName = Utility::cleanse_string($name);
                if (!isset($this->galaxies[$cleanName])) return null;
                return $this->galaxies[$cleanName];
        }

        public function advance (float $deltaTime = 1.0) : void
        {
                $this->tick();
                foreach ($this->galaxies as $galaxy)
                {
                        if ($galaxy instanceof Galaxy)
                        {
                                $galaxy->tick($deltaTime);
                        }
                }
        }

        public function createGalaxy (string $name, float $x = null, float $y = null, float $z = null) : bool
        {
                if ($x === null)
                {
                        $x = floatval(random_int(1, $this->empty_space['x']));
                }
                if ($y === null)
                {
                        $y = floatval(random_int(1, $this->empty_space['y']));
                }
                if ($z === null)
                {
                        $z = floatval(random_int(1, $this->empty_space['z']));
                }
                foreach ($this->galaxies as $galaxy)
                {
                        if (!is_a($galaxy, 'Galaxy')) continue;
                        if ($galaxy->name === $name)
                        {
                                Utility::write("Galaxy $name already exists, aborting", LOG_WARNING, L_CONSOLE);
                                return false;
                        }
                }
                $galaxy = new Galaxy($name);
                $this->registerGalaxy($galaxy);
                $galaxy->setMaxX($x);
                self::$current_x += $x;
                $this->empty_space['x'] -= $x;
                $galaxy->setMaxY($y);
                self::$current_y += $y;
                $this->empty_space['y'] -= $y;
                $galaxy->setMaxZ($z);
                self::$current_z += $z;
                $this->empty_space['z'] -= $z;
                return true;
        }

        public function galaxyList () : void
	{
		foreach ($this->galaxies as $idx => $galaxy) {
			if (empty($galaxy)) continue;
			echo "Galaxy: " . $galaxy->name . PHP_EOL;
		}
	}

	public function objectList () : void
	{
		foreach (self::$objectList as $idx => $object) {
			if (empty($object) || !(is_object($object))) continue;
			echo "Object: " . $object->name . PHP_EOL;
		}
	}

	public function dump () : void
	{
		var_dump (get_class_vars("Universe"));
	}
}
?>
