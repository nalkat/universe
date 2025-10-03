<?php declare(strict_types=1); // Updated for PHP 8.3.6

enum UniverseCommand: string
{
        case Start = 'start';
        case RunOnce = 'run-once';
        case Help = 'help';
        case Catalog = 'catalog';

        public static function fromString(string $value): ?self
        {
                return self::tryFrom(strtolower($value));
        }
}

require_once __DIR__ . "/telemetry/class_telemetry.php";
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/class_universeDaemon.php";

set_time_limit(0);
if (function_exists('ini_set'))
{
        ini_set('memory_limit', '-1');
}

$arguments = $_SERVER['argv'] ?? array();
array_shift($arguments);
$rawCommand = null;
if (!empty($arguments) && !str_starts_with($arguments[0], '--'))
{
        $rawCommand = array_shift($arguments);
}
$options = universe_parse_options($arguments);
if (array_key_exists('help', $options) || array_key_exists('h', $options))
{
        universe_print_usage();
        exit(0);
}
$command = $rawCommand !== null ? UniverseCommand::fromString($rawCommand) : UniverseCommand::Start;
if ($rawCommand !== null && $command === null)
{
        echo "Unknown command '{$rawCommand}'." . PHP_EOL;
        universe_print_usage();
        exit(1);
}

$blueprintSeed = universe_initialize_rng(isset($options['seed']) ? intval((string)$options['seed']) : null);

if (!defined('UNIVERSE_ASTRONOMICAL_UNIT')) define('UNIVERSE_ASTRONOMICAL_UNIT', 1.495978707E11);
if (!defined('UNIVERSE_SECONDS_PER_YEAR')) define('UNIVERSE_SECONDS_PER_YEAR', 31557600.0);
if (!defined('UNIVERSE_EARTH_MASS')) define('UNIVERSE_EARTH_MASS', 5.972E24);
if (!defined('UNIVERSE_EARTH_RADIUS')) define('UNIVERSE_EARTH_RADIUS', 6.371E6);
if (!defined('UNIVERSE_EARTH_GRAVITY')) define('UNIVERSE_EARTH_GRAVITY', 9.80665);
if (!is_dir(__DIR__ . '/logs'))
{
        if (!mkdir(__DIR__ . '/logs', 0775, true) && !is_dir(__DIR__ . '/logs'))
        {
                throw new RuntimeException('Failed to create log directory: ' . __DIR__ . '/logs');
        }
}
if (!is_dir(__DIR__ . '/runtime'))
{
        if (!mkdir(__DIR__ . '/runtime', 0775, true) && !is_dir(__DIR__ . '/runtime'))
        {
                throw new RuntimeException('Failed to create runtime directory: ' . __DIR__ . '/runtime');
        }
}
Utility::init();
$C = new Logger("logs/console.log",true);
$A = new Logger("logs/access.log", true);
$E = new Logger("logs/error.log", true);
$D = new Logger("logs/debug.log", true);
Utility::setLog($C, L_CONSOLE);
Utility::setLog($A, L_ACCESS);
Utility::setLog($E, L_ERROR);
Utility::setLog($D, L_DEBUG);

Universe::init();
Universe::setMaxX (10000);
Universe::setMaxY (10000);
Universe::setMaxZ (10000);
Universe::setRotationSpeed (100);
Universe::setExpansionRate (2.47, 1.22, 4.0);
Universe::setLocation (5000,5000,5000);

$universe = new Universe("Marstellar", Universe::getMaxX(), Universe::getMaxY(), Universe::getMaxZ());
$requestedWorkers = universe_resolve_worker_count($options);
if ($requestedWorkers > 1 && !function_exists('\\parallel\\run'))
{
        Utility::write('parallel extension not available; reducing worker count to 1.', LOG_WARNING, L_CONSOLE);
        $requestedWorkers = 1;
}
$universe->setWorkerCount($requestedWorkers);
$simulator = new UniverseSimulator($universe);

$blueprintOptions = $options;
$blueprintOptions['seed_used'] = $blueprintSeed;
$blueprint = universe_generate_blueprint($universe, $blueprintOptions);
$simulator->bootstrap($blueprint);

$blueprintMetadata = $blueprint['metadata']['statistics'] ?? null;
if (is_array($blueprintMetadata))
{
        $logMessage = sprintf(
                'Generated %d galaxies, %d systems, and %d planets (seed %d, %d habitable, %d countries)',
                intval($blueprintMetadata['galaxies'] ?? 0),
                intval($blueprintMetadata['systems'] ?? 0),
                intval($blueprintMetadata['planets'] ?? 0),
                $blueprintSeed,
                intval($blueprintMetadata['habitable_planets'] ?? 0),
                intval($blueprintMetadata['countries'] ?? 0)
        );
        Utility::write($logMessage, LOG_INFO, L_CONSOLE);
}

/**
 * @param string[] $arguments
 * @return array<string, bool|string>
 */
function universe_parse_options (array $arguments) : array
{
        $options = array();
        foreach ($arguments as $argument)
        {
                if (!str_starts_with($argument, '--'))
                {
                        continue;
                }
                $trimmed = substr($argument, 2);
                if ($trimmed === '')
                {
                        continue;
                }
                if (str_contains($trimmed, '='))
                {
                        [$key, $value] = explode('=', $trimmed, 2);
                }
                else
                {
                        $key = $trimmed;
                        $value = true;
                }
                $options[$key] = $value;
        }
        return $options;
}

function universe_detect_cpu_count () : int
{
        $env = getenv('NUMBER_OF_PROCESSORS');
        if ($env !== false)
        {
                $count = intval($env);
                if ($count > 0)
                {
                        return $count;
                }
        }

        $statPath = '/proc/stat';
        if (is_readable($statPath))
        {
                $contents = @file_get_contents($statPath);
                if ($contents !== false)
                {
                        if (preg_match_all('/^cpu[0-9]+\s/m', $contents, $matches))
                        {
                                $count = count($matches[0]);
                                if ($count > 0)
                                {
                                        return $count;
                                }
                        }
                }
        }

        $cpuInfoPath = '/proc/cpuinfo';
        if (is_readable($cpuInfoPath))
        {
                $contents = @file_get_contents($cpuInfoPath);
                if ($contents !== false)
                {
                        if (preg_match_all('/^processor\s*:/m', $contents, $matches))
                        {
                                $count = count($matches[0]);
                                if ($count > 0)
                                {
                                        return $count;
                                }
                        }
                }
        }

        return 1;
}

function universe_resolve_worker_count (array $options) : int
{
        $default = max(1, universe_detect_cpu_count());
        $value = null;
        foreach (array('workers', 'worker-count', 'worker') as $key)
        {
                if (isset($options[$key]))
                {
                        $value = $options[$key];
                        break;
                }
        }
        if ($value === null)
        {
                return $default;
        }

        $raw = strtolower(trim(strval($value)));
        if ($raw === '' || $raw === 'auto' || $raw === 'default')
        {
                return $default;
        }

        if (!is_numeric($raw))
        {
                Utility::write('Unrecognized worker count "' . $value . '", using ' . $default . '.', LOG_WARNING, L_CONSOLE);
                return $default;
        }

        $workers = intval($raw);
        if ($workers <= 0)
        {
                Utility::write('Worker count must be positive; using ' . $default . '.', LOG_WARNING, L_CONSOLE);
                return $default;
        }

        return $workers;
}

function universe_initialize_rng (?int $seed = null) : int
{
        if ($seed === null)
        {
                try
                {
                        $seed = random_int(PHP_INT_MIN, PHP_INT_MAX);
                }
                catch (Throwable)
                {
                        $seed = mt_rand();
                }
        }
        if (function_exists('mt_srand'))
        {
                if (PHP_VERSION_ID >= 70100)
                {
                        mt_srand($seed, MT_RAND_MT19937);
                }
                else
                {
                        mt_srand($seed);
                }
        }
        return $seed;
}

function universe_rand_float (float $min, float $max) : float
{
        if ($min > $max)
        {
                [$min, $max] = array($max, $min);
        }
        if ($min === $max)
        {
                return $min;
        }
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
}

function universe_rand_int (int $min, int $max) : int
{
        if ($min > $max)
        {
                [$min, $max] = array($max, $min);
        }
        return mt_rand($min, $max);
}

function universe_pick (array $values)
{
        if (empty($values))
        {
                return null;
        }
        $index = universe_rand_int(0, count($values) - 1);
        $arrayValues = array_values($values);
        return $arrayValues[$index];
}

function universe_weighted_choice (array $choices)
{
        if (empty($choices))
        {
                return null;
        }
        $total = 0.0;
        foreach ($choices as $choice => $weight)
        {
                $total += max(0.0, floatval($weight));
        }
        if ($total <= 0.0)
        {
                return array_key_first($choices);
        }
        $roll = universe_rand_float(0.0, $total);
        $accumulated = 0.0;
        foreach ($choices as $choice => $weight)
        {
                $accumulated += max(0.0, floatval($weight));
                if ($roll <= $accumulated)
                {
                        return $choice;
                }
        }
        return array_key_last($choices);
}

