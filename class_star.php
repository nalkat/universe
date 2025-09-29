<?php // 7.3.0-dev

class Star extends SystemObject
{
        public const SOLAR_MASS = 1.98847E30;
        public const SOLAR_LUMINOSITY = 3.828E26;

        private $luminosity;
        private $temperature;
        private $spectralClass;
        private $stage;
        private $mainSequenceLifetime;

        public function __construct (string $name, float $mass = 0.0, float $radius = 0.0, float $luminosity = 0.0, float $temperature = 0.0, string $spectralClass = 'G2V', ?array $position = null, ?array $velocity = null)
        {
                parent::__construct($name, $mass, $radius, $position, $velocity);
                $this->setType('Star');
                $this->luminosity = floatval(max($luminosity, 0));
                $this->temperature = floatval(max($temperature, 0));
                $this->spectralClass = Utility::cleanse_string($spectralClass);
                $this->mainSequenceLifetime = $this->estimateMainSequenceLifetime();
                $this->stage = ($this->mainSequenceLifetime > 0) ? 'main-sequence' : 'unknown';
        }

        public function setMass (float $mass) : void
        {
                parent::setMass($mass);
                $this->mainSequenceLifetime = $this->estimateMainSequenceLifetime();
        }

        public function getLuminosity () : float
        {
                return $this->luminosity;
        }

        public function setLuminosity (float $luminosity) : void
        {
                $this->luminosity = floatval(max($luminosity, 0));
        }

        public function getTemperature () : float
        {
                return $this->temperature;
        }

        public function setTemperature (float $temperature) : void
        {
                $this->temperature = floatval(max($temperature, 0));
        }

        public function getSpectralClass () : string
        {
                return $this->spectralClass;
        }

        public function setSpectralClass (string $spectralClass) : void
        {
                $this->spectralClass = Utility::cleanse_string($spectralClass);
        }

        public function getStage () : string
        {
                return $this->stage;
        }

        public function getMainSequenceLifetime () : float
        {
                return $this->mainSequenceLifetime;
        }

        public function emitEnergy (float $deltaTime = 1.0) : float
        {
                if ($deltaTime <= 0) return 0.0;
                return $this->luminosity * $deltaTime;
        }

        public function getHabitableZone () : array
        {
                if ($this->luminosity <= 0)
                {
                        return array('inner' => 0.0, 'outer' => 0.0);
                }
                $luminosityRatio = $this->luminosity / self::SOLAR_LUMINOSITY;
                $inner = sqrt($luminosityRatio / 1.1);
                $outer = sqrt($luminosityRatio / 0.53);
                return array('inner' => $inner, 'outer' => $outer);
        }

        public function tick (float $deltaTime = 1.0) : void
        {
                parent::tick($deltaTime);
                if ($deltaTime > 0)
                {
                        $this->updateStage();
                }
        }

        private function estimateMainSequenceLifetime () : float
        {
                if ($this->mass <= 0) return 0.0;
                $massRatio = $this->mass / self::SOLAR_MASS;
                if ($massRatio <= 0) return 0.0;
                $years = 1.0E10 * pow($massRatio, -2.5);
                $seconds = $years * 365.25 * 24 * 3600;
                return $seconds;
        }

        private function updateStage () : void
        {
                $previous = $this->stage;
                if ($this->mainSequenceLifetime <= 0)
                {
                        $this->stage = 'unknown';
                        return;
                }
                $fraction = $this->age / $this->mainSequenceLifetime;
                if ($fraction < 0.7)
                {
                        $this->stage = 'main-sequence';
                }
                elseif ($fraction < 1.0)
                {
                        $this->stage = 'subgiant';
                }
                else
                {
                        $this->stage = 'post-main-sequence';
                }
                if (($previous !== null) && ($previous !== $this->stage))
                {
                        Utility::write(
                                $this->getName() . " transitioned to " . $this->stage . " phase",
                                LOG_NOTICE,
                                L_CONSOLE
                        );
                }
        }
}
?>
