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
	private $intergalacticTravelers;
	private $intersystemTravelers;
	private $maxTransitObjects;

	private $randomEventChance;

	// timed events
	private $rotationStart;
	private $rotationTime;			// duration until a single rotation occurs
	private $rotationTimer;			// timer object to track rotations
	private $eventTime;			// duration until an event occurs
	private $eventTimer;			// timer object to track events
	private $createTime;			// duration until a creation event ocurrs
	private $createTimer;			// timer object to track creations
	private $workerCount;
	private $parallelWarningEmitted;

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
		self::$objectList = array();
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

        public function getName () : string
        {
                return $this->name;
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
                $this->intergalacticTravelers = array();
                $this->intersystemTravelers = array();
                $this->maxTransitObjects = 256;
                $this->workerCount = 1;
                $this->parallelWarningEmitted = false;

        }

        public function setWorkerCount (int $workers) : void
        {
                $this->workerCount = max(1, intval($workers));
                if ($this->workerCount === 1)
                {
                        $this->parallelWarningEmitted = false;
                }
        }

        public function getWorkerCount () : int
        {
                return max(1, intval($this->workerCount));
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
                $eventType = $this->selectCosmicEventType();
                switch ($eventType)
                {
                        case 'galactic-collision':
                                $this->initiateGalacticCollisionEvent();
                                break;
                        case 'stellar-mass-loss':
                                $this->initiateStellarMassLossEvent();
                                break;
                        case 'spawn-intergalactic-object':
                                $this->spawnIntergalacticTraveler();
                                break;
                        case 'spawn-intersystem-object':
                                $this->spawnIntersystemTraveler();
                                break;
                        case 'universal-expansion':
                                $this->grow(0.01, 0.01, 0.01);
                                break;
                        case 'universal-contraction':
                                $this->shrink(0.01, 0.01, 0.01);
                                break;
                        default:
                                Utility::write('Cosmic background hum maintains equilibrium.', LOG_DEBUG, L_CONSOLE);
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
                $step = max(0.0, $deltaTime);
                $this->tick();
                $this->simulateMacroDynamics($step);
                if ($this->attemptParallelAdvance($step))
                {
                        return;
                }
                foreach ($this->galaxies as $galaxy)
                {
                        if ($galaxy instanceof Galaxy)
                        {
                                $galaxy->tick($step);
                        }
                }
        }

        private function attemptParallelAdvance (float $deltaTime) : bool
        {
                $workers = $this->getWorkerCount();
                if ($workers <= 1)
                {
                        return false;
                }
                if (count($this->galaxies) <= 1)
                {
                        return false;
                }
                if (!function_exists('\parallel\run'))
                {
                        if (!$this->parallelWarningEmitted)
                        {
                                Utility::write('parallel extension not available; running sequentially.', LOG_WARNING, L_CONSOLE);
                                $this->parallelWarningEmitted = true;
                        }
                        return false;
                }

                $payloads = array();
                foreach ($this->galaxies as $name => $galaxy)
                {
                        if (!($galaxy instanceof Galaxy))
                        {
                                continue;
                        }
                        try
                        {
                                $payloads[$name] = serialize($galaxy);
                        }
                        catch (\Throwable $throwable)
                        {
                                Utility::write('Failed to serialize galaxy ' . $name . ': ' . $throwable->getMessage(), LOG_WARNING, L_CONSOLE);
                                return false;
                        }
                }
                if (count($payloads) <= 1)
                {
                        return false;
                }

                $slice = max(1, (int) ceil(count($payloads) / min($workers, count($payloads))));
                $chunks = array_chunk($payloads, $slice, true);
                $futures = array();
                foreach ($chunks as $chunk)
                {
                        $futures[] = \parallel\run(function (array $payload, float $delta, string $root) {
                                require_once $root . '/config.php';
                                $results = array();
                                foreach ($payload as $name => $serialized)
                                {
                                        try
                                        {
                                                $galaxy = unserialize($serialized);
                                        }
                                        catch (\Throwable)
                                        {
                                                $galaxy = null;
                                        }
                                        if ($galaxy instanceof Galaxy)
                                        {
                                                $galaxy->tick($delta);
                                                try
                                                {
                                                        $results[$name] = serialize($galaxy);
                                                }
                                                catch (\Throwable)
                                                {
                                                        // Skip entries we cannot marshal back to the main runtime.
                                                }
                                        }
                                }
                                return $results;
                        }, array($chunk, $deltaTime, PHPROOT));
                }

                $updated = array();
                $hadFailure = false;
                foreach ($futures as $future)
                {
                        try
                        {
                                $result = $future->value();
                                if (is_array($result))
                                {
                                        $updated = array_merge($updated, $result);
                                }
                        }
                        catch (\Throwable $throwable)
                        {
                                Utility::write('Parallel advance failed: ' . $throwable->getMessage(), LOG_WARNING, L_CONSOLE);
                                $hadFailure = true;
                        }
                }

                if ($hadFailure)
                {
                        return false;
                }

                foreach ($updated as $name => $serialized)
                {
                        try
                        {
                                $galaxy = unserialize($serialized);
                        }
                        catch (\Throwable $throwable)
                        {
                                Utility::write('Failed to hydrate galaxy ' . $name . ': ' . $throwable->getMessage(), LOG_WARNING, L_CONSOLE);
                                continue;
                        }
                        if ($galaxy instanceof Galaxy)
                        {
                                $this->galaxies[$name] = $galaxy;
                                $this->registerGalaxy($galaxy);
                        }
                }

                return !empty($updated);
        }

	private function simulateMacroDynamics (float $deltaTime) : void
	{
		$step = max(0.0, $deltaTime);
		$this->simulateGalaxyInteractions($step);
		$this->updateTransitObjects($step);
	}

	private function simulateGalaxyInteractions (float $deltaTime) : void
	{
		$galaxies = array_values($this->galaxies);
		$count = count($galaxies);
		if ($count < 2)
		{
			return;
		}
		$totalPairs = max(1, intdiv($count * ($count - 1), 2));
		$samples = min(3, $totalPairs);
		for ($i = 0; $i < $samples; $i++)
		{
			$pair = $this->selectGalaxyPair($galaxies);
			if ($pair === null)
			{
				break;
			}
			list($a, $b) = $pair;
			if (!($a instanceof Galaxy) || !($b instanceof Galaxy))
			{
				continue;
			}
			$radiusSum = $a->getInfluenceRadius() + $b->getInfluenceRadius();
			$distance = $a->distanceTo($b);
			if (($radiusSum <= 0.0) && ($distance <= 0.0))
			{
				continue;
			}
			if ($radiusSum <= 0.0)
			{
				$radiusSum = max(1.0, $distance);
			}
			if ($distance <= ($radiusSum * 1.2))
			{
				$this->handleGalacticTidalInteraction($a, $b, $distance, $radiusSum, $deltaTime);
			}
		}
	}

	private function handleGalacticTidalInteraction (Galaxy $a, Galaxy $b, float $distance, float $radiusSum, float $deltaTime) : void
	{
		$locA = $a->getLocation();
		$locB = $b->getLocation();
		$dx = $locB['x'] - $locA['x'];
		$dy = $locB['y'] - $locA['y'];
		$dz = $locB['z'] - $locA['z'];
		$length = sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
		if ($length <= 0.0)
		{
			$dx = 1.0;
			$dy = 0.0;
			$dz = 0.0;
			$length = 1.0;
		}
		$ux = $dx / $length;
		$uy = $dy / $length;
		$uz = $dz / $length;
		$penetration = max(0.0, ($radiusSum > 0.0) ? ($radiusSum - $distance) : 0.0);
		$severity = ($radiusSum > 0.0) ? min(1.0, $penetration / max($radiusSum, 1.0)) : 0.0;
		$nudgeBase = max(0.05, $deltaTime * 0.05);
		$nudge = $nudgeBase * (1.0 + $severity);
		$a->translate(-$ux * $nudge, -$uy * $nudge, -$uz * $nudge);
		$b->translate($ux * $nudge, $uy * $nudge, $uz * $nudge);
		if (($severity > 0.05) && (random_int(1, 50) === 1))
		{
			$percent = round($severity * 100.0, 1);
			$a->addChronicleEntry('galactic-tide', sprintf('%s exerted a tidal overlap of %.1f%%.', $b->name, $percent), self::$age, array($a->name, $b->name));
			$b->addChronicleEntry('galactic-tide', sprintf('%s exerted a tidal overlap of %.1f%%.', $a->name, $percent), self::$age, array($a->name, $b->name));
		}
	}

	private function selectGalaxyPair (?array $galaxies = null) : ?array
	{
		if ($galaxies === null)
		{
			$galaxies = array_values($this->galaxies);
		}
		$count = count($galaxies);
		if ($count < 2)
		{
			return null;
		}
		$indexes = array_rand($galaxies, 2);
		if (!is_array($indexes))
		{
			return null;
		}
		$first = $galaxies[$indexes[0]];
		$second = $galaxies[$indexes[1]];
		if (!($first instanceof Galaxy) || !($second instanceof Galaxy))
		{
			return null;
		}
		return array($first, $second);
	}

	private function selectCosmicEventType () : string
	{
		$systemTuples = $this->collectSystemTuples();
		$systemCount = count($systemTuples);
		$galaxyCount = count($this->galaxies);
		$weights = array(
			'galactic-collision' => ($galaxyCount >= 2) ? 3 : 0,
			'stellar-mass-loss' => ($systemCount > 0) ? 4 : 0,
			'spawn-intergalactic-object' => ($galaxyCount >= 2) ? 5 : 0,
			'spawn-intersystem-object' => ($systemCount > 0) ? 5 : 0,
			'universal-expansion' => 1,
			'universal-contraction' => 1,
			'none' => 3
		);
		$totalWeight = array_sum($weights);
		if ($totalWeight <= 0)
		{
			return 'none';
		}
		$roll = random_int(1, $totalWeight);
		$cumulative = 0;
		foreach ($weights as $type => $weight)
		{
			if ($weight <= 0)
			{
				continue;
			}
			$cumulative += $weight;
			if ($roll <= $cumulative)
			{
				return $type;
			}
		}
		return 'none';
	}

	private function initiateGalacticCollisionEvent () : void
	{
		$pair = $this->selectGalaxyPair();
		if ($pair === null)
		{
			return;
		}
		list($a, $b) = $pair;
		$distance = $a->distanceTo($b);
		$radiusSum = $a->getInfluenceRadius() + $b->getInfluenceRadius();
		if ($radiusSum <= 0.0)
		{
			$radiusSum = max(1.0, $distance);
		}
		$penetration = max(0.0, $radiusSum - $distance);
		$severity = ($radiusSum > 0.0) ? min(1.0, $penetration / $radiusSum) : 0.0;
		$percent = round($severity * 100.0, 1);
		Utility::write(
			sprintf('Galactic collision between %s and %s with overlap %.1f%%.', $a->name, $b->name, $percent),
			LOG_NOTICE,
			L_CONSOLE
		);
		$a->addChronicleEntry(
			'galactic-collision',
			sprintf('%s swept through the halo with %.1f%% overlap.', $b->name, $percent),
			self::$age,
			array($a->name, $b->name)
		);
		$b->addChronicleEntry(
			'galactic-collision',
			sprintf('%s collided with the halo at %.1f%% overlap.', $a->name, $percent),
			self::$age,
			array($a->name, $b->name)
		);
		$this->handleGalacticTidalInteraction($a, $b, $distance, $radiusSum, 1.0 + $severity);
		if ($severity > 0.05)
		{
			$this->spawnCollisionDebris($a, $b, $severity);
		}
	}

	private function spawnCollisionDebris (Galaxy $origin, Galaxy $destination, float $severity) : void
	{
		if ($this->totalTransitObjects() >= $this->maxTransitObjects)
		{
			$this->trimTransitQueues();
			if ($this->totalTransitObjects() >= $this->maxTransitObjects)
			{
				return;
			}
		}
		$originPos = $origin->getLocation();
		$destinationPos = $destination->getLocation();
		$distance = $origin->distanceTo($destination);
		$speed = max(10000.0, $severity * 250000.0);
		$travelTime = ($speed > 0.0) ? ($distance / $speed) : 0.0;
		$travelTime = max(3600.0, $travelTime);
		$velocity = $this->computeVelocityVector($originPos, $destinationPos, $speed);
		$name = sprintf('%s debris plume', $origin->name);
		$traveler = new TransitObject(
			$name,
			max(1.0E12, $severity * 1.0E13),
			max(1.0E6, $severity * 5.0E6),
			$originPos,
			$destinationPos,
			$travelTime,
			TransitObject::SCOPE_INTERGALACTIC,
			'tidal slingshot',
			'ragged filament',
			$velocity
		);
		$traveler->setEndpoints($origin->name, $destination->name);
		$traveler->setContext(array(
			'origin_galaxy' => $origin->name,
			'destination_galaxy' => $destination->name,
			'event' => 'collision-debris',
			'severity' => $severity
		));
		$this->intergalacticTravelers[] = $traveler;
		$origin->addChronicleEntry(
			'debris-launch',
			sprintf('Debris plume launched toward %s after collision.', $destination->name),
			self::$age,
			array($origin->name, $destination->name)
		);
		$this->trimTransitQueues();
	}

	private function initiateStellarMassLossEvent () : void
	{
		$tuples = $this->collectSystemTuples();
		if (empty($tuples))
		{
			return;
		}
		$tuple = $tuples[array_rand($tuples)];
		$galaxy = $tuple['galaxy'];
		$system = $tuple['system'];
		if (!($system instanceof System) || !($galaxy instanceof Galaxy))
		{
			return;
		}
		$star = $system->getPrimaryStar();
		if (!($star instanceof Star))
		{
			return;
		}
		$previousMass = $star->getMass();
		if ($previousMass <= 0.0)
		{
			return;
		}
		$lossFraction = random_int(5, 25) / 100.0;
		$newMass = max(0.0, $previousMass * (1.0 - $lossFraction));
		$star->setMass($newMass);
		$star->setLuminosity($star->getLuminosity() * (1.0 - ($lossFraction / 2.0)));
		$star->setRadius(max(0.0, $star->getRadius() * (1.0 - ($lossFraction / 3.0))));
		$ejected = $system->respondToPrimaryMassShift($previousMass, $newMass);
		foreach ($ejected as $object)
		{
			if ($object instanceof SystemObject)
			{
				$this->registerEjectedBody($galaxy, $system, $object);
			}
		}
		$percent = round($lossFraction * 100.0, 1);
		Utility::write(
			sprintf('%s shed %.1f%% of its mass inside %s.', $star->getName(), $percent, $system->getName()),
			LOG_INFO,
			L_CONSOLE
		);
		$galaxy->addChronicleEntry(
			'stellar-mass-loss',
			sprintf('%s reported %.1f%% stellar mass shedding in %s.', $star->getName(), $percent, $system->getName()),
			self::$age,
			array($galaxy->name, $system->getName(), $star->getName())
		);
	}

	private function registerEjectedBody (Galaxy $galaxy, System $system, SystemObject $object) : void
	{
		if ($this->totalTransitObjects() >= $this->maxTransitObjects)
		{
			$this->trimTransitQueues();
			if ($this->totalTransitObjects() >= $this->maxTransitObjects)
			{
				return;
			}
		}
		$origin = $object->getPosition();
		$velocity = $object->getVelocity();
		$speed = sqrt(($velocity['x'] * $velocity['x']) + ($velocity['y'] * $velocity['y']) + ($velocity['z'] * $velocity['z']));
		if ($speed <= 0.0)
		{
			$speed = random_int(500, 2000);
		}
		$destination = array(
			'x' => $origin['x'] + ($velocity['x'] * 1000.0),
			'y' => $origin['y'] + ($velocity['y'] * 1000.0),
			'z' => $origin['z'] + ($velocity['z'] * 1000.0)
		);
		$distance = sqrt(
			pow($destination['x'] - $origin['x'], 2) +
			pow($destination['y'] - $origin['y'], 2) +
			pow($destination['z'] - $origin['z'], 2)
		);
		$travelTime = max(86400.0, ($distance / max($speed, 1.0)));
		$traveler = new TransitObject(
			$object->getName() . ' (Ejected)',
			$object->getMass(),
			$object->getRadius(),
			$origin,
			$destination,
			$travelTime,
			TransitObject::SCOPE_INTERSYSTEM,
			'ballistic momentum',
			'shattered world fragment',
			$velocity
		);
		$traveler->setEndpoints($system->getName(), 'Deep interstellar medium');
		$traveler->setContext(array(
			'origin_system' => $system->getName(),
			'origin_galaxy' => $galaxy->name,
			'departure_object' => $object->getName(),
			'event' => 'stellar-mass-loss'
		));
		$this->intersystemTravelers[] = $traveler;
		$system->addChronicleEntry(
			'mass-loss-ejection',
			sprintf('%s escaped the system following stellar mass shedding.', $object->getName()),
			$system->getAge(),
			array($object->getName())
		);
		$this->trimTransitQueues();
	}

	private function spawnIntergalacticTraveler () : void
	{
		if ($this->totalTransitObjects() >= $this->maxTransitObjects)
		{
			$this->trimTransitQueues();
			if ($this->totalTransitObjects() >= $this->maxTransitObjects)
			{
				return;
			}
		}
		$pair = $this->selectGalaxyPair();
		if ($pair === null)
		{
			return;
		}
		list($origin, $destination) = $pair;
		$originPos = $origin->getLocation();
		$destinationPos = $destination->getLocation();
		$distance = $origin->distanceTo($destination);
		$speed = random_int(50000, 350000);
		$travelTime = ($speed > 0) ? ($distance / $speed) : 0.0;
		$travelTime = max(3600.0, $travelTime);
		$propulsion = array_rand(array_flip(array(
			'solar sail',
			'fusion torch',
			'antimatter wake',
			'magnetic ramjet'
		)));
		$shape = array_rand(array_flip(array(
			'needle hull',
			'icosahedral lattice',
			'spindle frame',
			'whip-tail shard'
		)));
		$velocity = $this->computeVelocityVector($originPos, $destinationPos, $speed);
		$name = sprintf('%s wayfarer %d', $origin->name, random_int(1000, 9999));
		$traveler = new TransitObject(
			$name,
			max(1.0E9, random_int(1, 9) * 1.0E9),
			max(1000.0, random_int(1, 5) * 1000.0),
			$originPos,
			$destinationPos,
			$travelTime,
			TransitObject::SCOPE_INTERGALACTIC,
			$propulsion,
			$shape,
			$velocity
		);
		$traveler->setEndpoints($origin->name, $destination->name);
		$traveler->setContext(array(
			'origin_galaxy' => $origin->name,
			'destination_galaxy' => $destination->name,
			'propulsion' => $propulsion,
			'shape' => $shape
		));
		$this->intergalacticTravelers[] = $traveler;
		$origin->addChronicleEntry(
			'intergalactic-launch',
			sprintf('%s departed toward %s using %s propulsion.', $name, $destination->name, $propulsion),
			self::$age,
			array($origin->name, $destination->name)
		);
		$this->trimTransitQueues();
	}

	private function spawnIntersystemTraveler () : void
	{
		if ($this->totalTransitObjects() >= $this->maxTransitObjects)
		{
			$this->trimTransitQueues();
			if ($this->totalTransitObjects() >= $this->maxTransitObjects)
			{
				return;
			}
		}
		$tuples = $this->collectSystemTuples();
		if (empty($tuples))
		{
			return;
		}
		$tuple = $tuples[array_rand($tuples)];
		$galaxy = $tuple['galaxy'];
		$system = $tuple['system'];
		if (!($system instanceof System) || !($galaxy instanceof Galaxy))
		{
			return;
		}
		$objects = $system->getObjects();
		if (empty($objects))
		{
			return;
		}
		$keys = array_keys($objects);
		$originName = $keys[array_rand($keys)];
		$originObject = $objects[$originName];
		if (!($originObject instanceof SystemObject))
		{
			return;
		}
		$originPos = $originObject->getPosition();
		$destinationPos = array(
			'x' => $originPos['x'] + random_int(-1000000, 1000000),
			'y' => $originPos['y'] + random_int(-1000000, 1000000),
			'z' => $originPos['z'] + random_int(-1000000, 1000000)
		);
		$speed = random_int(500, 150000);
		$distance = sqrt(
			pow($destinationPos['x'] - $originPos['x'], 2) +
			pow($destinationPos['y'] - $originPos['y'], 2) +
			pow($destinationPos['z'] - $originPos['z'], 2)
		);
		$travelTime = max(600.0, ($distance / max($speed, 1.0)));
		$propulsion = array_rand(array_flip(array('fusion torch', 'solar sail', 'ion drive', 'quantum spinnaker')));
		$shape = array_rand(array_flip(array('delta-wing courier', 'cylindrical barge', 'kite frame', 'monolith shard')));
		$velocity = $this->computeVelocityVector($originPos, $destinationPos, $speed);
		$name = sprintf('%s courier %d', $system->getName(), random_int(1000, 9999));
		$traveler = new TransitObject(
			$name,
			max(1.0E5, random_int(1, 50) * 1.0E5),
			max(50.0, random_int(1, 20) * 10.0),
			$originPos,
			$destinationPos,
			$travelTime,
			TransitObject::SCOPE_INTERSYSTEM,
			$propulsion,
			$shape,
			$velocity
		);
		$traveler->setEndpoints($originObject->getName() . ' orbit', $system->getName() . ' frontier');
		$traveler->setContext(array(
			'origin_system' => $system->getName(),
			'origin_galaxy' => $galaxy->name,
			'launch_object' => $originObject->getName(),
			'propulsion' => $propulsion
		));
		$this->intersystemTravelers[] = $traveler;
		$system->addChronicleEntry(
			'transit-launch',
			sprintf('%s departed from %s under %s propulsion.', $name, $originObject->getName(), $propulsion),
			$system->getAge(),
			array($originObject->getName(), $name)
		);
		if (method_exists($originObject, 'addChronicleEntry'))
		{
			$originObject->addChronicleEntry(
				'transit-launch',
				sprintf('Launched %s using %s propulsion.', $name, $propulsion),
				$system->getAge(),
				array($name)
			);
		}
		$this->trimTransitQueues();
	}

	private function computeVelocityVector (array $origin, array $destination, float $speed) : array
	{
		$dx = $destination['x'] - $origin['x'];
		$dy = $destination['y'] - $origin['y'];
		$dz = $destination['z'] - $origin['z'];
		$distance = sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
		if ($distance <= 0.0)
		{
			$distance = 1.0;
			$dx = 1.0;
			$dy = 0.0;
			$dz = 0.0;
		}
		$scale = $speed / $distance;
		return array(
			'x' => $dx * $scale,
			'y' => $dy * $scale,
			'z' => $dz * $scale
		);
	}

	private function updateTransitObjects (float $deltaTime) : void
	{
		$step = max(0.0, $deltaTime);
		foreach ($this->intergalacticTravelers as $index => $object)
		{
			if (!($object instanceof TransitObject))
			{
				unset($this->intergalacticTravelers[$index]);
				continue;
			}
			$object->advanceTransit($step);
			if ($object->isComplete())
			{
				$this->finalizeTransitObject($object);
				unset($this->intergalacticTravelers[$index]);
			}
		}
		$this->intergalacticTravelers = array_values($this->intergalacticTravelers);
		foreach ($this->intersystemTravelers as $index => $object)
		{
			if (!($object instanceof TransitObject))
			{
				unset($this->intersystemTravelers[$index]);
				continue;
			}
			$object->advanceTransit($step);
			if ($object->isComplete())
			{
				$this->finalizeTransitObject($object);
				unset($this->intersystemTravelers[$index]);
			}
		}
		$this->intersystemTravelers = array_values($this->intersystemTravelers);
		$this->trimTransitQueues();
	}

	private function finalizeTransitObject (TransitObject $object) : void
	{
		$context = $object->getContext();
		if ($object->getScope() === TransitObject::SCOPE_INTERGALACTIC)
		{
			$destinationName = $context['destination_galaxy'] ?? null;
			if (($destinationName !== null) && isset($this->galaxies[$destinationName]))
			{
				$galaxy = $this->galaxies[$destinationName];
				$galaxy->addChronicleEntry(
					'intergalactic-arrival',
					sprintf('%s arrived from %s powered by %s.', $object->getName(), $context['origin_galaxy'] ?? 'unknown origin', $object->getPropulsion()),
					self::$age,
					array($galaxy->name)
				);
			}
		}
		else
		{
			$systemName = $context['origin_system'] ?? ($context['destination_system'] ?? null);
			if ($systemName !== null)
			{
				foreach ($this->collectSystemTuples() as $tuple)
				{
					$system = $tuple['system'];
					if (($system instanceof System) && ($system->getName() === $systemName))
					{
						$system->addChronicleEntry(
							'transit-arrival',
							sprintf('%s completed a transit powered by %s.', $object->getName(), $object->getPropulsion()),
							$system->getAge(),
							array($object->getName())
						);
						break;
					}
				}
			}
		}
	}

	private function trimTransitQueues () : void
	{
		$total = $this->totalTransitObjects();
		if ($total <= $this->maxTransitObjects)
		{
			return;
		}
		$excess = $total - $this->maxTransitObjects;
		while (($excess > 0) && !empty($this->intersystemTravelers))
		{
			array_shift($this->intersystemTravelers);
			$excess--;
		}
		while (($excess > 0) && !empty($this->intergalacticTravelers))
		{
			array_shift($this->intergalacticTravelers);
			$excess--;
		}
	}

	private function totalTransitObjects () : int
	{
		return count($this->intergalacticTravelers) + count($this->intersystemTravelers);
	}

	private function collectSystemTuples () : array
	{
		$tuples = array();
		foreach ($this->galaxies as $galaxy)
		{
			if (!($galaxy instanceof Galaxy))
			{
				continue;
			}
			foreach ($galaxy->getSystems() as $system)
			{
				if (!($system instanceof System))
				{
					continue;
				}
				$tuples[] = array('galaxy' => $galaxy, 'system' => $system);
			}
		}
		return $tuples;
	}

        public function createGalaxy (string $name, ?float $x = null, ?float $y = null, ?float $z = null) : bool
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
                $maxBounds = array(
                        'x' => max(1, (int) round(self::$max_x)),
                        'y' => max(1, (int) round(self::$max_y)),
                        'z' => max(1, (int) round(self::$max_z))
                );
                $location = array();
                foreach ($maxBounds as $axis => $limit)
                {
                        if ($limit <= 2)
                        {
                                $location[$axis] = 1.0;
                                continue;
                        }
                        $location[$axis] = floatval(random_int(1, $limit - 1));
                }
                $galaxy->setLocation($location['x'], $location['y'], $location['z']);
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