function universe_clamp (float $value, float $min, float $max) : float
{
        if ($min > $max)
        {
                [$min, $max] = array($max, $min);
        }
        if ($value < $min) return $min;
        if ($value > $max) return $max;
        return $value;
}

function universe_gaussian (float $value, float $mean, float $spread) : float
{
        if ($spread <= 0.0)
        {
                return ($value === $mean) ? 1.0 : 0.0;
        }
        $delta = $value - $mean;
        return exp(-($delta * $delta) / (2.0 * $spread * $spread));
}

function universe_generate_unique_name (string $category, ?int $syllables = null) : string
{
        static $registry = array();
        $categoryKey = strtolower($category);
        if (!isset($registry[$categoryKey]))
        {
                $registry[$categoryKey] = array();
        }
        $syllableRanges = array(
                'galaxy' => array(3, 4),
                'system' => array(2, 3),
                'star' => array(2, 3),
                'planet' => array(2, 4),
                'country' => array(2, 3)
        );
        $range = $syllableRanges[$categoryKey] ?? array(2, 3);
        if ($syllables === null)
        {
                $syllables = universe_rand_int($range[0], $range[1]);
        }
        $consonants = array('b','c','d','f','g','h','j','k','l','m','n','p','qu','r','s','t','v','w','x','z','br','cr','dr','gr','pr','st','tr','vr','ph','th','cl','gl');
        $vowels = array('a','e','i','o','u','ae','ai','ia','io','oa','ou');
        $attempts = 0;
        $name = '';
        do
        {
                $attempts++;
                $segments = array();
                $useConsonant = (universe_rand_int(0, 1) === 1);
                for ($i = 0; $i < $syllables; $i++)
                {
                        if ($useConsonant)
                        {
                                $segments[] = strval(universe_pick($consonants));
                        }
                        else
                        {
                                $segments[] = strval(universe_pick($vowels));
                        }
                        $useConsonant = !$useConsonant;
                }
                $core = implode('', $segments);
                switch ($categoryKey)
                {
                        case 'galaxy':
                                $suffixes = array('ia','ara','ion','arae','ora','yx','eus');
                                $core .= strval(universe_pick($suffixes));
                                break;

                        case 'star':
                                $suffixes = array('a','on','ion','os','eus','ar','et','is');
                                $core .= strval(universe_pick($suffixes));
                                break;

                        case 'country':
                                $suffixes = array('ia','ara','on','ea','ium','ara');
                                $core .= strval(universe_pick($suffixes));
                                break;
                }
                $name = ucfirst($core);
                if ($categoryKey === 'system' && universe_rand_int(0, 1) === 1)
                {
                        $designators = array('Prime','Reach','Gate','Station','Node','Spire');
                        $name .= ' ' . strval(universe_pick($designators));
                }
        }
        while (isset($registry[$categoryKey][$name]) && $attempts < 50);
        if (isset($registry[$categoryKey][$name]))
        {
                $name .= '-' . universe_rand_int(2, 9999);
        }
        $registry[$categoryKey][$name] = true;
        return $name;
}

function universe_generate_blueprint (Universe $universe, array $options = array()) : array
{
        $galaxyCount = isset($options['galaxies']) ? max(1, intval((string)$options['galaxies'])) : universe_rand_int(12, 20);
        $systemsBaseline = isset($options['systems-per-galaxy']) ? max(2, intval((string)$options['systems-per-galaxy'])) : 14;
        $planetsBaseline = isset($options['planets-per-system']) ? max(3, intval((string)$options['planets-per-system'])) : 20;

        $remainingSpace = array(
                'x' => Universe::getMaxX(),
                'y' => Universe::getMaxY(),
                'z' => Universe::getMaxZ()
        );

        $totals = array(
                'galaxies' => 0,
                'systems' => 0,
                'planets' => 0,
                'habitable' => 0,
                'countries' => 0,
                'population_capacity' => 0,
                'habitability_sum' => 0.0
        );

        $galaxies = array();
        for ($galaxyIndex = 0; $galaxyIndex < $galaxyCount; $galaxyIndex++)
        {
                $remaining = $galaxyCount - $galaxyIndex - 1;
                $size = universe_allocate_galaxy_size($remainingSpace, $remaining);
                $systemCount = universe_rand_int(max(3, $systemsBaseline - 2), $systemsBaseline + 4);
                $systems = array();
                for ($systemIndex = 0; $systemIndex < $systemCount; $systemIndex++)
                {
                        $starProfile = universe_generate_star_profile();
                        $starProfile['name'] = universe_generate_unique_name('star');
                        $systems[] = array(
                                'name' => universe_generate_unique_name('system'),
                                'star' => $starProfile,
                                'time_step' => universe_rand_float(90.0, 7200.0),
                                'propagation_mode' => universe_weighted_choice(array(
                                        System::PROPAGATION_ANALYTIC => 0.6,
                                        System::PROPAGATION_NUMERICAL => 0.4
                                )),
                                'softening_length' => universe_rand_float(5.0E6, 5.0E7),
                                'planets' => universe_generate_planetary_system($starProfile, $planetsBaseline, $totals)
                        );
                }
                $galaxies[] = array(
                        'name' => universe_generate_unique_name('galaxy'),
                        'size' => $size,
                        'systems' => $systems
                );
                $totals['galaxies']++;
                $totals['systems'] += count($systems);
        }

        $metadata = array(
                'seed' => $options['seed_used'] ?? null,
                'parameters' => array(
                        'galaxies' => $galaxyCount,
                        'systems_per_galaxy' => $systemsBaseline,
                        'planets_per_system' => $planetsBaseline
                ),
                'statistics' => array(
                        'galaxies' => $totals['galaxies'],
                        'systems' => $totals['systems'],
                        'planets' => $totals['planets'],
                        'habitable_planets' => $totals['habitable'],
                        'countries' => $totals['countries'],
                        'population_capacity' => $totals['population_capacity'],
                        'average_habitability' => ($totals['planets'] > 0) ? $totals['habitability_sum'] / $totals['planets'] : 0.0
                )
        );

        return array(
                'metadata' => $metadata,
                'galaxies' => $galaxies
        );
}

function universe_allocate_galaxy_size (array &$remainingSpace, int $galaxiesLeft) : array
{
        $sizes = array();
        foreach (array('x', 'y', 'z') as $axis)
        {
                $available = max(500.0, floatval($remainingSpace[$axis] ?? 0.0));
                $share = $available / max(1, $galaxiesLeft + 1);
                $min = max(300.0, $share * 0.7);
                $max = max($min + 1.0, min($available, $share * 1.4));
                $sizes[$axis] = universe_rand_float($min, $max);
                $remainingSpace[$axis] = max(0.0, $available - $sizes[$axis]);
        }
        return $sizes;
}

function universe_generate_star_profile () : array
{
        $bands = array(
                array(
                        'label' => 'O',
                        'mass' => array(8.0, 16.0),
                        'radius' => array(4.0, 9.0),
                        'temperature' => array(30000.0, 38000.0),
                        'luminosity' => array(20000.0, 90000.0),
                        'weight' => 0.02,
                        'luminosity_class' => array('Ia' => 0.4, 'Ib' => 0.2, 'II' => 0.2, 'III' => 0.1, 'V' => 0.1)
                ),
                array(
                        'label' => 'B',
                        'mass' => array(2.1, 8.0),
                        'radius' => array(1.8, 5.0),
                        'temperature' => array(10000.0, 28000.0),
                        'luminosity' => array(25.0, 20000.0),
                        'weight' => 0.04,
                        'luminosity_class' => array('V' => 0.4, 'IV' => 0.2, 'III' => 0.2, 'II' => 0.1, 'Ib' => 0.1)
                ),
                array(
                        'label' => 'A',
                        'mass' => array(1.4, 2.1),
                        'radius' => array(1.3, 2.0),
                        'temperature' => array(7500.0, 10000.0),
                        'luminosity' => array(5.0, 25.0),
                        'weight' => 0.07,
                        'luminosity_class' => array('V' => 0.55, 'IV' => 0.2, 'III' => 0.2, 'II' => 0.05)
                ),
                array(
                        'label' => 'F',
                        'mass' => array(1.04, 1.4),
                        'radius' => array(1.1, 1.6),
                        'temperature' => array(6000.0, 7500.0),
                        'luminosity' => array(1.5, 5.0),
                        'weight' => 0.12,
                        'luminosity_class' => array('V' => 0.65, 'IV' => 0.2, 'III' => 0.15)
                ),
                array(
                        'label' => 'G',
                        'mass' => array(0.8, 1.04),
                        'radius' => array(0.85, 1.1),
                        'temperature' => array(5200.0, 6000.0),
                        'luminosity' => array(0.6, 1.5),
                        'weight' => 0.18,
                        'luminosity_class' => array('V' => 0.7, 'IV' => 0.15, 'III' => 0.15)
                ),
                array(
                        'label' => 'K',
                        'mass' => array(0.45, 0.8),
                        'radius' => array(0.7, 0.95),
                        'temperature' => array(3700.0, 5200.0),
                        'luminosity' => array(0.08, 0.6),
                        'weight' => 0.24,
                        'luminosity_class' => array('V' => 0.75, 'IV' => 0.15, 'III' => 0.1)
                ),
                array(
                        'label' => 'M',
                        'mass' => array(0.08, 0.45),
                        'radius' => array(0.1, 0.7),
                        'temperature' => array(2400.0, 3700.0),
                        'luminosity' => array(0.0001, 0.08),
                        'weight' => 0.33,
                        'luminosity_class' => array('V' => 0.8, 'IV' => 0.1, 'III' => 0.1)
                )
        );

        $weights = array();
        foreach ($bands as $band)
        {
                $weights[$band['label']] = $band['weight'];
        }
        $selectedLabel = universe_weighted_choice($weights);
        $band = null;
        foreach ($bands as $candidate)
        {
                if ($candidate['label'] === $selectedLabel)
                {
                        $band = $candidate;
                        break;
                }
        }
        if ($band === null)
        {
                $band = $bands[count($bands) - 1];
        }

        $massRatio = universe_rand_float($band['mass'][0], $band['mass'][1]);
        $radiusRatio = universe_rand_float($band['radius'][0], $band['radius'][1]);
        $luminosityRatio = universe_rand_float($band['luminosity'][0], $band['luminosity'][1]);
        $temperature = universe_rand_float($band['temperature'][0], $band['temperature'][1]);
        $subclass = universe_rand_int(0, 9);
        $luminosityClass = universe_weighted_choice($band['luminosity_class']);
        $spectralClass = $band['label'] . $subclass . $luminosityClass;

        return array(
                'mass' => Star::SOLAR_MASS * $massRatio,
                'radius' => 6.9634E8 * $radiusRatio,
                'luminosity' => Star::SOLAR_LUMINOSITY * $luminosityRatio,
                'temperature' => $temperature,
                'spectral_class' => $spectralClass
        );
}

