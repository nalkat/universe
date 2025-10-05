<?php // 7.3.0-dev

class UniverseDaemon
{
        private $simulator;
        private $socketPath;
        private $pidFile;
        private $deltaTime;
        private $loopInterval;
        private $autoSteps;
        private $server;
        private $clients;
        private $running;
        private $lastSnapshot;
        private $startedAt;
        private $forkedPid;

        public function __construct (UniverseSimulator $simulator, array $options = array())
        {
                $this->simulator = $simulator;
                $this->socketPath = $options['socket'] ?? (__DIR__ . '/runtime/universe.sock');
                $this->pidFile = $options['pid_file'] ?? (__DIR__ . '/runtime/universe.pid');
                $this->deltaTime = floatval($options['delta_time'] ?? 3600.0);
                $this->loopInterval = max(0.0, floatval($options['loop_interval'] ?? 1.0));
                $autoSteps = intval($options['auto_steps'] ?? 1);
                $this->autoSteps = ($autoSteps > 0) ? $autoSteps : 1;
                $this->clients = array();
                $this->running = false;
                $this->server = null;
                $this->lastSnapshot = null;
                $this->startedAt = microtime(true);
                $this->forkedPid = null;
        }

        public function daemonize () : bool
        {
                if (!function_exists('pcntl_fork'))
                {
                        Utility::write('pcntl_fork is not available, continuing in foreground mode', LOG_WARNING, L_CONSOLE);
                        return false;
                }

                $pid = pcntl_fork();
                if ($pid === -1)
                {
                        Utility::write('Unable to fork the universe daemon', LOG_ERROR, L_ERROR);
                        return false;
                }

                if ($pid > 0)
                {
                        $this->forkedPid = $pid;
                        return true;
                }

                if (function_exists('posix_setsid'))
                {
                        posix_setsid();
                }

                fclose(STDIN);
                fclose(STDOUT);
                fclose(STDERR);
                fopen('/dev/null', 'r');
                fopen('/dev/null', 'a');
                fopen('/dev/null', 'a');
                return false;
        }

        public function getForkedPid () : ?int
        {
                return $this->forkedPid;
        }

        public function run () : void
        {
                $this->prepareRuntime();
                $this->running = true;
                $this->startedAt = microtime(true);
                $this->installSignalHandlers();

                $nextStep = microtime(true);
                while ($this->running)
                {
                        $now = microtime(true);
                        if ($now >= $nextStep)
                        {
                                $this->advanceSimulation();
                                $nextStep = $now + $this->loopInterval;
                        }

                        $timeout = max(0.0, $nextStep - microtime(true));
                        $this->poll($timeout);
                }

                $this->shutdown();
        }

        private function prepareRuntime () : void
        {
                $this->ensureDirectory(dirname($this->socketPath));
                $this->ensureDirectory(dirname($this->pidFile));

                if (file_exists($this->socketPath))
                {
                        @unlink($this->socketPath);
                }

                $this->server = @stream_socket_server('unix://' . $this->socketPath, $errno, $errstr);
                if (!$this->server)
                {
                        throw new RuntimeException('Failed to create universe daemon socket: ' . $errstr, $errno);
                }

                stream_set_blocking($this->server, false);

                if (!empty($this->pidFile))
                {
                        $pid = getmypid();
                        file_put_contents($this->pidFile, $pid);
                }
        }

        private function ensureDirectory (string $path) : void
        {
                if ($path === '' || $path === '.' || file_exists($path))
                {
                        return;
                }
                if (!@mkdir($path, 0777, true) && !is_dir($path))
                {
                        throw new RuntimeException('Unable to create directory ' . $path);
                }
        }

        private function installSignalHandlers () : void
        {
                if (!function_exists('pcntl_signal'))
                {
                        return;
                }

                pcntl_async_signals(true);
                pcntl_signal(SIGTERM, array($this, 'handleSignal'));
                pcntl_signal(SIGINT, array($this, 'handleSignal'));
        }

        public function handleSignal (int $signal) : void
        {
                switch ($signal)
                {
                        case SIGINT:
                        case SIGTERM:
                                $this->running = false;
                                break;
                }
        }

