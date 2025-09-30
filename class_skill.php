<?php // 7.3.0-dev
class Skill
{
        private $name;
        private $level;
        private $experience;
        private $decayRate;

        public function __construct (string $name, float $level = 0.0)
        {
                $this->name = Utility::cleanse_string($name);
                $this->level = $this->sanitizeFraction($level);
                $this->experience = max(0.0, $level);
                $this->decayRate = 0.0;
        }

        public function getName () : string
        {
                return $this->name;
        }

        public function getLevel () : float
        {
                return $this->level;
        }

        public function getExperience () : float
        {
                return $this->experience;
        }

        public function getDecayRate () : float
        {
                return $this->decayRate;
        }

        public function setDecayRate (float $rate) : void
        {
                $this->decayRate = max(0.0, floatval($rate));
        }

        public function addExperience (float $amount) : void
        {
                if ($amount <= 0) return;
                $this->experience += $amount;
                $this->level = $this->sanitizeFraction($this->level + ($amount * 0.5));
        }

        public function reinforce (float $targetLevel, float $intensity) : void
        {
                $target = $this->sanitizeFraction($targetLevel);
                $intensity = max(0.0, min(1.0, floatval($intensity)));
                if ($intensity <= 0) return;
                $delta = ($target - $this->level) * $intensity;
                $this->level = $this->sanitizeFraction($this->level + $delta);
        }

        public function tick (float $deltaTime = 1.0) : void
        {
                if ($deltaTime <= 0) return;
                if ($this->decayRate <= 0) return;
                $decay = $deltaTime * $this->decayRate;
                if ($decay <= 0) return;
                $this->level = $this->sanitizeFraction($this->level - $decay);
        }

        public function toArray () : array
        {
                return array(
                        'name' => $this->name,
                        'level' => $this->level,
                        'experience' => $this->experience,
                        'decay_rate' => $this->decayRate
                );
        }

        private function sanitizeFraction ($value) : float
        {
                return max(0.0, min(1.0, floatval($value)));
        }
}
?>
