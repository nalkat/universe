<?php // 7.3.0-dev
class Insect extends Animal
{
        protected $colonyId;
        protected $role;
        protected $metamorphosisStage;
        protected $canFly;
        protected $pheromoneSignals;

        public function __construct(string $name, array $traits = array())
        {
                $traits['diet'] = $traits['diet'] ?? 'herbivore';
                parent::__construct($name, $traits);
                $this->colonyId = $traits['colony_id'] ?? null;
                $this->role = strtolower(trim($traits['role'] ?? 'worker'));
                $this->metamorphosisStage = strtolower(trim($traits['stage'] ?? 'larva'));
                $this->canFly = boolval($traits['can_fly'] ?? ($this->metamorphosisStage === 'adult'));
                $this->pheromoneSignals = array();
        }

        public function getColonyId()
        {
                return $this->colonyId;
        }

        public function setColonyId($colonyId) : void
        {
                $this->colonyId = $colonyId;
        }

        public function getRole() : string
        {
                return $this->role;
        }

        public function setRole(string $role) : void
        {
                $this->role = strtolower(trim($role));
        }

        public function getStage() : string
        {
                return $this->metamorphosisStage;
        }

        public function advanceStage() : void
        {
                $order = array('egg', 'larva', 'pupa', 'adult');
                $index = array_search($this->metamorphosisStage, $order, true);
                if ($index === false || $index === count($order) - 1)
                {
                        return;
                }
                $this->metamorphosisStage = $order[$index + 1];
                if ($this->metamorphosisStage === 'adult')
                {
                        $this->canFly = true;
                }
        }

        public function canFly() : bool
        {
                return $this->canFly;
        }

        public function addPheromoneSignal(string $type, float $strength) : void
        {
                $key = strtolower(trim($type));
                $this->pheromoneSignals[$key] = max(0.0, min(1.0, $strength));
        }

        public function getPheromoneSignals() : array
        {
                return $this->pheromoneSignals;
        }

        public function tick(float $deltaTime = 1.0) : void
        {
                parent::tick($deltaTime);
                if ($deltaTime <= 0)
                {
                        return;
                }
                foreach ($this->pheromoneSignals as $type => $strength)
                {
                        $this->pheromoneSignals[$type] = max(0.0, $strength - 0.1 * $deltaTime);
                        if ($this->pheromoneSignals[$type] <= 0.0)
                        {
                                unset($this->pheromoneSignals[$type]);
                        }
                }
                if ($this->getStage() !== 'adult' && $this->getAge() > 10 * 86400)
                {
                        $this->advanceStage();
                }
        }
}
?>