        private function advanceSimulation () : void
        {
                try
                {
                        for ($i = 0; $i < $this->autoSteps; $i++)
                        {
                                $this->lastSnapshot = $this->simulator->step($this->deltaTime);
                        }
                }
                catch (Throwable $error)
                {
                        Utility::write('Universe simulation step failed: ' . $error->getMessage(), LOG_ERROR, L_ERROR);
                }
        }

        private function poll (float $timeoutSeconds) : void
        {
                $read = array();
                if ($this->server)
                {
                        $read[] = $this->server;
                }

                foreach ($this->clients as $client)
                {
                        $read[] = $client;
                }

                if (empty($read))
                {
                        usleep((int) ($timeoutSeconds * 1000000));
                        return;
                }

                $sec = (int) $timeoutSeconds;
                $usec = (int) (($timeoutSeconds - $sec) * 1000000);

                $write = null;
                $except = null;
                $available = @stream_select($read, $write, $except, $sec, $usec);
                if ($available === false || $available === 0)
                {
                        return;
                }

                foreach ($read as $stream)
                {
                        if ($stream === $this->server)
                        {
                                $this->acceptClient();
                                continue;
                        }

                        $this->serviceClient($stream);
                }
        }

        private function acceptClient () : void
        {
                $client = @stream_socket_accept($this->server, 0);
                if (!$client)
                {
                        return;
                }

                stream_set_blocking($client, false);
                $this->clients[(int) $client] = $client;
        }

        private function serviceClient ($stream) : void
        {
                $data = @fgets($stream);
                if ($data === false)
                {
                        $this->dropClient($stream);
                        return;
                }

                $data = trim($data);
                if ($data === '')
                {
                        return;
                }

                $payload = json_decode($data, true);
                if (!is_array($payload) || empty($payload['command']))
                {
                        $this->respond($stream, array(
                                'ok' => false,
                                'error' => 'Invalid command payload'
                        ));
                        return;
                }

                $command = strtolower(strval($payload['command']));
                $args = $payload['args'] ?? array();
                $response = $this->executeCommand($command, $args);
                $this->respond($stream, $response);

                if (!$response['keep_alive'])
                {
                        $this->dropClient($stream);
                }
        }

        private function executeCommand (string $command, array $args) : array
        {
                switch ($command)
                {
                        case 'ping':
                                return $this->wrapResponse(true, array('message' => 'pong'));

                        case 'help':
                                return $this->wrapResponse(true, array(
                                        'commands' => array(
                                                'ping' => 'Check daemon responsiveness',
                                                'status' => 'Summarize current universe statistics',
                                                'snapshot' => 'Return the latest cached snapshot of the universe',
                                                'hierarchy' => 'Inspect galaxies, systems, planets, countries, and optional people',
                                                'advance' => 'Advance the simulation immediately (accepts steps, delta_time)',
                                                'shutdown' => 'Stop the universe daemon gracefully'
                                        )
                                ));

                        case 'status':
                                return $this->wrapResponse(true, $this->gatherStatus());

                        case 'snapshot':
                                return $this->wrapResponse(true, array(
                                        'snapshot' => $this->lastSnapshot ?? $this->simulator->snapshot()
                                ));

                        case 'advance':
                                $steps = max(1, intval($args['steps'] ?? 1));
                                $delta = isset($args['delta_time']) ? floatval($args['delta_time']) : $this->deltaTime;
                                $snapshot = null;
                                for ($i = 0; $i < $steps; $i++)
                                {
                                        $snapshot = $this->simulator->step($delta);
                                }
                                $this->lastSnapshot = $snapshot;
                                return $this->wrapResponse(true, array(
                                        'snapshot' => $snapshot,
                                        'steps' => $steps,
                                        'delta_time' => $delta
                                ));

                        case 'hierarchy':
                                $summary = $this->buildHierarchySummary($args);
                                return $this->wrapResponse(true, array(
                                        'hierarchy' => $summary
                                ));

                        case 'shutdown':
                                $this->running = false;
                                return $this->wrapResponse(true, array(
                                        'message' => 'Universe daemon is shutting down'
                                ), false);

                        default:
                                return $this->wrapResponse(false, array(
                                        'error' => "Unknown command '{$command}'"
                                ));
                }
        }

        private function wrapResponse (bool $ok, array $data, bool $keepAlive = true) : array
        {
                $payload = array_merge(array('ok' => $ok), $data);
                $payload['keep_alive'] = $keepAlive;
                return $payload;
        }

