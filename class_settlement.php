<?php // 7.3.0-dev
class Settlement extends Structure
{
        protected $population;
        protected $structures;
        protected $resources;
        protected $governance;

        public function __construct(string $name, array $properties = array())
        {
                parent::__construct($name, $properties);
                $this->population = intval($properties['population'] ?? 0);
                $this->structures = array();
                $this->resources = array(
                        'food' => 0.0,
                        'water' => 0.0,
                        'energy' => 0.0,
                        'materials' => 0.0,
                );
                $this->governance = $properties['governance'] ?? array(
                        'type' => 'informal',
                        'stability' => 0.5,
                );
        }

        public function getPopulation() : int
        {
                return $this->population;
        }

        public function adjustPopulation(int $delta) : void
        {
                $this->population = max(0, $this->population + $delta);
        }

        public function registerStructure(Structure $structure) : void
        {
                $this->structures[$structure->getName()] = $structure;
        }

        public function getStructures() : array
        {
                return $this->structures;
        }

        public function setResource(string $name, float $value) : void
        {
                $key = strtolower(trim($name));
                $this->resources[$key] = max(0.0, $value);
        }

        public function addResource(string $name, float $delta) : void
        {
                $key = strtolower(trim($name));
                if (!array_key_exists($key, $this->resources))
                {
                        $this->resources[$key] = 0.0;
                }
                $this->resources[$key] = max(0.0, $this->resources[$key] + $delta);
        }

        public function getResource(string $name) : float
        {
                $key = strtolower(trim($name));
                if (!array_key_exists($key, $this->resources))
                {
                        return 0.0;
                }
                return $this->resources[$key];
        }

        public function setGovernance(array $governance) : void
        {
                $this->governance = array_merge($this->governance, $governance);
                if (isset($this->governance['stability']))
                {
                        $this->governance['stability'] = max(0.0, min(1.0, floatval($this->governance['stability'])));
                }
        }

        public function getGovernance() : array
        {
                return $this->governance;
        }

        public function tick(float $deltaTime = 1.0) : void
        {
                parent::tick($deltaTime);
                if ($deltaTime <= 0)
                {
                        return;
                }
                $consumptionFactor = max(0, $this->population);
                $this->resources['food'] = max(0.0, $this->resources['food'] - ($consumptionFactor * 0.0001 * $deltaTime));
                $this->resources['water'] = max(0.0, $this->resources['water'] - ($consumptionFactor * 0.0002 * $deltaTime));
                if ($this->resources['food'] <= 0.0 || $this->resources['water'] <= 0.0)
                {
                        $this->governance['stability'] = max(0.0, ($this->governance['stability'] ?? 0.5) - 0.05);
                }
                else
                {
                        $this->governance['stability'] = min(1.0, ($this->governance['stability'] ?? 0.5) + 0.01);
                }
        }
}
?>
