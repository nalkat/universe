<?php // 7.3.0-dev
class Nest extends Habitat
{
        protected $resourceStores;
        protected $ventilation;

        public function __construct(string $name, array $properties = array())
        {
                parent::__construct($name, $properties);

                $this->resourceStores = max(0.0, floatval($properties['resourceStores'] ?? $properties['stores'] ?? 0.0));
                $this->ventilation = max(0.0, min(1.0, floatval($properties['ventilation'] ?? 0.5)));

                if (!isset($properties['origin']))
                {
                        $this->setOriginType('natural');
                }

                if (!isset($properties['metabolism']))
                {
                        $this->setMetabolism(0.2);
                }
        }

        public function getResourceStores() : float
        {
                return $this->resourceStores;
        }

        public function gatherResources(float $amount) : void
        {
                if ($amount <= 0)
                {
                        return;
                }

                $this->resourceStores += $amount;
                $this->trainResilience(min(0.05, $amount * 0.01));
                $this->recordEvent('gathering', 'Nest gathered resources: ' . $amount, $amount * 0.05);
        }

        public function consumeResources(float $amount) : void
        {
                if ($amount <= 0)
                {
                        return;
                }

                $this->resourceStores -= $amount;
                if ($this->resourceStores < 0)
                {
                        $deficit = abs($this->resourceStores);
                        $this->resourceStores = 0.0;
                        $this->recordEvent('resource_shortage', 'Nest depleted stores by ' . $deficit, -0.3 * (1.0 + $deficit));
                        $this->degrade(0.01 * (1.0 + $deficit));
                        $this->weakenResilience(0.02 * (1.0 + min(1.0, $deficit)));
                }
        }

        public function getVentilation() : float
        {
                return $this->ventilation;
        }

        public function setVentilation(float $value) : void
        {
                $this->ventilation = max(0.0, min(1.0, $value));
        }

        public function improveVentilation(float $delta) : void
        {
                if ($delta === 0.0)
                {
                        return;
                }

                $this->setVentilation($this->ventilation + $delta);

                if ($delta > 0)
                {
                        $this->trainResilience(min(0.05, $delta * 0.1));
                        $this->recordEvent('ventilation_upgrade', 'Ventilation improved by ' . $delta, $delta * 0.2);
                }
                else
                {
                        $this->weakenResilience(min(0.05, abs($delta) * 0.1));
                        $this->recordEvent('ventilation_loss', 'Ventilation reduced by ' . abs($delta), -0.2 * abs($delta));
                }
        }

        public function tick(float $deltaTime = 1.0) : void
        {
                if ($deltaTime <= 0)
                {
                        return;
                }

                $occupantLoad = count($this->getOccupants()) * 0.05;
                $consumption = ($this->getMetabolism() + $occupantLoad) * $deltaTime;
                if ($consumption > 0)
                {
                        $this->consumeResources($consumption);
                }

                if ($this->ventilation < 0.3)
                {
                        $penalty = (0.3 - $this->ventilation) * 0.01 * $deltaTime;
                        $this->degrade($penalty);
                        $this->recordEvent('ventilation_pressure', 'Poor ventilation stresses the nest.', -0.1 * $penalty * 100);
                }
                elseif ($this->ventilation > 0.7)
                {
                        $bonus = ($this->ventilation - 0.7) * 0.005 * $deltaTime;
                        $this->repair($bonus);
                        $this->trainResilience($bonus * 2.0);
                }

                parent::tick($deltaTime);
        }
}
?>