        private function respond ($stream, array $payload) : void
        {
                $encoded = json_encode($payload) . PHP_EOL;
                @fwrite($stream, $encoded);
        }

        private function dropClient ($stream) : void
        {
                $id = (int) $stream;
                if (isset($this->clients[$id]))
                {
                        unset($this->clients[$id]);
                }
                @fclose($stream);
        }

        private function gatherStatus () : array
        {
                $universe = $this->simulator->getUniverse();
                $galaxies = $universe->getGalaxies();
                $systemCount = 0;
                $planetCount = 0;
                $population = 0;

                foreach ($galaxies as $galaxy)
                {
                        if (!($galaxy instanceof Galaxy))
                        {
                                continue;
                        }
                        foreach ($galaxy->getSystems() as $system)
                        {
                                if (!($system instanceof System))
                                {
                                        continue;
                                }
                                $systemCount++;
                                foreach ($system->getPlanets() as $planet)
                                {
                                        if (!($planet instanceof Planet))
                                        {
                                                continue;
                                        }
                                        $planetCount++;
                                        $summary = $planet->getPopulationSummary();
                                        $population += intval($summary['population'] ?? 0);
                                }
                        }
                }

                return array(
                        'tick' => $universe->getTicks(),
                        'galaxies' => count($galaxies),
                        'systems' => $systemCount,
                        'planets' => $planetCount,
                        'population' => $population,
                        'uptime' => microtime(true) - $this->startedAt,
                        'auto_steps' => $this->autoSteps,
                        'delta_time' => $this->deltaTime,
                        'loop_interval' => $this->loopInterval
                );
        }

