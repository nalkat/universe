<?php // 7.3.0-dev
class Structure
{
        protected $name;
        protected $location;
        protected $materials;
        protected $capacity;
        protected $occupants;
        protected $condition;

        public function __construct(string $name, array $properties = array())
        {
                $this->name = $this->sanitize($name);
                $this->location = $properties['location'] ?? null;
                $this->materials = array();
                $this->capacity = isset($properties['capacity']) ? max(0, intval($properties['capacity'])) : 0;
                $this->occupants = array();
                $this->condition = max(0.0, min(1.0, floatval($properties['condition'] ?? 1.0)));

                if (!empty($properties['materials']) && is_array($properties['materials']))
                {
                        foreach ($properties['materials'] as $material)
                        {
                                $this->addMaterial($material);
                        }
                }
        }

        public function getName() : string
        {
                return $this->name;
        }

        public function getLocation()
        {
                return $this->location;
        }

        public function setLocation($location) : void
        {
                $this->location = $location;
        }

        public function getCapacity() : int
        {
                return $this->capacity;
        }

        public function setCapacity(int $capacity) : void
        {
                $this->capacity = max(0, $capacity);
                $this->trimOccupants();
        }

        public function getCondition() : float
        {
                return $this->condition;
        }

        public function degrade(float $amount) : void
        {
                if ($amount <= 0) return;
                $this->condition = max(0.0, $this->condition - $amount);
        }

        public function repair(float $amount) : void
        {
                if ($amount <= 0) return;
                $this->condition = min(1.0, $this->condition + $amount);
        }

        public function addMaterial($material) : void
        {
                if ($material === null) return;
                $this->materials[] = $material;
        }

        public function getMaterials() : array
        {
                return $this->materials;
        }

        public function addOccupant($occupant) : bool
        {
                if (!$this->hasSpace())
                {
                        return false;
                }
                if ($occupant === null)
                {
                        return false;
                }
                $this->occupants[] = $occupant;
                return true;
        }

        public function removeOccupant($occupant) : bool
        {
                foreach ($this->occupants as $index => $existing)
                {
                        if ($existing === $occupant)
                        {
                                unset($this->occupants[$index]);
                                $this->occupants = array_values($this->occupants);
                                return true;
                        }
                }
                return false;
        }

        public function getOccupants() : array
        {
                return $this->occupants;
        }

        public function hasSpace() : bool
        {
                if ($this->capacity === 0)
                {
                        return true;
                }
                return (count($this->occupants) < $this->capacity);
        }

        public function tick(float $deltaTime = 1.0) : void
        {
                if ($deltaTime <= 0)
                {
                        return;
                }
                if ($this->condition <= 0.2)
                {
                        $this->evictUnhealthyOccupants();
                }
        }

        protected function evictUnhealthyOccupants() : void
        {
                if (empty($this->occupants))
                {
                        return;
                }
                $filtered = array();
                foreach ($this->occupants as $occupant)
                {
                        if ($occupant instanceof Life)
                        {
                                if ($occupant->isAlive())
                                {
                                        $filtered[] = $occupant;
                                }
                        }
                        else
                        {
                                $filtered[] = $occupant;
                        }
                }
                $this->occupants = $filtered;
        }

        protected function trimOccupants() : void
        {
                if ($this->capacity === 0)
                {
                        return;
                }
                while (count($this->occupants) > $this->capacity)
                {
                        array_pop($this->occupants);
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
