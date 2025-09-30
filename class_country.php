<?php // 7.3.0-dev
class Country
{
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
                $this->planet->registerCountry($this);
                $this->initializeEconomy();
        }

        public function getName () : string
        {
                return $this->name;
        }

        public function getPlanet () : Planet
        {
                return $this->planet;
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
                        'population_capacity' => $this->populationCapacity,
                        'population' => $this->population,
                        'stockpiles' => $this->resourceStockpiles
                );
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
                return $this->consumeResource('food', $amount);
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
                $progress = $deltaTime * $this->developmentRate * 0.0001;
                $this->improveInfrastructure($progress);
                $this->improveTechnology($progress * 0.9);
                $this->improveResources($progress * 0.8);
                $this->improveStability($progress * 0.85);
                $this->rebalanceEmployment();
                $this->runEconomy($deltaTime);
                $this->advancePopulation($deltaTime);
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
                return $traits;
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
                $threshold = $population * 0.5;
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
                                if ($this->populationSeedTimer >= 86400.0)
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
                $days = $deltaTime / 86400.0;
                if ($days <= 0) return;

                $capacityRemaining = max(0, $this->populationCapacity - $population);
                if ($capacityRemaining <= 0)
                {
                        $this->birthAccumulator = 0.0;
                }
                else
                {
                        $foodPerCitizen = $this->getResourceStockpile('food') / max(1, $population);
                        $foodFactor = max(0.0, min(1.2, $foodPerCitizen / 2.0));
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

                $unrestPressure = max(0.0, 0.45 - $this->stability);
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
                $stabilityLoss = min(0.9, $intensity * 0.35);
                $infrastructureLoss = min(0.9, $intensity * 0.4);
                $technologyLoss = min(0.9, $intensity * 0.25);
                $this->stability = $this->sanitizeFraction($this->stability - $stabilityLoss);
                $this->infrastructure = $this->sanitizeFraction($this->infrastructure - $infrastructureLoss);
                $this->technology = $this->sanitizeFraction($this->technology - $technologyLoss);
                $resourceLossFactor = min(0.95, $intensity * 0.6);
                foreach ($this->resourceStockpiles as $resource => $amount)
                {
                        $this->resourceStockpiles[$resource] = max(0.0, $amount * (1.0 - $resourceLossFactor));
                }
                $casualtyTarget = intval(round($this->population * min(0.95, $intensity * 0.45)));
                $casualties = $this->applyCasualties($casualtyTarget, $cause);
                if ($casualties <= 0)
                {
                        $this->pruneDeadCitizens();
                }
                $injury = $intensity * 0.2;
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
                Utility::write(
                        $this->name . " suffered a disaster from $cause (intensity " . number_format($intensity, 2) . ")",
                        LOG_INFO,
                        L_CONSOLE
                );
                return array(
                        'casualties' => $casualties,
                        'infrastructure_loss' => $infrastructureLoss,
                        'stability_loss' => $stabilityLoss
                );
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
