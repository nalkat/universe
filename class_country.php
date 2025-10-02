<?php // 7.3.0-dev
class Country
{
        use MetadataBackedNarrative {
                addChronicleEntry as private recordChronicleEntry;
                importChronicle as private metadataImportChronicle;
                getChronicle as private metadataGetChronicle;
                setDescription as private metadataSetDescription;
                getDescription as private metadataGetDescription;
        }

        private $name;
        private $planet;
        private $infrastructure;
        private $technology;
        private $resourceIndex;
        private $stability;
        private $populationCapacity;
        private $population;
        private $developmentRate;
        private $people;
        private $jobs;
        private $resourceStockpiles;
        private $birthAccumulator;
        private $unrestAccumulator;
        private $populationSeedTimer;
        private $immortalityChance;
        private $adaptationLevel;
        private $adaptationAccumulator;
        private $culturalBackdrop;
        protected int $chronicleLimit = 64;
        private $territory;
        private $cities;

        public function __construct (string $name, Planet $planet, array $profile = array())
        {
                $this->name = Utility::cleanse_string($name);
                $this->planet = $planet;
                $this->infrastructure = $this->sanitizeFraction($profile['infrastructure'] ?? 0.0);
                $this->technology = $this->sanitizeFraction($profile['technology'] ?? 0.0);
                $this->resourceIndex = $this->sanitizeFraction($profile['resources'] ?? 0.0);
                $this->stability = $this->sanitizeFraction($profile['stability'] ?? 0.0);
                $this->populationCapacity = intval($profile['population_capacity'] ?? 0);
                $this->population = intval($profile['population'] ?? 0);
                $this->developmentRate = max(0.0, floatval($profile['development_rate'] ?? 1.0));
                $this->people = array();
                $this->jobs = array();
                $startingFood = floatval($profile['starting_food'] ?? max(10.0, $this->populationCapacity * 0.5));
                $this->resourceStockpiles = array(
                        'food' => max(0.0, $startingFood),
                        'materials' => max(0.0, floatval($profile['starting_materials'] ?? 0.0)),
                        'wealth' => max(0.0, floatval($profile['starting_wealth'] ?? 0.0))
                );
                $this->birthAccumulator = 0.0;
                $this->unrestAccumulator = 0.0;
                $this->populationSeedTimer = 0.0;
                $this->immortalityChance = $this->sanitizeFraction($profile['immortality_chance'] ?? 0.0);
                $this->adaptationLevel = $this->sanitizeFraction($profile['adaptation'] ?? 0.0);
                $this->adaptationAccumulator = 0.0;
                $this->culturalBackdrop = array();
                $this->territory = $this->generateTerritoryProfile($profile['territory'] ?? null);
                $this->cities = array();
                $this->planet->registerCountry($this);
                $this->initializeEconomy();
                $this->initializeLore();
                $this->generateCities($profile['cities'] ?? array());
                $this->synchronizeWithPlanetTimekeeping();
        }

        public function getName () : string
        {
                return $this->name;
        }

        public function getPlanet () : Planet
        {
                return $this->planet;
        }

        public function getLocalDayLengthSeconds () : float
        {
                return max(1.0, $this->planet->getDayLengthSeconds(true));
        }

        public function getLocalYearLengthSeconds () : float
        {
                return max($this->getLocalDayLengthSeconds(), $this->planet->getYearLengthSeconds(true));
        }

        public function getLocalHourLengthSeconds () : float
        {
                return max(1.0, $this->planet->getHourLengthSeconds(true));
        }

        private function getLocalWeekLengthSeconds () : float
        {
                return $this->getLocalDayLengthSeconds() * 7.0;
        }

        public function convertUniversalToLocalSeconds (float $seconds) : float
        {
                return $this->planet->convertUniversalToLocalSeconds($seconds);
        }

        public function synchronizeWithPlanetTimekeeping () : void
        {
                $day = $this->getLocalDayLengthSeconds();
                $this->populationSeedTimer = min($this->populationSeedTimer, $day);
                foreach ($this->people as $person)
                {
                        if ($person instanceof Person)
                        {
                                $person->synchronizeWithPlanetTimekeeping();
                        }
                }
        }

        public function getPopulation () : int
        {
                return $this->population;
        }

        public function getPopulationCapacity () : int
        {
                return $this->populationCapacity;
        }

        public function setPopulationCapacity (int $capacity) : void
        {
                $this->populationCapacity = max(0, $capacity);
        }

        public function getDevelopmentScore () : float
        {
                return (
                        $this->infrastructure +
                        $this->technology +
                        $this->resourceIndex +
                        $this->stability
                ) / 4;
        }

        public function isReadyForPopulation () : bool
        {
                if (!$this->planet->isReadyForCivilization()) return false;
                if ($this->populationCapacity <= 0) return false;
                $threshold = 0.55;
                $hasFood = ($this->getResourceStockpile('food') > 0.0);
                return (
                        ($this->infrastructure >= $threshold) &&
                        ($this->technology >= $threshold) &&
                        ($this->resourceIndex >= $threshold) &&
                        ($this->stability >= $threshold) &&
                        $hasFood
                );
        }

