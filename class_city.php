<?php // 7.3.0-dev
class City extends Settlement
{
        protected $districts;
        protected $services;
        protected $housing;
        protected $infrastructure;
        protected $residents;
        protected $radius;

        public function __construct(string $name, array $properties = array())
        {
                parent::__construct($name, $properties);
                $this->districts = array();
                $this->services = array(
                        'healthcare' => 0.3,
                        'education' => 0.3,
                        'security' => 0.3,
                        'transport' => 0.3,
                );
                $this->housing = array();
                $this->infrastructure = $properties['infrastructure'] ?? array(
                        'roads' => 0.5,
                        'utilities' => 0.5,
                        'sanitation' => 0.5,
                );
                $this->residents = array();
                $this->radius = floatval($properties['radius'] ?? mt_rand(20, 140) / 10.0);

                if (!empty($properties['districts']) && is_array($properties['districts']))
                {
                        foreach ($properties['districts'] as $district => $profile)
                        {
                                $this->districts[$district] = $profile;
                        }
                }

                if (empty($this->location) || !is_array($this->location))
                {
                        $this->location = array(
                                'latitude' => mt_rand(-8500, 8500) / 100.0,
                                'longitude' => mt_rand(-18000, 18000) / 100.0
                        );
                }

                $this->recordEvent('foundation', 'City established as a civic nexus.', 0.2);
        }

        public function getRadius() : float
        {
                return max(1.0, $this->radius);
        }

        public function setRadius(float $radius) : void
        {
                $this->radius = max(1.0, $radius);
        }

        public function getCoordinates() : array
        {
                if (!is_array($this->location))
                {
                        return array('latitude' => 0.0, 'longitude' => 0.0);
                }

                return array(
                        'latitude' => floatval($this->location['latitude'] ?? ($this->location['lat'] ?? 0.0)),
                        'longitude' => floatval($this->location['longitude'] ?? ($this->location['lon'] ?? 0.0))
                );
        }

        public function addDistrict(string $name, array $profile = array()) : void
        {
                $this->districts[$name] = $profile;
        }

        public function getDistricts() : array
        {
                return $this->districts;
        }

        public function setServiceLevel(string $service, float $value) : void
        {
                $key = strtolower(trim($service));
                $this->services[$key] = max(0.0, min(1.0, $value));
        }

        public function getServiceLevel(string $service) : float
        {
                $key = strtolower(trim($service));
                return $this->services[$key] ?? 0.0;
        }

        public function addHouse(House $house) : void
        {
                $this->housing[$house->getName()] = $house;
                $this->registerStructure($house);
        }

        public function getHouses() : array
        {
                return $this->housing;
        }

        public function assignResident(Life $life) : bool
        {
                foreach ($this->housing as $house)
                {
                        if ($house->hasSpace())
                        {
                                if ($house->addResident($life))
                                {
                                        $this->adjustPopulation(1);
                                        $this->residents[] = $life;
                                        $this->recordEvent('arrival', $life->getName() . ' settled in ' . $this->name . '.', 0.05);
                                        return true;
                                }
                        }
                }
                return false;
        }

        public function addResident(Life $life) : void
        {
                $this->residents[] = $life;
                $this->adjustPopulation(1);
                $this->recordEvent('arrival', $life->getName() . ' made their home in ' . $this->name . '.', 0.05);
        }

        public function removeResident(Life $life) : void
        {
                foreach ($this->residents as $index => $resident)
                {
                        if ($resident === $life)
                        {
                                unset($this->residents[$index]);
                                $this->residents = array_values($this->residents);
                                $this->adjustPopulation(-1);
                                $this->recordEvent('departure', $life->getName() . ' departed ' . $this->name . '.', -0.02);
                                break;
                        }
                }
        }

        public function getResidents() : array
        {
                return $this->residents;
        }

        public function getChronicle(int $limit = 16) : array
        {
                $events = $this->getEventLog($limit);
                $chronicle = array();
                foreach ($events as $event)
                {
                        if (!is_array($event)) continue;
                        $chronicle[] = array(
                                'type' => $event['type'] ?? 'event',
                                'text' => $event['description'] ?? '',
                                'timestamp' => $event['timestamp'] ?? microtime(true),
                                'impact' => $event['impact'] ?? 0.0
                        );
                }
                return $chronicle;
        }

        public function tick(float $deltaTime = 1.0) : void
        {
                parent::tick($deltaTime);
                if ($deltaTime <= 0)
                {
                        return;
                }
                foreach ($this->housing as $house)
                {
                        $house->tick($deltaTime);
                }
                $infrastructureHealth = array_sum($this->infrastructure) / max(1, count($this->infrastructure));
                $serviceModifier = max(0.1, $infrastructureHealth);
                foreach ($this->services as $service => $value)
                {
                        $this->services[$service] = max(0.0, min(1.0, $value + (0.001 * $deltaTime * ($serviceModifier - 0.5))));
                }
        }
}
?>
