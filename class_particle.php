<?php // 7.3.0-dev
class Particle
{
        public const SPEED_OF_LIGHT = 299792458.0;

        protected $name;
        protected $symbol;
        protected $category;
        protected $mass; // kilograms
        protected $charge; // coulombs
        protected $spin;
        protected $generation;
        protected $stable;
        protected $meanLifetime; // seconds

        public function __construct(string $name, array $properties = array())
        {
                $this->name = $this->sanitize($name);
                $this->symbol = $this->sanitize($properties['symbol'] ?? '');
                $this->category = $this->sanitize($properties['category'] ?? 'unknown');
                $this->mass = max(0.0, floatval($properties['mass'] ?? 0.0));
                $this->charge = floatval($properties['charge'] ?? 0.0);
                $this->spin = floatval($properties['spin'] ?? 0.0);
                $this->generation = intval($properties['generation'] ?? 1);
                $this->stable = boolval($properties['stable'] ?? true);
                $this->meanLifetime = max(0.0, floatval($properties['mean_lifetime'] ?? 0.0));
        }

        public function getName() : string
        {
                return $this->name;
        }

        public function getSymbol() : string
        {
                return $this->symbol;
        }

        public function getCategory() : string
        {
                return $this->category;
        }

        public function getMass() : float
        {
                return $this->mass;
        }

        public function getCharge() : float
        {
                return $this->charge;
        }

        public function getSpin() : float
        {
                return $this->spin;
        }

        public function getGeneration() : int
        {
                return $this->generation;
        }

        public function isStable() : bool
        {
                if ($this->stable)
                {
                        return true;
                }
                return ($this->meanLifetime > 0.0 && $this->meanLifetime >= 1e17);
        }

        public function getMeanLifetime() : float
        {
                return $this->meanLifetime;
        }

        public function setMeanLifetime(float $lifetime) : void
        {
                $this->meanLifetime = max(0.0, $lifetime);
        }

        public function getRestEnergy() : float
        {
                return $this->mass * self::SPEED_OF_LIGHT * self::SPEED_OF_LIGHT;
        }

        public function describe() : array
        {
                return array(
                        'name' => $this->name,
                        'symbol' => $this->symbol,
                        'category' => $this->category,
                        'mass' => $this->mass,
                        'charge' => $this->charge,
                        'spin' => $this->spin,
                        'generation' => $this->generation,
                        'stable' => $this->isStable(),
                        'mean_lifetime' => $this->meanLifetime,
                        'rest_energy' => $this->getRestEnergy(),
                );
        }

        protected function sanitize($value) : string
        {
                $string = strval($value);
                if (class_exists('Utility'))
                {
                        return Utility::cleanse_string($string);
                }
                return trim($string);
        }
}
?>