        public function getReadinessReport () : array
        {
                return array(
                        'planet_ready' => $this->planet->isReadyForCivilization(),
                        'infrastructure' => $this->infrastructure,
                        'technology' => $this->technology,
                        'resources' => $this->resourceIndex,
                        'stability' => $this->stability,
                        'adaptation' => $this->adaptationLevel,
                        'population_capacity' => $this->populationCapacity,
                        'population' => $this->population,
                        'stockpiles' => $this->resourceStockpiles,
                        'net_worth' => $this->getNetWorth(),
                        'territory' => $this->territory
                );
        }

        public function getTerritoryProfile () : array
        {
                return $this->territory;
        }

        public function getCities () : array
        {
                return array_values($this->cities);
        }

        public function getNetWorth () : float
        {
                $wealth = $this->resourceStockpiles['wealth'] ?? 0.0;
                foreach ($this->people as $person)
                {
                        if (!($person instanceof Person)) continue;
                        $wealth += $person->getNetWorth();
                }
                return max(0.0, $wealth);
        }

        public function getWealthPerCapita () : float
        {
                $population = max(1, $this->population);
                return $this->getNetWorth() / $population;
        }

        public function getAdaptationLevel () : float
        {
                return $this->adaptationLevel;
        }

        public function improveInfrastructure (float $delta) : void
        {
                $this->infrastructure = $this->sanitizeFraction($this->infrastructure + $delta);
        }

        public function improveTechnology (float $delta) : void
        {
                $this->technology = $this->sanitizeFraction($this->technology + $delta);
        }

        public function improveResources (float $delta) : void
        {
                $this->resourceIndex = $this->sanitizeFraction($this->resourceIndex + $delta);
        }

        public function improveStability (float $delta) : void
        {
                $this->stability = $this->sanitizeFraction($this->stability + $delta);
        }

        public function hasCapacityFor (int $count) : bool
        {
                return (($this->population + $count) <= $this->populationCapacity);
        }

        public function spawnPeople (int $count = 1, ?callable $namingStrategy = null) : array
        {
                if ($count <= 0) return array();
                if (!$this->isReadyForPopulation())
                {
                        Utility::write(
                                $this->name . " is not ready to support people",
                                LOG_INFO,
                                L_CONSOLE
                        );
                        return array();
                }
                if (!$this->hasCapacityFor($count))
                {
                        Utility::write(
                                $this->name . " lacks housing capacity for " . $count . " additional people",
                                LOG_INFO,
                                L_CONSOLE
                        );
                        return array();
                }
                $created = array();
                for ($i = 0; $i < $count; $i++)
                {
                        $name = ($namingStrategy === null)
                                ? $this->generateCitizenName()
                                : strval($namingStrategy($this->population + $i + 1, $this));
                        $traits = $this->generateCitizenTraits();
                        $person = new Person($name, $this, $traits);
                        $this->people[] = $person;
                        $this->assignCitizenBackstory($person);
                        $this->assignCitizenWealth($person);
                        $this->assignCitizenToCity($person);
                        $created[] = $person;
                }
                $this->population = count($this->people);
                $this->rebalanceEmployment();
                return $created;
        }

        public function getPeople () : array
        {
                return $this->people;
        }

        public function getJobs () : array
        {
                return $this->jobs;
        }

        public function getDescription () : string
        {
                $segments = array();
                $foundation = trim(strval($this->culturalBackdrop['foundation'] ?? ''));
                if ($foundation !== '') $segments[] = $foundation;
                $hook = trim(strval($this->culturalBackdrop['hook'] ?? ''));
                if ($hook !== '') $segments[] = $hook;
                $resource = trim(strval($this->culturalBackdrop['resource'] ?? ''));
                if ($resource !== '') $segments[] = $resource;
                $recent = $this->extractRecentChronicleLine();
                if ($recent !== '' && !in_array($recent, $segments, true)) $segments[] = $recent;
                return implode(' ', $segments);
        }

        public function getChronicle () : array
        {
                return $this->metadataGetChronicle();
        }

        public function getCulturalBackdrop () : array
        {
                return $this->culturalBackdrop;
        }

        public function addJob (Job $job) : void
        {
                $key = strtolower(trim(Utility::cleanse_string($job->getName())));
                if ($key === '')
                {
                        $key = spl_object_hash($job);
                }
                $this->jobs[$key] = $job;
        }

        public function getJob (string $name) : ?Job
        {
                $cleanName = strtolower(trim(Utility::cleanse_string($name)));
                if (!isset($this->jobs[$cleanName])) return null;
                return $this->jobs[$cleanName];
        }

        public function addResource (string $name, float $amount) : void
        {
                $key = Utility::cleanse_string($name);
                if ($key === '' || $amount == 0.0) return;
                if (!array_key_exists($key, $this->resourceStockpiles))
                {
                        $this->resourceStockpiles[$key] = 0.0;
                }
                $this->resourceStockpiles[$key] = max(0.0, $this->resourceStockpiles[$key] + $amount);
        }

        public function consumeResource (string $name, float $amount) : float
        {
                $key = Utility::cleanse_string($name);
                if ($key === '' || $amount <= 0) return 0.0;
                if (!array_key_exists($key, $this->resourceStockpiles)) return 0.0;
                $available = $this->resourceStockpiles[$key];
                if ($available <= 0) return 0.0;
                $consumed = min($available, $amount);
                $this->resourceStockpiles[$key] = $available - $consumed;
                return $consumed;
        }

