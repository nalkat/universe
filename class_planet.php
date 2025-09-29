<?php // 7.3.0-dev

class Planet extends SystemObject
{
        private $atmosphere;
        private $habitable;
        private $orbit;

        public function __construct (string $name, float $mass = 0.0, float $radius = 0.0, ?array $position = null, ?array $velocity = null)
        {
                parent::__construct($name, $mass, $radius, $position, $velocity);
                $this->setType('Planet');
                $this->atmosphere = array();
                $this->habitable = false;
                $this->orbit = null;
        }

        public function setAtmosphere (array $composition) : void
        {
                $this->atmosphere = $composition;
        }

        public function getAtmosphere () : array
        {
                return $this->atmosphere;
        }

        public function setHabitable (bool $habitable) : void
        {
                $this->habitable = $habitable;
        }

        public function isHabitable () : bool
        {
                return $this->habitable;
        }

        public function getOrbit () : ?array
        {
                return $this->orbit;
        }

        public function setOrbit (SystemObject $focus, float $semiMajorAxis, float $period, float $eccentricity = 0.0, float $phase = 0.0) : bool
        {
                if ($semiMajorAxis <= 0)
                {
                        Utility::write("Semi-major axis must be positive", LOG_WARNING, L_CONSOLE);
                        return false;
                }
                if ($period <= 0)
                {
                        Utility::write("Orbital period must be positive", LOG_WARNING, L_CONSOLE);
                        return false;
                }
                if ($eccentricity < 0) $eccentricity = 0.0;
                if ($eccentricity >= 1) $eccentricity = 0.999999;
                $this->orbit = array(
                        'focus' => $focus,
                        'semi_major_axis' => floatval($semiMajorAxis),
                        'period' => floatval($period),
                        'eccentricity' => floatval($eccentricity),
                        'angle' => floatval($phase)
                );
                $this->updateOrbit(0.0);
                return true;
        }

        public function tick (float $deltaTime = 1.0) : void
        {
                $useAnalyticOrbit = ($this->orbit !== null) && ($deltaTime > 0);
                if ($useAnalyticOrbit)
                {
                        $system = $this->getParentSystem();
                        if ($system instanceof System)
                        {
                                $useAnalyticOrbit = ($system->getPropagationMode() === System::PROPAGATION_ANALYTIC);
                        }
                }
                if ($useAnalyticOrbit)
                {
                        $this->updateOrbit($deltaTime);
                        $this->age += $deltaTime;
                        return;
                }
                parent::tick($deltaTime);
        }

        private function updateOrbit (float $deltaTime) : void
        {
                if ($this->orbit === null) return;
                $period = $this->orbit['period'];
                if ($period <= 0) return;
                $twoPi = 2 * pi();
                if ($deltaTime > 0)
                {
                        $this->orbit['angle'] += $twoPi * ($deltaTime / $period);
                }
                $this->orbit['angle'] = fmod($this->orbit['angle'], $twoPi);
                $focus = $this->orbit['focus'];
                $focusPosition = $focus->getPosition();
                $focusVelocity = $focus->getVelocity();
                $ecc = $this->orbit['eccentricity'];
                $semi = $this->orbit['semi_major_axis'];
                $angle = $this->orbit['angle'];
                $radius = $semi;
                if ($ecc > 0)
                {
                        $radius = ($semi * (1 - ($ecc * $ecc))) / (1 + ($ecc * cos($angle)));
                }
                $this->position['x'] = $focusPosition['x'] + ($radius * cos($angle));
                $this->position['y'] = $focusPosition['y'] + ($radius * sin($angle));
                $this->position['z'] = $focusPosition['z'];
                $angularSpeed = $twoPi / $period;
                $this->velocity['x'] = $focusVelocity['x'] - ($radius * $angularSpeed * sin($angle));
                $this->velocity['y'] = $focusVelocity['y'] + ($radius * $angularSpeed * cos($angle));
                $this->velocity['z'] = $focusVelocity['z'];
        }
}
?>