        private function buildHierarchySummary (array $args) : array
        {
                $depth = max(1, intval($args['depth'] ?? 3));
                $includePeople = $this->valueToBool($args['include_people'] ?? false);
                $selectors = $this->parseHierarchyPath($args['path'] ?? null);

                $focus = array();
                foreach (array('galaxy', 'system', 'planet', 'country', 'person') as $type)
                {
                        if (isset($selectors[$type]))
                        {
                                $focus[$type] = $selectors[$type];
                        }
                }

                $summary = array(
                        'tick' => $this->simulator->getUniverse()->getTicks(),
                        'depth' => $depth,
                        'depth_levels' => array(
                                1 => 'galaxies',
                                2 => 'systems',
                                3 => 'planets',
                                4 => 'countries',
                                5 => 'people'
                        ),
                        'include_people' => $includePeople,
                        'focus' => $focus,
                        'galaxies' => array()
                );

                $found = array(
                        'galaxy' => !isset($focus['galaxy']),
                        'system' => !isset($focus['system']),
                        'planet' => !isset($focus['planet']),
                        'country' => !isset($focus['country']),
                        'person' => !isset($focus['person'])
                );

                $universe = $this->simulator->getUniverse();
                $galaxies = $universe->getGalaxies();
                if (isset($focus['galaxy']))
                {
                        $galaxies = array();
                        $targetGalaxy = $universe->getGalaxy($focus['galaxy']);
                        if ($targetGalaxy instanceof Galaxy)
                        {
                                $galaxies[$targetGalaxy->name] = $targetGalaxy;
                                $found['galaxy'] = true;
                        }
                }

                $systemFocus = $focus['system'] ?? null;
                $planetFocus = $focus['planet'] ?? null;
                $countryFocus = $focus['country'] ?? null;
                $personFocus = $focus['person'] ?? null;
                $collectPeople = ($includePeople || $personFocus !== null);

                foreach ($galaxies as $galaxy)
                {
                        if (!($galaxy instanceof Galaxy))
                        {
                                continue;
                        }

                        $galaxyName = $galaxy->name ?? null;
                        if ($galaxyName !== null && isset($focus['galaxy']) && ($galaxyName === $focus['galaxy']))
                        {
                                $found['galaxy'] = true;
                        }

                        $galaxyEntry = array(
                                'name' => $galaxyName,
                                'type' => get_class($galaxy),
                                'system_count' => 0,
                                'systems' => array()
                        );

                        $systems = $galaxy->getSystems();
                        $galaxyEntry['system_count'] = count($systems);

                        if ($depth >= 2)
                        {
                                if ($systemFocus !== null)
                                {
                                        $targetSystem = $galaxy->getSystem($systemFocus);
                                        $systems = array();
                                        if ($targetSystem instanceof System)
                                        {
                                                $systems[$targetSystem->getName()] = $targetSystem;
                                                $found['system'] = true;
                                        }
                                }

                                foreach ($systems as $system)
                                {
                                        if (!($system instanceof System))
                                        {
                                                continue;
                                        }

                                        $systemEntry = array(
                                                'name' => $system->getName(),
                                                'propagation_mode' => $system->getPropagationMode(),
                                                'object_count' => $system->countObjects(),
                                                'planet_count' => count($system->getPlanets()),
                                                'planets' => array()
                                        );

                                        if ($depth >= 3)
                                        {
                                                $planets = $system->getPlanets();
                                                if ($planetFocus !== null)
                                                {
                                                        $planets = array();
                                                        $targetPlanet = $system->getObject($planetFocus);
                                                        if ($targetPlanet instanceof Planet)
                                                        {
                                                                $planets[$targetPlanet->getName()] = $targetPlanet;
                                                                $found['planet'] = true;
                                                        }
                                                }

                                                foreach ($planets as $planet)
                                                {
                                                        if (!($planet instanceof Planet))
                                                        {
                                                                continue;
                                                        }

                                                        $planetSummary = $planet->getPopulationSummary();
                                                        $planetEntry = array(
                                                                'name' => $planet->getName(),
                                                                'type' => $planet->getType(),
                                                                'habitability' => $planet->getHabitabilityScore(),
                                                                'population' => $planetSummary['population'] ?? 0,
                                                                'country_count' => $planetSummary['countries'] ?? count($planet->getCountries()),
                                                                'countries' => array()
                                                        );

                                                        if ($depth >= 4)
                                                        {
                                                                $countries = $planet->getCountries();
                                                                if ($countryFocus !== null)
                                                                {
                                                                        $countries = array();
                                                                        $targetCountry = $planet->getCountry($countryFocus);
                                                                        if ($targetCountry instanceof Country)
                                                                        {
                                                                                $countries[$targetCountry->getName()] = $targetCountry;
                                                                                $found['country'] = true;
                                                                        }
                                                                }

                                                                foreach ($countries as $country)
                                                                {
                                                                        if (!($country instanceof Country))
                                                                        {
                                                                                continue;
                                                                        }

                                                                        $countryEntry = array(
                                                                                'name' => $country->getName(),
                                                                                'population' => $country->getPopulation(),
                                                                                'capacity' => $country->getPopulationCapacity(),
                                                                                'development' => $country->getDevelopmentScore(),
                                                                                'adaptation' => $country->getAdaptationLevel(),
                                                                                'resources' => array(
                                                                                        'food' => $country->getResourceStockpile('food'),
                                                                                        'materials' => $country->getResourceStockpile('materials'),
                                                                                        'wealth' => $country->getResourceStockpile('wealth')
                                                                                ),
                                                                                'jobs' => array()
                                                                        );

                                                                        $jobs = $country->getJobs();
                                                                        foreach ($jobs as $job)
                                                                        {
                                                                                if (!($job instanceof Job))
                                                                                {
                                                                                        continue;
                                                                                }
                                                                                $countryEntry['jobs'][] = array(
                                                                                        'name' => $job->getName(),
                                                                                        'category' => $job->getCategory(),
                                                                                        'capacity' => $job->getCapacity(),
                                                                                        'workers' => count($job->getWorkers())
                                                                                );
                                                                        }

                                                                        if ($collectPeople)
                                                                        {
                                                                                $people = $country->getPeople();
                                                                                if ($personFocus !== null)
                                                                                {
                                                                                        $filtered = array();
                                                                                        foreach ($people as $person)
                                                                                        {
                                                                                                if (!($person instanceof Person))
                                                                                                {
                                                                                                        continue;
                                                                                                }
                                                                                                if ($person->getName() === $personFocus)
                                                                                                {
                                                                                                        $filtered[] = $person;
                                                                                                        $found['person'] = true;
                                                                                                        break;
                                                                                                }
                                                                                        }
                                                                                        $people = $filtered;
                                                                                }

                                                                                if ($includePeople || ($personFocus !== null && !empty($people)))
                                                                                {
                                                                                        $peopleEntries = array();
                                                                                        foreach ($people as $person)
                                                                                        {
                                                                                                if (!($person instanceof Person))
                                                                                                {
                                                                                                        continue;
                                                                                                }
                                                                                                if ($personFocus !== null && $person->getName() === $personFocus)
                                                                                                {
                                                                                                        $found['person'] = true;
                                                                                                }

                                                                                                $personEntry = array(
                                                                                                        'name' => $person->getName(),
                                                                                                        'alive' => $person->isAlive(),
                                                                                                        'age' => $person->getAge(),
                                                                                                        'age_years' => $person->getAgeInYears(),
                                                                                                        'health' => $person->getHealth(),
                                                                                                        'hunger' => $person->getHungerLevel(),
                                                                                                        'resilience' => $person->getResilience(),
                                                                                                        'profession' => $person->getProfession(),
                                                                                                        'life_expectancy_years' => $person->getLifeExpectancyYears(),
                                                                                                        'life_expectancy_seconds' => $person->getLifeExpectancySeconds(),
                                                                                                        'senescence_years' => $person->getSenescenceStartYears(),
                                                                                                        'senescence_seconds' => $person->getSenescenceStartSeconds()
                                                                                                );

                                                                                                $job = $person->getJob();
                                                                                                if ($job instanceof Job)
                                                                                                {
                                                                                                        $personEntry['job'] = $job->getName();
                                                                                                }

                                                                                                $skills = $person->getSkills();
                                                                                                if (!empty($skills))
                                                                                                {
                                                                                                        $personEntry['skills'] = $skills;
                                                                                                }

                                                                                                if (!$person->isAlive() && $person->getDeathReason() !== null)
                                                                                                {
                                                                                                        $personEntry['death_cause'] = $person->getDeathReason();
                                                                                                }

                                                                                                $peopleEntries[] = $personEntry;
                                                                                        }

                                                                                        if (!empty($peopleEntries))
                                                                                        {
                                                                                                $countryEntry['people'] = $peopleEntries;
                                                                                        }
                                                                                }
                                                                        }

                                                                        $planetEntry['countries'][] = $countryEntry;
                                                                }
                                                        }

                                                        $systemEntry['planets'][] = $planetEntry;
                                                }
                                        }

                                        $galaxyEntry['systems'][] = $systemEntry;
                                }
                        }

                        $summary['galaxies'][] = $galaxyEntry;
                }

                foreach ($focus as $type => $name)
                {
                        if (array_key_exists($type, $found) && !$found[$type])
                        {
                                if (!isset($summary['missing']))
                                {
                                        $summary['missing'] = array();
                                }
                                $summary['missing'][$type] = $name;
                        }
                }

                return $summary;
        }

