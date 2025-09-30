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
        private $weatherSystems;
        private $weatherHistory;
        private $currentWeatherIndex;
        private $weatherTimer;
        private $climateProfile;
        private $timekeeping;
        private $lastLocalTickDuration;
        private $lastUniversalTickDuration;

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
                $this->weatherSystems = array();
                $this->weatherHistory = array();
                $this->currentWeatherIndex = null;
                $this->weatherTimer = 0.0;
                $this->climateProfile = null;
                $this->timekeeping = $this->generateTimekeepingProfile();
                $this->lastLocalTickDuration = 0.0;
                $this->lastUniversalTickDuration = 0.0;
                $this->setDescription('A newly catalogued world awaiting survey data.');
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
                $this->refreshEnvironmentalNarrative(true);
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

        public function setTimekeeping (array $profile) : void
        {
                $this->applyTimekeepingProfile($profile);
                $this->refreshTemporalDependents();
        }

        public function getTimekeepingProfile () : array
        {
                $dayLocal = $this->getDayLengthSeconds(true);
                $dayUniversal = $this->getDayLengthSeconds(false);
                $hourLocal = $this->getHourLengthSeconds(true);
                $hourUniversal = $this->getHourLengthSeconds(false);
                $yearLocal = $this->getYearLengthSeconds(true);
                $yearUniversal = $this->getYearLengthSeconds(false);
                $hoursPerDay = max(1.0, floatval($this->timekeeping['hours_per_day'] ?? 24.0));
                $dayCount = ($dayLocal > 0.0) ? ($yearLocal / $dayLocal) : 0.0;

                return array(
                        'relative_rate' => $this->getRelativeTimeRate(),
                        'hours_per_day' => $hoursPerDay,
                        'day_length_local_seconds' => $dayLocal,
                        'day_length_seconds' => $dayUniversal,
                        'hour_length_local_seconds' => $hourLocal,
                        'hour_length_seconds' => $hourUniversal,
                        'year_length_local_seconds' => $yearLocal,
                        'year_length_seconds' => $yearUniversal,
                        'year_length_days' => $dayCount,
                        'last_tick' => $this->getLastTickDurations(),
                        'summary' => $this->describeTimekeeping()
                );
        }

        public function getRelativeTimeRate () : float
        {
                return max(0.05, floatval($this->timekeeping['relative_rate'] ?? 1.0));
        }

        public function getDayLengthSeconds (bool $local = false) : float
        {
                $dayLocal = floatval($this->timekeeping['day_length_local'] ?? 86400.0);
                if ($local) return $dayLocal;
                $rate = $this->getRelativeTimeRate();
                return ($rate > 0.0) ? ($dayLocal / $rate) : $dayLocal;
        }

        public function getHourLengthSeconds (bool $local = false) : float
        {
                $hoursPerDay = max(1.0, floatval($this->timekeeping['hours_per_day'] ?? 24.0));
                $daySeconds = $this->getDayLengthSeconds($local);
                return $daySeconds / $hoursPerDay;
        }

        public function getYearLengthSeconds (bool $local = false) : float
        {
                $yearLocal = floatval($this->timekeeping['year_length_local'] ?? 31557600.0);
                if ($local) return $yearLocal;
                $rate = $this->getRelativeTimeRate();
                return ($rate > 0.0) ? ($yearLocal / $rate) : $yearLocal;
        }

        public function convertUniversalToLocalSeconds (float $seconds) : float
        {
                if ($seconds <= 0.0) return 0.0;
                return $seconds * $this->getRelativeTimeRate();
        }

        public function convertLocalToUniversalSeconds (float $seconds) : float
        {
                if ($seconds <= 0.0) return 0.0;
                $rate = $this->getRelativeTimeRate();
                return ($rate > 0.0) ? ($seconds / $rate) : $seconds;
        }

        public function getLastTickDurations () : array
        {
                return array(
                        'universal_seconds' => $this->lastUniversalTickDuration,
                        'local_seconds' => $this->lastLocalTickDuration
                );
        }

        private function convertSecondsToLocalHours (float $seconds) : float
        {
                $hour = max(1.0, $this->getHourLengthSeconds(true));
                return max(0.0, $seconds / $hour);
        }

        public function describeTimekeeping () : string
        {
                $dayUniversalHours = $this->getDayLengthSeconds(false) / 3600.0;
                $hoursPerDay = max(1.0, floatval($this->timekeeping['hours_per_day'] ?? 24.0));
                $yearDays = ($this->getDayLengthSeconds(true) > 0.0)
                        ? ($this->getYearLengthSeconds(true) / $this->getDayLengthSeconds(true))
                        : 0.0;
                $universalYearDays = $this->getYearLengthSeconds(false) / 86400.0;
                $rate = $this->getRelativeTimeRate();

                $phrases = array();
                $phrases[] = sprintf(
                        'Local days last %s universal hours across %s local hours.',
                        number_format($dayUniversalHours, 1),
                        number_format($hoursPerDay, 1)
                );
                $phrases[] = sprintf(
                        'A local year spans %s local days (~%s universal days).',
                        number_format($yearDays, 1),
                        number_format($universalYearDays, 1)
                );
                if (abs($rate - 1.0) < 0.01)
                {
                        $phrases[] = 'Time here flows at the simulator\'s standard rate.';
                }
                elseif ($rate > 1.0)
                {
                        $phrases[] = sprintf('Time here runs %sx faster than the universal frame.', number_format($rate, 2));
                }
                else
                {
                        $phrases[] = sprintf('Time here runs %sx slower than the universal frame.', number_format(1.0 / max(0.01, $rate), 2));
                }
                return implode(' ', $phrases);
        }

        private function generateTimekeepingProfile () : array
        {
                $hoursPerDay = max(12.0, min(40.0, (random_int(120, 360) / 10.0)));
                $dayLengthLocal = $hoursPerDay * 3600.0;
                $yearDays = max(60.0, min(900.0, random_int(120, 720)));
                $yearLengthLocal = $dayLengthLocal * $yearDays;
                $relativeRate = max(0.4, min(2.5, random_int(40, 200) / 100.0));

                return $this->ensureTimekeepingConsistency(array(
                        'relative_rate' => $relativeRate,
                        'hours_per_day' => $hoursPerDay,
                        'day_length_local' => $dayLengthLocal,
                        'year_length_local' => $yearLengthLocal
                ));
        }

        private function applyTimekeepingProfile (array $profile) : void
        {
                $current = $this->timekeeping ?? $this->generateTimekeepingProfile();
                if (isset($profile['relative_rate']))
                {
                        $current['relative_rate'] = max(0.05, min(5.0, floatval($profile['relative_rate'])));
                }
                if (isset($profile['hours_per_day']))
                {
                        $current['hours_per_day'] = max(4.0, min(48.0, floatval($profile['hours_per_day'])));
                }
                if (isset($profile['day_length_local_seconds']) || isset($profile['day_length_local']))
                {
                        $value = $profile['day_length_local_seconds'] ?? $profile['day_length_local'];
                        $current['day_length_local'] = max(3600.0, floatval($value));
                }
                elseif (isset($profile['day_length_seconds']))
                {
                        $seconds = max(3600.0, floatval($profile['day_length_seconds']));
                        $rate = max(0.05, floatval($current['relative_rate'] ?? 1.0));
                        $current['day_length_local'] = $seconds * $rate;
                }
                if (isset($profile['year_length_local_seconds']) || isset($profile['year_length_local']))
                {
                        $value = $profile['year_length_local_seconds'] ?? $profile['year_length_local'];
                        $current['year_length_local'] = max(
                                max(3600.0, $current['day_length_local'] ?? 86400.0) * 16.0,
                                floatval($value)
                        );
                }
                elseif (isset($profile['year_length_seconds']))
                {
                        $seconds = max(3600.0, floatval($profile['year_length_seconds']));
                        $rate = max(0.05, floatval($current['relative_rate'] ?? 1.0));
                        $current['year_length_local'] = max(
                                max(3600.0, $current['day_length_local'] ?? 86400.0) * 16.0,
                                $seconds * $rate
                        );
                }
                $this->timekeeping = $this->ensureTimekeepingConsistency($current);
        }

        private function ensureTimekeepingConsistency (array $profile) : array
        {
                $profile['relative_rate'] = max(0.05, min(5.0, floatval($profile['relative_rate'] ?? 1.0)));
                $dayLocal = max(3600.0, floatval($profile['day_length_local'] ?? 86400.0));
                $hoursPerDay = max(4.0, min(48.0, floatval($profile['hours_per_day'] ?? 24.0)));
                $yearLocal = max($dayLocal * 16.0, floatval($profile['year_length_local'] ?? ($dayLocal * 365.0)));
                return array(
                        'relative_rate' => $profile['relative_rate'],
                        'hours_per_day' => $hoursPerDay,
                        'day_length_local' => $dayLocal,
                        'year_length_local' => $yearLocal
                );
        }

        private function refreshTemporalDependents () : void
        {
                if (!empty($this->weatherSystems))
                {
                        $this->initializeWeatherSystems(true);
                }
                foreach ($this->countries as $country)
                {
                        if ($country instanceof Country)
                        {
                                $country->synchronizeWithPlanetTimekeeping();
                        }
                }
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

        public function getWeatherSystems () : array
        {
                return $this->weatherSystems;
        }

        public function getCurrentWeather () : ?array
        {
                if ($this->currentWeatherIndex === null) return null;
                if (!isset($this->weatherSystems[$this->currentWeatherIndex])) return null;
                $current = $this->weatherSystems[$this->currentWeatherIndex];
                $duration = floatval($current['duration'] ?? 0.0);
                $elapsed = floatval($current['elapsed'] ?? 0.0);
                $progress = ($duration > 0.0) ? max(0.0, min(1.0, $elapsed / $duration)) : 0.0;
                return array(
                        'name' => strval($current['name'] ?? 'weather system'),
                        'type' => strval($current['type'] ?? 'unknown'),
                        'intensity' => floatval($current['intensity'] ?? 0.0),
                        'duration_hours' => ($duration > 0.0) ? $this->convertSecondsToLocalHours($duration) : 0.0,
                        'progress' => $progress,
                        'narrative' => strval($current['summary'] ?? '')
                );
        }

        public function getWeatherHistory (int $limit = 10) : array
        {
                if ($limit <= 0) return array();
                $history = array_reverse($this->weatherHistory);
                return array_slice($history, 0, $limit);
        }

        public function describeClimate () : array
        {
                if ($this->climateProfile === null)
                {
                        $this->climateProfile = $this->deriveClimateProfile();
                }
                return $this->climateProfile;
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
                        'factors' => $this->habitabilityFactors,
                        'timekeeping' => $this->getTimekeepingProfile(),
                        'longevity' => $this->summarizePopulationLongevity()
                );
        }

        private function summarizePopulationLongevity () : array
        {
                $expectancies = array();
                $senescence = array();
                foreach ($this->countries as $country)
                {
                        if (!($country instanceof Country)) continue;
                        foreach ($country->getPeople() as $person)
                        {
                                if (!($person instanceof Person)) continue;
                                $expectancies[] = $person->getLifeExpectancyYears();
                                $senescence[] = $person->getSenescenceStartYears();
                        }
                }
                $yearLocal = $this->getYearLengthSeconds(true);
                $yearDays = ($this->getDayLengthSeconds(true) > 0.0)
                        ? ($yearLocal / $this->getDayLengthSeconds(true))
                        : 0.0;
                if (!empty($expectancies))
                {
                        $average = array_sum($expectancies) / count($expectancies);
                        sort($expectancies);
                        $min = reset($expectancies);
                        $max = end($expectancies);
                        $senescenceAverage = (!empty($senescence)) ? array_sum($senescence) / count($senescence) : null;
                        return array(
                                'basis' => 'observed',
                                'life_expectancy_years' => $average,
                                'life_expectancy_years_range' => array(
                                        'min' => $min,
                                        'max' => $max
                                ),
                                'senescence_start_years' => $senescenceAverage,
                                'year_length_local_seconds' => $yearLocal,
                                'year_length_seconds' => $this->getYearLengthSeconds(false),
                                'year_length_days' => $yearDays
                        );
                }
                list($expected, $senescenceStart) = $this->estimateBaseLongevity();
                return array(
                        'basis' => 'projection',
                        'life_expectancy_years' => $expected,
                        'senescence_start_years' => $senescenceStart,
                        'year_length_local_seconds' => $yearLocal,
                        'year_length_seconds' => $this->getYearLengthSeconds(false),
                        'year_length_days' => $yearDays
                );
        }

        private function estimateBaseLongevity () : array
        {
                $habitability = max(0.0, min(1.0, $this->habitabilityScore));
                $profile = $this->describeClimate();
                $variance = floatval($profile['variance'] ?? 0.0);
                $rate = $this->getRelativeTimeRate();
                $expected = 60.0 + ($habitability * 35.0) - ($variance * 12.0);
                if ($rate < 1.0)
                {
                        $expected += (1.0 - $rate) * 6.0;
                }
                elseif ($rate > 1.0)
                {
                        $expected -= min(12.0, ($rate - 1.0) * 8.0);
                }
                $expected = max(30.0, min(120.0, $expected));
                $senescence = max(20.0, min($expected - 4.0, $expected * (0.6 + $habitability * 0.2)));
                return array($expected, $senescence);
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
                $deltaTime = floatval($deltaTime);
                $localDelta = $this->convertUniversalToLocalSeconds($deltaTime);
                $this->lastUniversalTickDuration = max(0.0, $deltaTime);
                $this->lastLocalTickDuration = max(0.0, $localDelta);
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
                $this->advanceWeather($localDelta);
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
                $this->refreshEnvironmentalNarrative(true);
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
                $this->refreshEnvironmentalNarrative(false);
        }

        private function refreshEnvironmentalNarrative (bool $forceWeather) : void
        {
                $this->climateProfile = $this->deriveClimateProfile();
                $this->initializeWeatherSystems($forceWeather);
                $this->refreshDescription();
        }

        private function deriveClimateProfile () : array
        {
                $env = $this->environment;
                $temperature = floatval($env['temperature'] ?? 0.0);
                $water = self::normalizeFraction($env['water'] ?? 0.0);
                $atmosphere = self::normalizeFraction($env['atmosphere'] ?? 0.0);
                $variance = self::normalizeFraction($env['climate_variance'] ?? 0.0);
                $gravity = max(0.0, floatval($env['gravity'] ?? 0.0));
                $pressure = max(0.0, floatval($env['pressure'] ?? 0.0));
                $biosignatures = self::normalizeFraction($env['biosignatures'] ?? 0.0);
                $resources = self::normalizeFraction($env['resources'] ?? 0.0);

                $climateAdjective = 'temperate';
                if ($temperature <= -120)
                {
                        $climateAdjective = 'cryogenic';
                }
                elseif ($temperature <= -40)
                {
                        $climateAdjective = 'glacial';
                }
                elseif ($temperature <= 5)
                {
                        $climateAdjective = 'chilled';
                }
                elseif ($temperature <= 28)
                {
                        $climateAdjective = 'temperate';
                }
                elseif ($temperature <= 55)
                {
                        $climateAdjective = 'warm';
                }
                elseif ($temperature <= 95)
                {
                        $climateAdjective = 'simmering';
                }
                else
                {
                        $climateAdjective = 'searing';
                }

                $humidityClass = 'balanced';
                if ($water >= 0.8)
                {
                        $humidityClass = 'oceanic';
                }
                elseif ($water >= 0.6)
                {
                        $humidityClass = 'humid';
                }
                elseif ($water >= 0.4)
                {
                        $humidityClass = 'balanced';
                }
                elseif ($water >= 0.2)
                {
                        $humidityClass = 'arid';
                }
                else
                {
                        $humidityClass = 'desert';
                }

                $biomeDescriptor = 'continental';
                switch ($humidityClass)
                {
                        case 'oceanic':
                                $biomeDescriptor = ($temperature <= 0) ? 'glacial oceanic' : 'oceanic';
                                break;
                        case 'humid':
                                $biomeDescriptor = ($temperature >= 30) ? 'tropical' : 'lush continental';
                                break;
                        case 'arid':
                                $biomeDescriptor = ($temperature >= 40) ? 'sun-baked plateau' : 'semi-arid steppe';
                                break;
                        case 'desert':
                                $biomeDescriptor = ($temperature >= 25) ? 'desert' : 'cold desert';
                                break;
                        default:
                                $biomeDescriptor = 'continental';
                                break;
                }
                if ($temperature <= -60)
                {
                        $biomeDescriptor = 'glacial expanse';
                }
                elseif ($temperature >= 120)
                {
                        $biomeDescriptor = 'volcanic badlands';
                }

                $seasonalityPhrase = 'with steady seasons';
                $seasonalityDescriptor = 'steady sky currents';
                if ($variance > 0.75)
                {
                        $seasonalityPhrase = 'with wild seasonal upheavals';
                        $seasonalityDescriptor = 'tempestuous air rivers';
                }
                elseif ($variance > 0.5)
                {
                        $seasonalityPhrase = 'with dramatic seasonal swings';
                        $seasonalityDescriptor = 'roaring jet streams';
                }
                elseif ($variance > 0.25)
                {
                        $seasonalityPhrase = 'with gentle seasonal cycles';
                        $seasonalityDescriptor = 'measured sky tides';
                }

                $lifePhrase = 'No confirmed native biospheres yet catalogued.';
                if ($biosignatures >= 0.75)
                {
                        $lifePhrase = 'Biospheres flourish across the landscape.';
                }
                elseif ($biosignatures >= 0.4)
                {
                        $lifePhrase = 'Signs of developing ecosystems cluster around sheltered regions.';
                }
                elseif ($biosignatures >= 0.2)
                {
                        $lifePhrase = 'Hardy microbial colonies trace mineral veins beneath the surface.';
                }

                $resourcePhrase = 'Resource outlook: scarce, requiring extensive imports.';
                if ($resources >= 0.75)
                {
                        $resourcePhrase = 'Resource outlook: abundant strategic metals and organics.';
                }
                elseif ($resources >= 0.45)
                {
                        $resourcePhrase = 'Resource outlook: balanced reserves for sustainable development.';
                }
                elseif ($resources >= 0.25)
                {
                        $resourcePhrase = 'Resource outlook: sparse deposits demanding careful stewardship.';
                }

                $skyPhrase = 'Skies remain open with occasional cloud towers.';
                if ($atmosphere <= 0.2 || $pressure <= 0.4)
                {
                        $skyPhrase = 'Skies are thin and reveal stark starfields.';
                }
                elseif ($atmosphere >= 0.85 && $pressure >= 1.1)
                {
                        $skyPhrase = 'Dense skies diffuse light into brilliant dawns.';
                }

                $gravityPhrase = 'Gravity aligns closely with human norms.';
                if ($gravity <= 0.7)
                {
                        $gravityPhrase = 'Gravity runs light, encouraging towering formations.';
                }
                elseif ($gravity >= 1.3)
                {
                        $gravityPhrase = 'Gravity bears heavily on the landscape.';
                }

                $communityHook = 'Communities balance agrarian plains with skyward observatories.';
                switch ($humidityClass)
                {
                        case 'oceanic':
                                $communityHook = 'Communities trace their history along tidal archipelagos.';
                                break;
                        case 'humid':
                                $communityHook = 'Communities thrive beneath monsoon-fed canopies and terraces.';
                                break;
                        case 'arid':
                                $communityHook = 'Communities migrate between oases and wind-carved ridges.';
                                break;
                        case 'desert':
                                $communityHook = 'Communities shelter in cavernous sanctuaries beneath dune seas.';
                                break;
                }
                if ($variance > 0.7)
                {
                        $communityHook .= ' Seasonal migrations synchronize with the volatile climate.';
                }

                $stellarFlux = max(0.0, min(1.0, floatval($env['stellar_flux'] ?? 0.0)));
                $weatherEnergy = min(1.0, max(0.0, ($variance * 0.65) + ($stellarFlux * 0.2) + ($atmosphere * 0.15)));

                return array(
                        'climate_adjective' => $climateAdjective,
                        'biome_descriptor' => $biomeDescriptor,
                        'humidity_class' => $humidityClass,
                        'seasonality_phrase' => $seasonalityPhrase,
                        'seasonality_descriptor' => $seasonalityDescriptor,
                        'life_phrase' => $lifePhrase,
                        'resource_phrase' => $resourcePhrase,
                        'sky_phrase' => $skyPhrase,
                        'gravity_phrase' => $gravityPhrase,
                        'community_hook' => $communityHook,
                        'weather_energy' => $weatherEnergy,
                        'variance' => $variance,
                        'temperature' => $temperature,
                        'water' => $water
                );
        }

        private function initializeWeatherSystems (bool $force) : void
        {
                if (!$force && !empty($this->weatherSystems))
                {
                        foreach ($this->weatherSystems as $index => $pattern)
                        {
                                $this->weatherSystems[$index]['intensity'] = $this->recalculateWeatherIntensity(floatval($pattern['intensity'] ?? 0.3));
                                $this->weatherSystems[$index]['summary'] = $this->summarizeWeatherPattern($this->weatherSystems[$index]);
                        }
                        if ($this->currentWeatherIndex === null && !empty($this->weatherSystems))
                        {
                                $this->currentWeatherIndex = 0;
                        }
                        return;
                }

                $this->weatherSystems = array();
                $profile = $this->describeClimate();
                $volatility = max(0.0, min(1.0, floatval($this->environment['climate_variance'] ?? 0.0)));
                $count = max(3, min(8, 3 + intval(round($volatility * 4))));
                for ($i = 0; $i < $count; $i++)
                {
                        $this->weatherSystems[] = $this->createWeatherPattern($i, $profile);
                }
                if (empty($this->weatherSystems))
                {
                        $this->currentWeatherIndex = null;
                        return;
                }
                $this->currentWeatherIndex = 0;
                $this->weatherSystems[$this->currentWeatherIndex]['summary'] = $this->summarizeWeatherPattern($this->weatherSystems[$this->currentWeatherIndex]);
                $this->weatherHistory = array();
                $this->weatherTimer = 0.0;
        }

        private function createWeatherPattern (int $index, array $profile) : array
        {
                $archetypes = array(
                        'oceanic' => array('tidal bloom', 'mariner squall', 'mistfall gyre', 'cyclonic surge'),
                        'humid' => array('canopy deluge', 'jungle monsoon', 'fog tier procession', 'rainfront chorus'),
                        'balanced' => array('continental rain band', 'jetstream sweep', 'temperate front', 'polar exchange'),
                        'arid' => array('dusk gale', 'mirage storm', 'loess current', 'plateau gust'),
                        'desert' => array('sirocco tide', 'dune cyclone', 'ember squall', 'sandglass surge')
                );
                $humidity = $profile['humidity_class'] ?? 'balanced';
                if (!isset($archetypes[$humidity]))
                {
                        $humidity = 'balanced';
                }
                $options = $archetypes[$humidity];
                $choice = $options[$index % count($options)];
                $baseEnergy = floatval($profile['weather_energy'] ?? 0.4);
                $intensity = $this->recalculateWeatherIntensity($baseEnergy + (((mt_rand() / mt_getrandmax()) - 0.5) * 0.2));
                $durationHours = $this->generateWeatherDurationHours();
                $pattern = array(
                        'id' => sprintf('wx-%s-%d', substr(hash('crc32b', $this->getName() . $choice . microtime(true)), 0, 6), $index + 1),
                        'name' => ucwords($choice),
                        'type' => $humidity,
                        'intensity' => $intensity,
                        'duration' => $durationHours * $this->getHourLengthSeconds(true),
                        'elapsed' => 0.0,
                        'summary' => ''
                );
                $pattern['summary'] = $this->summarizeWeatherPattern($pattern);
                return $pattern;
        }

        private function generateWeatherDurationHours () : int
        {
                $variance = max(0.0, min(1.0, floatval($this->environment['climate_variance'] ?? 0.0)));
                $base = 10 + intval(round($variance * 40));
                $min = max(6, $base - 6);
                $max = max($min + 2, $base + 12);
                return random_int($min, $max);
        }

        private function recalculateWeatherIntensity (float $baseline) : float
        {
                $energy = floatval($this->climateProfile['weather_energy'] ?? 0.4);
                $noise = ((mt_rand() / mt_getrandmax()) - 0.5) * 0.25;
                $result = ($baseline * 0.3) + ($energy * 0.6) + $noise + 0.1;
                return max(0.05, min(1.0, $result));
        }

        private function describeIntensity (float $intensity) : string
        {
                if ($intensity < 0.2) return 'gentle';
                if ($intensity < 0.4) return 'mild';
                if ($intensity < 0.6) return 'brisk';
                if ($intensity < 0.8) return 'intense';
                return 'ferocious';
        }

        private function summarizeWeatherPattern (array $pattern) : string
        {
                $profile = $this->describeClimate();
                $intensityLabel = $this->describeIntensity(floatval($pattern['intensity'] ?? 0.0));
                $biome = strtolower(strval($profile['biome_descriptor'] ?? 'terrain'));
                $seasonalityDescriptor = $profile['seasonality_descriptor'] ?? 'changing skies';
                $name = strtolower(strval($pattern['name'] ?? 'weather system'));
                $durationHours = max(1.0, $this->convertSecondsToLocalHours(floatval($pattern['duration'] ?? 0.0)));
                $line = sprintf('%s %s channels %s over the %s for roughly %.1f hours.', ucfirst($intensityLabel), $name, $seasonalityDescriptor, $biome, $durationHours);
                $life = trim(strval($profile['life_phrase'] ?? ''));
                if ($life !== '')
                {
                        $line .= ' ' . $life;
                }
                return $line;
        }

        private function advanceWeather (float $deltaTime) : void
        {
                if ($deltaTime <= 0) return;
                if (empty($this->weatherSystems))
                {
                        $this->initializeWeatherSystems(true);
                }
                if (empty($this->weatherSystems)) return;
                if ($this->currentWeatherIndex === null)
                {
                        $this->currentWeatherIndex = 0;
                }
                if (!isset($this->weatherSystems[$this->currentWeatherIndex])) return;

                $deltaTime = floatval($deltaTime);
                $this->weatherTimer += $deltaTime;
                $current = $this->weatherSystems[$this->currentWeatherIndex];
                $current['elapsed'] = floatval($current['elapsed'] ?? 0.0) + $deltaTime;
                $duration = floatval($current['duration'] ?? 0.0);
                if (($duration > 0.0) && ($current['elapsed'] >= $duration))
                {
                        $current['elapsed'] = $duration;
                        $current['summary'] = $this->summarizeWeatherPattern($current);
                        $this->weatherSystems[$this->currentWeatherIndex] = $current;
                        $this->weatherHistory[] = $this->buildWeatherHistoryEntry($current);
                        if (count($this->weatherHistory) > 24)
                        {
                                array_shift($this->weatherHistory);
                        }
                        $this->currentWeatherIndex = ($this->currentWeatherIndex + 1) % count($this->weatherSystems);
                        $this->weatherTimer = 0.0;
                        if (isset($this->weatherSystems[$this->currentWeatherIndex]))
                        {
                                $this->weatherSystems[$this->currentWeatherIndex]['elapsed'] = 0.0;
                                $this->weatherSystems[$this->currentWeatherIndex]['summary'] = $this->summarizeWeatherPattern($this->weatherSystems[$this->currentWeatherIndex]);
                        }
                        $this->refreshDescription();
                        return;
                }

                $this->weatherSystems[$this->currentWeatherIndex] = $current;
                if ($this->weatherTimer >= $this->getHourLengthSeconds(true))
                {
                        $this->weatherSystems[$this->currentWeatherIndex]['summary'] = $this->summarizeWeatherPattern($current);
                        $this->weatherTimer = 0.0;
                        $this->refreshDescription();
                }
        }

        private function buildWeatherHistoryEntry (array $pattern) : string
        {
                $name = strtolower(strval($pattern['name'] ?? 'weather system'));
                $intensityLabel = $this->describeIntensity(floatval($pattern['intensity'] ?? 0.0));
                $summary = trim(strval($pattern['summary'] ?? ''));
                $entry = sprintf('%s %s completed its cycle.', ucfirst($intensityLabel), $name);
                if ($summary !== '')
                {
                        $entry .= ' ' . $summary;
                }
                return $entry;
        }

        private function refreshDescription () : void
        {
                if ($this->climateProfile === null)
                {
                        $this->setDescription('Planetary climate data pending survey.');
                        return;
                }

                $intro = sprintf(
                        'A %s %s world %s.',
                        $this->climateProfile['climate_adjective'],
                        $this->climateProfile['biome_descriptor'],
                        $this->climateProfile['seasonality_phrase']
                );
                $lifeLine = $this->climateProfile['life_phrase'];
                $resourceLine = $this->climateProfile['resource_phrase'];
                $skyLine = $this->climateProfile['sky_phrase'];
                $gravityLine = $this->climateProfile['gravity_phrase'];
                $habitabilityLine = sprintf(
                        'Habitability score %.2f (%s).',
                        $this->habitabilityScore,
                        $this->habitabilityClass
                );
                $weather = $this->getCurrentWeather();
                $weatherLine = ($weather === null) ? '' : $weather['narrative'];
                $timeLine = $this->describeTimekeeping();
                $segments = array($intro, $lifeLine, $resourceLine, $skyLine, $gravityLine, $habitabilityLine, $weatherLine, $timeLine);
                $filtered = array();
                foreach ($segments as $segment)
                {
                        $segment = trim(strval($segment));
                        if ($segment === '') continue;
                        $filtered[] = $segment;
                }
                $this->setDescription(implode(' ', $filtered));
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