        public function provideFood (float $amount) : float
        {
                $requested = max(0.0, floatval($amount));
                if ($requested <= 0.0) return 0.0;
                $efficiency = 1.0 + ($this->adaptationLevel * 0.25);
                $withdrawal = $requested / $efficiency;
                $consumed = $this->consumeResource('food', $withdrawal);
                $delivered = $consumed * $efficiency;
                if ($delivered > $requested)
                {
                        return $requested;
                }
                if ($consumed < $withdrawal)
                {
                        $this->recordHardship(min(1.0, 1.0 - ($consumed / max(0.0001, $withdrawal))));
                }
                return $delivered;
        }

        public function getResourceStockpile (string $name) : float
        {
                $key = Utility::cleanse_string($name);
                if ($key === '') return 0.0;
                return $this->resourceStockpiles[$key] ?? 0.0;
        }

        public function getResourceReport () : array
        {
                return $this->resourceStockpiles;
        }

        public function tick (float $deltaTime = 1.0) : void
        {
                if ($deltaTime <= 0) return;
                $localDelta = $this->convertUniversalToLocalSeconds($deltaTime);
                if ($localDelta <= 0)
                {
                        foreach ($this->people as $person)
                        {
                                if ($person instanceof Person)
                                {
                                        $person->tick($deltaTime);
                                }
                        }
                        return;
                }
                $progress = $localDelta * $this->developmentRate * 0.0001;
                $this->improveInfrastructure($progress);
                $this->improveTechnology($progress * 0.9);
                $this->improveResources($progress * 0.8);
                $this->improveStability($progress * 0.85);
                $this->rebalanceEmployment();
                $this->runEconomy($localDelta);
                $this->investInAdaptation($localDelta);
                $this->advancePopulation($localDelta);
                $this->processAdaptation($localDelta);
                $alive = array();
                foreach ($this->people as $person)
                {
                        if (!($person instanceof Person)) continue;
                        $person->tick($deltaTime);
                        if ($person->isAlive())
                        {
                                $alive[] = $person;
                                continue;
                        }
                        $city = $person->getResidenceCity();
                        if ($city instanceof City)
                        {
                                $city->removeResident($person);
                        }
                        $this->population--;
                }
                $this->people = $alive;
                $this->population = count($this->people);
        }

        private function sanitizeFraction ($value) : float
        {
                return max(0.0, min(1.0, floatval($value)));
        }

        private function generateCitizenName () : string
        {
                $index = $this->population + 1;
                return $this->name . " Citizen " . $index;
        }

        private function generateCitizenTraits () : array
        {
                $traits = array();
                if ($this->immortalityChance > 0.0)
                {
                        $roll = mt_rand() / mt_getrandmax();
                        if ($roll <= $this->immortalityChance)
                        {
                                $traits['mortality'] = 'immortal';
                                $traits['immortal'] = true;
                        }
                }
                if ($this->adaptationLevel > 0.0)
                {
                        $baseResilience = 0.05 + ($this->adaptationLevel * 0.6);
                        $variance = ((mt_rand() / mt_getrandmax()) - 0.5) * 0.2;
                        $traits['resilience'] = max(0.0, min(1.0, $baseResilience + $variance));
                }
                $longevity = $this->estimateCitizenLongevity();
                if ($longevity !== null)
                {
                        $lifeSpread = max(2.0, $longevity['expectancy'] * 0.1);
                        $lifeRoll = ((mt_rand() / mt_getrandmax()) - 0.5) * $lifeSpread;
                        $lifeExpectancy = max(24.0, $longevity['expectancy'] + $lifeRoll);
                        $senescenceSpread = max(1.0, $longevity['senescence'] * 0.08);
                        $senescenceRoll = ((mt_rand() / mt_getrandmax()) - 0.5) * $senescenceSpread;
                        $senescenceAge = max(18.0, min($lifeExpectancy - 2.0, $longevity['senescence'] + $senescenceRoll));
                        $traits['life_expectancy_years'] = $lifeExpectancy;
                        $traits['senescence_years'] = $senescenceAge;
                }
                return $traits;
        }

        private function assignCitizenWealth (Person $person) : void
        {
                $nationalWealth = max(0.0, $this->resourceStockpiles['wealth'] ?? 0.0);
                $populationBaseline = ($this->populationCapacity > 0)
                        ? $this->populationCapacity
                        : max(1, count($this->people));
                $baseline = ($nationalWealth > 0.0)
                        ? max(20.0, $nationalWealth / $populationBaseline)
                        : 40.0 + (($this->technology + $this->infrastructure) * 35.0);
                $variance = 0.5 + (mt_rand(0, 100) / 100.0);
                $stabilityFactor = 0.6 + ($this->stability * 0.5);
                $developmentFactor = 0.7 + ($this->getDevelopmentScore() * 0.6);
                $wealth = $baseline * $variance * $stabilityFactor * $developmentFactor;
                if ($person->getProfession() !== null)
                {
                        $wealth *= 1.15;
                }
                $person->setNetWorth($wealth);
        }

