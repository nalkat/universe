<?php // 7.3.0-dev
class Structure
{
        protected $name;
        protected $location;
        protected $materials;
        protected $capacity;
        protected $occupants;
        protected $condition;
        protected $originType;
        protected $creator;
        protected $traits;
        protected $eventLog;
        protected $resilience;

        public function __construct(string $name, array $properties = array())
        {
                $this->name = $this->sanitize($name);
                $this->location = $properties['location'] ?? null;
                $this->materials = array();
                $this->capacity = isset($properties['capacity']) ? max(0, intval($properties['capacity'])) : 0;
                $this->occupants = array();
                $this->condition = max(0.0, min(1.0, floatval($properties['condition'] ?? 1.0)));
                $this->originType = $this->normalizeOriginType($properties['origin'] ?? ($properties['originType'] ?? 'constructed'));
                $this->creator = $this->normalizeCreator($properties['creator'] ?? null);
                $this->traits = array();
                $this->eventLog = array();
                $this->resilience = max(0.0, min(1.0, floatval($properties['resilience'] ?? 0.5)));

                if (!empty($properties['materials']) && is_array($properties['materials']))
                {
                        foreach ($properties['materials'] as $material)
                        {
                                $this->addMaterial($material);
                        }
                }

                if (!empty($properties['traits']) && is_array($properties['traits']))
                {
                        foreach ($properties['traits'] as $trait)
                        {
                                $this->addTrait($trait);
                        }
                }

                if (!empty($properties['events']) && is_array($properties['events']))
                {
                        foreach ($properties['events'] as $event)
                        {
                                if (is_array($event))
                                {
                                        $this->recordEvent($event['type'] ?? 'unknown', $event['description'] ?? '', floatval($event['impact'] ?? 0.0));
                                }
                                elseif (is_string($event))
                                {
                                        $this->recordEvent($event, '', 0.0);
                                }
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

        public function getOriginType() : string
        {
                return $this->originType;
        }

        public function setOriginType(string $origin) : void
        {
                $this->originType = $this->normalizeOriginType($origin);
        }

        public function getCreator()
        {
                return $this->creator;
        }

        public function setCreator($creator) : void
        {
                $this->creator = $this->normalizeCreator($creator);
        }

        public function getTraits() : array
        {
                return $this->traits;
        }

        public function addTrait($trait) : void
        {
                if ($trait === null)
                {
                        return;
                }

                if ($trait instanceof Taxonomy)
                {
                        $this->traits[] = $trait;
                        return;
                }

                if (is_array($trait))
                {
                        $this->traits[] = $trait;
                        return;
                }

                $this->traits[] = $this->sanitize(strval($trait));
        }

        public function removeTrait($trait) : bool
        {
                foreach ($this->traits as $index => $existing)
                {
                        if ($existing === $trait)
                        {
                                unset($this->traits[$index]);
                                $this->traits = array_values($this->traits);
                                return true;
                        }

                        if (is_string($trait) && is_string($existing) && strtolower($existing) === strtolower($trait))
                        {
                                unset($this->traits[$index]);
                                $this->traits = array_values($this->traits);
                                return true;
                        }
                }

                return false;
        }

        public function getEventLog(int $limit = 0) : array
        {
                if ($limit <= 0)
                {
                        return $this->eventLog;
                }

                return array_slice($this->eventLog, -1 * $limit, $limit);
        }

        public function getResilience() : float
        {
                return $this->resilience;
        }

        public function trainResilience(float $amount) : void
        {
                if ($amount <= 0)
                {
                        return;
                }

                $this->resilience = min(1.0, $this->resilience + $amount);
        }

        public function weakenResilience(float $amount) : void
        {
                if ($amount <= 0)
                {
                        return;
                }

                $this->resilience = max(0.0, $this->resilience - $amount);
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

        public function recordEvent(string $type, string $description = '', float $impact = 0.0) : void
        {
                $normalizedType = $this->sanitize($type);
                $normalizedDescription = $description === '' ? '' : $this->sanitize($description);
                $impact = floatval($impact);

                $this->eventLog[] = array(
                        'timestamp'   => microtime(true),
                        'type'        => $normalizedType,
                        'description' => $normalizedDescription,
                        'impact'      => $impact,
                );

                $this->eventLog = $this->trimEventLog($this->eventLog);

                if ($impact > 0)
                {
                        $this->repair($impact * 0.1);
                        $this->trainResilience(min(0.25, $impact * 0.05));
                }
                elseif ($impact < 0)
                {
                        $this->degrade(abs($impact) * 0.05);
                        $this->weakenResilience(min(0.25, abs($impact) * 0.05));
                }
        }

        public function experiment(array $hypothesis = array()) : array
        {
                $pressure = isset($hypothesis['pressure']) ? floatval($hypothesis['pressure']) : 0.0;
                $innovation = $hypothesis['innovation'] ?? null;
                $risk = isset($hypothesis['risk']) ? max(0.0, min(1.0, floatval($hypothesis['risk']))) : 0.5;
                $baseline = 0.35 - ($this->resilience * 0.2) + ($pressure * 0.05);
                $baseline = max(0.05, min(0.95, $baseline));

                $roll = mt_rand(0, 1000) / 1000;
                $success = ($roll > $baseline);

                $changes = array();

                if ($success)
                {
                        $boost = max(0.01, (1.0 - $risk) * 0.1);
                        $this->trainResilience($boost);
                        $this->recordEvent('experiment_success', is_string($innovation) ? $innovation : 'Structure adapted successfully.', $boost * 2.0);
                        if (!empty($hypothesis['traits']) && is_array($hypothesis['traits']))
                        {
                                foreach ($hypothesis['traits'] as $trait)
                                {
                                        $this->addTrait($trait);
                                }
                                $changes['traits_added'] = $hypothesis['traits'];
                        }
                }
                else
                {
                        $penalty = max(0.01, $risk * 0.1 + $pressure * 0.05);
                        $this->weakenResilience($penalty);
                        $this->recordEvent('experiment_failure', is_string($innovation) ? $innovation : 'Experiment backfired.', -1.0 * $penalty * 2.0);
                        $changes['penalty'] = $penalty;
                }

                if (!empty($hypothesis['origin']))
                {
                        $this->setOriginType($hypothesis['origin']);
                        $changes['origin'] = $this->originType;
                }

                return array(
                        'success'   => $success,
                        'changes'   => $changes,
                        'roll'      => $roll,
                        'threshold' => $baseline,
                );
        }

        public function mutateStructure(array $mutations = array()) : void
        {
                if (isset($mutations['origin']))
                {
                        $this->setOriginType($mutations['origin']);
                }

                if (isset($mutations['creator']))
                {
                        $this->setCreator($mutations['creator']);
                }

                if (!empty($mutations['addTrait']))
                {
                        $this->addTrait($mutations['addTrait']);
                }

                if (!empty($mutations['removeTrait']))
                {
                        $this->removeTrait($mutations['removeTrait']);
                }

                if (!empty($mutations['resilienceBoost']))
                {
                        $this->trainResilience(floatval($mutations['resilienceBoost']));
                }

                if (!empty($mutations['resiliencePenalty']))
                {
                        $this->weakenResilience(floatval($mutations['resiliencePenalty']));
                }

                if (!empty($mutations['event']))
                {
                        $event = $mutations['event'];
                        if (is_array($event))
                        {
                                $this->recordEvent($event['type'] ?? 'mutation', $event['description'] ?? '', floatval($event['impact'] ?? 0.0));
                        }
                        else
                        {
                                $this->recordEvent('mutation', strval($event), 0.0);
                        }
                }
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

        public function tick(float $deltaTime = 1.0) : void
        {
                if ($deltaTime <= 0)
                {
                        return;
                }

                $decay = $this->calculateDecayRate($deltaTime);
                if ($decay > 0)
                {
                        $this->degrade($decay);
                }

                $this->stabilizeResilience($deltaTime);

                if ($this->condition <= 0.2)
                {
                        $this->evictUnhealthyOccupants();
                }

                if ($this->condition <= 0.05 && !$this->hasRecentEvent('collapse'))
                {
                        $this->recordEvent('collapse', 'Structure integrity critically low.', -0.5);
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

        protected function normalizeOriginType($origin) : string
        {
                if (is_string($origin))
                {
                        $normalized = strtolower(trim($origin));
                        $normalized = $normalized === '' ? 'unknown' : $normalized;
                        $allowed = array('natural', 'constructed', 'hybrid', 'emergent', 'unknown');
                        if (!in_array($normalized, $allowed, true))
                        {
                                return 'unknown';
                        }
                        return $normalized;
                }

                return 'unknown';
        }

        protected function normalizeCreator($creator)
        {
                if ($creator instanceof Taxonomy)
                {
                        return $creator;
                }

                if (is_array($creator))
                {
                        return new Taxonomy($creator);
                }

                if (is_string($creator) && $creator !== '')
                {
                        return $this->sanitize($creator);
                }

                return null;
        }

        protected function trimEventLog(array $log) : array
        {
                $maximum = 200;
                if (count($log) <= $maximum)
                {
                        return $log;
                }

                return array_slice($log, -1 * $maximum, $maximum);
        }

        protected function calculateDecayRate(float $deltaTime) : float
        {
                $base = 0.0;

                switch ($this->originType)
                {
                        case 'natural':
                                $base = 0.0005;
                                break;
                        case 'hybrid':
                                $base = 0.0008;
                                break;
                        case 'emergent':
                                $base = 0.0012;
                                break;
                        case 'constructed':
                                $base = 0.0015;
                                break;
                        default:
                                $base = 0.0010;
                                break;
                }

                $pressure = (1.0 - $this->resilience);
                $pressure = max(0.1, $pressure);

                return $base * $deltaTime * $pressure;
        }

        protected function stabilizeResilience(float $deltaTime) : void
        {
                if ($deltaTime <= 0)
                {
                        return;
                }

                if ($this->condition >= 0.8)
                {
                        $this->trainResilience(0.01 * $deltaTime);
                }
                elseif ($this->condition <= 0.3)
                {
                        $this->weakenResilience(0.02 * $deltaTime);
                }
        }

        protected function hasRecentEvent(string $type, int $window = 1) : bool
        {
                if ($window <= 0)
                {
                        $window = 1;
                }

                $type = strtolower($this->sanitize($type));
                $events = $this->getEventLog($window);
                foreach ($events as $event)
                {
                        if (!is_array($event))
                        {
                                continue;
                        }

                        if (!array_key_exists('type', $event))
                        {
                                continue;
                        }

                        if (strtolower(strval($event['type'])) === $type)
                        {
                                return true;
                        }
                }

                return false;
        }
}
?>
