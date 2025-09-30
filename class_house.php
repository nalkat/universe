<?php // 7.3.0-dev
class House extends Structure
{
        protected $household;
        protected $comfortLevel;
        protected $maintenance;

        public function __construct(string $name, array $properties = array())
        {
                parent::__construct($name, $properties);
                $this->household = array();
                $this->comfortLevel = max(0.0, min(1.0, floatval($properties['comfort'] ?? 0.5)));
                $this->maintenance = max(0.0, min(1.0, floatval($properties['maintenance'] ?? 0.5)));
        }

        public function addResident($resident) : bool
        {
                $added = parent::addOccupant($resident);
                if ($added)
                {
                        $this->household[] = $resident;
                }
                return $added;
        }

        public function removeResident($resident) : bool
        {
                $removed = parent::removeOccupant($resident);
                if ($removed)
                {
                        foreach ($this->household as $index => $member)
                        {
                                if ($member === $resident)
                                {
                                        unset($this->household[$index]);
                                        $this->household = array_values($this->household);
                                        break;
                                }
                        }
                }
                return $removed;
        }

        public function getResidents() : array
        {
                return $this->household;
        }

        public function getComfortLevel() : float
        {
                return $this->comfortLevel;
        }

        public function improveComfort(float $amount) : void
        {
                if ($amount <= 0)
                {
                        return;
                }
                $this->comfortLevel = min(1.0, $this->comfortLevel + $amount);
        }

        public function maintain(float $effort) : void
        {
                if ($effort <= 0)
                {
                        return;
                }
                $this->maintenance = min(1.0, $this->maintenance + $effort);
                $this->repair($effort * 0.5);
        }

        public function tick(float $deltaTime = 1.0) : void
        {
                parent::tick($deltaTime);
                if ($deltaTime <= 0)
                {
                        return;
                }
                $wear = max(0.0, (0.001 * $deltaTime) - ($this->maintenance * 0.0005 * $deltaTime));
                $this->degrade($wear);
                $this->comfortLevel = max(0.0, $this->comfortLevel - ($wear * 5));
        }
}
?>
