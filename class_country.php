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
                $this->planet->registerCountry($this);
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
                return (
                        ($this->infrastructure >= $threshold) &&
                        ($this->technology >= $threshold) &&
                        ($this->resourceIndex >= $threshold) &&
                        ($this->stability >= $threshold)
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
                        'population' => $this->population
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
                $this->population += count($created);
                return $created;
        }

        public function getPeople () : array
        {
                return $this->people;
        }

        public function tick (float $deltaTime = 1.0) : void
        {
                if ($deltaTime <= 0) return;
                $progress = $deltaTime * $this->developmentRate * 0.0001;
                $this->improveInfrastructure($progress);
                $this->improveTechnology($progress * 0.9);
                $this->improveResources($progress * 0.8);
                $this->improveStability($progress * 0.85);
                foreach ($this->people as $person)
                {
                        $person->tick($deltaTime);
                }
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
}
?>
