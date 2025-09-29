<?php // 7.3.0-dev
class Galaxy 
{
	// properties common to all instanced galaxies:
	public static $numSystems;
	public static $numObjects;
	public static $objectList;

	public $name;
	private $type;
	
	private $max_x;
	private $max_y;
	private $max_z;
	public $maxSize;

	private $current_x;
	private $current_y;
	private $current_z;
	public $currentSize;

	private $expansion_rate;
	private $rotation_speed;
	private $movement_speed;
	private $movement_direction;
	private $last_location;
	private $current_location;

	private $systems;
	private $objects;
	private $empty_space;
	private $used_space;
	private $fill_rate;

	private $age;
	private $ticks;
	private $tickEvents;

	private $randomEventChance;

	private $rotationStart;
	private $rotationTime;
	private $rotationTimer;
	private $eventTime;
	private $eventTimer;
	private $createTime;
	private $createTimer;

	public static function init() : void
	{
		self::$numSystems = 0;
		self::$numObjects = 0;
		self::$objectList = array();
	}

	public function __construct (string $name)
	{
		$this->initializeObject ();
		$this->name = Utility::cleanse_string($name);
		return;
	}

	private function initializeObject () : void
	{
		$this->name = null;
		$this->type = "Galaxy";
		$this->max_x = floatval(0);
		$this->max_y = floatval (0);
		$this->max_z = floatval (0);
		$this->maxSize = floatval (0);
		$this->current_x = floatval (0);
		$this->current_y = floatval (0);
		$this->current_z = floatval (0);
		$this->currentSize = floatval (0);
		$this->expansion_rate = array('x' => floatval(0), 'y' => floatval(0), 'z' => floatval(0));
		$this->rotation_speed = floatval(0);
		$this->movement_speed = array('x' => floatval(0), 'y' => floatval(0), 'z' => floatval(0));
		$this->movement_direction = array ('x' => floatval(0), 'y' => floatval(0), 'z' => floatval(0));
		$this->last_location = array('x' => floatval(0), 'y' => floatval(0), 'z' => floatval(0));
		$this->current_location = array('x' => floatval(0), 'y' => floatval(0), 'z' => floatval(0));
		$this->systems = array();
		$this->objects = array();
		$this->empty_space = floatval(0);
		$this->used_space = floatval(0);
		$this->fill_rate = floatval(0);
		$this->age = floatval(0);
		$this->ticks = floatval(0);
		$this->tickEvents = array();
		$this->randomEventChance = 0;
		$this->rotationStart = null;
		$this->rotationTime = null;
		$this->rotationTimer = new Timer();
		$this->eventTime = null;
		$this->eventTimer = new Timer();
		$this->createTime = null;
		$this->createTimer = new Timer();
	}

	public function setType (string $type) : bool
	{
		if (is_string($type)) {
			$this->type = strval($type);
			return (true);
		}
		return (false);
	}

	public function setMaxX (float $x) : void
	{
		if (is_float($x)) {
			$this->max_x = $x;
		}
		return;
	}

	public function setMaxY (float $y) : void
	{
		if (is_float($y)) {
			$this->max_y = $y;
		}
		return;
	}

	public function setMaxZ (float $z) : void
	{
		if (is_float($z)) {
			$this->max_z = $z;
		}
		return;
	}

	public function setExpansionRate (float $x, float $y, float $z) : void
	{
		if (is_float($x) && is_float($y) && is_float($z)) {
			$this->expansion_rate['x'] = $x;
			$this->expansion_rate['y'] = $y;
			$this->expansion_rate['z'] = $z;
		}
		return;
	}

	public function setRotationSpeed (float $ticks) : void
	{
		if (is_float($ticks)) {
			$this->rotation_speed = $ticks;
		}
		return;
	}

	public function setMovementSpeed (float $x, float $y, float $z) : void
	{
		$this->movement_speed['x'] = $x;
		$this->movement_speed['y'] = $y;
		$this->movement_speed['z'] = $z;
	}

	// check Universe bounds ... these coordinates are in scope of "Universe"
	public function setLocation (float $x, float $y, float $z) : bool
	{
		$invalidX = false;
		$invalidY = false;
		$invalidZ = false;
		$max_x = Universe::getMaxX();
		$max_y = Universe::getMaxY();
		$max_z = Universe::getMaxZ();
		// does any edge of the galaxy extend beyond the Universe boundaries?
		if ($x > $max_x) $invalidX = true;
		if ($y > $max_y) $invalidY = true;
		if ($z > $max_z) $invalidZ = true;
		// does the move cause the galaxy to drift past the bounds of the Universe?
		if ((($this->current_x + $x) > $max_x) || (($this->current_x + $x) <= 0)) $invalidX = true;
		if ((($this->current_y + $y) > $max_y) || (($this->current_y + $y) <= 0)) $invalidY = true;
		if ((($this->current_z + $z) > $max_z) || (($this->current_z + $y) <= 0)) $invalidZ = true;
		if (($invalidX === true) || ($invalidY === true) || ($invalidZ === true))
		{
			if ($invalidX === true) Utility::write("Setting the location would violate the x-plane limit", LOG_INFO, L_CONSOLE);
			if ($invalidY === true) Utility::write("Setting the location would violate the y-plane limit", LOG_INFO, L_CONSOLE);
			if ($invalidZ === true) Utility::write("Setting the location would violate the z-plane limit", LOG_INFO, L_CONSOLE);
		  return false;
		}
		else
		{
			$this->current_x = $x;
			$this->current_y = $y;
			$this->current_z = $z;
			Utility::write("Successfully set the center of the Galaxy to ($x, $y, $z)", LOG_NOTICE, L_CONSOLE);
			return true;
		}
	}

	public function getType () : string
	{
		return $this->type;
	}
}
?>
