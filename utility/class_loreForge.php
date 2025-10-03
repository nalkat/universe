<?php declare(strict_types=1);

class LoreForge
{
        private const CHRONICLE_LIMIT = 64;

        private static function randomChoice (array $values, string $fallback = '') : string
        {
                if (empty($values)) return $fallback;
                $index = mt_rand(0, count($values) - 1);
                return strval($values[$index]);
        }

        private static function chronicle (string $type, string $text, ?float $timestamp = null, array $participants = array()) : array
        {
                return array(
                        'type' => Utility::cleanse_string($type === '' ? 'event' : $type),
                        'text' => trim(strval($text)),
                        'timestamp' => ($timestamp === null) ? microtime(true) : floatval($timestamp),
                        'participants' => array_values(array_unique(array_map('strval', $participants)))
                );
        }

        private static function randomFilament () : string
        {
                $prefix = self::randomChoice(array('Azul', 'Myrr', 'Vel', 'Orion', 'Cygnus', 'Lyra'), 'Aster');
                $suffix = self::randomChoice(array('Spire', 'Ribbon', 'Whorl', 'Veil', 'Cascade'), 'Stream');
                return $prefix . ' ' . $suffix;
        }

        private static function randomClimatePhrase () : string
        {
                return self::randomChoice(array(
                        'seasonal auroras',
                        'tidal booms that shake canyon walls',
                        'rains that crystallise mid-air',
                        'luminescent dusk tides',
                        'winds that sing through hollow stone'
                ), 'strange meteorological rituals');
        }

        private static function randomElementTrait () : string
        {
                return self::randomChoice(array(
                        'catalytic brilliance',
                        'resonant lattice patterns',
                        'eager electron exchanges',
                        'glimmering ion trails',
                        'stubborn inertness'
                ), 'enigmatic behaviour');
        }

        public static function describeUniverse (Universe $universe) : array
        {
                $galaxies = $universe->getGalaxies();
                $galaxyCount = count($galaxies);
                $dimensions = array(
                        'x' => Universe::getMaxX(),
                        'y' => Universe::getMaxY(),
                        'z' => Universe::getMaxZ()
                );
                $volume = max(1.0, $dimensions['x'] * $dimensions['y'] * $dimensions['z']);
                $ticks = $universe->getTicks();
                $description = sprintf(
                        'Universe %s spans %.0f×%.0f×%.0f units (~%.2e cubic units) and currently rests at tick %.0f.',
                        $universe->getName(),
                        $dimensions['x'],
                        $dimensions['y'],
                        $dimensions['z'],
                        $volume,
                        $ticks
                );
                $chronicle = array(
                        self::chronicle('ignition', sprintf('%s erupted from a %s fluctuation.', $universe->getName(), self::randomFilament())),
                        self::chronicle('survey', sprintf('Explorers mapped %d galaxies across %.2e cubic units.', $galaxyCount, $volume))
                );
                return array(
                        'summary' => sprintf('%d galaxies cataloged; %.0f ticks elapsed.', $galaxyCount, $ticks),
                        'description' => $description,
                        'chronicle' => $chronicle,
                        'statistics' => array(
                                'galaxies' => $galaxyCount,
                                'volume' => $volume,
                                'ticks' => $ticks,
                                'dimensions' => $dimensions
                        )
                );
        }

        public static function describeGalaxy (Galaxy $galaxy, array $context = array()) : array
        {
                $bounds = method_exists($galaxy, 'getBounds') ? $galaxy->getBounds() : array('x' => 0.0, 'y' => 0.0, 'z' => 0.0);
                $systemCount = isset($context['systems']) ? intval($context['systems']) : count($galaxy->getSystems());
                $volume = max(1.0, floatval($bounds['x']) * floatval($bounds['y']) * floatval($bounds['z']));
                $density = $volume > 0.0 ? ($systemCount / $volume) : 0.0;
                $description = sprintf(
                        '%s galaxy spans %.0f×%.0f×%.0f units with %d systems (density %.6f).',
                        self::randomChoice(array('Ancient', 'Luminous', 'Veiled', 'Restless', 'Shimmering'), 'Spiral'),
                        floatval($bounds['x']),
                        floatval($bounds['y']),
                        floatval($bounds['z']),
                        $systemCount,
                        $density
                );
                $chronicle = array(
                        self::chronicle('formation', sprintf('%s condensed along the %s filament.', $galaxy->name, self::randomFilament())),
                        self::chronicle('observation', sprintf('Surveyors recorded %d stellar families within %.2e units.', $systemCount, $volume))
                );
                if (method_exists($galaxy, 'ensureVisualAsset'))
                {
                        $galaxy->ensureVisualAsset('primary', function () use ($galaxy, $context) : array {
                                return VisualForge::galaxy($galaxy, $context);
                        });
                }
                return array('description' => $description, 'chronicle' => $chronicle);
        }

        public static function describeSystem (System $system, array $context = array()) : array
        {
                $star = $system->getPrimaryStar();
                $planetCount = count($system->getPlanets());
                $starName = ($star instanceof Star) ? $star->getName() : 'an unnamed primary';
                $description = sprintf(
                        'System anchored by %s operating in %s propagation with a %.1f second step, sheltering %d worlds.',
                        $starName,
                        $system->getPropagationMode(),
                        $system->getTimeStep(),
                        $planetCount
                );
                $chronicle = array(
                        self::chronicle('formation', sprintf('%s crystallised as %d protoplanets coalesced around %s.', $system->getName(), $planetCount, $starName)),
                        self::chronicle('cadence', sprintf('Clockwork cadence revises orbital drift every %.1f seconds.', $system->getTimeStep()))
                );
                if (method_exists($system, 'ensureVisualAsset'))
                {
                        $system->ensureVisualAsset('primary', function () use ($system, $planetCount) : array {
                                return VisualForge::system($system, array('planets' => $planetCount));
                        });
                }
                return array('description' => $description, 'chronicle' => $chronicle);
        }

