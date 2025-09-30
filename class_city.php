<?php // 7.3.0-dev
class City extends Settlement
{
        protected $districts;
        protected $services;
        protected $housing;
        protected $infrastructure;

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

                if (!empty($properties['districts']) && is_array($properties['districts']))
                {
                        foreach ($properties['districts'] as $district => $profile)
                        {
                                $this->districts[$district] = $profile;
                        }
                }
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
                                        return true;
                                }
                        }
                }
                return false;
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