function universe_generate_planetary_system (array $starProfile, int $planetsBaseline, array &$totals) : array
{
        $count = universe_rand_int(max(4, $planetsBaseline - 3), $planetsBaseline + 6);
        $slots = array();
        for ($i = 0; $i < $count; $i++)
        {
                $slots[] = universe_rand_float(0.0, 1.0);
        }
        sort($slots);
        $planets = array();
        foreach ($slots as $index => $slot)
        {
                $planetName = universe_generate_unique_name('planet');
                $planets[] = universe_build_planet_spec($planetName, $starProfile, $slot, $index, $count, $totals);
        }
        return $planets;
}

function universe_build_planet_spec (string $planetName, array $starProfile, float $normalizedOrbit, int $index, int $count, array &$totals) : array
{
        $totals['planets']++;

        $starMassRatio = max(0.05, $starProfile['mass'] / Star::SOLAR_MASS);
        $starLuminosityRatio = max(0.0001, $starProfile['luminosity'] / Star::SOLAR_LUMINOSITY);

        $planetTypeKey = universe_select_planet_type($normalizedOrbit);
        $planetCatalog = array(
                'dwarf' => array(
                        'mass' => array(0.05, 0.3),
                        'radius' => array(0.25, 0.6),
                        'radius_scale' => 0.28,
                        'greenhouse' => array(-90.0, -20.0),
                        'albedo' => array(0.4, 0.7),
                        'water' => array(0.05, 0.45),
                        'atmosphere' => array(0.0, 0.25),
                        'magnetosphere' => array(0.0, 0.25),
                        'pressure' => array(0.05, 0.3),
                        'resources' => array(0.3, 0.7),
                        'geology' => array(0.4, 0.7),
                        'climate_variance' => array(0.3, 0.6),
                        'biosignature_bias' => 0.0
                ),
                'terrestrial' => array(
                        'mass' => array(0.5, 1.5),
                        'radius' => array(0.85, 1.2),
                        'radius_scale' => 0.28,
                        'greenhouse' => array(0.0, 30.0),
                        'albedo' => array(0.2, 0.4),
                        'water' => array(0.4, 0.9),
                        'atmosphere' => array(0.5, 0.9),
                        'magnetosphere' => array(0.4, 0.8),
                        'pressure' => array(0.6, 1.4),
                        'resources' => array(0.5, 0.9),
                        'geology' => array(0.5, 0.9),
                        'climate_variance' => array(0.1, 0.3),
                        'biosignature_bias' => 0.2
                ),
                'super_earth' => array(
                        'mass' => array(1.5, 5.0),
                        'radius' => array(1.1, 1.9),
                        'radius_scale' => 0.25,
                        'greenhouse' => array(5.0, 45.0),
                        'albedo' => array(0.15, 0.35),
                        'water' => array(0.3, 0.8),
                        'atmosphere' => array(0.5, 0.95),
                        'magnetosphere' => array(0.4, 0.9),
                        'pressure' => array(0.8, 1.8),
                        'resources' => array(0.6, 0.95),
                        'geology' => array(0.5, 0.85),
                        'climate_variance' => array(0.1, 0.35),
                        'biosignature_bias' => 0.25
                ),
                'ocean' => array(
                        'mass' => array(0.8, 4.0),
                        'radius' => array(1.0, 1.8),
                        'radius_scale' => 0.27,
                        'greenhouse' => array(5.0, 35.0),
                        'albedo' => array(0.25, 0.55),
                        'water' => array(0.6, 1.0),
                        'atmosphere' => array(0.6, 0.95),
                        'magnetosphere' => array(0.4, 0.85),
                        'pressure' => array(0.8, 1.6),
                        'resources' => array(0.5, 0.85),
                        'geology' => array(0.4, 0.7),
                        'climate_variance' => array(0.1, 0.3),
                        'biosignature_bias' => 0.3
                ),
                'volcanic' => array(
                        'mass' => array(0.6, 1.8),
                        'radius' => array(0.8, 1.3),
                        'radius_scale' => 0.28,
                        'greenhouse' => array(20.0, 80.0),
                        'albedo' => array(0.15, 0.35),
                        'water' => array(0.05, 0.4),
                        'atmosphere' => array(0.4, 0.8),
                        'magnetosphere' => array(0.3, 0.7),
                        'pressure' => array(0.7, 1.6),
                        'resources' => array(0.6, 0.95),
                        'geology' => array(0.7, 1.0),
                        'climate_variance' => array(0.2, 0.5),
                        'biosignature_bias' => 0.1
                ),
                'ice_giant' => array(
                        'mass' => array(8.0, 40.0),
                        'radius' => array(2.5, 5.5),
                        'radius_scale' => 0.32,
                        'greenhouse' => array(-120.0, -40.0),
                        'albedo' => array(0.4, 0.7),
                        'water' => array(0.1, 0.5),
                        'atmosphere' => array(0.2, 0.6),
                        'magnetosphere' => array(0.3, 0.7),
                        'pressure' => array(0.5, 1.2),
                        'resources' => array(0.5, 0.9),
                        'geology' => array(0.4, 0.7),
                        'climate_variance' => array(0.2, 0.5),
                        'biosignature_bias' => 0.0
                ),
                'gas_giant' => array(
                        'mass' => array(30.0, 250.0),
                        'radius' => array(5.0, 12.0),
                        'radius_scale' => 0.5,
                        'greenhouse' => array(60.0, 150.0),
                        'albedo' => array(0.3, 0.6),
                        'water' => array(0.0, 0.2),
                        'atmosphere' => array(0.7, 1.0),
                        'magnetosphere' => array(0.6, 1.0),
                        'pressure' => array(1.0, 2.0),
                        'resources' => array(0.7, 1.0),
                        'geology' => array(0.2, 0.5),
                        'climate_variance' => array(0.2, 0.5),
                        'biosignature_bias' => 0.0
                )
        );
        if (!isset($planetCatalog[$planetTypeKey]))
        {
                $planetTypeKey = 'terrestrial';
        }
        $type = $planetCatalog[$planetTypeKey];

        $massRatio = universe_rand_float($type['mass'][0], $type['mass'][1]);
        $radiusRatio = universe_clamp(pow($massRatio, $type['radius_scale']), $type['radius'][0], $type['radius'][1]);

        $mass = UNIVERSE_EARTH_MASS * $massRatio;
        $radius = UNIVERSE_EARTH_RADIUS * $radiusRatio;

        $innerLimit = 0.08;
        $outerLimit = 45.0 * max(0.6, sqrt($starMassRatio));
        $semiMajorAxisAu = universe_clamp($innerLimit + $normalizedOrbit * ($outerLimit - $innerLimit), $innerLimit, $outerLimit);
        $semiMajorAxisAu = min($semiMajorAxisAu + ($index * 0.05), $outerLimit);
        $semiMajorAxis = $semiMajorAxisAu * UNIVERSE_ASTRONOMICAL_UNIT;

        $periodYears = sqrt(pow($semiMajorAxisAu, 3) / max(0.05, $starMassRatio));
        $periodSeconds = $periodYears * UNIVERSE_SECONDS_PER_YEAR;

        $eccentricity = universe_clamp(universe_rand_float(0.0, 0.25 + $normalizedOrbit * 0.5), 0.0, 0.92);
        $inclination = universe_rand_float(0.0, 5.0 + $normalizedOrbit * 12.0);
        $ascendingNode = universe_rand_float(0.0, 360.0);
        $argumentOfPeriapsis = universe_rand_float(0.0, 360.0);
        $phase = universe_rand_float(0.0, 2.0 * pi());

        $albedo = universe_clamp(universe_rand_float($type['albedo'][0], $type['albedo'][1]), 0.0, 0.95);
        $greenhouse = universe_rand_float($type['greenhouse'][0], $type['greenhouse'][1]);
        $flux = $starLuminosityRatio / max(0.01, $semiMajorAxisAu * $semiMajorAxisAu);
        $fluxAdjusted = max(0.0001, $flux * (1.0 - $albedo));
        $equilibriumTempK = 278.0 * pow($fluxAdjusted, 0.25);
        $temperatureC = ($equilibriumTempK - 273.15) + $greenhouse;

        $water = universe_clamp(universe_rand_float($type['water'][0], $type['water'][1]), 0.0, 1.0);
        $atmosphere = universe_clamp(universe_rand_float($type['atmosphere'][0], $type['atmosphere'][1]), 0.0, 1.0);
        $magnetosphere = universe_clamp(universe_rand_float($type['magnetosphere'][0], $type['magnetosphere'][1]), 0.0, 1.0);
        $pressureBase = universe_rand_float($type['pressure'][0], $type['pressure'][1]);
        $resources = universe_clamp(universe_rand_float($type['resources'][0], $type['resources'][1]), 0.0, 1.0);
        $geology = universe_clamp(universe_rand_float($type['geology'][0], $type['geology'][1]), 0.0, 1.0);
        $climateVariance = universe_clamp(universe_rand_float($type['climate_variance'][0], $type['climate_variance'][1]), 0.0, 1.0);

        $gravityRatio = max(0.05, $massRatio / ($radiusRatio * $radiusRatio));

        $temperatureSuitability = universe_gaussian($temperatureC, 15.0, 45.0);
        $water = universe_clamp($water * (0.4 + $temperatureSuitability), 0.0, 1.0);
        if ($temperatureC < -80.0 || $temperatureC > 120.0)
        {
                $water *= 0.3;
        }

        $atmosphere = universe_clamp($atmosphere * (0.5 + min($gravityRatio, 2.0) / 2.0), 0.0, 1.0);
        $magnetosphere = universe_clamp($magnetosphere * (0.4 + min($gravityRatio, 2.5) / 2.5), 0.0, 1.0);

        $pressureRatio = $pressureBase * (0.6 + min($gravityRatio, 3.0) / 3.0) + $atmosphere * 0.4;

        $shielding = min(1.0, ($magnetosphere * 0.7) + ($atmosphere * 0.3));
        $radiationHazard = min(1.0, ($flux / 2.5)) * (1.0 - $shielding);
        $radiation = universe_clamp(1.0 - $radiationHazard, 0.0, 1.0);

        $climateVariance = universe_clamp($climateVariance + ($eccentricity * 1.2), 0.0, 1.0);
        if ($planetTypeKey === 'volcanic')
        {
                $climateVariance = universe_clamp($climateVariance + 0.2, 0.0, 1.0);
                $geology = universe_clamp($geology + 0.1, 0.0, 1.0);
        }
        if ($planetTypeKey === 'gas_giant')
        {
                $resources = universe_clamp($resources + 0.1, 0.0, 1.0);
        }

        $biosignatures = 0.0;
        if (in_array($planetTypeKey, array('terrestrial', 'super_earth', 'ocean', 'volcanic'), true))
        {
                $biosignatures = universe_clamp(
                        ($temperatureSuitability * $water * $atmosphere * $radiation) + $type['biosignature_bias'],
                        0.0,
                        1.0
                );
                $biosignatures *= universe_clamp(1.0 - $climateVariance, 0.0, 1.0);
        }

        $environment = array(
                'temperature' => $temperatureC,
                'water' => $water,
                'atmosphere' => $atmosphere,
                'magnetosphere' => $magnetosphere,
                'biosignatures' => $biosignatures,
                'gravity' => universe_clamp($gravityRatio, 0.0, 3.0),
                'pressure' => universe_clamp($pressureRatio, 0.0, 3.0),
                'radiation' => $radiation,
                'resources' => $resources,
                'geology' => $geology,
                'stellar_flux' => $flux,
                'climate_variance' => $climateVariance
        );

        $analysis = Planet::analyzeHabitability($environment);
        $totals['habitability_sum'] += $analysis['score'];
        if ($analysis['habitable'])
        {
                $totals['habitable']++;
        }

        $countries = array();
        if ($analysis['habitable'])
        {
                $countryTotal = universe_rand_int(2, 6);
                for ($i = 0; $i < $countryTotal; $i++)
                {
                        $profile = universe_generate_country_profile($analysis['score'], $environment);
                        $countryName = universe_generate_unique_name('country');
                        $spawn = (int) round($profile['population_capacity'] * universe_rand_float(0.35, 0.75));
                        $spawn = max(0, min($spawn, $profile['population_capacity']));
                        $countries[] = array(
                                'name' => $countryName,
                                'profile' => $profile,
                                'spawn_people' => $spawn
                        );
                        $totals['countries']++;
                        $totals['population_capacity'] += $profile['population_capacity'];
                }
        }

        $planet = array(
                'name' => $planetName,
                'mass' => $mass,
                'radius' => $radius,
                'environment' => $environment,
                'orbit' => array(
                        'semi_major_axis' => $semiMajorAxis,
                        'period' => $periodSeconds,
                        'eccentricity' => $eccentricity,
                        'inclination_deg' => $inclination,
                        'ascending_node_deg' => $ascendingNode,
                        'argument_of_periapsis_deg' => $argumentOfPeriapsis,
                        'phase' => $phase
                ),
                'metadata' => array(
                        'type' => $planetTypeKey,
                        'habitability_score' => $analysis['score']
                )
        );
        if (!empty($countries))
        {
                $planet['countries'] = $countries;
        }

        return $planet;
}