        private function assignCitizenToCity (Person $person) : void
        {
                if (!($person instanceof Person)) return;
                if (empty($this->cities))
                {
                        $this->generateCities(array());
                }
                $city = $this->selectCityForResident();
                if ($city instanceof City)
                {
                        $city->addResident($person);
                        $person->setResidenceCity($city);
                }
        }

        private function selectCityForResident () : ?City
        {
                $choice = null;
                $lowest = null;
                foreach ($this->cities as $city)
                {
                        if (!($city instanceof City)) continue;
                        $population = $city->getPopulation();
                        if ($choice === null || $population < $lowest)
                        {
                                $choice = $city;
                                $lowest = $population;
                        }
                }
                if ($choice instanceof City)
                {
                        return $choice;
                }
                if (empty($this->cities))
                {
                        return null;
                }
                $index = array_rand($this->cities);
                $city = $this->cities[$index];
                return ($city instanceof City) ? $city : null;
        }

        private function generateCities (array $profile) : void
        {
                if (!empty($this->cities))
                {
                        return;
                }
                $requested = intval($profile['count'] ?? 0);
                if ($requested <= 0)
                {
                        $capacity = max(1, ($this->populationCapacity > 0) ? $this->populationCapacity : 2000);
                        $requested = max(3, min(16, intval(round($capacity / 800)) + 2));
                }
                $names = array();
                if (!empty($profile['names']) && is_array($profile['names']))
                {
                        $names = array_values($profile['names']);
                }

                for ($i = 0; $i < $requested; $i++)
                {
                        $name = $names[$i] ?? $this->generateCityName($i + 1);
                        $radius = max(4.0, mt_rand(20, 80) / 2.0);
                        $location = $this->randomizeCityCoordinates(null, $radius);
                        $city = new City($name, array(
                                'population' => 0,
                                'radius' => $radius,
                                'location' => array(
                                        'latitude' => $location['latitude'],
                                        'longitude' => $location['longitude']
                                )
                        ));
                        $city->setRadius($radius);
                        $city->setLocation($location);
                        $this->cities[$city->getName()] = $city;
                        $this->recordChronicleEntry(
                                'city_foundation',
                                $city->getName() . ' chartered within ' . $this->name . '.',
                                microtime(true),
                                array($city->getName())
                        );
                }
        }

        private function generateCityName (int $index) : string
        {
                $prefixes = array('New', 'North', 'Port', 'Lake', 'Fort', 'Star', 'Silver', 'Aurora', 'Sky', 'River');
                $cores = array('Vale', 'Reach', 'Harbor', 'Hold', 'Haven', 'Grove', 'Spire', 'Landing', 'Crest', 'Forge');
                $prefix = $prefixes[mt_rand(0, count($prefixes) - 1)];
                $core = $cores[mt_rand(0, count($cores) - 1)];
                return sprintf('%s %s %d', $prefix, $core, $index);
        }

        private function randomizeCityCoordinates (?array $center, float $radius) : array
        {
                if ($center === null)
                {
                        $center = $this->territory['center'] ?? array('latitude' => 0.0, 'longitude' => 0.0);
                }
                $span = $this->territory['span'] ?? array('latitude' => 18.0, 'longitude' => 28.0);
                $latSpread = max(1.0, floatval($span['latitude'] ?? 18.0) / 2.5);
                $lonSpread = max(1.0, floatval($span['longitude'] ?? 28.0) / 2.5);
                $latitude = floatval($center['latitude'] ?? ($center['lat'] ?? 0.0));
                $longitude = floatval($center['longitude'] ?? ($center['lon'] ?? 0.0));
                $latitude += (mt_rand(-1000, 1000) / 1000.0) * $latSpread;
                $longitude += (mt_rand(-1000, 1000) / 1000.0) * $lonSpread;
                $latitude = max(-90.0, min(90.0, $latitude));
                $longitude = $this->normalizeLongitude($longitude);
                return array('latitude' => $latitude, 'longitude' => $longitude);
        }

        private function generateTerritoryProfile ($profile) : array
        {
                $climate = $this->planet->describeClimate();
                $center = array(
                        'latitude' => floatval($profile['center']['latitude'] ?? ($profile['center']['lat'] ?? mt_rand(-6000, 6000) / 100.0)),
                        'longitude' => floatval($profile['center']['longitude'] ?? ($profile['center']['lon'] ?? mt_rand(-18000, 18000) / 100.0))
                );
                $center['latitude'] = max(-90.0, min(90.0, $center['latitude']));
                $center['longitude'] = $this->normalizeLongitude($center['longitude']);
                $span = array(
                        'latitude' => max(6.0, floatval($profile['span']['latitude'] ?? mt_rand(600, 2600) / 10.0)),
                        'longitude' => max(8.0, floatval($profile['span']['longitude'] ?? mt_rand(900, 3600) / 10.0))
                );
                $biome = strval($profile['biome'] ?? ($climate['biome_descriptor'] ?? 'continental'));
                $terrain = strval($profile['terrain'] ?? $this->randomTerrainDescriptor($biome));
                return array(
                        'center' => $center,
                        'span' => $span,
                        'biome' => $biome,
                        'terrain' => $terrain
                );
        }