        public static function describeStar (Star $star, array $context = array()) : array
        {
                $spectral = $star->getSpectralClass();
                $stage = $star->getStage();
                $massRatio = ($star->getMass() > 0) ? ($star->getMass() / Star::SOLAR_MASS) : 0.0;
                $chronicle = array(
                        self::chronicle('stellar_class', sprintf('%s shines as a %s star in the %s phase.', $star->getName(), $spectral, $stage)),
                        self::chronicle('fusion', sprintf('Core furnaces convert %.2f solar masses worth of fuel each cycle.', $massRatio))
                );
                if (method_exists($star, 'ensureVisualAsset'))
                {
                        $star->ensureVisualAsset('primary', function () use ($star, $context) : array {
                                return VisualForge::star($star, $context);
                        });
                }
                return array('chronicle' => $chronicle);
        }

        public static function describePlanet (Planet $planet, array $context = array()) : array
        {
                $climate = $planet->describeClimate();
                $habitability = $planet->getHabitabilityScore();
                $habitabilityClass = $planet->getHabitabilityClassification();
                $chronicle = array(
                        self::chronicle(
                                'climate',
                                sprintf('%s hosts %s %s with %s.',
                                        $planet->getName(),
                                        $climate['climate_adjective'] ?? 'temperate',
                                        $climate['biome_descriptor'] ?? 'biomes',
                                        $climate['seasonality_phrase'] ?? 'subtle seasons'
                                )
                        ),
                        self::chronicle(
                                'habitability',
                                sprintf('Habitability scored %.2f (%s) and invites %s.', $habitability, $habitabilityClass, self::randomClimatePhrase())
                        )
                );
                foreach ($planet->getWeatherHistory(5) as $line)
                {
                        $chronicle[] = self::chronicle('weather', $line);
                }
                if (method_exists($planet, 'ensureVisualAsset'))
                {
                        $planet->ensureVisualAsset('primary', function () use ($planet, $habitability, $habitabilityClass) : array {
                                return VisualForge::planet($planet, array('habitability' => $habitability, 'classification' => $habitabilityClass));
                        });
                }
                return array('chronicle' => $chronicle);
        }

        public static function describeElement (Element $element, array $context = array()) : array
        {
                $description = sprintf(
                        '%s (%s) sits in group %s, period %s and remains %s under standard conditions with estimated valence %d.',
                        $element->getName(),
                        $element->getSymbol(),
                        ($element->getGroup() === null) ? 'unknown' : $element->getGroup(),
                        ($element->getPeriod() === null) ? 'unknown' : $element->getPeriod(),
                        $element->getStateAtSTP(),
                        $element->estimateValenceElectrons()
                );
                $chronicle = array(
                        self::chronicle('nucleosynthesis', sprintf('%s emerged from supernova crucibles, carrying %s.', $element->getName(), self::randomElementTrait())),
                        self::chronicle('discovery', sprintf('Catalogued for its %s reactions.', self::randomElementTrait()))
                );
                if (method_exists($element, 'ensureVisualAsset'))
                {
                        $element->ensureVisualAsset('primary', function () use ($element) : array {
                                return VisualForge::element($element, array());
                        });
                }
                return array('description' => $description, 'chronicle' => $chronicle);
        }

        public static function describeCompound (Compound $compound, array $context = array()) : array
        {
                $components = $compound->getComponents();
                $parts = array();
                foreach ($components as $symbol => $ratio)
                {
                        $parts[] = $symbol . ((abs($ratio - 1.0) < 1e-6) ? '' : sprintf('%.1f', $ratio));
                }
                $formula = ($compound->getFormula() !== '') ? $compound->getFormula() : implode('', $parts);
                $description = sprintf(
                        '%s (%s) aligns as a %s compound, typically %s with density %s kg/m³.',
                        $compound->getName(),
                        $formula,
                        $compound->getClassification(),
                        $compound->getStateAtSTP(),
                        $compound->getDensity() === null ? 'unknown' : number_format($compound->getDensity(), 2)
                );
                $chronicle = array(
                        self::chronicle('synthesis', sprintf('Molecular artisans braided %s from %s.', $compound->getName(), implode(', ', array_keys($components)))),
                        self::chronicle('applications', sprintf('Known for %s bonds and resilient lattices.', self::randomElementTrait()))
                );
                if (method_exists($compound, 'ensureVisualAsset'))
                {
                        $compound->ensureVisualAsset('primary', function () use ($compound) : array {
                                return VisualForge::compound($compound, array());
                        });
                }
                return array('description' => $description, 'chronicle' => $chronicle);
        }

        public static function describePerson (Person $person, array $context = array()) : array
        {
                $backstory = $person->getBackstory();
                $chronicle = $person->getChronicle();
                if ($backstory !== '')
                {
                        array_unshift($chronicle, self::chronicle('backstory', $backstory, null, array($person->getName())));
                }
                if (method_exists($person, 'ensureVisualAsset'))
                {
                        $person->ensureVisualAsset('primary', function () use ($person) : array {
                                return VisualForge::person($person, array());
                        });
                }
                return array('chronicle' => array_slice($chronicle, -self::CHRONICLE_LIMIT));
        }
}
