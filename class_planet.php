<?php // 7.3.0-dev

class Planet extends SystemObject
{
        private $atmosphere;
        private $habitable;
        private $orbit;
        private $environment;
        private $countries;
        private $habitabilityScore;

        public function __construct (string $name, float $mass = 0.0, float $radius = 0.0, ?array $position = null, ?array $velocity = null)
        {
                parent::__construct($name, $mass, $radius, $position, $velocity);
                $this->setType('Planet');
                $this->atmosphere = array();
                $this->habitable = false;
                $this->orbit = null;
                $this->environment = array(
                        'temperature' => 0.0,
                        'water' => 0.0,
                        'atmosphere' => 0.0,
                        'magnetosphere' => 0.0,
                        'biosignatures' => 0.0
                );
                $this->countries = array();
                $this->habitabilityScore = 0.0;
        }

        public function setAtmosphere (array $composition) : void
        {
                $this->atmosphere = $composition;
        }

        public function getAtmosphere () : array
        {
                return $this->atmosphere;
        }

        public function setHabitable (bool $habitable) : void
        {
                $this->habitable = $habitable;
        }

        public function isHabitable () : bool
        {
                return $this->habitable;
        }

        public function setEnvironment (array $environment) : void
        {
                foreach ($this->environment as $key => $value)
                {
                        if (!array_key_exists($key, $environment)) continue;
                        $incoming = $environment[$key];
                        if (in_array($key, array('temperature'), true))
                        {
                                $this->environment[$key] = floatval($incoming);
                                continue;
                        }
                        $this->environment[$key] = $this->normalizeFraction($incoming);
                }
                $this->updateHabitabilityScore();
        }

        public function getEnvironment () : array
        {
                return $this->environment;
        }

        public function getHabitabilityScore () : float
        {
                return $this->habitabilityScore;
        }

        public function isReadyForCivilization () : bool
        {
                return ($this->habitable && ($this->habitabilityScore >= 0.6));
        }

        public function registerCountry (Country $country) : void
        {
                $name = $country->getName();
                $this->countries[$name] = $country;
        }

        public function createCountry (string $name, array $profile = array()) : ?Country
        {
                if (!$this->isReadyForCivilization())
                {
                        Utility::write(
                                $this->getName() . " lacks the environmental stability for countries",
                                LOG_INFO,
                                L_CONSOLE
                        );
                        return null;
                }
                $cleanName = Utility::cleanse_string($name);
                if (isset($this->countries[$cleanName]))
                {
                        Utility::write("Country $cleanName already exists on " . $this->getName(), LOG_WARNING, L_CONSOLE);
                        return $this->countries[$cleanName];
                }
                $country = new Country($cleanName, $this, $profile);
                return $country;
        }

        public function getCountries () : array
        {
                return $this->countries;
        }

        public function getCountry (string $name) : ?Country
        {
                $cleanName = Utility::cleanse_string($name);
                if (!isset($this->countries[$cleanName])) return null;
                return $this->countries[$cleanName];
        }

        public function getPopulationSummary () : array
        {
                $population = 0;
                foreach ($this->countries as $country)
                {
                        $population += $country->getPopulation();
                }
                return array(
                        'population' => $population,
                        'countries' => count($this->countries),
                        'habitability' => $this->habitabilityScore
                );
        }

        public function getOrbit () : ?array
        {
                return $this->orbit;
        }

        public function setOrbit (
                SystemObject $focus,
                float $semiMajorAxis,
                float $period,
                float $eccentricity = 0.0,
                float $phase = 0.0,
                float $inclination = 0.0,
                float $ascendingNode = 0.0,
                float $argumentOfPeriapsis = 0.0
        ) : bool
        {
                if ($semiMajorAxis <= 0)
                {
                        Utility::write("Semi-major axis must be positive", LOG_WARNING, L_CONSOLE);
                        return false;
                }
                if ($period <= 0)
                {
                        Utility::write("Orbital period must be positive", LOG_WARNING, L_CONSOLE);
                        return false;
                }
                if ($eccentricity < 0) $eccentricity = 0.0;
                if ($eccentricity >= 1) $eccentricity = 0.999999;
                $this->orbit = array(
                        'focus' => $focus,
                        'semi_major_axis' => floatval($semiMajorAxis),
                        'period' => floatval($period),
                        'eccentricity' => floatval($eccentricity),
                        'angle' => floatval($phase),
                        'inclination' => floatval($inclination),
                        'ascending_node' => floatval($ascendingNode),
                        'argument_of_periapsis' => floatval($argumentOfPeriapsis)
                );
                $this->updateOrbit(0.0);
                return true;
        }

