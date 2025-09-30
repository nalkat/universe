<?php // 7.3.0-dev
class Life
{
        protected $name;
        protected $age;
        protected $health;
        protected $traits;
        protected $deathReason;
        protected $resilience;

        public function __construct (string $name, array $traits = array())
        {
                $this->name = Utility::cleanse_string($name);
                $this->age = floatval(0);
                $this->health = floatval(1.0);
                $this->traits = array();
                $this->deathReason = null;
                $this->resilience = 0.0;
                foreach ($traits as $key => $value)
                {
                        $this->setTrait($key, $value);
                }
                if (array_key_exists('resilience', $traits))
                {
                        $this->resilience = $this->normalizeResilience($traits['resilience']);
                }
                $this->traits['resilience'] = $this->resilience;
        }

        public function getName () : string
        {
                return $this->name;
        }

        public function getAge () : float
        {
                return $this->age;
        }

        public function getHealth () : float
        {
                return $this->health;
        }

        public function setHealth (float $health, ?string $cause = null) : void
        {
                $this->health = max(0.0, min(1.0, floatval($health)));
                if ($this->health <= 0.0)
                {
                        if ($cause !== null)
                        {
                                $this->deathReason = Utility::cleanse_string($cause);
                        }
                        elseif ($this->deathReason === null)
                        {
                                $this->deathReason = 'health_depleted';
                        }
                        $this->traits['death_cause'] = $this->deathReason;
                }
                else
                {
                        $this->deathReason = null;
                }
        }

        public function modifyHealth (float $delta) : void
        {
                $this->setHealth($this->health + $delta);
        }

        public function tick (float $deltaTime = 1.0) : void
        {
                if ($deltaTime <= 0) return;
                $this->age += $deltaTime;
        }

        public function isAlive () : bool
        {
                return ($this->health > 0.0);
        }

        public function kill (string $cause = 'unknown') : void
        {
                $this->setHealth(0.0, $cause);
        }

        public function getDeathReason () : ?string
        {
                return $this->deathReason;
        }

        public function getTraits () : array
        {
                return $this->traits;
        }

        public function getTrait (string $name)
        {
                $key = Utility::cleanse_string($name);
                if (!array_key_exists($key, $this->traits)) return null;
                return $this->traits[$key];
        }

        public function setTrait (string $name, $value) : void
        {
                if ($name === '') return;
                $key = Utility::cleanse_string($name);
                if ($key === 'resilience')
                {
                        $this->resilience = $this->normalizeResilience($value);
                        $this->traits[$key] = $this->resilience;
                        return;
                }
                $this->traits[$key] = $value;
        }

        public function isImmortal () : bool
        {
                $value = $this->getTrait('immortal');
                if (is_bool($value))
                {
                        return $value;
                }
                if (is_numeric($value))
                {
                        return (floatval($value) > 0.0);
                }
                if (is_string($value))
                {
                        $normalized = strtolower(trim($value));
                        return in_array($normalized, array('1', 'true', 'yes', 'on', 'immortal', 'ageless'), true);
                }
                return false;
        }

        public function getResilience () : float
        {
                return $this->resilience;
        }

        public function improveResilience (float $delta) : void
        {
                if ($delta <= 0) return;
                $this->resilience = max(0.0, min(1.0, $this->resilience + $delta));
                $this->traits['resilience'] = $this->resilience;
        }

        public function reduceResilience (float $delta) : void
        {
                if ($delta <= 0) return;
                $this->resilience = max(0.0, $this->resilience - $delta);
                $this->traits['resilience'] = $this->resilience;
        }

        protected function normalizeResilience ($value) : float
        {
                if (is_bool($value))
                {
                        return $value ? 1.0 : 0.0;
                }
                if (is_numeric($value))
                {
                        return max(0.0, min(1.0, floatval($value)));
                }
                if (is_string($value))
                {
                        $normalized = strtolower(trim($value));
                        if ($normalized === '') return 0.0;
                        if (in_array($normalized, array('high', 'strong'), true))
                        {
                                return 0.75;
                        }
                        if (in_array($normalized, array('medium', 'moderate'), true))
                        {
                                return 0.5;
                        }
                        if (in_array($normalized, array('low', 'weak'), true))
                        {
                                return 0.2;
                        }
                        if (in_array($normalized, array('true', 'yes', 'on'), true))
                        {
                                return 1.0;
                        }
                        if (in_array($normalized, array('false', 'no', 'off'), true))
                        {
                                return 0.0;
                        }
                        if (is_numeric($normalized))
                        {
                                return max(0.0, min(1.0, floatval($normalized)));
                        }
                }
                return 0.0;
        }
}
?>
