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
                $this->loopInterval = max(0.1, floatval($options['loop_interval'] ?? 1.0));
                $this->autoSteps = max(1, intval($options['auto_steps'] ?? 1));
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