        private function normalizeLongitude (float $longitude) : float
        {
                while ($longitude < -180.0)
                {
                        $longitude += 360.0;
                }
                while ($longitude > 180.0)
                {
                        $longitude -= 360.0;
                }
                return $longitude;
        }

        private function randomTerrainDescriptor (string $biome) : string
        {
                $biome = strtolower(trim($biome));
                $options = array(
                        'desert' => array('dune seas', 'stony basins', 'salt flats', 'oasis chains'),
                        'glacial expanse' => array('ice shelf plateaus', 'frozen archipelagos', 'permafrost ridges'),
                        'tropical' => array('rainforest canopy', 'mangrove deltas', 'coral archipelagos'),
                        'sun-baked plateau' => array('mesa fields', 'dust steppes', 'scarlet cliffs'),
                        'oceanic' => array('atoll clusters', 'kelp forests', 'tidal marshes'),
                        'continental' => array('rolling plains', 'river valleys', 'ancient highlands'),
                        'semi-arid steppe' => array('wind-scoured prairies', 'sagebrush flats', 'basalt mesas'),
                        'volcanic badlands' => array('igneous terraces', 'obsidian barrens', 'smoldering calderas'),
                        'glacial' => array('alpine ridges', 'glittering fjords', 'crystal tundra')
                );
                $pool = $options[$biome] ?? $options['continental'];
                return $pool[mt_rand(0, count($pool) - 1)];
        }

        private function estimateCitizenLongevity () : ?array
        {
                $habitability = max(0.0, min(1.0, $this->planet->getHabitabilityScore()));
                $climate = $this->planet->describeClimate();
                $variance = floatval($climate['variance'] ?? 0.0);
                $rate = $this->planet->getRelativeTimeRate();
                $base = 62.0 + ($habitability * 28.0) - ($variance * 10.0);
                $base += ($this->technology * 12.0) + ($this->infrastructure * 9.0);
                $base += ($this->resourceIndex * 6.0) + ($this->stability * 5.0);
                $base += $this->adaptationLevel * 14.0;
                if ($rate < 1.0)
                {
                        $base += (1.0 - $rate) * 4.0;
                }
                elseif ($rate > 1.0)
                {
                        $base -= min(10.0, ($rate - 1.0) * 6.0);
                }
                $base = max(30.0, min(118.0, $base));
                $senescence = max(22.0, min($base - 3.0, $base * (0.58 + $habitability * 0.22)));
                return array('expectancy' => $base, 'senescence' => $senescence);
        }

        private function initializeEconomy () : void
        {
                $this->jobs = array();
                $this->addJob(new Job('Farmer', array(
                        'category' => 'agriculture',
                        'requires' => array('agriculture' => 0.3, 'endurance' => 0.2),
                        'produces' => array('food' => 1.5),
                        'training' => array('agriculture' => 0.6, 'endurance' => 0.2),
                        'capacity' => 0,
                        'priority' => 20
                )));
                $this->addJob(new Job('Artisan', array(
                        'category' => 'industry',
                        'requires' => array('crafting' => 0.4),
                        'produces' => array('materials' => 0.6, 'wealth' => 0.3),
                        'training' => array('crafting' => 0.4),
                        'capacity' => 0,
                        'priority' => 8
                )));
                $this->addJob(new Job('Service Worker', array(
                        'category' => 'services',
                        'requires' => array('diplomacy' => 0.3),
                        'produces' => array('wealth' => 0.8),
                        'training' => array('diplomacy' => 0.3),
                        'capacity' => 0,
                        'priority' => 5
                )));
        }

        private function initializeLore () : void
        {
                $climate = $this->planet->describeClimate();
                $planetDescription = $this->planet->getDescription();
                $foundation = sprintf(
                        '%s was founded amidst the %s reaches of %s %s.',
                        $this->name,
                        $climate['biome_descriptor'] ?? 'continental',
                        $this->planet->getName(),
                        $climate['seasonality_phrase'] ?? 'with steady seasons'
                );
                $life = trim(strval($climate['life_phrase'] ?? ''));
                if ($life !== '')
                {
                        $foundation .= ' ' . $life;
                }
                $this->culturalBackdrop = array(
                        'climate' => $climate,
                        'planet_description' => $planetDescription,
                        'foundation' => $foundation,
                        'hook' => trim(strval($climate['community_hook'] ?? '')),
                        'resource' => trim(strval($climate['resource_phrase'] ?? ''))
                );
                $this->metadataImportChronicle(array());
                $this->addChronicleEntry('foundation', $foundation);
                if ($this->culturalBackdrop['hook'] !== '')
                {
                        $this->addChronicleEntry('tradition', $this->culturalBackdrop['hook']);
                }
                if ($this->culturalBackdrop['resource'] !== '')
                {
                        $this->addChronicleEntry('resources', $this->culturalBackdrop['resource']);
                }
        }

