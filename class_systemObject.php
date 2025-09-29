<?php // 7.3.0-dev

class SystemObject
{
        public const GRAVITATIONAL_CONSTANT = 6.67430E-11;

        protected $name;
        protected $type;
        protected $mass;
        protected $radius;
        protected $position;
        protected $velocity;
        protected $metadata;
        protected $parentSystem;
        protected $age;

        public function __construct (string $name, float $mass = 0.0, float $radius = 0.0, ?array $position = null, ?array $velocity = null)
        {
                $this->name = Utility::cleanse_string($name);
                $this->type = get_class($this);
                $this->mass = floatval($mass);
                $this->radius = floatval($radius);
                $this->position = $this->sanitizeVector($position);
                $this->velocity = $this->sanitizeVector($velocity);
                $this->metadata = array();
                $this->parentSystem = null;
                $this->age = floatval(0);
        }

        protected function sanitizeVector (?array $vector) : array
        {
                $defaults = array('x' => floatval(0), 'y' => floatval(0), 'z' => floatval(0));
                if ($vector === null) return $defaults;
                foreach ($defaults as $axis => $value)
                {
                        if (isset($vector[$axis]))
                        {
                                $defaults[$axis] = floatval($vector[$axis]);
                        }
                }
                return $defaults;
        }

        public function getName () : string
        {
                return $this->name;
        }

        public function getType () : string
        {
                return $this->type;
        }

        public function setType (string $type) : void
        {
                if (!empty($type))
                {
                        $this->type = Utility::cleanse_string($type);
                }
        }

        public function getMass () : float
        {
                return $this->mass;
        }

        public function setMass (float $mass) : void
        {
                $this->mass = floatval(max($mass, 0));
        }

        public function getRadius () : float
        {
                return $this->radius;
        }

        public function setRadius (float $radius) : void
        {
                $this->radius = floatval(max($radius, 0));
        }

        public function getPosition () : array
        {
                return $this->position;
        }

        public function setPosition (array $position) : void
        {
                $this->position = $this->sanitizeVector($position);
        }

        public function translate (array $offset) : void
        {
                $offset = $this->sanitizeVector($offset);
                foreach ($this->position as $axis => $value)
                {
                        $this->position[$axis] += $offset[$axis];
                }
        }

        public function getVelocity () : array
        {
                return $this->velocity;
        }

        public function setVelocity (array $velocity) : void
        {
                $this->velocity = $this->sanitizeVector($velocity);
        }

        public function getSpeed () : float
        {
                return sqrt(
                        pow($this->velocity['x'], 2) +
                        pow($this->velocity['y'], 2) +
                        pow($this->velocity['z'], 2)
                );
        }

        public function applyAcceleration (array $acceleration, float $deltaTime) : void
        {
                if ($deltaTime <= 0) return;
                $acceleration = $this->sanitizeVector($acceleration);
                foreach ($this->velocity as $axis => $value)
                {
                        $this->velocity[$axis] += $acceleration[$axis] * $deltaTime;
                }
        }

        public function tick (float $deltaTime = 1.0) : void
        {
                if ($deltaTime <= 0) return;
                foreach ($this->position as $axis => $value)
                {
                        $this->position[$axis] += $this->velocity[$axis] * $deltaTime;
                }
                $this->age += $deltaTime;
        }

        public function getAge () : float
        {
                return $this->age;
        }

        public function resetAge () : void
        {
                $this->age = floatval(0);
        }

        public function attachToSystem (?System $system) : void
        {
                $this->parentSystem = $system;
                if ($system !== null)
                {
                        Utility::write(
                                $this->name . " now belongs to system " . $system->getName(),
                                LOG_INFO,
                                L_CONSOLE
                        );
                }
        }

        public function getParentSystem () : ?System
        {
                return $this->parentSystem;
        }

        public function isBoundToSystem () : bool
        {
                return ($this->parentSystem instanceof System);
        }

        public function distanceTo (SystemObject $object) : float
        {
                $other = $object->getPosition();
                $dx = $this->position['x'] - $other['x'];
                $dy = $this->position['y'] - $other['y'];
                $dz = $this->position['z'] - $other['z'];
                return sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
        }

        public function getGravitationalParameter () : float
        {
                return self::GRAVITATIONAL_CONSTANT * $this->mass;
        }

        public function setMetadata (string $key, $value) : void
        {
                if ($key === '') return;
                $this->metadata[$key] = $value;
        }

        public function getMetadata (?string $key = null)
        {
                if ($key === null) return $this->metadata;
                if (!array_key_exists($key, $this->metadata)) return null;
                return $this->metadata[$key];
        }

        public function __toString () : string
        {
                return $this->type . ' ' . $this->name;
        }
}
?>
