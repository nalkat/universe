<?php // 7.3.0-dev

require_once __DIR__ . "/EnVision/class_envision.php";
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/class_universeDaemon.php";
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
$simulator = new UniverseSimulator($universe);

$blueprint = array(
        'galaxies' => array(
                array(
                        'name' => 'Permeoid',
                        'size' => array('x' => 42.323, 'y' => 44.131, 'z' => 20.12),
                        'systems' => array(
                                array(
                                        'name' => 'Helios',
                                        'star' => array(
                                                'name' => 'Helios',
                                                'mass' => Star::SOLAR_MASS * 1.05,
                                                'radius' => 6.9634E8 * 1.02,
                                                'luminosity' => Star::SOLAR_LUMINOSITY * 1.1,
                                                'temperature' => 5900,
                                                'spectral_class' => 'G1V'
                                        ),
                                        'time_step' => 3600,
                                        'propagation_mode' => System::PROPAGATION_ANALYTIC,
                                        'planets' => array(
                                                array(
                                                        'name' => 'Maris',
                                                        'mass' => 5.972E24,
                                                        'radius' => 6.371E6,
                                                        'environment' => array(
                                                                'temperature' => 15,
                                                                'water' => 0.71,
                                                                'atmosphere' => 0.78,
                                                                'magnetosphere' => 0.62,
                                                                'biosignatures' => 0.72
                                                        ),
                                                        'habitable' => true,
                                                        'orbit' => array(
                                                                'semi_major_axis' => 1.496E11,
                                                                'period' => 365.25 * 86400,
                                                                'eccentricity' => 0.0167
                                                        ),
                                                        'countries' => array(
                                                                array(
                                                                        'name' => 'Aurora Republic',
                                                                        'profile' => array(
                                                                                'infrastructure' => 0.65,
                                                                                'technology' => 0.62,
                                                                                'resources' => 0.68,
                                                                                'stability' => 0.6,
                                                                                'population_capacity' => 2000000,
                                                                                'development_rate' => 2.0
                                                                        ),
                                                                        'spawn_people' => 50000
                                                                )
                                                        )
                                                ),
                                                array(
                                                        'name' => 'Thorne',
                                                        'mass' => 6.39E23,
                                                        'radius' => 3.389E6,
                                                        'environment' => array(
                                                                'temperature' => -40,
                                                                'water' => 0.1,
                                                                'atmosphere' => 0.05,
                                                                'magnetosphere' => 0.1,
                                                                'biosignatures' => 0.0
                                                        ),
                                                        'habitable' => false,
                                                        'orbit' => array(
                                                                'semi_major_axis' => 2.279E11,
                                                                'period' => 687 * 86400,
                                                                'eccentricity' => 0.0934
                                                        )
                                                )
                                        )
                                )
                        )
                ),
                array(
                        'name' => 'Andromeda',
                        'size' => array('x' => 45.333, 'y' => 48.313, 'z' => 55.98),
                        'systems' => array(
                                array(
                                        'name' => 'Nadir',
                                        'star' => array(
                                                'name' => 'Nadir',
                                                'mass' => Star::SOLAR_MASS * 0.8,
                                                'radius' => 6.9634E8 * 0.9,
                                                'luminosity' => Star::SOLAR_LUMINOSITY * 0.6,
                                                'temperature' => 4800,
                                                'spectral_class' => 'K3V'
                                        ),
                                        'time_step' => 7200,
                                        'propagation_mode' => System::PROPAGATION_NUMERICAL,
                                        'softening_length' => 1.0E7,
                                        'planets' => array(
                                                array(
                                                        'name' => 'Ilyra',
                                                        'mass' => 4.8E24,
                                                        'radius' => 6.1E6,
                                                        'environment' => array(
                                                                'temperature' => 5,
                                                                'water' => 0.6,
                                                                'atmosphere' => 0.7,
                                                                'magnetosphere' => 0.58,
                                                                'biosignatures' => 0.55
                                                        ),
                                                        'habitable' => true,
                                                        'orbit' => array(
                                                                'semi_major_axis' => 1.1E11,
                                                                'period' => 320 * 86400,
                                                                'eccentricity' => 0.05
                                                        ),
                                                        'countries' => array(
                                                                array(
                                                                        'name' => 'Celes Dominion',
                                                                        'profile' => array(
                                                                                'infrastructure' => 0.6,
                                                                                'technology' => 0.58,
                                                                                'resources' => 0.61,
                                                                                'stability' => 0.58,
                                                                                'population_capacity' => 1500000,
                                                                                'development_rate' => 1.5
                                                                        ),
                                                                        'spawn_people' => 25000
                                                                )
                                                        )
                                                )
                                        )
                                )
                        )
                )
        )
);

$simulator->bootstrap($blueprint);

function universe_parse_options (array $arguments) : array
{
        $options = array();
        foreach ($arguments as $argument)
        {
                if (strpos($argument, '--') !== 0)
                {
                        continue;
                }
                $trimmed = substr($argument, 2);
                if ($trimmed === '')
                {
                        continue;
                }
                if (strpos($trimmed, '=') !== false)
                {
                        list($key, $value) = explode('=', $trimmed, 2);
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
                                echo "    Planet: {$planetName} | Habitability: " . round($summary['habitability'], 2) . " | Population: " . $summary['population'] . PHP_EOL;
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
        echo "  php universe.php start [--delta=3600] [--interval=1] [--auto-steps=1] [--socket=path] [--pid-file=path] [--no-daemonize]" . PHP_EOL;
        echo "  php universe.php run-once [--steps=10] [--delta=3600]" . PHP_EOL;
        echo "  php universe.php help" . PHP_EOL;
}

$arguments = $_SERVER['argv'] ?? array();
array_shift($arguments);
$command = 'start';
if (!empty($arguments) && strpos($arguments[0], '--') !== 0)
{
        $command = strtolower(array_shift($arguments));
}
$options = universe_parse_options($arguments);

switch ($command)
{
        case 'start':
                $daemonOptions = array(
                        'socket' => $options['socket'] ?? (__DIR__ . '/runtime/universe.sock'),
                        'pid_file' => $options['pid-file'] ?? (__DIR__ . '/runtime/universe.pid'),
                        'delta_time' => isset($options['delta']) ? max(1.0, floatval($options['delta'])) : 3600.0,
                        'loop_interval' => isset($options['interval']) ? max(0.1, floatval($options['interval'])) : 1.0,
                        'auto_steps' => isset($options['auto-steps']) ? max(1, intval($options['auto-steps'])) : 1
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

        case 'run-once':
                $steps = isset($options['steps']) ? max(1, intval($options['steps'])) : 10;
                $deltaTime = isset($options['delta']) ? max(1.0, floatval($options['delta'])) : 3600.0;
                $simulator->run($steps, $deltaTime);
                universe_print_summary($universe);
                break;

        case 'help':
                universe_print_usage();
                break;

        default:
                echo "Unknown command '{$command}'." . PHP_EOL;
                universe_print_usage();
                exit(1);
}

?>
