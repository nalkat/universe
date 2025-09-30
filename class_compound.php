<?php // 7.3.0-dev
class Compound
{
        protected $name;
        protected $formula;
        protected $components; // element symbol => ratio
        protected $stateAtSTP;
        protected $classification;
        protected $density;
        protected $molarMass;
        protected $bondTypes;

        public function __construct(string $name, array $components = array(), array $properties = array())
        {
                $this->name = $this->sanitize($name);
                $this->formula = $this->sanitize($properties['formula'] ?? '');
                $this->components = array();
                $this->stateAtSTP = $this->sanitize($properties['state_at_stp'] ?? 'solid');
                $this->classification = $this->sanitize($properties['classification'] ?? 'inorganic');
                $this->density = isset($properties['density']) ? max(0.0, floatval($properties['density'])) : null;
                $this->molarMass = isset($properties['molar_mass']) ? max(0.0, floatval($properties['molar_mass'])) : null;
                $this->bondTypes = array();

                foreach ($components as $component => $ratio)
                {
                        $this->setComponent($component, $ratio);
                }

                if (!empty($properties['bond_types']) && is_array($properties['bond_types']))
                {
                        foreach ($properties['bond_types'] as $bond)
                        {
                                $this->addBondType($bond);
                        }
                }
        }

        public function getName() : string
        {
                return $this->name;
        }

        public function getFormula() : string
        {
                if ($this->formula !== '')
                {
                        return $this->formula;
                }
                return $this->buildFormulaFromComponents();
        }

        public function setFormula(string $formula) : void
        {
                $this->formula = $this->sanitize($formula);
        }

        public function getComponents() : array
        {
                return $this->components;
        }

        public function setComponent($component, float $ratio) : void
        {
                if ($component instanceof Element)
                {
                        $symbol = $component->getSymbol();
                        $mass = $component->getAtomicMass();
                        if ($this->molarMass === null)
                        {
                                $this->molarMass = 0.0;
                        }
                        $this->molarMass += $mass * max(0.0, $ratio);
                }
                else
                {
                        $symbol = strtoupper($this->sanitize(strval($component)));
                }
                if ($symbol === '')
                {
                        return;
                }
                $this->components[$symbol] = max(0.0, $ratio);
        }

        public function removeComponent(string $symbol) : void
        {
                $key = strtoupper($this->sanitize($symbol));
                if (array_key_exists($key, $this->components))
                {
                        unset($this->components[$key]);
                }
        }

        public function getStateAtSTP() : string
        {
                return $this->stateAtSTP;
        }

        public function getClassification() : string
        {
                return $this->classification;
        }

        public function getDensity() : ?float
        {
                return $this->density;
        }

        public function setDensity(float $density) : void
        {
                $this->density = max(0.0, $density);
        }

        public function getMolarMass() : ?float
        {
                if ($this->molarMass !== null)
                {
                        return $this->molarMass;
                }
                $mass = 0.0;
                foreach ($this->components as $symbol => $ratio)
                {
                        $mass += $this->estimateAtomicMass($symbol) * $ratio;
                }
                return $mass;
        }

        public function addBondType(string $bondType) : void
        {
                $normalized = strtolower(trim($bondType));
                if ($normalized === '')
                {
                        return;
                }
                if (!in_array($normalized, $this->bondTypes, true))
                {
                        $this->bondTypes[] = $normalized;
                }
        }

        public function getBondTypes() : array
        {
                return $this->bondTypes;
        }

        public function isOrganic() : bool
        {
                if ($this->classification === '')
                {
                        return isset($this->components['C']) && isset($this->components['H']);
                }
                return (strpos(strtolower($this->classification), 'organic') !== false);
        }

        protected function buildFormulaFromComponents() : string
        {
                if (empty($this->components))
                {
                        return '';
                }
                $parts = array();
                ksort($this->components);
                foreach ($this->components as $symbol => $ratio)
                {
                        $suffix = ($ratio === 1.0) ? '' : $this->formatRatio($ratio);
                        $parts[] = $symbol . $suffix;
                }
                return implode('', $parts);
        }

        protected function estimateAtomicMass(string $symbol) : float
        {
                static $approximations = array(
                        'H' => 1.008,
                        'C' => 12.011,
                        'N' => 14.007,
                        'O' => 15.999,
                        'Na' => 22.990,
                        'Cl' => 35.45,
                        'Fe' => 55.845,
                );
                if (array_key_exists($symbol, $approximations))
                {
                        return $approximations[$symbol];
                }
                return 10.0;
        }

        protected function formatRatio(float $ratio) : string
        {
                if (abs($ratio - round($ratio)) < 1e-6)
                {
                        return strval(intval(round($ratio)));
                }
                return rtrim(rtrim(sprintf('%.2f', $ratio), '0'), '.');
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
