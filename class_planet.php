<?php // 7.3.0-dev

class Planet extends SystemObject
{
        private $atmosphere;
        private $habitable;
        private $orbit;
        private $environment;
        private $countries;
        private $habitabilityScore;
        private $habitabilityFactors;
        private $habitabilityClass;

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
                        'biosignatures' => 0.0,
                        'gravity' => 0.0,
                        'pressure' => 0.0,
                        'radiation' => 0.0,
                        'resources' => 0.0,
                        'geology' => 0.0,
                        'stellar_flux' => 1.0,
                        'climate_variance' => 0.0
                );
                $this->countries = array();
                $this->habitabilityScore = 0.0;
                $this->habitabilityFactors = array();
                $this->habitabilityClass = 'barren';
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
                        if ($key === 'temperature')
                        {
                                $this->environment[$key] = floatval($incoming);
                                continue;
                        }
                        if ($key === 'stellar_flux')
                        {
                                $this->environment[$key] = max(0.0, floatval($incoming));
                                continue;
                        }
                        if ($key === 'gravity' || $key === 'pressure')
                        {
                                $this->environment[$key] = max(0.0, floatval($incoming));
                                continue;
                        }
                        if ($key === 'climate_variance')
                        {
                                $this->environment[$key] = self::normalizeFraction($incoming);
                                continue;
                        }
                        $this->environment[$key] = self::normalizeFraction($incoming);
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

        public function getHabitabilityFactors () : array
        {
                return $this->habitabilityFactors;
        }

        public function getHabitabilityClassification () : string
        {
                return $this->habitabilityClass;
        }

        public function isReadyForCivilization () : bool
        {
                return ($this->habitable && ($this->habitabilityScore >= 0.6));
        }

        public function getEnvironmentSnapshot () : array
        {
                return $this->environment;
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
                        'habitability' => $this->habitabilityScore,
                        'classification' => $this->habitabilityClass,
                        'factors' => $this->habitabilityFactors
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

        public function onImpact (SystemObject $impactor, float $impactEnergy, float $relativeSpeed) : void
        {
                if ($impactor === $this) return;
                $planetMass = max(1.0, $this->getMass());
                $specificEnergy = $impactEnergy / $planetMass;
                $intensity = min(1.0, log10(1.0 + max(0.0, $specificEnergy)) / 6.0);
                if ($intensity <= 0)
                {
                        return;
                }
                $temperatureShock = min(150.0, $relativeSpeed * 0.01 * $intensity);
                $this->environment['temperature'] += $temperatureShock;
                foreach (array('water', 'atmosphere', 'magnetosphere', 'biosignatures', 'resources', 'geology', 'pressure') as $key)
                {
                        $this->environment[$key] = max(0.0, $this->environment[$key] * (1.0 - $intensity * 0.4));
                }
                $this->environment['radiation'] = max(0.0, $this->environment['radiation'] * (1.0 - $intensity * 0.25));
                $this->environment['gravity'] = max(0.0, min(1.0, $this->environment['gravity'] * (1.0 - $intensity * 0.1)));
                $this->updateHabitabilityScore();
                foreach ($this->countries as $country)
                {
                        if ($country instanceof Country)
                        {
                                $country->sufferDisaster($intensity, 'impact by ' . $impactor->getName());
                        }
                }
                Utility::write(
                        $this->getName() . ' endured an impact from ' . $impactor->getName() . ' (intensity ' . number_format($intensity, 2) . ')',
                        LOG_INFO,
                        L_CONSOLE
                );
        }

        private static function normalizeFraction ($value) : float
        {
                return max(0.0, min(1.0, floatval($value)));
        }

        private static function gaussianScore (float $value, float $ideal, float $spread) : float
        {
                if ($spread <= 0)
                {
                        return ($value === $ideal) ? 1.0 : 0.0;
                }
                $delta = $value - $ideal;
                $exponent = -($delta * $delta) / (2.0 * $spread * $spread);
                return max(0.0, min(1.0, exp($exponent)));
        }

        private function updateHabitabilityScore () : void
        {
                $analysis = self::analyzeHabitability($this->environment);
                $this->habitabilityScore = $analysis['score'];
                $this->habitable = $analysis['habitable'];
                $this->habitabilityFactors = $analysis['factors'];
                $this->habitabilityClass = $analysis['classification'];
        }

        public static function analyzeHabitability (array $environment) : array
        {
                $defaults = array(
                        'temperature' => 0.0,
                        'water' => 0.0,
                        'atmosphere' => 0.0,
                        'magnetosphere' => 0.0,
                        'biosignatures' => 0.0,
                        'gravity' => 0.0,
                        'pressure' => 0.0,
                        'radiation' => 0.0,
                        'resources' => 0.0,
                        'geology' => 0.0,
                        'stellar_flux' => 1.0,
                        'climate_variance' => 0.0
                );
                $env = array_merge($defaults, $environment);

                $temperatureScore = self::gaussianScore($env['temperature'], 15.0, 45.0);
                $fluxScore = self::gaussianScore($env['stellar_flux'], 1.0, 0.6);
                $gravityScore = self::gaussianScore($env['gravity'], 1.0, 0.35);
                $pressureScore = self::gaussianScore($env['pressure'], 1.0, 0.5);
                $radiationScore = $env['radiation'];
                $waterScore = $env['water'];
                $atmosphereScore = $env['atmosphere'];
                $magnetosphereScore = $env['magnetosphere'];
                $biosignaturesScore = $env['biosignatures'];
                $resourceScore = $env['resources'];
                $geologyScore = $env['geology'];
                $climateScore = 1.0 - self::normalizeFraction($env['climate_variance']);

                $weights = array(
                        'temperature' => 0.20,
                        'water' => 0.12,
                        'atmosphere' => 0.12,
                        'magnetosphere' => 0.08,
                        'biosignatures' => 0.10,
                        'gravity' => 0.08,
                        'pressure' => 0.06,
                        'radiation' => 0.08,
                        'resources' => 0.06,
                        'geology' => 0.05,
                        'stellar_flux' => 0.05,
                        'climate' => 0.05
                );

                $factors = array(
                        'temperature' => $temperatureScore,
                        'water' => $waterScore,
                        'atmosphere' => $atmosphereScore,
                        'magnetosphere' => $magnetosphereScore,
                        'biosignatures' => $biosignaturesScore,
                        'gravity' => $gravityScore,
                        'pressure' => $pressureScore,
                        'radiation' => $radiationScore,
                        'resources' => $resourceScore,
                        'geology' => $geologyScore,
                        'stellar_flux' => $fluxScore,
                        'climate' => $climateScore
                );

                $score = 0.0;
                foreach ($factors as $name => $value)
                {
                        $weight = $weights[$name] ?? 0.0;
                        $score += $weight * max(0.0, min(1.0, $value));
                }

                $score = max(0.0, min(1.0, $score));
                $habitable = ($score >= 0.62) && ($temperatureScore > 0.2) && ($waterScore > 0.2) && ($atmosphereScore > 0.2);

                $classification = 'barren';
                if ($score >= 0.85)
                {
                        $classification = 'lush';
                }
                elseif ($score >= 0.75)
                {
                        $classification = 'temperate';
                }
                elseif ($score >= 0.62)
                {
                        $classification = 'marginal';
                }
                elseif ($score >= 0.45)
                {
                        $classification = 'hostile';
                }

                return array(
                        'score' => $score,
                        'habitable' => $habitable,
                        'classification' => $classification,
                        'factors' => $factors
                );
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
