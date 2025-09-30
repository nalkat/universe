<?php // 7.3.0-dev
class Person extends Life
{
        private $homeCountry;
        private $skills;
        private $profession;

        public function __construct (string $name, ?Country $homeCountry = null, array $traits = array())
        {
                parent::__construct($name, $traits);
                $this->homeCountry = null;
                $this->skills = array();
                $this->profession = null;
                if ($homeCountry instanceof Country)
                {
                        $this->setHomeCountry($homeCountry);
                }
        }

        public function setHomeCountry (?Country $country) : void
        {
                $this->homeCountry = $country;
        }

        public function getHomeCountry () : ?Country
        {
                return $this->homeCountry;
        }

        public function setProfession (?string $profession) : void
        {
                if ($profession === null)
                {
                        $this->profession = null;
                        return;
                }
                $this->profession = Utility::cleanse_string($profession);
        }

        public function getProfession () : ?string
        {
                return $this->profession;
        }

        public function addSkill (string $name, float $level = 0.0) : void
        {
                if ($name === '') return;
                $key = Utility::cleanse_string($name);
                $this->skills[$key] = max(0.0, min(1.0, floatval($level)));
        }

        public function getSkill (string $name) : ?float
        {
                $key = Utility::cleanse_string($name);
                if (!array_key_exists($key, $this->skills)) return null;
                return $this->skills[$key];
        }

        public function getSkills () : array
        {
                return $this->skills;
        }

        public function tick (float $deltaTime = 1.0) : void
        {
                parent::tick($deltaTime);
                if (!$this->isAlive())
                {
                        $this->skills = array();
                        $this->profession = null;
                }
        }
}
?>
