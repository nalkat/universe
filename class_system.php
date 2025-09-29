<?php // 7.3.0-dev

class System
{
        private $name;
        private $objects;
        private $primaryStar;
        private $age;
        private $timeStep;

        public function __construct (string $name, ?Star $primaryStar = null)
        {
                $this->name = Utility::cleanse_string($name);
                $this->objects = array();
                $this->primaryStar = null;
                $this->age = floatval(0);
                $this->timeStep = floatval(60);
                if ($primaryStar instanceof Star)
                {
                        $this->setPrimaryStar($primaryStar);
                }
        }

        public function getName () : string
        {
                return $this->name;
        }

        public function getAge () : float
        {
                return $this->age;
        }

        public function setTimeStep (float $seconds) : void
        {
                if ($seconds <= 0)
                {
                        Utility::write("Time step must be greater than zero", LOG_WARNING, L_CONSOLE);
                        return;
                }
                $this->timeStep = floatval($seconds);
        }

        public function getTimeStep () : float
        {
                return $this->timeStep;
        }

        public function setPrimaryStar (Star $star) : void
        {
                $this->primaryStar =& $star;
                $this->registerObject($star);
        }

        public function getPrimaryStar () : ?Star
        {
                return $this->primaryStar;
        }

        public function addObject (SystemObject $object) : void
        {
                $this->registerObject($object);
        }

        public function addPlanet (Planet $planet, ?SystemObject $focus = null, ?float $semiMajorAxis = null, ?float $period = null, float $eccentricity = 0.0, float $phase = 0.0) : void
        {
                $this->registerObject($planet);
                if (($focus instanceof SystemObject) && ($semiMajorAxis !== null) && ($period !== null))
                {
                        if ($planet->setOrbit($focus, $semiMajorAxis, $period, $eccentricity, $phase))
                        {
                                Utility::write(
                                        $planet->getName() . " orbit registered around " . $focus->getName(),
                                        LOG_INFO,
                                        L_CONSOLE
                                );
                        }
                }
        }

        private function registerObject (SystemObject $object) : void
        {
                $name = $object->getName();
                if (isset($this->objects[$name]))
                {
                        Utility::write(
                                "Replacing existing object $name in system " . $this->name,
                                LOG_WARNING,
                                L_CONSOLE
                        );
                }
                $this->objects[$name] =& $object;
                $object->attachToSystem($this);
        }

        public function removeObject (string $name) : ?SystemObject
        {
                if (!isset($this->objects[$name])) return null;
                $object = $this->objects[$name];
                unset($this->objects[$name]);
                if ($object->getParentSystem() === $this)
                {
                        $object->attachToSystem(null);
                }
                if (($this->primaryStar !== null) && ($this->primaryStar->getName() === $name))
                {
                        $this->primaryStar = null;
                }
                return $object;
        }

        public function getObjects () : array
        {
                return $this->objects;
        }

        public function getObject (string $name) : ?SystemObject
        {
                if (!isset($this->objects[$name])) return null;
                return $this->objects[$name];
        }

        public function getPlanets () : array
        {
                $planets = array();
                foreach ($this->objects as $object)
                {
                        if ($object instanceof Planet)
                        {
                                $planets[$object->getName()] = $object;
                        }
                }
                return $planets;
        }

        public function hasObject (string $name) : bool
        {
                return isset($this->objects[$name]);
        }

        public function countObjects () : int
        {
                return count($this->objects);
        }

        public function tick (?float $deltaTime = null) : void
        {
                $step = ($deltaTime === null) ? $this->timeStep : floatval($deltaTime);
                if ($step <= 0) return;
                foreach ($this->objects as $object)
                {
                        $object->tick($step);
                }
                $this->age += $step;
        }

        public function snapshotState () : array
        {
                $state = array();
                foreach ($this->objects as $object)
                {
                        $state[$object->getName()] = array(
                                'type' => $object->getType(),
                                'mass' => $object->getMass(),
                                'radius' => $object->getRadius(),
                                'position' => $object->getPosition(),
                                'velocity' => $object->getVelocity(),
                                'age' => $object->getAge()
                        );
                }
                return $state;
        }
}
?>