        private function assignCitizenBackstory (Person $person) : void
        {
                $climate = $this->culturalBackdrop['climate'] ?? $this->planet->describeClimate();
                $adjective = $climate['climate_adjective'] ?? 'temperate';
                $biome = $climate['biome_descriptor'] ?? 'lands';
                $seasonality = $climate['seasonality_phrase'] ?? 'with steady seasons';
                $intro = sprintf(
                        '%s grew up amid the %s %s of %s %s.',
                        $person->getName(),
                        $adjective,
                        $biome,
                        $this->name,
                        $seasonality
                );
                $hook = trim(strval($this->culturalBackdrop['hook'] ?? ''));
                $resource = trim(strval($this->culturalBackdrop['resource'] ?? ''));
                $eventLine = $this->extractRecentChronicleLine();
                $connections = $this->selectCommunityConnections($person);
                $connectionLines = array();
                $participants = array($person->getName());
                foreach ($connections as $connection)
                {
                        $line = $this->composeConnectionLine($person, $connection['person'], $connection['role']);
                        if ($line !== '')
                        {
                                $connectionLines[] = $line;
                        }
                        $role = $connection['role'];
                        $otherName = $connection['person']->getName();
                        $person->addRelationship($role, $otherName);
                        $participants[] = $otherName;
                }
                $segments = array($intro);
                if ($hook !== '') $segments[] = $hook;
                if ($resource !== '') $segments[] = $resource;
                if ($eventLine !== '') $segments[] = $eventLine;
                $segments = array_merge($segments, $connectionLines);
                $backstory = implode(' ', $segments);
                $person->setBackstory($backstory);
                $this->addChronicleEntry('biography', $backstory, $participants);
        }

        private function extractRecentChronicleLine () : string
        {
                $entries = array_reverse($this->metadataGetChronicle());
                foreach ($entries as $entry)
                {
                        if (!is_array($entry)) continue;
                        $type = strval($entry['type'] ?? '');
                        if ($type === 'biography') continue;
                        $text = trim(strval($entry['text'] ?? ''));
                        if ($text !== '')
                        {
                                return $text;
                        }
                }
                return '';
        }

        private function selectCommunityConnections (Person $person) : array
        {
                $candidates = array();
                foreach ($this->people as $resident)
                {
                        if (!($resident instanceof Person)) continue;
                        if ($resident === $person) continue;
                        if (!$resident->isAlive()) continue;
                        $candidates[] = $resident;
                }
                if (empty($candidates)) return array();
                shuffle($candidates);
                $max = min(3, count($candidates));
                $rolePool = array('mentor', 'friend', 'partner', 'rival', 'inspiration');
                shuffle($rolePool);
                $connections = array();
                for ($i = 0; $i < $max; $i++)
                {
                        $role = $rolePool[$i % count($rolePool)];
                        $connections[] = array('role' => $role, 'person' => $candidates[$i]);
                }
                return $connections;
        }

        private function composeConnectionLine (Person $person, Person $other, string $role) : string
        {
                $templates = array(
                        'mentor' => '%s apprenticed under %s to master local crafts.',
                        'friend' => '%s shares dawn gatherings with longtime friend %s.',
                        'partner' => '%s charts communal projects alongside %s.',
                        'rival' => '%s tests ambitions against rival %s during seasonal contests.',
                        'inspiration' => '%s draws inspiration from the stories of %s.'
                );
                $template = $templates[$role] ?? '%s maintains close ties with %s.';
                return sprintf($template, $person->getName(), $other->getName());
        }

        private function addChronicleEntry (string $type, string $text, array $participants = array()) : void
        {
                $cleanType = Utility::cleanse_string($type);
                $normalizedText = trim(strval($text));
                if ($normalizedText === '') return;
                $participantList = array_values(array_unique(array_filter(array_map('strval', $participants))));
                $timestamp = microtime(true);
                $this->recordChronicleEntry(
                        ($cleanType === '') ? 'event' : $cleanType,
                        $normalizedText,
                        $timestamp,
                        $participantList
                );
                foreach ($participantList as $participant)
                {
                        $person = $this->findPersonByName($participant);
                        if ($person instanceof Person)
                        {
                                $person->addChronicleEntry(($cleanType === '') ? 'event' : $cleanType, $normalizedText, $timestamp, $participantList);
                        }
                }
        }

        private function findPersonByName (string $name) : ?Person
        {
                $clean = Utility::cleanse_string($name);
                if ($clean === '') return null;
                foreach ($this->people as $person)
                {
                        if (!($person instanceof Person)) continue;
                        if (Utility::cleanse_string($person->getName()) === $clean)
                        {
                                return $person;
                        }
                }
                return null;
        }

        private function rebalanceEmployment () : void
        {
                foreach ($this->jobs as $job)
                {
                        $job->pruneInvalidWorkers();
                }
                $foodPressure = $this->needsFoodSupport();
                foreach ($this->people as $person)
                {
                        if (!($person instanceof Person)) continue;
                        if (!$person->isAlive()) continue;
                        $job = $person->getJob();
                        if ($foodPressure && $job instanceof Job && $job->getCategory() !== 'agriculture')
                        {
                                $job->removeWorker($person);
                                $job = null;
                        }
                        if (!($job instanceof Job))
                        {
                                $this->assignBestJob($person);
                                continue;
                        }
                        if (!$job->hasWorker($person))
                        {
                                $person->setJob(null);
                                $this->assignBestJob($person);
                        }
                }
        }

        private function runEconomy (float $deltaTime) : void
        {
                foreach ($this->jobs as $job)
                {
                        $job->perform($deltaTime, $this);
                }
        }

