<?php // 7.3.0-dev
class Habitat extends Structure
{
        protected $preferredTaxa;
        protected $environmentalFactors;
        protected $metabolism;

        public function __construct(string $name, array $properties = array())
        {
                parent::__construct($name, $properties);

                $this->preferredTaxa = array();
                $this->environmentalFactors = array();
                $this->metabolism = max(0.0, floatval($properties['metabolism'] ?? 0.0));

                if (!empty($properties['preferredTaxa']) && is_array($properties['preferredTaxa']))
                {
                        foreach ($properties['preferredTaxa'] as $taxon)
                        {
                                $this->addPreferredTaxon($taxon);
                        }
                }

                if (!empty($properties['environmentalFactors']) && is_array($properties['environmentalFactors']))
                {
                        foreach ($properties['environmentalFactors'] as $factor => $intensity)
                        {
                                $this->setEnvironmentalFactor($factor, floatval($intensity));
                        }
                }

                if (!empty($properties['builder']))
                {
                        $this->setCreator($properties['builder']);
                }
        }

        public function addOccupant($occupant) : bool
        {
                if ($occupant instanceof Life && !$this->supportsLife($occupant))
                {
                        return false;
                }

                return parent::addOccupant($occupant);
        }

        public function getPreferredTaxa() : array
        {
                return $this->preferredTaxa;
        }

        public function addPreferredTaxon($taxon) : void
        {
                $normalized = $this->normalizeTaxonPreference($taxon);
                foreach ($normalized as $tag)
                {
                        if (!in_array($tag, $this->preferredTaxa, true))
                        {
                                $this->preferredTaxa[] = $tag;
                        }
                }
        }

        public function clearPreferredTaxa() : void
        {
                $this->preferredTaxa = array();
        }

        public function supportsLife($candidate) : bool
        {
                if (empty($this->preferredTaxa))
                {
                        return true;
                }

                $candidateTags = $this->gatherCandidateTags($candidate);
                if (empty($candidateTags))
                {
                        return false;
                }

                foreach ($candidateTags as $tag)
                {
                        if (in_array($tag, $this->preferredTaxa, true))
                        {
                                return true;
                        }
                }

                return false;
        }

        public function getEnvironmentalFactors() : array
        {
                return $this->environmentalFactors;
        }

        public function setEnvironmentalFactor(string $factor, float $intensity) : void
        {
                $key = strtolower($this->sanitize($factor));
                $this->environmentalFactors[$key] = max(0.0, min(1.0, $intensity));
        }

        public function adjustEnvironmentalFactor(string $factor, float $delta) : void
        {
                $key = strtolower($this->sanitize($factor));
                $current = $this->environmentalFactors[$key] ?? 0.0;
                $this->environmentalFactors[$key] = max(0.0, min(1.0, $current + $delta));
        }

        public function getMetabolism() : float
        {
                return $this->metabolism;
        }

        public function setMetabolism(float $value) : void
        {
                $this->metabolism = max(0.0, $value);
        }

        public function adaptToPressure(string $factor, float $intensity) : void
        {
                $this->setEnvironmentalFactor($factor, $intensity);
                $intensity = max(0.0, min(1.0, $intensity));

                if ($intensity > 0.6)
                {
                        $this->weakenResilience($intensity * 0.02);
                        $this->recordEvent('environmental_pressure', $factor . ' stress rose to ' . $intensity, -1.0 * $intensity);
                }
                else
                {
                        $this->trainResilience($intensity * 0.01);
                        $this->recordEvent('environmental_adjustment', $factor . ' adjustments logged at ' . $intensity, $intensity * 0.5);
                }
        }

        public function simulateSeason(array $conditions = array()) : array
        {
                $outcome = array(
                        'pressures' => array(),
                        'resilience' => $this->getResilience(),
                );

                foreach ($conditions as $factor => $intensity)
                {
                        $this->adaptToPressure($factor, floatval($intensity));
                        $outcome['pressures'][$factor] = max(0.0, min(1.0, floatval($intensity)));
                }

                $outcome['resilience'] = $this->getResilience();

                return $outcome;
        }

