<?php // 7.3.0-dev

class TransitObject extends SystemObject
{
        public const SCOPE_INTERGALACTIC = 'intergalactic';
        public const SCOPE_INTERSYSTEM = 'intersystem';

        private $scope;
        private $originName;
        private $destinationName;
        private $propulsion;
        private $shape;
        private $travelTime;
        private $elapsed;
        private $progress;
        private $originVector;
        private $destinationVector;
        private $context;
        private $completed;

        public function __construct(
                string $name,
                float $mass,
                float $radius,
                array $originVector,
                array $destinationVector,
                float $travelTime,
                string $scope,
                string $propulsion,
                string $shape,
                ?array $initialVelocity = null
        )
        {
                parent::__construct($name, $mass, $radius, $originVector, $initialVelocity);
                $this->setType('TransitObject');
                $this->scope = ($scope === self::SCOPE_INTERGALACTIC) ? self::SCOPE_INTERGALACTIC : self::SCOPE_INTERSYSTEM;
                $this->originName = '';
                $this->destinationName = '';
                $this->propulsion = Utility::cleanse_string($propulsion);
                $this->shape = Utility::cleanse_string($shape);
                $this->travelTime = max(0.0, $travelTime);
                $this->elapsed = 0.0;
                $this->progress = 0.0;
                $this->originVector = $this->sanitizeVector($originVector);
                $this->destinationVector = $this->sanitizeVector($destinationVector);
                $this->context = array();
                $this->completed = ($this->travelTime === 0.0);
                if ($this->completed)
                {
                        $this->progress = 1.0;
                        $this->setPosition($this->destinationVector);
                }
                $this->refreshDescription();
        }

        public function setEndpoints(string $originName, string $destinationName) : void
        {
                $this->originName = Utility::cleanse_string($originName);
                $this->destinationName = Utility::cleanse_string($destinationName);
                $this->refreshDescription();
        }

        public function setContext(array $context) : void
        {
                $this->context = $context;
        }

        public function getContext() : array
        {
                return $this->context;
        }

        public function getScope() : string
        {
                return $this->scope;
        }

        public function getPropulsion() : string
        {
                return $this->propulsion;
        }

        public function getShape() : string
        {
                return $this->shape;
        }

        public function getProgress() : float
        {
                return $this->progress;
        }

        public function isComplete() : bool
        {
                return $this->completed;
        }

        public function advanceTransit(float $deltaTime) : void
        {
                if ($deltaTime <= 0.0)
                {
                        return;
                }
                if ($this->completed)
                {
                        $this->age += $deltaTime;
                        return;
                }
                $step = min($deltaTime, max(0.0, $this->travelTime - $this->elapsed));
                $this->elapsed += $step;
                $this->age += $step;
                if ($this->travelTime <= 0.0)
                {
                        $this->progress = 1.0;
                }
                else
                {
                        $this->progress = min(1.0, max(0.0, $this->elapsed / $this->travelTime));
                }
                $previousPosition = $this->getPosition();
                $newPosition = $this->interpolatePosition($this->progress);
                $this->setPosition($newPosition);
                if ($step > 0.0)
                {
                        $this->setVelocity(array(
                                'x' => ($newPosition['x'] - $previousPosition['x']) / $step,
                                'y' => ($newPosition['y'] - $previousPosition['y']) / $step,
                                'z' => ($newPosition['z'] - $previousPosition['z']) / $step
                        ));
                }
                if ($this->elapsed >= $this->travelTime)
                {
                        $this->completed = true;
                        $this->progress = 1.0;
                        $this->setPosition($this->destinationVector);
                }
                $this->refreshDescription();
        }

        private function interpolatePosition(float $progress) : array
        {
                $progress = min(1.0, max(0.0, $progress));
                $position = array('x' => 0.0, 'y' => 0.0, 'z' => 0.0);
                foreach ($position as $axis => $value)
                {
                        $start = $this->originVector[$axis];
                        $end = $this->destinationVector[$axis];
                        $position[$axis] = $start + (($end - $start) * $progress);
                }
                return $position;
        }

        private function refreshDescription () : void
        {
                $origin = ($this->originName === '') ? 'an unknown origin' : $this->originName;
                $destination = ($this->destinationName === '') ? 'an undefined destination' : $this->destinationName;
                $progressPercent = round($this->progress * 100, 1);
                $segments = array(
                        sprintf('Transit object shaped as %s driven by %s propulsion.',
                                ($this->shape === '' ? 'an indeterminate frame' : $this->shape),
                                ($this->propulsion === '' ? 'unknown' : $this->propulsion)
                        ),
                        sprintf('Currently travelling from %s to %s.', $origin, $destination),
                        sprintf('Transit completion: %.1f%%.', $progressPercent)
                );
                $this->setDescription(implode(' ', $segments));
        }
}
?>
