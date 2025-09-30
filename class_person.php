<?php // 7.3.0-dev
class Person extends Life
{
        private $homeCountry;
        private $skills;
        private $profession;
        private $job;
        private $hunger;
        private $dailyFoodNeed;
        private $nutritionInterval;

        public function __construct (string $name, ?Country $homeCountry = null, array $traits = array())
        {
                parent::__construct($name, $traits);
                $this->homeCountry = null;
                $this->skills = array();
                $this->profession = null;
                $this->job = null;
                $this->hunger = 0.25;
                $this->dailyFoodNeed = max(0.1, floatval($traits['daily_food_need'] ?? 1.0));
                $this->nutritionInterval = max(1.0, floatval($traits['nutrition_interval'] ?? 86400.0));
                if ($homeCountry instanceof Country)
                {
                        $this->setHomeCountry($homeCountry);
                }
                $this->addSkill('survival', 0.2);
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

        public function setJob (?Job $job) : void
        {
                if ($this->job === $job) return;
                $this->job = $job;
                if ($job instanceof Job)
                {
                        $this->setProfession($job->getName());
                        foreach ($job->getRequiredSkills() as $skill => $level)
                        {
                                if (!isset($this->skills[$skill]))
                                {
                                        $this->skills[$skill] = new Skill($skill, max(0.0, $level * 0.5));
                                }
                        }
                        foreach ($job->getTrainingFocus() as $skill => $rate)
                        {
                                if (!isset($this->skills[$skill]))
                                {
                                        $this->skills[$skill] = new Skill($skill, 0.0);
                                }
                        }
                        return;
                }
                $this->setProfession(null);
        }

        public function getJob () : ?Job
        {
                return $this->job;
        }

        public function addSkill (string $name, float $level = 0.0) : void
        {
                if ($name === '') return;
                $key = Utility::cleanse_string($name);
                if ($key === '') return;
                if (!array_key_exists($key, $this->skills))
                {
                        $this->skills[$key] = new Skill($key, $level);
                        return;
                }
                $this->skills[$key]->reinforce($level, 0.5);
        }

        public function trainSkill (string $name, float $amount) : void
        {
                if ($amount <= 0) return;
                $key = Utility::cleanse_string($name);
                if ($key === '') return;
                if (!array_key_exists($key, $this->skills))
                {
                        $this->skills[$key] = new Skill($key, 0.0);
                }
                $this->skills[$key]->addExperience($amount);
        }

        public function getSkill (string $name) : ?float
        {
                $key = Utility::cleanse_string($name);
                if (!array_key_exists($key, $this->skills)) return null;
                return $this->skills[$key]->getLevel();
        }

        public function getSkillObject (string $name) : ?Skill
        {
                $key = Utility::cleanse_string($name);
                if (!array_key_exists($key, $this->skills)) return null;
                return $this->skills[$key];
        }

        public function getSkills () : array
        {
                $summary = array();
                foreach ($this->skills as $name => $skill)
                {
                        $summary[$name] = $skill->getLevel();
                }
                return $summary;
        }

        public function getHungerLevel () : float
        {
                return $this->hunger;
        }

        public function tick (float $deltaTime = 1.0) : void
        {
                parent::tick($deltaTime);
                if (!$this->isAlive())
                {
                        $this->skills = array();
                        $this->profession = null;
                        if ($this->job instanceof Job)
                        {
                                $job = $this->job;
                                $this->job = null;
                                $job->removeWorker($this);
                        }
                        return;
                }
                if ($deltaTime <= 0) return;

                foreach ($this->skills as $skill)
                {
                        $skill->tick($deltaTime);
                }

                $this->hunger += ($deltaTime / $this->nutritionInterval);
                $foodNeeded = $this->dailyFoodNeed * ($deltaTime / 86400.0);
                $foodReceived = 0.0;
                if ($this->homeCountry instanceof Country)
                {
                        $foodReceived = $this->homeCountry->provideFood($foodNeeded);
                }
                if ($foodReceived > 0)
                {
                        $this->satiate($foodReceived);
                }
                if ($foodReceived < $foodNeeded)
                {
                        $this->applyStarvation($foodNeeded - $foodReceived, $deltaTime);
                }
                else
                {
                        $this->recoverHealth($deltaTime);
                }
        }

        private function satiate (float $consumed) : void
        {
                if ($consumed <= 0) return;
                $dailyNeed = max(0.0001, $this->dailyFoodNeed);
                $reduction = $consumed / $dailyNeed;
                $this->hunger = max(0.0, $this->hunger - $reduction);
        }

        private function applyStarvation (float $deficit, float $deltaTime) : void
        {
                if ($deficit <= 0) return;
                $dailyNeed = max(0.0001, $this->dailyFoodNeed);
                $increase = $deficit / $dailyNeed;
                $this->hunger += $increase;
                $timeFactor = max(0.1, $deltaTime / 86400.0);
                $damage = $increase * 0.12 * $timeFactor;
                $this->modifyHealth(-$damage);
                if ($this->hunger >= 2.0)
                {
                        $this->setHealth(0.0);
                }
        }

        private function recoverHealth (float $deltaTime) : void
        {
                if ($this->hunger > 0.8) return;
                $timeFactor = $deltaTime / 86400.0;
                if ($timeFactor <= 0) return;
                $this->modifyHealth(0.02 * $timeFactor);
        }
}
?>