        private function assignBestJob (Person $person) : void
        {
                $bestJob = null;
                $bestScore = 0.0;
                foreach ($this->jobs as $job)
                {
                        if (!$job->hasCapacity()) continue;
                        $score = $job->scoreCandidate($person);
                        if ($score <= 0) continue;
                        if ($bestJob === null || $score > $bestScore)
                        {
                                $bestJob = $job;
                                $bestScore = $score;
                        }
                }
                if ($bestJob instanceof Job)
                {
                        $bestJob->addWorker($person);
                }
        }

        private function needsFoodSupport () : bool
        {
                $population = max(1, count($this->people));
                $buffer = max(0.2, 0.5 - ($this->adaptationLevel * 0.2));
                $threshold = $population * $buffer;
                return ($this->getResourceStockpile('food') < $threshold);
        }

        private function advancePopulation (float $deltaTime) : void
        {
                if ($deltaTime <= 0) return;
                $population = count($this->people);
                if ($population <= 0)
                {
                        if ($this->isReadyForPopulation())
                        {
                                $this->populationSeedTimer += $deltaTime;
                                if ($this->populationSeedTimer >= $this->getLocalDayLengthSeconds())
                                {
                                        $this->populationSeedTimer = 0.0;
                                        $seed = intval(max(1, min(
                                                $this->populationCapacity,
                                                max(5, round($this->populationCapacity * 0.01))
                                        )));
                                        if ($seed > 0)
                                        {
                                                $this->spawnPeople($seed);
                                        }
                                }
                        }
                        else
                        {
                                $this->populationSeedTimer = 0.0;
                        }
                        $this->birthAccumulator = 0.0;
                        $this->unrestAccumulator = 0.0;
                        return;
                }

                $this->populationSeedTimer = 0.0;
                $dayLength = $this->getLocalDayLengthSeconds();
                $days = ($dayLength > 0.0) ? ($deltaTime / $dayLength) : 0.0;
                if ($days <= 0) return;

                $capacityRemaining = max(0, $this->populationCapacity - $population);
                if ($capacityRemaining <= 0)
                {
                        $this->birthAccumulator = 0.0;
                }
                else
                {
                        $foodPerCitizen = $this->getResourceStockpile('food') / max(1, $population);
                        $foodFactor = max(0.0, min(1.2, ($foodPerCitizen / 2.0) * (1.0 + $this->adaptationLevel * 0.3)));
                        $shortage = max(0.0, 1.0 - min(1.0, $foodPerCitizen));
                        if ($shortage > 0)
                        {
                                $this->recordHardship($shortage * 0.5);
                        }
                        $capacityFactor = ($this->populationCapacity <= 0)
                                ? 0.0
                                : min(1.0, $capacityRemaining / max(1, $this->populationCapacity * 0.3));
                        $developmentFactor = 0.4 + 0.6 * $this->getDevelopmentScore();
                        $stabilityFactor = 0.4 + 0.6 * $this->stability;
                        $baseBirthRate = 0.00028;
                        $birthRate = $baseBirthRate * $foodFactor * $capacityFactor * $developmentFactor * $stabilityFactor;
                        $expectedBirths = $population * $birthRate * $days;
                        $this->birthAccumulator += $expectedBirths;
                        $births = intval(floor($this->birthAccumulator));
                        if ($births > 0)
                        {
                                $this->birthAccumulator -= $births;
                                $births = min($births, $capacityRemaining);
                                if ($births > 0)
                                {
                                        $this->spawnPeople($births);
                                }
                        }
                }

                $unrestPressure = max(0.0, 0.45 - $this->stability - ($this->adaptationLevel * 0.15));
                if ($unrestPressure > 0)
                {
                        $unrestRate = $unrestPressure * 0.00018;
                        $this->unrestAccumulator += $population * $unrestRate * $days;
                        $losses = intval(floor($this->unrestAccumulator));
                        if ($losses > 0)
                        {
                                $this->unrestAccumulator -= $losses;
                                $this->applyCasualties($losses, 'civil unrest');
                        }
                        else
                        {
                                $this->recordHardship(min(1.0, $unrestPressure * 0.5));
                        }
                }
                else
                {
                        $this->unrestAccumulator = max(0.0, $this->unrestAccumulator - ($days * 0.5));
                }
        }

        private function applyCasualties (int $count, string $cause) : int
        {
                if ($count <= 0) return 0;
                $total = count($this->people);
                if ($total <= 0) return 0;
                $count = min($count, $total);
                $indices = array_rand($this->people, $count);
                if (!is_array($indices))
                {
                        $indices = array($indices);
                }
                $losses = 0;
                foreach ($indices as $index)
                {
                        $person = $this->people[$index];
                        if (($person instanceof Person) && $person->isAlive())
                        {
                                $person->kill($cause);
                                $losses++;
                        }
                }
                if ($losses > 0)
                {
                        $this->pruneDeadCitizens();
                        $this->recordHardship(min(1.0, $losses / max(1, $total)));
                        Utility::write($this->name . " lost $losses citizens to $cause", LOG_INFO, L_CONSOLE);
                }
                return $losses;
        }

