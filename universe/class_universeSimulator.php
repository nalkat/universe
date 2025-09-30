<?php // 7.3.0-dev
class UniverseSimulator
{
        private $universe;
        private $systemsByGalaxy;

        public function __construct (Universe $universe)
        {
                $this->universe = $universe;
                $this->systemsByGalaxy = array();
        }

        public function getUniverse () : Universe
        {
                return $this->universe;
        }

        public function bootstrap (array $blueprint) : void
        {
                $galaxies = $blueprint;
                if (isset($blueprint['galaxies']) && is_array($blueprint['galaxies']))
                {
                        $galaxies = $blueprint['galaxies'];
                }
                foreach ($galaxies as $galaxySpec)
                {
                        $this->buildGalaxy($galaxySpec);
                }
        }

        public function run (int $steps, float $deltaTime = 1.0) : array
        {
                $snapshots = array();
                for ($i = 0; $i < $steps; $i++)
                {
                        $this->universe->advance($deltaTime);
                        $snapshots[] = $this->collectSnapshot();
                }
                return $snapshots;
        }

        public function getSystemsForGalaxy (string $galaxyName) : array
        {
                $clean = Utility::cleanse_string($galaxyName);
                if (!isset($this->systemsByGalaxy[$clean])) return array();
                return $this->systemsByGalaxy[$clean];
        }

        private function buildGalaxy (array $spec) : ?Galaxy
        {
                if (empty($spec['name']))
                {
                        Utility::write('Skipping unnamed galaxy specification', LOG_WARNING, L_CONSOLE);
                        return null;
                }
                $name = Utility::cleanse_string($spec['name']);
                $size = $spec['size'] ?? array();
                $x = floatval($size['x'] ?? $this->universe->getFreeSpace()['x'] ?? 1.0);
                $y = floatval($size['y'] ?? $this->universe->getFreeSpace()['y'] ?? 1.0);
                $z = floatval($size['z'] ?? $this->universe->getFreeSpace()['z'] ?? 1.0);
                if (!$this->universe->createGalaxy($name, $x, $y, $z))
                {
                        return $this->universe->getGalaxy($name);
                }
                $galaxy = $this->universe->getGalaxy($name);
                if (!($galaxy instanceof Galaxy)) return null;
                if (!empty($spec['systems']) && is_array($spec['systems']))
                {
                        foreach ($spec['systems'] as $systemSpec)
                        {
                                $system = $this->buildSystem($galaxy, $systemSpec);
                                if ($system instanceof System)
                                {
                                        $this->systemsByGalaxy[$name][$system->getName()] = $system;
                                }
                        }
                }
                return $galaxy;
        }

        private function buildSystem (Galaxy $galaxy, array $spec) : ?System
        {
                if (empty($spec['name']))
                {
                        Utility::write('Skipping unnamed system specification', LOG_WARNING, L_CONSOLE);
                        return null;
                }
                $name = Utility::cleanse_string($spec['name']);
                $starSpec = $spec['star'] ?? array();
                if (empty($starSpec['name']))
                {
                        $starSpec['name'] = $name . ' Prime';
                }
                $star = new Star(
                        Utility::cleanse_string($starSpec['name']),
                        floatval($starSpec['mass'] ?? Star::SOLAR_MASS),
                        floatval($starSpec['radius'] ?? 6.9634E8),
                        floatval($starSpec['luminosity'] ?? Star::SOLAR_LUMINOSITY),
                        floatval($starSpec['temperature'] ?? 5772),
                        strval($starSpec['spectral_class'] ?? 'G2V')
                );
                $system = $galaxy->createSystem($name, $star, $spec['planets'] ?? array());
                if (isset($spec['time_step']))
                {
                        $system->setTimeStep(floatval($spec['time_step']));
                }
                if (isset($spec['propagation_mode']))
                {
                        $system->setPropagationMode(strval($spec['propagation_mode']));
                }
                if (isset($spec['softening_length']))
                {
                        $system->setGravitySofteningLength(floatval($spec['softening_length']));
                }
                return $system;
        }

        private function collectSnapshot () : array
        {
                $snapshot = array(
                        'tick' => $this->universe->getTicks(),
                        'galaxies' => array()
                );
                foreach ($this->universe->getGalaxies() as $galaxyName => $galaxy)
                {
                        if (!($galaxy instanceof Galaxy)) continue;
                        $systems = array();
                        foreach ($galaxy->getSystems() as $systemName => $system)
                        {
                                $systems[$systemName] = $this->collectSystemSnapshot($system);
                        }
                        $snapshot['galaxies'][$galaxyName] = array(
                                'systems' => $systems
                        );
                }
                return $snapshot;
        }

        private function collectSystemSnapshot (System $system) : array
        {
                $planets = array();
                foreach ($system->getPlanets() as $planetName => $planet)
                {
                        $planets[$planetName] = array(
                                'position' => $planet->getPosition(),
                                'velocity' => $planet->getVelocity(),
                                'habitability' => $planet->getHabitabilityScore(),
                                'population' => $planet->getPopulationSummary()
                        );
                }
                return array(
                        'age' => $system->getAge(),
                        'propagation_mode' => $system->getPropagationMode(),
                        'object_count' => $system->countObjects(),
                        'planets' => $planets
                );
        }
}
?>
