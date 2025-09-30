<?php // 7.3.0-dev
class Element
{
        protected $name;
        protected $symbol;
        protected $atomicNumber;
        protected $atomicMass;
        protected $group;
        protected $period;
        protected $category;
        protected $stateAtSTP;
        protected $particleCounts;
        protected $isotopes;

        public function __construct(string $name, string $symbol, int $atomicNumber, array $properties = array())
        {
                $this->name = $this->sanitize($name);
                $this->symbol = strtoupper($this->sanitize($symbol));
                $this->atomicNumber = max(0, $atomicNumber);
                $this->atomicMass = floatval($properties['atomic_mass'] ?? $atomicNumber);
                $this->group = isset($properties['group']) ? intval($properties['group']) : null;
                $this->period = isset($properties['period']) ? intval($properties['period']) : null;
                $this->category = $this->sanitize($properties['category'] ?? 'unknown');
                $this->stateAtSTP = $this->sanitize($properties['state_at_stp'] ?? 'solid');
                $this->particleCounts = array(
                        'protons' => $this->atomicNumber,
                        'neutrons' => intval($properties['neutrons'] ?? max(0, round($this->atomicMass) - $this->atomicNumber)),
                        'electrons' => $this->atomicNumber,
                );
                $this->isotopes = array();
                if (!empty($properties['isotopes']) && is_array($properties['isotopes']))
                {
                        foreach ($properties['isotopes'] as $massNumber => $details)
                        {
                                $this->addIsotope(intval($massNumber), $details);
                        }
                }
        }

        public function getName() : string
        {
                return $this->name;
        }

        public function getSymbol() : string
        {
                return $this->symbol;
        }

        public function getAtomicNumber() : int
        {
                return $this->atomicNumber;
        }

        public function getAtomicMass() : float
        {
                return $this->atomicMass;
        }

        public function getGroup() : ?int
        {
                return $this->group;
        }

        public function getPeriod() : ?int
        {
                return $this->period;
        }

        public function getCategory() : string
        {
                return $this->category;
        }

        public function getStateAtSTP() : string
        {
                return $this->stateAtSTP;
        }

        public function setParticleCount(string $type, int $count) : void
        {
                $normalized = strtolower(trim($type));
                if (!array_key_exists($normalized, $this->particleCounts))
                {
                        return;
                }
                $this->particleCounts[$normalized] = max(0, $count);
        }

        public function getParticleCount(string $type) : int
        {
                $normalized = strtolower(trim($type));
                if (!array_key_exists($normalized, $this->particleCounts))
                {
                        return 0;
                }
                return $this->particleCounts[$normalized];
        }

        public function addIsotope(int $massNumber, $details = null) : void
        {
                if ($massNumber <= 0)
                {
                        return;
                }
                $this->isotopes[$massNumber] = array(
                        'mass_number' => $massNumber,
                        'abundance' => isset($details['abundance']) ? max(0.0, min(1.0, floatval($details['abundance']))) : null,
                        'half_life' => isset($details['half_life']) ? max(0.0, floatval($details['half_life'])) : null,
                        'name' => $this->sanitize($details['name'] ?? ($this->symbol . '-' . $massNumber)),
                );
        }

        public function getIsotopes() : array
        {
                return $this->isotopes;
        }

        public function estimateValenceElectrons() : int
        {
                if ($this->group === null)
                {
                        return min(8, $this->atomicNumber);
                }
                if ($this->group >= 1 && $this->group <= 2)
                {
                        return $this->group;
                }
                if ($this->group >= 13 && $this->group <= 18)
                {
                        return $this->group - 10;
                }
                return 8;
        }

        public function canBondWith(Element $other, string $bondType = 'covalent') : bool
        {
                $bond = strtolower(trim($bondType));
                if ($bond === 'ionic')
                {
                        $difference = abs($this->electronegativityEstimate() - $other->electronegativityEstimate());
                        return ($difference >= 1.7);
                }
                if ($bond === 'metallic')
                {
                        return ($this->isMetal() && $other->isMetal());
                }
                return (abs($this->electronegativityEstimate() - $other->electronegativityEstimate()) <= 1.7);
        }

        public function isMetal() : bool
        {
                $category = strtolower($this->category);
                return (strpos($category, 'metal') !== false);
        }

        public function electronegativityEstimate() : float
        {
                if ($this->atomicNumber <= 0)
                {
                        return 0.0;
                }
                $period = max(1, $this->period ?? 1);
                $group = max(1, $this->group ?? 1);
                return max(0.5, min(4.0, (4.0 - ($group / 18.0) - ($period / 10.0))));
        }

        protected function sanitize($value) : string
        {
                $string = strval($value);
                if (class_exists('Utility'))
                {
                        return Utility::cleanse_string($string);
                }
                return trim($string);
        }
}
?>
