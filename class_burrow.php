<?php // 7.3.0-dev
class Burrow extends Habitat
{
        protected $depth;
        protected $tunnelComplexity;

        public function __construct(string $name, array $properties = array())
        {
                parent::__construct($name, $properties);

                $this->depth = max(0.0, floatval($properties['depth'] ?? 1.0));
                $this->tunnelComplexity = max(0.0, floatval($properties['complexity'] ?? 1.0));

                if (!isset($properties['origin']))
                {
                        $this->setOriginType('natural');
                }

                if (!isset($properties['metabolism']))
                {
                        $this->setMetabolism(0.1);
                }
        }

        public function getDepth() : float
        {
                return $this->depth;
        }

        public function setDepth(float $value) : void
        {
                $this->depth = max(0.0, $value);
        }

        public function getComplexity() : float
        {
                return $this->tunnelComplexity;
        }

        public function setComplexity(float $value) : void
        {
                $this->tunnelComplexity = max(0.0, $value);
        }

        public function dig(float $effort) : void
        {
                if ($effort <= 0)
                {
                        return;
                }

                $this->setDepth($this->depth + ($effort * 0.5));
                $this->setComplexity($this->tunnelComplexity + ($effort * 0.2));
                $this->trainResilience(min(0.05, $effort * 0.01));
                $this->recordEvent('excavation', 'Burrow expanded with effort ' . $effort, $effort * 0.03);
        }

        public function collapse(float $severity) : void
        {
                if ($severity <= 0)
                {
                        return;
                }

                $severity = min(1.0, $severity);
                $this->degrade(0.05 * $severity);
                $this->weakenResilience(0.05 * $severity);
                $this->recordEvent('collapse', 'Burrow collapse severity ' . $severity, -1.0 * $severity);
        }

        public function mapTunnel() : array
        {
                return array(
                        'depth' => $this->depth,
                        'complexity' => $this->tunnelComplexity,
                        'resilience' => $this->getResilience(),
                        'origin' => $this->getOriginType(),
                );
        }

        public function tick(float $deltaTime = 1.0) : void
        {
                if ($deltaTime <= 0)
                {
                        return;
                }

                $pressure = ($this->depth * 0.01) + ($this->tunnelComplexity * 0.005) - ($this->getResilience() * 0.02);
                if ($pressure > 0)
                {
                        $this->degrade($pressure * $deltaTime);
                        if ($pressure > 0.05)
                        {
                                $this->recordEvent('structural_strain', 'Burrow walls strain under depth.', -0.5 * $pressure);
                        }
                }
                else
                {
                        $this->trainResilience(min(0.02, abs($pressure) * $deltaTime));
                }

                if ($this->depth > 20.0 && !$this->hasRecentEvent('deepening'))
                {
                        $this->recordEvent('deepening', 'Burrow reached depth ' . $this->depth, 0.2);
                }

                parent::tick($deltaTime);
        }
}
?>
