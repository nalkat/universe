<?php // 7.3.0-dev
class Animal extends Life
{
        protected $species;
        protected $taxonomy;
        protected $diet;
        protected $hunger;
        protected $thirst;
        protected $energy;
        protected $stamina;
        protected $mass;
        protected $speed;
        protected $position;

        public function __construct(string $name, array $traits = array())
        {
                parent::__construct($name, $traits);
                $this->species = $traits['species'] ?? $this->getName();
                $this->taxonomy = ($traits['taxonomy'] ?? null) instanceof Taxonomy
                        ? $traits['taxonomy']
                        : new Taxonomy(is_array($traits['taxonomy'] ?? null) ? $traits['taxonomy'] : array());
                $this->diet = strtolower(trim($traits['diet'] ?? 'omnivore'));
                $this->hunger = max(0.0, min(1.0, floatval($traits['hunger'] ?? 0.2)));
                $this->thirst = max(0.0, min(1.0, floatval($traits['thirst'] ?? 0.2)));
                $this->energy = max(0.0, min(1.0, floatval($traits['energy'] ?? 0.8)));
                $this->stamina = max(0.0, min(1.0, floatval($traits['stamina'] ?? 0.5)));
                $this->mass = max(0.0, floatval($traits['mass'] ?? 70.0));
                $this->speed = max(0.0, floatval($traits['speed'] ?? 1.0));
                $this->position = isset($traits['position']) && is_array($traits['position'])
                        ? $traits['position']
                        : array(0.0, 0.0, 0.0);
        }

        public function getSpecies() : string
        {
                return $this->species;
        }

        public function setSpecies(string $species) : void
        {
                $this->species = $this->sanitize($species);
        }

        public function getTaxonomy() : Taxonomy
        {
                return $this->taxonomy;
        }

        public function setTaxonomy(Taxonomy $taxonomy) : void
        {
                $this->taxonomy = $taxonomy;
        }

        public function getDiet() : string
        {
                return $this->diet;
        }

        public function setDiet(string $diet) : void
        {
                $this->diet = strtolower(trim($diet));
        }

        public function getHunger() : float
        {
                return $this->hunger;
        }

        public function getThirst() : float
        {
                return $this->thirst;
        }

        public function getEnergy() : float
        {
                return $this->energy;
        }

        public function getMass() : float
        {
                return $this->mass;
        }

        public function getSpeed() : float
        {
                return $this->speed;
        }

        public function feed(float $nutrition) : void
        {
                if ($nutrition <= 0)
                {
                        return;
                }
                $this->hunger = max(0.0, $this->hunger - $nutrition);
                $this->energy = min(1.0, $this->energy + ($nutrition * 0.5));
        }

        public function drink(float $hydration) : void
        {
                if ($hydration <= 0)
                {
                        return;
                }
                $this->thirst = max(0.0, $this->thirst - $hydration);
        }

        public function move(array $delta, float $effort = 1.0) : void
        {
                if ($this->energy <= 0.0)
                {
                        return;
                }
                $distance = sqrt(pow($delta[0] ?? 0.0, 2) + pow($delta[1] ?? 0.0, 2) + pow($delta[2] ?? 0.0, 2));
                $cost = $distance * 0.01 * $effort;
                $this->energy = max(0.0, $this->energy - $cost);
                $this->stamina = max(0.0, $this->stamina - ($cost * 0.5));
                $this->position[0] = ($this->position[0] ?? 0.0) + ($delta[0] ?? 0.0);
                $this->position[1] = ($this->position[1] ?? 0.0) + ($delta[1] ?? 0.0);
                $this->position[2] = ($this->position[2] ?? 0.0) + ($delta[2] ?? 0.0);
        }

        public function rest(float $duration) : void
        {
                if ($duration <= 0)
                {
                        return;
                }
                $recovery = min(1.0, $duration * 0.05);
                $this->energy = min(1.0, $this->energy + $recovery);
                $this->stamina = min(1.0, $this->stamina + $recovery * 0.8);
        }

        public function tick(float $deltaTime = 1.0) : void
        {
                parent::tick($deltaTime);
                if ($deltaTime <= 0)
                {
                        return;
                }
                $this->hunger = min(1.0, $this->hunger + 0.01 * $deltaTime);
                $this->thirst = min(1.0, $this->thirst + 0.015 * $deltaTime);
                $this->energy = max(0.0, $this->energy - 0.005 * $deltaTime);

                if ($this->hunger >= 1.0 || $this->thirst >= 1.0)
                {
                        $this->modifyHealth(-0.02 * $deltaTime);
                        $this->reduceResilience(0.01 * $deltaTime);
                }
                else
                {
                        $this->improveResilience(0.005 * $deltaTime);
                }
        }

        protected function sanitize(string $value) : string
        {
                if (class_exists('Utility'))
                {
                        return Utility::cleanse_string($value);
                }
                return trim($value);
        }
}
?>