        public function tick(float $deltaTime = 1.0) : void
        {
                if ($deltaTime <= 0)
                {
                        return;
                }

                $pressure = $this->calculateEnvironmentalPressure();
                if ($pressure > 0)
                {
                        $this->degrade($pressure * 0.0005 * $deltaTime);
                }

                if ($pressure < 0.2)
                {
                        $this->trainResilience(0.005 * $deltaTime);
                }

                parent::tick($deltaTime);
        }

        protected function normalizeTaxonPreference($preference) : array
        {
                $normalized = array();

                if ($preference instanceof Taxonomy)
                {
                        $map = $preference->toArray();
                        foreach ($map as $rank => $value)
                        {
                                if ($value !== null)
                                {
                                        $normalized[] = strtolower($rank . ':' . $this->sanitize($value));
                                }
                        }

                        $scientific = $preference->getScientificName();
                        if ($scientific !== null)
                        {
                                $normalized[] = strtolower($this->sanitize($scientific));
                        }

                        return $normalized;
                }

                if (is_array($preference))
                {
                        if (array_key_exists('rank', $preference) && array_key_exists('value', $preference))
                        {
                                $rank = strtolower($this->sanitize(strval($preference['rank'])));
                                $value = strtolower($this->sanitize(strval($preference['value'])));
                                if ($rank !== '' && $value !== '')
                                {
                                        $normalized[] = $rank . ':' . $value;
                                }
                        }
                        else
                        {
                                $taxonomy = new Taxonomy($preference);
                                return $this->normalizeTaxonPreference($taxonomy);
                        }

                        return $normalized;
                }

                if (is_string($preference))
                {
                        $value = strtolower($this->sanitize($preference));
                        if ($value !== '')
                        {
                                $normalized[] = $value;
                        }
                }

                return $normalized;
        }

        protected function gatherCandidateTags($candidate) : array
        {
                $tags = array();

                if ($candidate instanceof Life)
                {
                        $tags[] = strtolower($this->sanitize($candidate->getName()));
                        $traits = $candidate->getTraits();
                        foreach (array('species', 'type', 'role') as $key)
                        {
                                if (array_key_exists($key, $traits) && is_string($traits[$key]))
                                {
                                        $tags[] = strtolower($this->sanitize($traits[$key]));
                                }
                        }

                        $taxonomyTrait = $candidate->getTrait('taxonomy');
                        if ($taxonomyTrait instanceof Taxonomy)
                        {
                                $tags = array_merge($tags, $this->normalizeTaxonPreference($taxonomyTrait));
                        }
                        elseif (is_array($taxonomyTrait))
                        {
                                $tags = array_merge($tags, $this->normalizeTaxonPreference($taxonomyTrait));
                        }
                        elseif (is_string($taxonomyTrait) && $taxonomyTrait !== '')
                        {
                                $tags[] = strtolower($this->sanitize($taxonomyTrait));
                        }
                }
                elseif ($candidate instanceof Taxonomy)
                {
                        $tags = array_merge($tags, $this->normalizeTaxonPreference($candidate));
                }
                elseif (is_array($candidate))
                {
                        $tags = array_merge($tags, $this->normalizeTaxonPreference($candidate));
                }
                elseif (is_string($candidate))
                {
                        $value = strtolower($this->sanitize($candidate));
                        if ($value !== '')
                        {
                                $tags[] = $value;
                        }
                }

                return array_values(array_unique($tags));
        }

        protected function calculateEnvironmentalPressure() : float
        {
                if (empty($this->environmentalFactors))
                {
                        return $this->metabolism;
                }

                $pressure = $this->metabolism;
                foreach ($this->environmentalFactors as $intensity)
                {
                        $pressure += max(0.0, floatval($intensity));
                }

                return $pressure;
        }
}
?>