function universe_select_planet_type (float $normalizedOrbit) : string
{
        $weights = array(
                'dwarf' => 0.1,
                'terrestrial' => 0.2,
                'super_earth' => 0.15,
                'ocean' => 0.1,
                'volcanic' => 0.08,
                'ice_giant' => 0.18,
                'gas_giant' => 0.19
        );
        if ($normalizedOrbit < 0.2)
        {
                $weights['terrestrial'] += 0.1;
                $weights['volcanic'] += 0.05;
                $weights['gas_giant'] = max(0.02, $weights['gas_giant'] - 0.08);
                $weights['ice_giant'] = max(0.02, $weights['ice_giant'] - 0.05);
        }
        elseif ($normalizedOrbit > 0.6)
        {
                $weights['gas_giant'] += 0.15;
                $weights['ice_giant'] += 0.1;
                $weights['dwarf'] += 0.05;
                $weights['terrestrial'] = max(0.02, $weights['terrestrial'] - 0.1);
                $weights['volcanic'] = max(0.02, $weights['volcanic'] - 0.05);
        }
        foreach ($weights as $key => $value)
        {
                $weights[$key] = max(0.01, $value);
        }
        return strval(universe_weighted_choice($weights));
}

function universe_generate_country_profile (float $habitabilityScore, array $environment) : array
{
        $score = universe_clamp($habitabilityScore, 0.0, 1.0);
        $climateVariance = universe_clamp(floatval($environment['climate_variance'] ?? 0.0), 0.0, 1.0);
        $resourceBase = universe_clamp(floatval($environment['resources'] ?? 0.5), 0.0, 1.0);

        $infrastructure = universe_clamp($score + universe_rand_float(-0.15, 0.2), 0.2, 0.98);
        $technology = universe_clamp($score + universe_rand_float(-0.1, 0.25), 0.2, 0.98);
        $resources = universe_clamp($resourceBase + universe_rand_float(-0.1, 0.2), 0.2, 1.0);
        $stability = universe_clamp($score + universe_rand_float(-0.2, 0.2) - ($climateVariance * 0.3), 0.15, 0.98);
        $adaptation = universe_clamp($score + universe_rand_float(-0.05, 0.25), 0.2, 1.0);

        $populationCapacity = (int) round(universe_rand_float(0.6, 1.4) * 1000000 * max(0.3, $score));
        $populationCapacity = max(50000, $populationCapacity);
        $developmentRate = universe_clamp(1.0 + $score * 2.5 + universe_rand_float(-0.2, 1.0), 0.5, 5.0);

        return array(
                'infrastructure' => $infrastructure,
                'technology' => $technology,
                'resources' => $resources,
                'stability' => $stability,
                'population_capacity' => $populationCapacity,
                'development_rate' => $developmentRate,
                'starting_food' => $populationCapacity * universe_rand_float(0.4, 0.9),
                'starting_materials' => $populationCapacity * universe_rand_float(0.2, 0.6),
                'starting_wealth' => $populationCapacity * universe_rand_float(0.1, 0.5),
                'adaptation' => $adaptation,
                'immortality_chance' => universe_clamp(universe_rand_float(0.0, $score * 0.2), 0.0, 0.2)
        );
}

