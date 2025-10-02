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
        private $lifeExpectancyYears;
        private $senescenceStartYears;
        private $agingInterval;
        private $agingAccumulator;
        private $nutritionCadenceFactor;
        private $agingCadenceFactor;
        private $mortalityModel;
        private $resilienceExperience;
        private $calmAccumulator;
        private $backstory;
        private $relationships;
        private $chronicle;
        private $netWorth;
        private $coordinates;
        private $residenceCity;

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
                $this->nutritionCadenceFactor = max(0.1, $this->nutritionInterval / 86400.0);
                $this->lifeExpectancyYears = max(35.0, floatval($traits['life_expectancy_years'] ?? 82.0));
                $this->senescenceStartYears = max(25.0, min($this->lifeExpectancyYears, floatval($traits['senescence_years'] ?? 65.0)));
                $this->agingInterval = max(3600.0, floatval($traits['aging_interval'] ?? 86400.0));
                $this->agingAccumulator = 0.0;
                $this->agingCadenceFactor = max(0.05, $this->agingInterval / 86400.0);
                $this->mortalityModel = strtolower(trim(strval($traits['mortality'] ?? 'finite')));
                $this->resilienceExperience = 0.0;
                $this->calmAccumulator = 0.0;
                $this->backstory = '';
                $this->relationships = array();
                $this->chronicle = array();
                $this->netWorth = max(0.0, floatval($traits['net_worth'] ?? 0.0));
                $this->coordinates = null;
                $this->residenceCity = null;
                if ($this->mortalityModel === '')
                {
                        $this->mortalityModel = 'finite';
                }
                if (in_array($this->mortalityModel, array('immortal', 'ageless', 'eternal'), true))
                {
                        $this->setTrait('immortal', true);
                }
                if ($this->getResilience() <= 0.0)
                {
                        parent::improveResilience(0.05);
                }
                if ($homeCountry instanceof Country)
                {
                        $this->setHomeCountry($homeCountry);
                }
                else
                {
                        $this->synchronizeWithPlanetTimekeeping();
                }
                $this->addSkill('survival', 0.2);
        }

        public function setHomeCountry (?Country $country) : void
        {
                $this->homeCountry = $country;
                $this->synchronizeWithPlanetTimekeeping();
        }

        public function getHomeCountry () : ?Country
        {
                return $this->homeCountry;
        }

        public function synchronizeWithPlanetTimekeeping () : void
        {
                $dayLength = $this->resolveLocalDayLengthSeconds();
                $yearLength = $this->resolveLocalYearLengthSeconds();
                $this->nutritionInterval = max(600.0, $dayLength * max(0.1, $this->nutritionCadenceFactor));
                $this->agingInterval = max(3600.0, $dayLength * max(0.05, $this->agingCadenceFactor));
                $this->agingAccumulator = min($this->agingAccumulator, $this->agingInterval);
                $calmThreshold = $this->getCalmThresholdSeconds();
                if ($calmThreshold > 0.0 && $this->calmAccumulator > $calmThreshold)
                {
                        $this->calmAccumulator = fmod($this->calmAccumulator, $calmThreshold);
                }
                $this->setTrait('nutrition_interval', $this->nutritionInterval);
                $this->setTrait('aging_interval', $this->agingInterval);
                $this->setTrait('local_year_seconds', $yearLength);
        }

        private function getHomePlanet () : ?Planet
        {
                if ($this->homeCountry instanceof Country)
                {
                        return $this->homeCountry->getPlanet();
                }
                return null;
        }

        private function resolveLocalDayLengthSeconds () : float
        {
                $planet = $this->getHomePlanet();
                if ($planet instanceof Planet)
                {
                        return max(3600.0, $planet->getDayLengthSeconds(true));
                }
                return 86400.0;
        }

        private function resolveLocalYearLengthSeconds () : float
        {
                $planet = $this->getHomePlanet();
                if ($planet instanceof Planet)
                {
                        return max($this->resolveLocalDayLengthSeconds(), $planet->getYearLengthSeconds(true));
                }
                return 31557600.0;
        }

        private function convertUniversalToLocalSeconds (float $seconds) : float
        {
                $planet = $this->getHomePlanet();
                if ($planet instanceof Planet)
                {
                        return $planet->convertUniversalToLocalSeconds($seconds);
                }
                return $seconds;
        }

        private function getCalmThresholdSeconds () : float
        {
                return $this->resolveLocalDayLengthSeconds() * 7.0;
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

        public function setResidenceCity (?City $city) : void
        {
                if ($this->residenceCity === $city)
                {
                        return;
                }

                $this->residenceCity = $city;
                if ($city instanceof City)
                {
                        $coords = $city->getCoordinates();
                        $this->setCoordinates($this->scatterWithinCity($coords, $city->getRadius()));
                }
        }

        public function getResidenceCity () : ?City
        {
                return $this->residenceCity;
        }

        public function setCoordinates (?array $coordinates) : void
        {
                if ($coordinates === null)
                {
                        $this->coordinates = null;
                        return;
                }
                $latitude = floatval($coordinates['latitude'] ?? ($coordinates['lat'] ?? 0.0));
                $longitude = floatval($coordinates['longitude'] ?? ($coordinates['lon'] ?? 0.0));
                $this->coordinates = array('latitude' => $latitude, 'longitude' => $longitude);
                $this->setTrait('latitude', $latitude);
                $this->setTrait('longitude', $longitude);
        }

        public function getCoordinates () : ?array
        {
                if ($this->coordinates === null)
                {
                        return null;
                }
                return $this->coordinates;
        }

        public function setNetWorth (float $amount) : void
        {
                $this->netWorth = max(0.0, $amount);
                $this->setTrait('net_worth', $this->netWorth);
        }

        public function adjustNetWorth (float $delta) : void
        {
                if ($delta === 0.0)
                {
                        return;
                }
                $this->setNetWorth($this->netWorth + $delta);
        }

        public function getNetWorth () : float
        {
                return $this->netWorth;
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
                return $this->skills;
        }

        public function setBackstory (string $backstory) : void
        {
                $normalized = trim(strval($backstory));
                $this->backstory = $normalized;
                $this->setTrait('backstory', $normalized);
                if ($normalized !== '')
                {
                        $this->addChronicleEntry('backstory', $normalized);
                }
        }

        public function getBackstory () : string
        {
                return $this->backstory;
        }

        public function addRelationship (string $role, string $name) : void
        {
                $key = Utility::cleanse_string($role);
                if ($key === '') return;
                $this->relationships[$key] = strval($name);
                $this->addChronicleEntry('relationship', sprintf('%s acknowledged %s as %s.', $this->getName(), strval($name), $key));
        }

        public function getRelationships () : array
        {
                return $this->relationships;
        }

        public function addChronicleEntry (string $type, string $text, ?float $timestamp = null, array $participants = array()) : void
        {
                $normalized = trim(strval($text));
                if ($normalized === '') return;
                $participantList = array();
                if (!empty($participants) && is_array($participants))
                {
                        $participantList = array_map('strval', $participants);
                }
                $entry = array(
                        'type' => Utility::cleanse_string($type === '' ? 'event' : $type),
                        'text' => $normalized,
                        'timestamp' => ($timestamp === null) ? $this->age : floatval($timestamp),
                        'participants' => array_values(array_unique(array_merge(array($this->getName()), $participantList)))
                );
                $this->chronicle[] = $entry;
                if (count($this->chronicle) > 64)
                {
                        $this->chronicle = array_slice($this->chronicle, -64);
                }
        }

        public function getChronicle (?int $limit = null) : array
        {
                $entries = $this->chronicle;
                $entries[] = array(
                        'type' => 'status',
                        'text' => sprintf('%s is %.1f local years into their journey.', $this->getName(), $this->getAgeInYears()),
                        'timestamp' => $this->age,
                        'participants' => array($this->getName()),
                        'synthetic' => true
                );
                if ($this->isAlive() === false)
                {
                        $entries[] = array(
                                'type' => 'status',
                                'text' => sprintf('%s currently rests beyond the mortal coil.', $this->getName()),
                                'timestamp' => $this->age,
                                'participants' => array($this->getName()),
                                'synthetic' => true
                        );
                }
                if ($limit === null) return $entries;
                $limit = max(0, intval($limit));
                if ($limit === 0) return array();
                return array_slice($entries, -$limit);
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

                $localDelta = $this->convertUniversalToLocalSeconds($deltaTime);
                if ($localDelta <= 0) return;

                foreach ($this->skills as $skill)
                {
                        $skill->tick($localDelta);
                }

                $hungerDrift = ($localDelta / $this->nutritionInterval);
                $hungerDrift *= max(0.3, 1.0 - ($this->getResilience() * 0.25));
                $this->hunger += $hungerDrift;
                $dayLength = $this->resolveLocalDayLengthSeconds();
                $foodNeeded = $this->dailyFoodNeed * (($dayLength > 0.0) ? ($localDelta / $dayLength) : 0.0);
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
                        $this->recoverHealth($localDelta);
                }
                $this->agingAccumulator += $localDelta;
                if ($this->agingAccumulator >= $this->agingInterval)
                {
                        $this->applyAging($this->agingAccumulator);
                        $this->agingAccumulator = 0.0;
                }
                $this->processResilienceGrowth($localDelta);
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
                $resilience = $this->getResilience();
                $hungerMultiplier = max(0.25, 1.0 - ($resilience * 0.4));
                $this->hunger += $increase * $hungerMultiplier;
                $dayLength = $this->resolveLocalDayLengthSeconds();
                $timeFactor = ($dayLength > 0.0) ? max(0.1, $deltaTime / $dayLength) : 0.1;
                $damageMitigation = max(0.25, 1.0 - ($resilience * 0.65));
                $damage = $increase * 0.12 * $timeFactor * $damageMitigation;
                $this->modifyHealth(-$damage);
                if ($this->isAlive())
                {
                        $this->accumulateResilience($increase * $timeFactor);
                }
                if ($this->hunger >= 2.0)
                {
                        $this->kill('starvation');
                }
        }

        private function recoverHealth (float $deltaTime) : void
        {
                if ($this->hunger > 0.8) return;
                $dayLength = $this->resolveLocalDayLengthSeconds();
                $timeFactor = ($dayLength > 0.0) ? ($deltaTime / $dayLength) : 0.0;
                if ($timeFactor <= 0) return;
                $recoveryBoost = 1.0 + ($this->getResilience() * 0.6);
                $this->modifyHealth(0.02 * $timeFactor * $recoveryBoost);
        }

        private function applyAging (float $elapsedSeconds) : void
        {
                if ($this->isImmortal()) return;
                $yearLength = $this->resolveLocalYearLengthSeconds();
                $localAge = $this->convertUniversalToLocalSeconds($this->age);
                $ageYears = ($yearLength > 0.0) ? ($localAge / $yearLength) : 0.0;
                if ($ageYears < $this->senescenceStartYears) return;
                $span = max(1.0, $this->lifeExpectancyYears - $this->senescenceStartYears);
                $excess = max(0.0, $ageYears - $this->senescenceStartYears);
                $pressure = min(2.0, $excess / $span);
                $timeFactor = ($yearLength > 0.0) ? ($elapsedSeconds / $yearLength) : 0.0;
                $damage = max(0.0, $pressure * 0.05 * $timeFactor);
                if ($damage > 0.0)
                {
                        $this->modifyHealth(-$damage);
                }
                if ($ageYears >= ($this->lifeExpectancyYears * 1.35))
                {
                        $this->kill('senescence');
                }
        }

        public function kill (string $cause = 'unknown') : void
        {
                $wasAlive = $this->isAlive();
                parent::kill($cause);
                if ($wasAlive && !$this->isAlive() && $this->job instanceof Job)
                {
                        $job = $this->job;
                        $this->job = null;
                        $job->removeWorker($this);
                }
        }

        public function getMortalityModel () : string
        {
                return $this->mortalityModel;
        }

        public function getAgeInYears () : float
        {
                $yearLength = $this->resolveLocalYearLengthSeconds();
                if ($yearLength <= 0.0) return 0.0;
                $localAge = $this->convertUniversalToLocalSeconds($this->age);
                return $localAge / $yearLength;
        }

        public function getLifeExpectancyYears () : float
        {
                return $this->lifeExpectancyYears;
        }

        public function getLifeExpectancySeconds () : float
        {
                return $this->lifeExpectancyYears * $this->resolveLocalYearLengthSeconds();
        }

        public function getSenescenceStartYears () : float
        {
                return $this->senescenceStartYears;
        }

        public function getSenescenceStartSeconds () : float
        {
                return $this->senescenceStartYears * $this->resolveLocalYearLengthSeconds();
        }

        public function sufferTrauma (float $severity, string $cause) : void
        {
                $severity = max(0.0, floatval($severity));
                if ($severity <= 0) return;
                $resilience = $this->getResilience();
                $mitigation = max(0.2, 1.0 - ($resilience * 0.6));
                $damage = $severity * $mitigation;
                $this->modifyHealth(-$damage);
                if (!$this->isAlive())
                {
                        $this->kill($cause);
                        return;
                }
                $this->accumulateResilience($damage * 0.5);
        }

        private function accumulateResilience (float $pressure) : void
        {
                if ($pressure <= 0) return;
                $this->resilienceExperience = min(5.0, $this->resilienceExperience + $pressure);
                $this->calmAccumulator = 0.0;
        }

        private function processResilienceGrowth (float $deltaTime) : void
        {
                if ($deltaTime <= 0) return;
                if ($this->resilienceExperience > 0.0)
                {
                        $gain = min(0.05, $this->resilienceExperience * 0.04);
                        $this->resilienceExperience = max(0.0, $this->resilienceExperience - ($gain * 2.0));
                        if ($gain > 0)
                        {
                                parent::improveResilience($gain);
                        }
                        return;
                }
                $this->calmAccumulator += $deltaTime;
                $calmThreshold = $this->getCalmThresholdSeconds();
                if ($calmThreshold <= 0) return;
                if ($this->calmAccumulator >= $calmThreshold)
                {
                        $periods = floor($this->calmAccumulator / $calmThreshold);
                        if ($periods > 0)
                        {
                                $decay = min(0.05, $periods * 0.01);
                                if ($decay > 0)
                                {
                                        parent::reduceResilience($decay);
                                }
                                $this->calmAccumulator -= ($periods * $calmThreshold);
                        }
                }
        }

        private function scatterWithinCity (array $coordinates, float $radius) : array
        {
                $latitude = floatval($coordinates['latitude'] ?? 0.0);
                $longitude = floatval($coordinates['longitude'] ?? 0.0);
                $spread = max(0.1, $radius * 0.05);
                $latitude += mt_rand(-1000, 1000) / 1000.0 * $spread;
                $longitude += mt_rand(-1000, 1000) / 1000.0 * $spread;
                $latitude = max(-90.0, min(90.0, $latitude));
                if ($longitude < -180.0)
                {
                        $longitude += 360.0;
                }
                elseif ($longitude > 180.0)
                {
                        $longitude -= 360.0;
                }
                return array('latitude' => $latitude, 'longitude' => $longitude);
        }
}
?>
