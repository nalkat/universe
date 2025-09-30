<?php // 7.3.0-dev

class System
{
        public const PROPAGATION_ANALYTIC = 'analytic';
        public const PROPAGATION_NUMERICAL = 'numerical';

        private $name;
        private $objects;
        private $primaryStar;
        private $age;
        private $timeStep;
        private $propagationMode;
        private $gravitySofteningLength;

        public function __construct (string $name, ?Star $primaryStar = null)
        {
                $this->name = Utility::cleanse_string($name);
                $this->objects = array();
                $this->primaryStar = null;
                $this->age = floatval(0);
                $this->timeStep = floatval(60);
                $this->propagationMode = self::PROPAGATION_ANALYTIC;
                $this->gravitySofteningLength = floatval(0);
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

        public function setPropagationMode (string $mode) : void
        {
                $mode = strtolower(trim($mode));
                if (!in_array($mode, array(self::PROPAGATION_ANALYTIC, self::PROPAGATION_NUMERICAL), true))
                {
                        Utility::write("Unknown propagation mode: $mode", LOG_WARNING, L_CONSOLE);
                        return;
                }
                $this->propagationMode = $mode;
        }

        public function getPropagationMode () : string
        {
                return $this->propagationMode;
        }

        public function setGravitySofteningLength (float $length) : void
        {
                $this->gravitySofteningLength = max(floatval($length), 0.0);
        }

        public function getGravitySofteningLength () : float
        {
                return $this->gravitySofteningLength;
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

        public function addPlanet (
                Planet $planet,
                ?SystemObject $focus = null,
                ?float $semiMajorAxis = null,
                ?float $period = null,
                float $eccentricity = 0.0,
                float $phase = 0.0,
                float $inclination = 0.0,
                float $ascendingNode = 0.0,
                float $argumentOfPeriapsis = 0.0
        ) : void
        {
                $this->registerObject($planet);
                if (($focus instanceof SystemObject) && ($semiMajorAxis !== null) && ($period !== null))
                {
                        if ($planet->setOrbit($focus, $semiMajorAxis, $period, $eccentricity, $phase, $inclination, $ascendingNode, $argumentOfPeriapsis))
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
                if ($this->propagationMode === self::PROPAGATION_NUMERICAL)
                {
                        $this->applyGravitationalAccelerations($step);
                }
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

        public function calculateCenterOfMass () : array
        {
                $totalMass = 0.0;
                $center = array('x' => 0.0, 'y' => 0.0, 'z' => 0.0);
                foreach ($this->objects as $object)
                {
                        $mass = $object->getMass();
                        if ($mass <= 0) continue;
                        $position = $object->getPosition();
                        $center['x'] += $position['x'] * $mass;
                        $center['y'] += $position['y'] * $mass;
                        $center['z'] += $position['z'] * $mass;
                        $totalMass += $mass;
                }
                if ($totalMass <= 0)
                {
                        return $center;
                }
                $center['x'] /= $totalMass;
                $center['y'] /= $totalMass;
                $center['z'] /= $totalMass;
                return $center;
        }

        public function calculateTotalEnergy () : array
        {
                $kinetic = 0.0;
                $potential = 0.0;
                $objects = array_values($this->objects);
                $count = count($objects);
                for ($i = 0; $i < $count; $i++)
                {
                        $object = $objects[$i];
                        $kinetic += $object->getKineticEnergy();
                }
                for ($i = 0; $i < $count; $i++)
                {
                        $a = $objects[$i];
                        $massA = $a->getMass();
                        if ($massA <= 0) continue;
                        $posA = $a->getPosition();
                        for ($j = $i + 1; $j < $count; $j++)
                        {
                                $b = $objects[$j];
                                $massB = $b->getMass();
                                if ($massB <= 0) continue;
                                $posB = $b->getPosition();
                                $dx = $posB['x'] - $posA['x'];
                                $dy = $posB['y'] - $posA['y'];
                                $dz = $posB['z'] - $posA['z'];
                                $distanceSquared = ($dx * $dx) + ($dy * $dy) + ($dz * $dz);
                                if ($distanceSquared <= 0) continue;
                                $distance = sqrt($distanceSquared);
                                if ($distance <= 0) continue;
                                $potential -= (SystemObject::GRAVITATIONAL_CONSTANT * $massA * $massB) / $distance;
                        }
                }
                return array(
                        'kinetic' => $kinetic,
                        'potential' => $potential,
                        'total' => $kinetic + $potential
                );
        }

        private function applyGravitationalAccelerations (float $deltaTime) : void
        {
                if ($deltaTime <= 0) return;
                $names = array_keys($this->objects);
                $count = count($names);
                if ($count < 2) return;
                $accelerations = array();
                foreach ($names as $name)
                {
                        $accelerations[$name] = array('x' => 0.0, 'y' => 0.0, 'z' => 0.0);
                }
                for ($i = 0; $i < $count; $i++)
                {
                        $nameA = $names[$i];
                        $objectA = $this->objects[$nameA];
                        $massA = $objectA->getMass();
                        for ($j = $i + 1; $j < $count; $j++)
                        {
                                $nameB = $names[$j];
                                $objectB = $this->objects[$nameB];
                                $massB = $objectB->getMass();
                                if (($massA <= 0) && ($massB <= 0))
                                {
                                        continue;
                                }
                                $posA = $objectA->getPosition();
                                $posB = $objectB->getPosition();
                                $dx = $posB['x'] - $posA['x'];
                                $dy = $posB['y'] - $posA['y'];
                                $dz = $posB['z'] - $posA['z'];
                                $distanceSquared = ($dx * $dx) + ($dy * $dy) + ($dz * $dz);
                                $softening = $this->gravitySofteningLength;
                                if ($softening > 0)
                                {
                                        $distanceSquared += $softening * $softening;
                                }
                                if ($distanceSquared <= 0)
                                {
                                        continue;
                                }
                                $distance = sqrt($distanceSquared);
                                if ($distance <= 0)
                                {
                                        continue;
                                }
                                $ux = $dx / $distance;
                                $uy = $dy / $distance;
                                $uz = $dz / $distance;
                                if ($massA > 0)
                                {
                                        $accMagA = SystemObject::GRAVITATIONAL_CONSTANT * $massB / $distanceSquared;
                                        $accelerations[$nameA]['x'] += $ux * $accMagA;
                                        $accelerations[$nameA]['y'] += $uy * $accMagA;
                                        $accelerations[$nameA]['z'] += $uz * $accMagA;
                                }
                                if ($massB > 0)
                                {
                                        $accMagB = SystemObject::GRAVITATIONAL_CONSTANT * $massA / $distanceSquared;
                                        $accelerations[$nameB]['x'] -= $ux * $accMagB;
                                        $accelerations[$nameB]['y'] -= $uy * $accMagB;
                                        $accelerations[$nameB]['z'] -= $uz * $accMagB;
                                }
                        }
                }
                foreach ($accelerations as $name => $vector)
                {
                        $object = $this->objects[$name];
                        if ($object->getMass() <= 0)
                        {
                                continue;
                        }
                        $object->applyAcceleration($vector, $deltaTime);
                }
        }
}
?>
