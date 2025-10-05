<?php // 7.3.0-dev
class Job
{
        private $name;
        private $category;
        private $requiredSkills;
        private $resourceOutput;
        private $trainingFocus;
        private $capacity;
        private $priority;
        private $workers;

        public function __construct (string $name, array $profile = array())
        {
                $this->name = Utility::cleanse_string($name);
                $this->category = Utility::cleanse_string($profile['category'] ?? 'general');
                $this->requiredSkills = $this->sanitizeSkillRequirements($profile['requires'] ?? array());
                $this->resourceOutput = $this->sanitizeResourceOutputs($profile['produces'] ?? array());
                $this->trainingFocus = $this->sanitizeSkillRequirements($profile['training'] ?? array());
                $this->capacity = max(0, intval($profile['capacity'] ?? 0));
                $this->priority = intval($profile['priority'] ?? 1);
                $this->workers = array();
        }

        public function getName () : string
        {
                return $this->name;
        }

        public function getCategory () : string
        {
                return $this->category;
        }

        public function getRequiredSkills () : array
        {
                return $this->requiredSkills;
        }

        public function getResourceOutput () : array
        {
                return $this->resourceOutput;
        }

        public function getTrainingFocus () : array
        {
                return $this->trainingFocus;
        }

        public function getPriority () : int
        {
                return $this->priority;
        }

        public function getCapacity () : int
        {
                return $this->capacity;
        }

        public function getWorkers () : array
        {
                return array_values($this->workers);
        }

        public function hasWorker (Person $person) : bool
        {
                $id = spl_object_hash($person);
                return array_key_exists($id, $this->workers);
        }

        public function hasCapacity () : bool
        {
                if ($this->capacity <= 0) return true;
                return (count($this->workers) < $this->capacity);
        }

        public function addWorker (Person $person) : bool
        {
                if ($this->hasWorker($person)) return true;
                if (!$this->hasCapacity()) return false;
                $currentJob = $person->getJob();
                if ($currentJob instanceof Job && $currentJob !== $this)
                {
                        $currentJob->removeWorker($person);
                }
                $id = spl_object_hash($person);
                $this->workers[$id] = $person;
                $person->setJob($this);
                return true;
        }

        public function removeWorker (Person $person) : void
        {
                $id = spl_object_hash($person);
                if (!array_key_exists($id, $this->workers)) return;
                unset($this->workers[$id]);
                if ($person->getJob() === $this)
                {
                        $person->setJob(null);
                }
        }

        public function pruneInvalidWorkers () : void
        {
                foreach ($this->workers as $id => $worker)
                {
                        if (!($worker instanceof Person))
                        {
                                unset($this->workers[$id]);
                                continue;
                        }
                        if (!$worker->isAlive())
                        {
                                unset($this->workers[$id]);
                                continue;
                        }
                }
        }

        public function scoreCandidate (Person $person) : float
        {
                $productivity = $this->calculateProductivity($person);
                return ($this->priority * 2.0) + $productivity;
        }

        public function calculateProductivity (Person $person) : float
        {
                if (empty($this->requiredSkills)) return 1.0;
                $ratios = array();
                foreach ($this->requiredSkills as $skill => $required)
                {
                        if ($required <= 0)
                        {
                                $ratios[] = 1.0;
                                continue;
                        }
                        $level = $person->getSkill($skill) ?? 0.0;
                        $ratio = $level / $required;
                        $ratio = max(0.1, min(1.5, $ratio));
                        $ratios[] = $ratio;
                }
                if (empty($ratios)) return 1.0;
                $average = array_sum($ratios) / count($ratios);
                return max(0.1, min(1.2, $average));
        }

        public function perform (float $deltaTime, Country $country) : void
        {
                if ($deltaTime <= 0) return;
                if (empty($this->resourceOutput)) return;
                $dayLength = $country->getLocalDayLengthSeconds();
                $timeFactor = ($dayLength > 0.0) ? ($deltaTime / $dayLength) : 0.0;
                foreach ($this->workers as $worker)
                {
                        if (!($worker instanceof Person)) continue;
                        if (!$worker->isAlive()) continue;
                        $productivity = $this->calculateProductivity($worker);
                        foreach ($this->resourceOutput as $resource => $perDay)
                        {
                                if ($perDay <= 0) continue;
                                $country->addResource($resource, $perDay * $timeFactor * $productivity);
                        }
                        foreach ($this->trainingFocus as $skill => $rate)
                        {
                                if ($rate <= 0) continue;
                                $worker->trainSkill($skill, $rate * $timeFactor * $productivity);
                        }
                }
        }

        private function sanitizeSkillRequirements (array $skills) : array
        {
                $result = array();
                foreach ($skills as $name => $level)
                {
                        $cleanName = Utility::cleanse_string(strval($name));
                        if ($cleanName === '') continue;
                        $result[$cleanName] = max(0.0, min(1.0, floatval($level)));
                }
                return $result;
        }

        private function sanitizeResourceOutputs (array $outputs) : array
        {
                $result = array();
                foreach ($outputs as $name => $amount)
                {
                        $cleanName = Utility::cleanse_string(strval($name));
                        if ($cleanName === '') continue;
                        $result[$cleanName] = max(0.0, floatval($amount));
                }
                return $result;
        }
}
?>
