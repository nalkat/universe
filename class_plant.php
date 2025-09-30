<?php // 7.3.0-dev
class Plant extends Life
{
        protected $species;
        protected $taxonomy;
        protected $height;
        protected $energyReserve;
        protected $waterReserve;
        protected $nutrientReserve;
        protected $growthStage;
        protected $growthRate;
        protected $photosynthesisRate;

        public function __construct(string $name, array $traits = array())
        {
                parent::__construct($name, $traits);
                $this->species = $traits['species'] ?? $this->getName();
                $this->taxonomy = ($traits['taxonomy'] ?? null) instanceof Taxonomy
                        ? $traits['taxonomy']
                        : new Taxonomy(is_array($traits['taxonomy'] ?? null) ? $traits['taxonomy'] : array('kingdom' => 'plantae'));
                $this->height = max(0.0, floatval($traits['height'] ?? 0.1));
                $this->energyReserve = max(0.0, floatval($traits['energy'] ?? 0.2));
                $this->waterReserve = max(0.0, floatval($traits['water'] ?? 0.2));
                $this->nutrientReserve = max(0.0, floatval($traits['nutrients'] ?? 0.1));
                $this->growthStage = strtolower(trim($traits['stage'] ?? 'seedling'));
                $this->growthRate = max(0.0, floatval($traits['growth_rate'] ?? 0.01));
                $this->photosynthesisRate = max(0.0, floatval($traits['photosynthesis_rate'] ?? 0.05));
        }

        public function getSpecies() : string
        {
                return $this->species;
        }

        public function getTaxonomy() : Taxonomy
        {
                return $this->taxonomy;
        }

        public function getHeight() : float
        {
                return $this->height;
        }

        public function getGrowthStage() : string
        {
                return $this->growthStage;
        }

        public function absorbWater(float $amount) : void
        {
                if ($amount <= 0)
                {
                        return;
                }
                $this->waterReserve = min(1.0, $this->waterReserve + $amount);
        }

        public function absorbNutrients(float $amount) : void
        {
                if ($amount <= 0)
                {
                        return;
                }
                $this->nutrientReserve = min(1.0, $this->nutrientReserve + $amount);
        }

        public function photosynthesize(float $sunlight, float $deltaTime = 1.0) : void
        {
                if ($sunlight <= 0 || $deltaTime <= 0)
                {
                        return;
                }
                $gain = min(1.0, $sunlight * $this->photosynthesisRate * $deltaTime);
                $this->energyReserve = min(1.0, $this->energyReserve + $gain);
        }

        public function grow(float $deltaTime = 1.0) : void
        {
                if ($deltaTime <= 0)
                {
                        return;
                }
                $available = min($this->energyReserve, $this->waterReserve, $this->nutrientReserve);
                if ($available <= 0.0)
                {
                        $this->modifyHealth(-0.01 * $deltaTime);
                        return;
                }
                $growth = $available * $this->growthRate * $deltaTime;
                $this->height += $growth;
                $this->energyReserve = max(0.0, $this->energyReserve - $growth * 0.5);
                $this->waterReserve = max(0.0, $this->waterReserve - $growth * 0.3);
                $this->nutrientReserve = max(0.0, $this->nutrientReserve - $growth * 0.2);
                $this->updateGrowthStage();
                $this->improveResilience($growth * 0.01);
        }

        public function tick(float $deltaTime = 1.0) : void
        {
                parent::tick($deltaTime);
                if ($deltaTime <= 0)
                {
                        return;
                }
                $this->photosynthesize(0.6, $deltaTime);
                $this->grow($deltaTime);
                if ($this->waterReserve <= 0.01)
                {
                        $this->modifyHealth(-0.015 * $deltaTime);
                }
        }

        protected function updateGrowthStage() : void
        {
                if ($this->height < 0.2)
                {
                        $this->growthStage = 'seedling';
                        return;
                }
                if ($this->height < 1.0)
                {
                        $this->growthStage = 'juvenile';
                        return;
                }
                $this->growthStage = 'mature';
        }
}
?>