function universe_print_summary (Universe $universe) : void
{
        echo "Simulation completed (" . $universe->getTicks() . " ticks)." . PHP_EOL;
        foreach ($universe->getGalaxies() as $galaxyName => $galaxy)
        {
                if (!($galaxy instanceof Galaxy))
                {
                        continue;
                }
                echo "Galaxy: {$galaxyName}" . PHP_EOL;
                foreach ($galaxy->getSystems() as $systemName => $system)
                {
                        if (!($system instanceof System))
                        {
                                continue;
                        }
                        echo "  System: {$systemName} | Age: " . round($system->getAge(), 2) . "s | Objects: " . $system->countObjects() . PHP_EOL;
                        foreach ($system->getPlanets() as $planetName => $planet)
                        {
                                if (!($planet instanceof Planet))
                                {
                                        continue;
                                }
                                $summary = $planet->getPopulationSummary();
                                $classification = ucfirst($summary['classification'] ?? 'unknown');
                                $habitability = round($summary['habitability'], 2);
                                $countryCount = $summary['countries'];
                                $line = "    Planet: {$planetName} | Class: {$classification} | Habitability: {$habitability} | Population: " . $summary['population'];
                                if ($countryCount > 0)
                                {
                                        $line .= " | Countries: {$countryCount}";
                                }
                                $factors = $summary['factors'] ?? array();
                                if (is_array($factors) && !empty($factors))
                                {
                                        arsort($factors);
                                        $topFactors = array_slice(array_keys($factors), 0, 2);
                                        $factorLabels = array();
                                        foreach ($topFactors as $factorKey)
                                        {
                                                $value = $factors[$factorKey];
                                                $label = ucfirst(str_replace('_', ' ', strval($factorKey)));
                                                $factorLabels[] = $label . ':' . round($value, 2);
                                        }
                                        if (!empty($factorLabels))
                                        {
                                                $line .= ' | Drivers: ' . implode(', ', $factorLabels);
                                        }
                                }
                                echo $line . PHP_EOL;
                                foreach ($planet->getCountries() as $countryName => $country)
                                {
                                        if (!($country instanceof Country))
                                        {
                                                continue;
                                        }
                                        $report = $country->getReadinessReport();
                                        echo "      Country: {$countryName} | Population: " . $report['population'] . "/" . $report['population_capacity'];
                                        echo " | Development: " . round($country->getDevelopmentScore(), 2);
                                        if (!$country->isReadyForPopulation())
                                        {
                                                echo " (developing)";
                                        }
                                        echo PHP_EOL;
                                }
                        }
                }
        }
}

function universe_print_usage () : void
{
        echo "Universe simulator usage:" . PHP_EOL;
        echo "  php universe.php start [--delta=3600] [--interval=1] [--auto-steps=1] [--socket=path] [--pid-file=path] [--no-daemonize] [--workers=auto]" . PHP_EOL;
        echo "  php universe.php run-once [--steps=10] [--delta=3600] [--tick-delay=0] [--workers=auto]" . PHP_EOL;
        echo "  php universe.php catalog [--format=json] [--pretty] [--people-limit=50] [--chronicle-limit=12]" . PHP_EOL;
        echo "  php universe.php --help" . PHP_EOL;
        echo "  php universe.php help" . PHP_EOL;
        echo PHP_EOL . "Generation options:" . PHP_EOL;
        echo "  --seed=<int>                Use a deterministic RNG seed for the generated universe" . PHP_EOL;
        echo "  --galaxies=<int>            Override the number of galaxies generated" . PHP_EOL;
        echo "  --systems-per-galaxy=<int>  Target system count per galaxy before variance" . PHP_EOL;
        echo "  --planets-per-system=<int>  Target planet count per system before variance" . PHP_EOL;
        echo "  --workers=<int|auto>        Number of worker threads to advance galaxies in parallel (default auto)" . PHP_EOL;
        echo PHP_EOL . "Catalog options:" . PHP_EOL;
        echo "  --people-limit=<int>        Maximum number of citizens per country to include (default 50)" . PHP_EOL;
        echo "  --chronicle-limit=<int>     Maximum chronicle entries to retain per object (default 12)" . PHP_EOL;
        echo "  --format=json               Output format (currently only json)" . PHP_EOL;
        echo "  --pretty                    Pretty-print JSON output" . PHP_EOL;
}

switch ($command ?? UniverseCommand::Start)
{
        case UniverseCommand::Start:
                $deltaTime = isset($options['delta']) ? floatval((string)$options['delta']) : 3600.0;
                if ($deltaTime <= 0)
                {
                        Utility::write('Delta time of ' . $deltaTime . ' seconds supplied; the daemon will reuse it without clamping.', LOG_WARNING, L_CONSOLE);
                }
                $loopInterval = isset($options['interval']) ? floatval((string)$options['interval']) : 1.0;
                if ($loopInterval < 0)
                {
                        Utility::write('Loop interval cannot be negative; using 0 to run as fast as possible.', LOG_WARNING, L_CONSOLE);
                        $loopInterval = 0.0;
                }
                $autoSteps = isset($options['auto-steps']) ? intval((string)$options['auto-steps']) : 1;
                if ($autoSteps <= 0)
                {
                        Utility::write('Auto-steps must be greater than zero; using 1.', LOG_WARNING, L_CONSOLE);
                        $autoSteps = 1;
                }
                $daemonOptions = array(
                        'socket' => $options['socket'] ?? (__DIR__ . '/runtime/universe.sock'),
                        'pid_file' => $options['pid-file'] ?? (__DIR__ . '/runtime/universe.pid'),
                        'delta_time' => $deltaTime,
                        'loop_interval' => $loopInterval,
                        'auto_steps' => $autoSteps
                );
                $daemon = new UniverseDaemon($simulator, $daemonOptions);
                $shouldExit = empty($options['no-daemonize']) && $daemon->daemonize();
                if ($shouldExit)
                {
                        $pid = $daemon->getForkedPid();
                        if ($pid !== null)
                        {
                                echo "Universe daemon started with PID {$pid}." . PHP_EOL;
                        }
                        else
                        {
                                echo "Universe daemon started." . PHP_EOL;
                        }
                        exit(0);
                }
                $daemon->run();
                break;

        case UniverseCommand::RunOnce:
                $steps = isset($options['steps']) ? max(1, intval((string)$options['steps'])) : 10;
                $deltaTime = isset($options['delta']) ? floatval((string)$options['delta']) : 3600.0;
                if ($deltaTime <= 0)
                {
                        Utility::write('Run-once delta of ' . $deltaTime . ' seconds supplied; executing without clamping.', LOG_WARNING, L_CONSOLE);
                }
                $tickDelay = isset($options['tick-delay']) ? floatval((string)$options['tick-delay']) : 0.0;
                if ($tickDelay < 0.0)
                {
                        Utility::write('Tick delay cannot be negative; using 0.', LOG_WARNING, L_CONSOLE);
                        $tickDelay = 0.0;
                }
                $simulator->run($steps, $deltaTime, $tickDelay);
                universe_print_summary($universe);
                break;

        case UniverseCommand::Catalog:
                $peopleLimit = isset($options['people-limit']) ? max(0, intval((string)$options['people-limit'])) : 50;
                $chronicleLimit = isset($options['chronicle-limit']) ? max(0, intval((string)$options['chronicle-limit'])) : 12;
                $format = strtolower(strval($options['format'] ?? 'json'));
                $pretty = array_key_exists('pretty', $options);
                $catalog = universe_build_catalog($universe, array(
                        'people_limit' => $peopleLimit,
                        'chronicle_limit' => $chronicleLimit
                ));
                if ($format !== 'json')
                {
                        echo "Unsupported catalog format '{$format}'." . PHP_EOL;
                        exit(1);
                }
                $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
                if ($pretty)
                {
                        $flags |= JSON_PRETTY_PRINT;
                }
                $encoded = json_encode($catalog, $flags);
                if ($encoded === false)
                {
                        echo 'Failed to encode catalog: ' . json_last_error_msg() . PHP_EOL;
                        exit(1);
                }
                echo $encoded . PHP_EOL;
                break;

        case UniverseCommand::Help:
                universe_print_usage();
                break;
}


function universe_random_choice (array $values, $fallback = null)
{
        if (empty($values)) return $fallback;
        return $values[mt_rand(0, count($values) - 1)];
}