        private function parseHierarchyPath (?string $path) : array
        {
                $result = array('segments' => array());
                if ($path === null)
                {
                        return $result;
                }

                $trimmed = trim(strval($path));
                if ($trimmed === '')
                {
                        return $result;
                }

                $segments = preg_split('/\s*\/\s*/', $trimmed, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($segments as $segment)
                {
                        $parts = explode(':', $segment, 2);
                        if (count($parts) < 2)
                        {
                                continue;
                        }
                        $type = strtolower(trim($parts[0]));
                        $name = Utility::cleanse_string($parts[1]);
                        if ($name === '')
                        {
                                continue;
                        }
                        if (!in_array($type, array('galaxy', 'system', 'planet', 'country', 'person'), true))
                        {
                                continue;
                        }
                        $result[$type] = $name;
                        $result['segments'][] = array('type' => $type, 'name' => $name);
                }

                return $result;
        }

        private function valueToBool ($value) : bool
        {
                if (is_bool($value))
                {
                        return $value;
                }

                $normalized = strtolower(trim(strval($value)));
                return !in_array($normalized, array('', '0', 'false', 'no', 'off'), true);
        }

        private function shutdown () : void
        {
                foreach ($this->clients as $client)
                {
                        $this->respond($client, $this->wrapResponse(true, array('message' => 'Daemon shutting down'), false));
                        @fclose($client);
                }
                $this->clients = array();

                if ($this->server)
                {
                        @fclose($this->server);
                        $this->server = null;
                }

                if (file_exists($this->socketPath))
                {
                        @unlink($this->socketPath);
                }

                if (!empty($this->pidFile) && file_exists($this->pidFile))
                {
                        @unlink($this->pidFile);
                }
        }
}

?>
