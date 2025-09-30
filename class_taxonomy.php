<?php // 7.3.0-dev
class Taxonomy
{
        protected $ranks;

        public function __construct(array $ranks = array())
        {
                $this->ranks = array(
                        'domain'      => null,
                        'kingdom'     => null,
                        'phylum'      => null,
                        'class'       => null,
                        'order'       => null,
                        'family'      => null,
                        'genus'       => null,
                        'species'     => null,
                        'subspecies'  => null,
                );
                foreach ($ranks as $rank => $value)
                {
                        $this->setRank($rank, $value);
                }
        }

        public function setRank(string $rank, ?string $value) : void
        {
                $normalized = strtolower(trim($rank));
                if (!array_key_exists($normalized, $this->ranks))
                {
                        return;
                }
                if ($value === null)
                {
                        $this->ranks[$normalized] = null;
                        return;
                }
                $this->ranks[$normalized] = $this->sanitize($value);
        }

        public function getRank(string $rank) : ?string
        {
                $normalized = strtolower(trim($rank));
                if (!array_key_exists($normalized, $this->ranks))
                {
                        return null;
                }
                return $this->ranks[$normalized];
        }

        public function toArray() : array
        {
                return $this->ranks;
        }

        public function getScientificName() : ?string
        {
                $genus = $this->ranks['genus'];
                $species = $this->ranks['species'];
                if ($genus === null || $species === null)
                {
                        return null;
                }
                $scientific = $genus . ' ' . strtolower($species);
                if ($this->ranks['subspecies'] !== null)
                {
                        $scientific .= ' ' . strtolower($this->ranks['subspecies']);
                }
                return $scientific;
        }

        protected function sanitize(string $value) : string
        {
                if (class_exists('Utility'))
                {
                        return Utility::cleanse_string($value);
                }
                return trim($value);
        }
}
?>
