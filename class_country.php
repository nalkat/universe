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
                        $person = new Person($name, $this);
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
}
?>