function universe_generate_symbol (string $name, array &$used) : string
{
        $letters = preg_replace('/[^A-Za-z]/', '', $name);
        $letters = ($letters === '') ? 'Element' : $letters;
        $letters = ucfirst(strtolower($letters));
        $primary = strtoupper($letters[0]);
        $secondary = (strlen($letters) > 1) ? strtolower($letters[1]) : '';
        $symbol = $primary . $secondary;
        if (!isset($used[$symbol]))
        {
                $used[$symbol] = true;
                return $symbol;
        }
        for ($i = 2; $i < strlen($letters); $i++)
        {
                $candidate = $primary . strtolower($letters[$i]);
                if (!isset($used[$candidate]))
                {
                        $used[$candidate] = true;
                        return $candidate;
                }
        }
        do
        {
                $candidate = chr(mt_rand(65, 90)) . chr(mt_rand(97, 122));
        }
        while (isset($used[$candidate]));
        $used[$candidate] = true;
        return $candidate;
}

function universe_catalog_object_category (SystemObject $object) : string
{
        if ($object instanceof Planet)
        {
                return 'planet';
        }
        if ($object instanceof Star)
        {
                return 'star';
        }
        if ($object instanceof TransitObject)
        {
                return 'transit';
        }
        $type = strtolower($object->getType());
        if ($type !== '')
        {
                return $type;
        }
        return 'object';
}

function universe_catalog_vector (array $vector) : array
{
        return array(
                'x' => floatval($vector['x'] ?? 0.0),
                'y' => floatval($vector['y'] ?? 0.0),
                'z' => floatval($vector['z'] ?? 0.0)
        );
}

function universe_vector_distance (array $a, array $b) : float
{
        $ax = floatval($a['x'] ?? 0.0);
        $ay = floatval($a['y'] ?? 0.0);
        $az = floatval($a['z'] ?? 0.0);
        $bx = floatval($b['x'] ?? 0.0);
        $by = floatval($b['y'] ?? 0.0);
        $bz = floatval($b['z'] ?? 0.0);

        $dx = $ax - $bx;
        $dy = $ay - $by;
        $dz = $az - $bz;

        return sqrt(($dx * $dx) + ($dy * $dy) + ($dz * $dz));
}

function universe_catalog_dynamics (SystemObject $object, int $nearbyLimit = 3) : array
{
        $snapshot = array(
                'position' => universe_catalog_vector($object->getPosition()),
                'velocity' => universe_catalog_vector($object->getVelocity()),
                'speed' => $object->getSpeed(),
                'mass' => $object->getMass(),
                'radius' => $object->getRadius(),
        );

        $system = $object->getParentSystem();
        if ($system instanceof System)
        {
                $tick = $system->getTimeStep();
                $snapshot['tick_seconds'] = $tick;
                $snapshot['time_step'] = $tick;
                $nearby = array();
                foreach ($system->getObjects() as $candidate)
                {
                        if (!($candidate instanceof SystemObject) || $candidate === $object)
                        {
                                continue;
                        }
                        $nearby[] = array(
                                'name' => $candidate->getName(),
                                'category' => universe_catalog_object_category($candidate),
                                'position' => universe_catalog_vector($candidate->getPosition()),
                                'velocity' => universe_catalog_vector($candidate->getVelocity()),
                                'speed' => $candidate->getSpeed(),
                                'mass' => $candidate->getMass(),
                                'radius' => $candidate->getRadius(),
                                'distance' => universe_vector_distance($object->getPosition(), $candidate->getPosition()),
                        );
                }
                usort($nearby, function (array $left, array $right) : int {
                        $a = floatval($left['distance'] ?? 0.0);
                        $b = floatval($right['distance'] ?? 0.0);
                        return $a <=> $b;
                });
                if ($nearbyLimit > 0)
                {
                        $nearby = array_slice($nearby, 0, $nearbyLimit);
                }
                $snapshot['nearby'] = $nearby;
        }

        return $snapshot;
}

function universe_catalog_system_dynamics (System $system) : array
{
        $objects = array();
        foreach ($system->getObjects() as $object)
        {
                if (!($object instanceof SystemObject)) continue;
                $objects[] = array(
                        'name' => $object->getName(),
                        'category' => universe_catalog_object_category($object),
                        'position' => universe_catalog_vector($object->getPosition()),
                        'velocity' => universe_catalog_vector($object->getVelocity()),
                        'speed' => $object->getSpeed(),
                        'mass' => $object->getMass(),
                        'radius' => $object->getRadius(),
                );
        }

        return array(
                'tick_seconds' => $system->getTimeStep(),
                'objects' => $objects,
        );
}

function universe_catalog_galaxy_dynamics (Galaxy $galaxy) : array
{
        $snapshot = array(
                'position' => universe_catalog_vector($galaxy->getLocation()),
                'velocity' => array('x' => 0.0, 'y' => 0.0, 'z' => 0.0),
                'influence_radius' => $galaxy->getInfluenceRadius(),
        );

        return $snapshot;
}

function universe_build_catalog (Universe $universe, array $options = array()) : array
{
        $peopleLimit = max(0, intval($options['people_limit'] ?? 50));
        $chronicleLimit = max(0, intval($options['chronicle_limit'] ?? 12));

        $lore = LoreForge::describeUniverse($universe);
        $root = array(
                'category' => 'universe',
                'icon' => 'universe',
                'name' => $universe->getName(),
                'summary' => $lore['summary'] ?? '',
                'description' => $lore['description'] ?? '',
                'statistics' => $lore['statistics'] ?? array(),
                'chronicle' => array_slice($lore['chronicle'] ?? array(), -$chronicleLimit),
                'children' => array()
        );

        $totals = array('galaxies' => 0, 'systems' => 0, 'planets' => 0, 'habitable' => 0, 'countries' => 0, 'population' => 0);
        foreach ($universe->getGalaxies() as $galaxyName => $galaxy)
        {
                if (!($galaxy instanceof Galaxy)) continue;
                $root['children'][] = universe_catalog_galaxy($galaxy, $chronicleLimit, $peopleLimit, $totals);
        }

        $materials = universe_generate_material_catalog(array('chronicle_limit' => $chronicleLimit));
        if (!empty($materials))
        {
                $root['children'][] = $materials;
        }

        $root['statistics']['galaxies'] = $totals['galaxies'];
        $root['statistics']['systems'] = $totals['systems'];
        $root['statistics']['planets'] = $totals['planets'];
        $root['statistics']['habitable_planets'] = $totals['habitable'];
        $root['statistics']['countries'] = $totals['countries'];
        $root['statistics']['population'] = $totals['population'];

        return $root;
}

function universe_catalog_galaxy (Galaxy $galaxy, int $chronicleLimit, int $peopleLimit, array &$totals) : array
{
        $systems = array();
        foreach ($galaxy->getSystems() as $system)
        {
                if (!($system instanceof System)) continue;
                $systems[] = universe_catalog_system($system, $chronicleLimit, $peopleLimit, $totals);
        }
        $totals['galaxies']++;
        $bounds = method_exists($galaxy, 'getBounds') ? $galaxy->getBounds() : array();
        $chronicle = method_exists($galaxy, 'getChronicle') ? $galaxy->getChronicle($chronicleLimit) : array();
        return array(
                'category' => 'galaxy',
                'icon' => 'galaxy',
                'name' => $galaxy->name,
                'summary' => sprintf('%d systems recorded.', count($systems)),
                'description' => method_exists($galaxy, 'getDescription') ? $galaxy->getDescription() : '',
                'image' => method_exists($galaxy, 'exportVisualAsset') ? $galaxy->exportVisualAsset() : null,
                'statistics' => array(
                        'system_count' => count($systems),
                        'bounds' => $bounds
                ),
                'chronicle' => array_slice($chronicle, -$chronicleLimit),
                'children' => $systems,
                'metadata' => array(
                        'dynamics' => universe_catalog_galaxy_dynamics($galaxy)
                )
        );
}

function universe_catalog_system (System $system, int $chronicleLimit, int $peopleLimit, array &$totals) : array
{
        $children = array();
        $star = $system->getPrimaryStar();
        if ($star instanceof Star)
        {
                $children[] = universe_catalog_star($star, $chronicleLimit);
        }
        $planets = array();
        foreach ($system->getPlanets() as $planet)
        {
                if (!($planet instanceof Planet)) continue;
                $planets[] = universe_catalog_planet($planet, $chronicleLimit, $peopleLimit, $totals);
        }
        $children = array_merge($children, $planets);
        $totals['systems']++;
        $chronicle = method_exists($system, 'getChronicle') ? $system->getChronicle($chronicleLimit) : array();
        return array(
                'category' => 'system',
                'icon' => 'system',
                'name' => $system->getName(),
                'summary' => sprintf('%d planets orbit within the %s cadence.', count($planets), $system->getPropagationMode()),
                'description' => method_exists($system, 'getDescription') ? $system->getDescription() : '',
                'image' => method_exists($system, 'exportVisualAsset') ? $system->exportVisualAsset() : null,
                'statistics' => array(
                        'age_seconds' => $system->getAge(),
                        'propagation_mode' => $system->getPropagationMode(),
                        'time_step_seconds' => $system->getTimeStep()
                ),
                'chronicle' => array_slice($chronicle, -$chronicleLimit),
                'children' => $children,
                'metadata' => array(
                        'dynamics' => universe_catalog_system_dynamics($system)
                )
        );
}