        public function tick (float $deltaTime = 1.0) : void
        {
                $useAnalyticOrbit = ($this->orbit !== null) && ($deltaTime > 0);
                if ($useAnalyticOrbit)
                {
                        $system = $this->getParentSystem();
                        if ($system instanceof System)
                        {
                                $useAnalyticOrbit = ($system->getPropagationMode() === System::PROPAGATION_ANALYTIC);
                        }
                }
                if ($useAnalyticOrbit)
                {
                        $this->updateOrbit($deltaTime);
                        $this->age += $deltaTime;
                }
                else
                {
                        parent::tick($deltaTime);
                }
                foreach ($this->countries as $country)
                {
                        $country->tick($deltaTime);
                }
        }

        private function normalizeFraction ($value) : float
        {
                return max(0.0, min(1.0, floatval($value)));
        }

        private function updateHabitabilityScore () : void
        {
                $temperatureScore = 0.0;
                $temperature = $this->environment['temperature'];
                if ($temperature >= -50 && $temperature <= 70)
                {
                        $temperatureScore = 1.0 - (abs($temperature - 15) / 85);
                        $temperatureScore = max(0.0, min(1.0, $temperatureScore));
                }
                $scores = array(
                        $temperatureScore,
                        $this->environment['water'],
                        $this->environment['atmosphere'],
                        $this->environment['magnetosphere'],
                        $this->environment['biosignatures']
                );
                $this->habitabilityScore = array_sum($scores) / count($scores);
                if ($this->habitabilityScore >= 0.6)
                {
                        $this->habitable = true;
                }
        }

        private function updateOrbit (float $deltaTime) : void
        {
                if ($this->orbit === null) return;
                $period = $this->orbit['period'];
                if ($period <= 0) return;
                $twoPi = 2 * pi();
                if ($deltaTime > 0)
                {
                        $this->orbit['angle'] += $twoPi * ($deltaTime / $period);
                }
                $this->orbit['angle'] = fmod($this->orbit['angle'], $twoPi);
                $focus = $this->orbit['focus'];
                $focusPosition = $focus->getPosition();
                $focusVelocity = $focus->getVelocity();
                $ecc = $this->orbit['eccentricity'];
                $semi = $this->orbit['semi_major_axis'];
                $angle = $this->orbit['angle'];
                $inclination = $this->orbit['inclination'];
                $ascendingNode = $this->orbit['ascending_node'];
                $argumentOfPeriapsis = $this->orbit['argument_of_periapsis'];
                $radius = $semi;
                if ($ecc > 0)
                {
                        $radius = ($semi * (1 - ($ecc * $ecc))) / (1 + ($ecc * cos($angle)));
                }
                $orbitalPosition = array(
                        'x' => $radius * cos($angle),
                        'y' => $radius * sin($angle),
                        'z' => 0.0
                );
                $angularSpeed = $twoPi / $period;
                $orbitalVelocity = array(
                        'x' => -$radius * $angularSpeed * sin($angle),
                        'y' => $radius * $angularSpeed * cos($angle),
                        'z' => 0.0
                );
                $rotatedPosition = $this->rotateFromOrbitalPlane($orbitalPosition, $argumentOfPeriapsis, $inclination, $ascendingNode);
                $rotatedVelocity = $this->rotateFromOrbitalPlane($orbitalVelocity, $argumentOfPeriapsis, $inclination, $ascendingNode);
                $this->position['x'] = $focusPosition['x'] + $rotatedPosition['x'];
                $this->position['y'] = $focusPosition['y'] + $rotatedPosition['y'];
                $this->position['z'] = $focusPosition['z'] + $rotatedPosition['z'];
                $this->velocity['x'] = $focusVelocity['x'] + $rotatedVelocity['x'];
                $this->velocity['y'] = $focusVelocity['y'] + $rotatedVelocity['y'];
                $this->velocity['z'] = $focusVelocity['z'] + $rotatedVelocity['z'];
        }

        private function rotateFromOrbitalPlane (array $vector, float $argumentOfPeriapsis, float $inclination, float $ascendingNode) : array
        {
                $x = $vector['x'];
                $y = $vector['y'];
                $z = $vector['z'];

                $cosArg = cos($argumentOfPeriapsis);
                $sinArg = sin($argumentOfPeriapsis);
                $x1 = ($cosArg * $x) - ($sinArg * $y);
                $y1 = ($sinArg * $x) + ($cosArg * $y);
                $z1 = $z;

                $cosInc = cos($inclination);
                $sinInc = sin($inclination);
                $x2 = $x1;
                $y2 = ($cosInc * $y1) - ($sinInc * $z1);
                $z2 = ($sinInc * $y1) + ($cosInc * $z1);

                $cosNode = cos($ascendingNode);
                $sinNode = sin($ascendingNode);
                $x3 = ($cosNode * $x2) - ($sinNode * $y2);
                $y3 = ($sinNode * $x2) + ($cosNode * $y2);
                $z3 = $z2;

                return array('x' => $x3, 'y' => $y3, 'z' => $z3);
        }
}
?>