        public function sufferDisaster (float $intensity, string $cause) : array
        {
                $intensity = max(0.0, min(1.0, floatval($intensity)));
                if ($intensity <= 0)
                {
                        return array('casualties' => 0, 'infrastructure_loss' => 0.0, 'stability_loss' => 0.0);
                }
                $mitigation = max(0.2, 1.0 - ($this->adaptationLevel * 0.6));
                $effectiveIntensity = min(1.0, $intensity * $mitigation);
                $stabilityLoss = min(0.9, $effectiveIntensity * 0.35);
                $infrastructureLoss = min(0.9, $effectiveIntensity * 0.4);
                $technologyLoss = min(0.9, $effectiveIntensity * 0.25);
                $this->stability = $this->sanitizeFraction($this->stability - $stabilityLoss);
                $this->infrastructure = $this->sanitizeFraction($this->infrastructure - $infrastructureLoss);
                $this->technology = $this->sanitizeFraction($this->technology - $technologyLoss);
                $resourceLossFactor = min(0.95, $effectiveIntensity * 0.6);
                foreach ($this->resourceStockpiles as $resource => $amount)
                {
                        $this->resourceStockpiles[$resource] = max(0.0, $amount * (1.0 - $resourceLossFactor));
                }
                $casualtyTarget = intval(round($this->population * min(0.95, $effectiveIntensity * 0.45)));
                $casualties = $this->applyCasualties($casualtyTarget, $cause);
                if ($casualties <= 0)
                {
                        $this->pruneDeadCitizens();
                }
                $injury = $effectiveIntensity * 0.2;
                if ($injury > 0)
                {
                        foreach ($this->people as $person)
                        {
                                if ($person instanceof Person)
                                {
                                        $person->sufferTrauma($injury * 0.5, $cause . ' injuries');
                                }
                        }
                }
                $casualtyFraction = ($casualties > 0 && $this->population + $casualties > 0)
                        ? min(1.0, $casualties / max(1, $this->population + $casualties))
                        : 0.0;
                $this->recordHardship(min(1.0, $effectiveIntensity + ($casualtyFraction * 0.5)));
                $mitigationNote = (abs($effectiveIntensity - $intensity) > 0.0001)
                        ? " mitigated from " . number_format($intensity, 2)
                        : '';
                Utility::write(
                        $this->name . " suffered a disaster from $cause (intensity " . number_format($effectiveIntensity, 2) .
                        $mitigationNote . ")",
                        LOG_INFO,
                        L_CONSOLE
                );
                $summary = sprintf(
                        '%s weathered %s (intensity %.2f) with %d casualties.',
                        $this->name,
                        $cause,
                        $effectiveIntensity,
                        $casualties
                );
                $this->addChronicleEntry('disaster', $summary);
                return array(
                        'casualties' => $casualties,
                        'infrastructure_loss' => $infrastructureLoss,
                        'stability_loss' => $stabilityLoss
                );
        }

        private function investInAdaptation (float $deltaTime) : void
        {
                if ($deltaTime <= 0) return;
                if ($this->population <= 0) return;
                $need = max(0.0, 1.0 - $this->adaptationLevel);
                if ($need <= 0.0) return;
                $population = max(1, $this->population);
                $materialsBudget = min($this->getResourceStockpile('materials') * 0.1, $deltaTime * 0.02 * $population);
                if ($materialsBudget <= 0) return;
                $materialsSpent = $this->consumeResource('materials', $materialsBudget);
                if ($materialsSpent <= 0) return;
                $wealthSpent = $this->consumeResource('wealth', $materialsSpent * 0.5);
                $investment = ($materialsSpent + ($wealthSpent * 0.6)) / max(1.0, $population);
                $this->adaptationAccumulator = min(5.0, $this->adaptationAccumulator + ($investment * ($need + 0.2)));
        }

        private function processAdaptation (float $deltaTime) : void
        {
                if ($deltaTime <= 0) return;
                if ($this->adaptationAccumulator > 0.0)
                {
                        $gain = min(0.05, $this->adaptationAccumulator * 0.2);
                        $this->adaptationAccumulator = max(0.0, $this->adaptationAccumulator - ($gain * 3.0));
                        $this->adaptationLevel = $this->sanitizeFraction($this->adaptationLevel + $gain);
                        return;
                }
                $week = $this->getLocalWeekLengthSeconds();
                if ($week <= 0) return;
                $decay = ($deltaTime / $week) * 0.01;
                if ($decay > 0)
                {
                        $this->adaptationLevel = max(0.0, $this->adaptationLevel - $decay);
                }
        }

        private function recordHardship (float $severity) : void
        {
                $severity = max(0.0, min(1.0, $severity));
                if ($severity <= 0.0) return;
                $pressure = $severity * (1.0 + ($this->needsFoodSupport() ? 0.5 : 0.0));
                $this->adaptationAccumulator = min(5.0, $this->adaptationAccumulator + $pressure);
        }

        private function pruneDeadCitizens () : void
        {
                $alive = array();
                foreach ($this->people as $person)
                {
                        if (($person instanceof Person) && $person->isAlive())
                        {
                                $alive[] = $person;
                        }
                }
                $this->people = $alive;
                $this->population = count($this->people);
        }
}
?>