function universe_catalog_star (Star $star, int $chronicleLimit) : array
{
        $massRatio = ($star->getMass() > 0) ? $star->getMass() / Star::SOLAR_MASS : 0.0;
        $chronicle = $star->getChronicle($chronicleLimit);
        $lore = LoreForge::describeStar($star, array());
        if (!empty($lore['chronicle']))
        {
                $chronicle = array_merge($chronicle, $lore['chronicle']);
        }
        return array(
                'category' => 'star',
                'icon' => 'star',
                'name' => $star->getName(),
                'summary' => sprintf('%s star at %.2f solar masses.', $star->getSpectralClass(), $massRatio),
                'description' => $star->getDescription(),
                'image' => method_exists($star, 'exportVisualAsset') ? $star->exportVisualAsset() : null,
                'statistics' => array(
                        'mass' => $star->getMass(),
                        'radius' => $star->getRadius(),
                        'luminosity' => $star->getLuminosity(),
                        'temperature' => $star->getTemperature(),
                        'stage' => $star->getStage()
                ),
                'metadata' => array(
                        'dynamics' => universe_catalog_dynamics($star, 5)
                ),
                'chronicle' => array_slice($chronicle, -$chronicleLimit),
                'children' => array()
        );
}

function universe_catalog_planet (Planet $planet, int $chronicleLimit, int $peopleLimit, array &$totals) : array
{
        $summary = $planet->getPopulationSummary();
        $countries = array();
        foreach ($planet->getCountries() as $country)
        {
                if (!($country instanceof Country)) continue;
                $countries[] = universe_catalog_country($country, $chronicleLimit, $peopleLimit, $totals);
        }
        $totals['planets']++;
        if ($planet->isReadyForCivilization())
        {
                $totals['habitable']++;
        }
        $chronicle = $planet->getChronicle($chronicleLimit);
        $lore = LoreForge::describePlanet($planet, array());
        if (!empty($lore['chronicle']))
        {
                $chronicle = array_merge($chronicle, $lore['chronicle']);
        }
        return array(
                'category' => 'planet',
                'icon' => 'planet',
                'name' => $planet->getName(),
                'summary' => sprintf('%s world with %d countries and population %d.',
                        ucfirst(strval($summary['classification'] ?? $planet->getHabitabilityClassification())),
                        count($countries),
                        intval($summary['population'] ?? 0)
                ),
                'description' => $planet->getDescription(),
                'image' => method_exists($planet, 'exportVisualAsset') ? $planet->exportVisualAsset() : null,
                'statistics' => array(
                        'habitability_score' => $summary['habitability'] ?? $planet->getHabitabilityScore(),
                        'classification' => $summary['classification'] ?? $planet->getHabitabilityClassification(),
                        'population' => $summary['population'] ?? 0,
                        'country_count' => count($countries),
                        'environment' => $planet->getEnvironmentSnapshot(),
                        'timekeeping' => $planet->getTimekeepingProfile(),
                        'net_worth' => $summary['net_worth'] ?? $planet->getNetWorth(),
                        'life_breakdown' => $summary['biosphere'] ?? $planet->getBiosphereComposition()
                ),
                'chronicle' => array_slice($chronicle, -$chronicleLimit),
                'children' => $countries,
                'metadata' => array(
                        'dynamics' => universe_catalog_dynamics($planet),
                        'weather' => array(
                                'current' => $planet->getCurrentWeather(),
                                'history' => array_slice($planet->getWeatherHistory($chronicleLimit), 0, $chronicleLimit)
                        ),
                        'map' => $planet->getMapOverview($peopleLimit)
                )
        );
}

function universe_allocate_city_quota (int $globalLimit, int $remaining, int $totalResidents, int $cityPopulation, int $cityCount, int $index) : int
{
        if ($globalLimit <= 0 || $remaining <= 0 || $cityCount <= 0)
        {
                return 0;
        }
        if ($totalResidents <= 0)
        {
                if ($index === $cityCount - 1)
                {
                        return $remaining;
                }
                $perCity = intval(floor($globalLimit / $cityCount));
                return max(0, min($remaining, max(1, $perCity)));
        }
        if ($cityPopulation <= 0)
        {
                if ($index === $cityCount - 1)
                {
                        return $remaining;
                }
                return max(0, min($remaining, max(1, intval($globalLimit / max(1, $cityCount)))));
        }
        $share = intval(round(($cityPopulation / max(1, $totalResidents)) * $globalLimit));
        $share = max(1, $share);
        $share = min($share, $remaining);
        if ($index === $cityCount - 1)
        {
                $share = $remaining;
        }
        return $share;
}

function universe_catalog_country (Country $country, int $chronicleLimit, int $peopleLimit, array &$totals) : array
{
        $totals['countries']++;
        $totals['population'] += $country->getPopulation();
        $people = $country->getPeople();
        $cities = $country->getCities();
        if (method_exists($country, 'ensureVisualAsset'))
        {
                $country->ensureVisualAsset('primary', function () use ($country) : array {
                        return VisualForge::country($country, array());
                });
        }
        $cityNodes = array();
        $totalResidents = count($people);
        $remaining = ($peopleLimit > 0) ? $peopleLimit : 0;
        $cityCount = count($cities);
        foreach ($cities as $index => $city)
        {
                if (!($city instanceof City)) continue;
                $cityPopulation = max(0, $city->getPopulation());
                $allocation = ($peopleLimit > 0)
                        ? universe_allocate_city_quota($peopleLimit, $remaining, $totalResidents, $cityPopulation, $cityCount, $index)
                        : 0;
                if ($peopleLimit > 0)
                {
                        $remaining = max(0, $remaining - $allocation);
                }
                $cityNodes[] = universe_catalog_city($city, $country, $chronicleLimit, $allocation);
        }
        $chronicle = $country->getChronicle();
        $description = $country->getDescription();
        $metadata = array(
                'cultural_backdrop' => $country->getCulturalBackdrop(),
                'territory' => $country->getTerritoryProfile(),
                'map' => array(
                        'cities' => array_map(function ($city) use ($country) {
                                if (!($city instanceof City)) return null;
                                return array(
                                        'name' => $city->getName(),
                                        'country' => $country->getName(),
                                        'coordinates' => $city->getCoordinates(),
                                        'radius' => $city->getRadius(),
                                        'population' => $city->getPopulation()
                                );
                        }, $cities)
                )
        );
        $metadata['map']['cities'] = array_values(array_filter($metadata['map']['cities']));
        if ($peopleLimit > 0 && $totalResidents > $peopleLimit)
        {
                $metadata['omitted_citizens'] = $totalResidents - $peopleLimit;
        }
        return array(
                'category' => 'country',
                'icon' => 'country',
                'name' => $country->getName(),
                'summary' => sprintf('Population %d of %d capacity.', $country->getPopulation(), $country->getPopulationCapacity()),
                'description' => $description,
                'image' => method_exists($country, 'exportVisualAsset') ? $country->exportVisualAsset() : null,
                'statistics' => array(
                        'population' => $country->getPopulation(),
                        'capacity' => $country->getPopulationCapacity(),
                        'resources' => $country->getResourceReport(),
                        'net_worth' => $country->getNetWorth(),
                        'wealth_per_capita' => $country->getWealthPerCapita()
                ),
                'chronicle' => array_slice($chronicle, -$chronicleLimit),
                'children' => $cityNodes,
                'metadata' => $metadata
        );
}

function universe_catalog_city (City $city, Country $country, int $chronicleLimit, int $peopleLimit) : array
{
        $residents = $city->getResidents();
        $selected = ($peopleLimit > 0) ? array_slice($residents, 0, $peopleLimit) : $residents;
        if (method_exists($city, 'ensureVisualAsset'))
        {
                $city->ensureVisualAsset('primary', function () use ($city) : array {
                        return VisualForge::city($city, array());
                });
        }
        $children = array();
        foreach ($selected as $person)
        {
                if (!($person instanceof Person)) continue;
                $children[] = universe_catalog_person($person, $chronicleLimit);
        }
        $chronicle = method_exists($city, 'getChronicle') ? $city->getChronicle($chronicleLimit) : array();
        $coordinates = $city->getCoordinates();
        $metadata = array(
                'map' => array(
                        'coordinates' => $coordinates,
                        'radius' => $city->getRadius(),
                        'residents' => array()
                )
        );
        foreach ($selected as $person)
        {
                if (!($person instanceof Person)) continue;
                $metadata['map']['residents'][] = array(
                        'name' => $person->getName(),
                        'coordinates' => $person->getCoordinates() ?? $coordinates,
                        'net_worth' => $person->getNetWorth()
                );
        }
        if ($peopleLimit > 0 && count($residents) > $peopleLimit)
        {
                $metadata['omitted_residents'] = count($residents) - $peopleLimit;
        }
        return array(
                'category' => 'city',
                'icon' => 'city',
                'name' => $city->getName(),
                'summary' => sprintf('Population %d within %s.', $city->getPopulation(), $country->getName()),
                'description' => sprintf('%s serves as a civic hub for %s.', $city->getName(), $country->getName()),
                'image' => method_exists($city, 'exportVisualAsset') ? $city->exportVisualAsset() : null,
                'statistics' => array(
                        'population' => $city->getPopulation(),
                        'coordinates' => $coordinates,
                        'radius' => $city->getRadius()
                ),
                'chronicle' => array_slice($chronicle, -$chronicleLimit),
                'children' => $children,
                'metadata' => $metadata
        );
}

