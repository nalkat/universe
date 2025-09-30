<?php // 7.3.0-dev
class Life
{
        protected $name;
        protected $age;
        protected $health;
        protected $traits;

        public function __construct (string $name, array $traits = array())
        {
                $this->name = Utility::cleanse_string($name);
                $this->age = floatval(0);
                $this->health = floatval(1.0);
                $this->traits = array();
                foreach ($traits as $key => $value)
                {
                        $this->setTrait($key, $value);
                }
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

        public function setHealth (float $health) : void
        {
                $this->health = max(0.0, min(1.0, floatval($health)));
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
                $this->traits[$key] = $value;
        }
}
?>