function universe_catalog_person (Person $person, int $chronicleLimit) : array
{
        $home = $person->getHomeCountry();
        $chronicle = $person->getChronicle($chronicleLimit);
        $lore = LoreForge::describePerson($person, array());
        if (!empty($lore['chronicle']))
        {
                $chronicle = array_merge($chronicle, $lore['chronicle']);
        }
        return array(
                'category' => 'person',
                'icon' => 'person',
                'name' => $person->getName(),
                'summary' => ($person->getProfession() === null)
                        ? sprintf('Citizen of %s%s.',
                                ($home instanceof Country) ? $home->getName() : 'unknown origins',
                                ($person->getResidenceCity() instanceof City) ? ' residing in ' . $person->getResidenceCity()->getName() : ''
                        )
                        : sprintf('%s of %s%s.',
                                ucfirst($person->getProfession()),
                                ($home instanceof Country) ? $home->getName() : 'unknown origins',
                                ($person->getResidenceCity() instanceof City) ? ' in ' . $person->getResidenceCity()->getName() : ''
                        ),
                'description' => $person->getBackstory(),
                'image' => method_exists($person, 'exportVisualAsset') ? $person->exportVisualAsset() : null,
                'statistics' => array(
                        'age_years' => round($person->getAgeInYears(), 2),
                        'life_expectancy_years' => $person->getLifeExpectancyYears(),
                        'senescence_years' => $person->getSenescenceStartYears(),
                        'mortality_model' => $person->getMortalityModel(),
                        'relationships' => $person->getRelationships(),
                        'home_country' => ($home instanceof Country) ? $home->getName() : null,
                        'net_worth' => $person->getNetWorth(),
                        'coordinates' => $person->getCoordinates(),
                        'residence_city' => ($person->getResidenceCity() instanceof City) ? $person->getResidenceCity()->getName() : null
                ),
                'chronicle' => array_slice($chronicle, -$chronicleLimit),
                'children' => array()
        );
}

function universe_generate_material_catalog (array $options = array()) : array
{
        $chronicleLimit = max(0, intval($options['chronicle_limit'] ?? 12));
        $elementCount = max(4, intval($options['element_count'] ?? 12));
        $compoundCount = max(2, intval($options['compound_count'] ?? 8));

        $store = MetadataStore::instance();

        $elementEntries = array();
        $elements = array();
        $usedSymbols = array();
        for ($i = 0; $i < $elementCount; $i++)
        {
                $name = universe_generate_unique_name('element', 3);
                $symbol = universe_generate_symbol($name, $usedSymbols);
                $atomicNumber = mt_rand(6, 118);
                $properties = array(
                        'group' => mt_rand(1, 18),
                        'period' => mt_rand(1, 7),
                        'category' => universe_random_choice(array('alkali metal', 'alkaline earth', 'transition metal', 'metalloid', 'noble gas', 'halogen', 'lanthanide', 'actinide', 'superfluid', 'synthetic element'), 'unknown'),
                        'state_at_stp' => universe_random_choice(array('solid', 'liquid', 'gas', 'plasma'), 'solid'),
                        'atomic_mass' => mt_rand(10, 260) + mt_rand() / mt_getrandmax()
                );
                $element = new Element($name, $symbol, $atomicNumber, $properties);
                $elements[$symbol] = $element;
                $lore = LoreForge::describeElement($element, array());
                $imageKey = Element::class . ':catalog:' . $element->getSymbol();
                $image = $store->exportMediaAsset(Element::class, $imageKey);
                if ($image === null)
                {
                        $visual = VisualForge::element($element, array());
                        $store->storeMediaAsset(Element::class, $imageKey, 'primary', 'image', $visual['mime_type'], $visual['content'], $visual['metadata'] ?? $visual);
                        $image = $store->exportMediaAsset(Element::class, $imageKey);
                }
                $elementEntries[] = array(
                        'category' => 'element',
                        'icon' => 'element',
                        'name' => $element->getName(),
                        'summary' => sprintf('Atomic number %d, %s at STP.', $element->getAtomicNumber(), $element->getStateAtSTP()),
                        'description' => $lore['description'],
                        'image' => $image,
                        'statistics' => array(
                                'symbol' => $element->getSymbol(),
                                'atomic_number' => $element->getAtomicNumber(),
                                'atomic_mass' => $element->getAtomicMass(),
                                'group' => $element->getGroup(),
                                'period' => $element->getPeriod(),
                                'category' => $element->getCategory(),
                                'state' => $element->getStateAtSTP()
                        ),
                        'chronicle' => array_slice($lore['chronicle'], -$chronicleLimit),
                        'children' => array()
                );
        }

        $compoundEntries = array();
        $elementSymbols = array_keys($elements);
        if (!empty($elementSymbols))
        {
                for ($i = 0; $i < $compoundCount; $i++)
                {
                        shuffle($elementSymbols);
                        $componentTotal = mt_rand(2, min(4, count($elementSymbols)));
                        $components = array();
                        $molarMass = 0.0;
                        for ($j = 0; $j < $componentTotal; $j++)
                        {
                                $symbol = $elementSymbols[$j];
                                $ratio = mt_rand(1, 4);
                                $components[$symbol] = $ratio;
                                $molarMass += $elements[$symbol]->getAtomicMass() * $ratio;
                        }
                        $name = universe_generate_unique_name('compound', 2);
                        $properties = array(
                                'classification' => universe_random_choice(array('inorganic', 'organic', 'alloy', 'ceramic', 'polymeric'), 'inorganic'),
                                'state_at_stp' => universe_random_choice(array('solid', 'liquid', 'gas'), 'solid'),
                                'density' => mt_rand(5, 200) / 10.0,
                                'molar_mass' => $molarMass
                        );
                        $compound = new Compound($name, $components, $properties);
                        $lore = LoreForge::describeCompound($compound, array());
                        $compoundKey = Compound::class . ':catalog:' . $compound->getName();
                        $compoundImage = $store->exportMediaAsset(Compound::class, $compoundKey);
                        if ($compoundImage === null)
                        {
                                $visual = VisualForge::compound($compound, array());
                                $store->storeMediaAsset(Compound::class, $compoundKey, 'primary', 'image', $visual['mime_type'], $visual['content'], $visual['metadata'] ?? $visual);
                                $compoundImage = $store->exportMediaAsset(Compound::class, $compoundKey);
                        }
                        $compoundEntries[] = array(
                                'category' => 'compound',
                                'icon' => 'compound',
                                'name' => $compound->getName(),
                                'summary' => sprintf('Components: %s.', implode(', ', array_keys($components))),
                                'description' => $lore['description'],
                                'image' => $compoundImage,
                                'statistics' => array(
                                        'formula' => $compound->getFormula(),
                                        'components' => $components,
                                        'classification' => $compound->getClassification(),
                                        'state' => $compound->getStateAtSTP(),
                                        'density' => $compound->getDensity(),
                                        'molar_mass' => $compound->getMolarMass()
                                ),
                                'chronicle' => array_slice($lore['chronicle'], -$chronicleLimit),
                                'children' => array()
                        );
                }
        }

        if (empty($elementEntries) && empty($compoundEntries))
        {
                return array();
        }

        return array(
                'category' => 'materials',
                'icon' => 'materials',
                'name' => 'Material Registry',
                'summary' => sprintf('%d elements and %d compounds chronicled.', count($elementEntries), count($compoundEntries)),
                'description' => 'Elements and compounds cataloged for interface explorers.',
                'statistics' => array(
                        'elements' => count($elementEntries),
                        'compounds' => count($compoundEntries)
                ),
                'chronicle' => array(
                        array(
                                'type' => 'registry',
                                'text' => 'Material catalog compiled for artisans charting the universe.',
                                'timestamp' => microtime(true),
                                'participants' => array()
                        )
                ),
                'children' => array(
                        array(
                                'category' => 'element_group',
                                'icon' => 'elements',
                                'name' => 'Elements',
                                'summary' => sprintf('%d foundational elements.', count($elementEntries)),
                                'description' => 'Atomic identities forged in stellar furnaces.',
                                'chronicle' => array(),
                                'children' => $elementEntries
                        ),
                        array(
                                'category' => 'compound_group',
                                'icon' => 'compounds',
                                'name' => 'Compounds',
                                'summary' => sprintf('%d bonded alliances of matter.', count($compoundEntries)),
                                'description' => 'Molecular architectures birthed from cosmic chemistry.',
                                'chronicle' => array(),
                                'children' => $compoundEntries
                        )
                )
        );
}

?>
