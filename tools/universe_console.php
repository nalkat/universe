<?php // 7.3.0-dev

require_once __DIR__ . '/../config.php';

function console_print_usage () : void
{
        echo "Universe console usage:" . PHP_EOL;
        echo "  php tools/universe_console.php status [--socket=path] [--json]" . PHP_EOL;
        echo "  php tools/universe_console.php snapshot [--socket=path] [--json]" . PHP_EOL;
        echo "  php tools/universe_console.php advance [--steps=1] [--delta=3600] [--socket=path] [--json]" . PHP_EOL;
        echo "  php tools/universe_console.php hierarchy [--depth=3] [--path=selector] [--include-people=0] [--socket=path] [--json]" . PHP_EOL;
        echo "  php tools/universe_console.php shutdown [--socket=path] [--json]" . PHP_EOL;
        echo "  php tools/universe_console.php repl [--socket=path] [--json]" . PHP_EOL;
        echo "  php tools/universe_console.php help" . PHP_EOL;
}

function console_parse_options (array $arguments) : array
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

function console_option_is_truthy ($value) : bool
{
        if (is_bool($value))
        {
                return $value;
        }

        $normalized = strtolower(strval($value));
        return !in_array($normalized, array('0', 'false', 'no', 'off', ''));
}

function console_connect (string $socketPath, bool $exitOnFailure = true)
{
        $client = @stream_socket_client('unix://' . $socketPath, $errno, $errstr, 2);
        if (!$client)
        {
                $message = "Unable to connect to universe daemon at {$socketPath}: {$errstr}";
                if ($exitOnFailure)
                {
                        fwrite(STDERR, $message . PHP_EOL);
                        exit(1);
                }
                fwrite(STDERR, $message . PHP_EOL);
                return false;
        }
        stream_set_blocking($client, true);
        return $client;
}

function console_send_command ($client, string $command, array $args = array()) : array
{
        $payload = json_encode(array(
                'command' => $command,
                'args' => $args
        )) . PHP_EOL;
        fwrite($client, $payload);
        $response = fgets($client);
        if ($response === false)
        {
                return array('ok' => false, 'error' => 'No response from daemon');
        }
        $decoded = json_decode($response, true);
        if (!is_array($decoded))
        {
                return array('ok' => false, 'error' => 'Malformed response from daemon');
        }
        return $decoded;
}

function console_pretty_print ($data, int $indent = 0) : void
{
        if (is_array($data))
        {
                foreach ($data as $key => $value)
                {
                        if (is_array($value))
                        {
                                echo str_repeat(' ', $indent) . $key . ':' . PHP_EOL;
                                console_pretty_print($value, $indent + 2);
                        }
                        else
                        {
                                echo str_repeat(' ', $indent) . $key . ': ' . $value . PHP_EOL;
                        }
                }
        }
        else
        {
                if (is_bool($data))
                {
                        $data = $data ? 'true' : 'false';
                }
                echo str_repeat(' ', $indent) . strval($data) . PHP_EOL;
        }
}

function console_execute_command (string $command, array $options, string $socketPath, bool $exitOnFailure = true) : bool
{
        $commandOptions = $options;
        unset($commandOptions['socket'], $commandOptions['json']);

        switch ($command)
        {
                case 'status':
                case 'snapshot':
                case 'shutdown':
                case 'ping':
                        $args = array();
                        break;

                case 'advance':
                        $steps = isset($commandOptions['steps']) ? max(1, intval($commandOptions['steps'])) : 1;
                        $delta = isset($commandOptions['delta']) ? max(1.0, floatval($commandOptions['delta'])) : 3600.0;
                        $args = array(
                                'steps' => $steps,
                                'delta_time' => $delta
                        );
                        break;

                case 'hierarchy':
                        $depth = isset($commandOptions['depth']) ? max(1, intval($commandOptions['depth'])) : 3;
                        $path = isset($commandOptions['path']) ? strval($commandOptions['path']) : null;
                        $includePeopleOption = $commandOptions['include-people'] ?? ($commandOptions['include_people'] ?? false);
                        $includePeople = console_option_is_truthy($includePeopleOption);
                        $args = array(
                                'depth' => $depth,
                                'include_people' => $includePeople
                        );
                        if ($path !== null && $path !== '')
                        {
                                $args['path'] = $path;
                        }
                        break;

                default:
                        fwrite(STDERR, "Unknown command '{$command}'." . PHP_EOL);
                        if ($exitOnFailure)
                        {
                                console_print_usage();
                                exit(1);
                        }
                        return false;
        }

        $client = console_connect($socketPath, $exitOnFailure);
        if ($client === false)
        {
                return false;
        }

        $response = console_send_command($client, $command, $args);
        fclose($client);

        if (!$response['ok'])
        {
                $error = $response['error'] ?? 'Daemon error';
                fwrite(STDERR, $error . PHP_EOL);
                if ($exitOnFailure)
                {
                        exit(1);
                }
                return false;
        }

        $jsonOutput = isset($options['json']) && console_option_is_truthy($options['json']);
        if ($jsonOutput)
        {
                echo json_encode($response, JSON_PRETTY_PRINT) . PHP_EOL;
        }
        else
        {
                console_pretty_print($response);
        }

        return true;
}

function console_run_repl (string $socketPath, array $options = array()) : void
{
        $currentSocket = $socketPath;
        $jsonOutput = isset($options['json']) && console_option_is_truthy($options['json']);

        echo "Universe console interactive shell" . PHP_EOL;
        echo "Type 'help' to list commands, 'quit' to exit." . PHP_EOL;
        echo "Using socket: {$currentSocket}" . PHP_EOL;

        $handle = fopen('php://stdin', 'r');
        if ($handle === false)
        {
                fwrite(STDERR, "Unable to read from STDIN." . PHP_EOL);
                exit(1);
        }

        while (true)
        {
                echo '[' . $currentSocket . ']> ';
                $line = fgets($handle);
                if ($line === false)
                {
                        echo PHP_EOL;
                        break;
                }

                $line = trim($line);
                if ($line === '')
                {
                        continue;
                }

                if (in_array(strtolower($line), array('exit', 'quit')))
                {
                        break;
                }

                if (strtolower($line) === 'help')
                {
                        console_print_usage();
                        echo "Additional REPL commands:" . PHP_EOL;
                        echo "  socket <path>    Change the target UNIX socket." . PHP_EOL;
                        echo "  json on|off      Toggle JSON output mode." . PHP_EOL;
                        echo "  quit             Leave the interactive shell." . PHP_EOL;
                        continue;
                }

                if (stripos($line, 'socket ') === 0)
                {
                        $newSocket = trim(substr($line, 6));
                        if ($newSocket === '')
                        {
                                fwrite(STDERR, "Socket path cannot be empty." . PHP_EOL);
                                continue;
                        }
                        $currentSocket = $newSocket;
                        echo "Socket updated to {$currentSocket}" . PHP_EOL;
                        continue;
                }

                if (stripos($line, 'json ') === 0)
                {
                        $mode = strtolower(trim(substr($line, 4)));
                        if ($mode === 'on')
                        {
                                $jsonOutput = true;
                                echo "JSON output enabled." . PHP_EOL;
                        }
                        elseif ($mode === 'off')
                        {
                                $jsonOutput = false;
                                echo "JSON output disabled." . PHP_EOL;
                        }
                        else
                        {
                                fwrite(STDERR, "Unknown JSON mode '{$mode}'. Use 'on' or 'off'." . PHP_EOL);
                        }
                        continue;
                }

                $parts = preg_split('/\s+/', $line);
                if (empty($parts))
                {
                        continue;
                }

                $replCommand = strtolower(array_shift($parts));
                $commandOptions = console_parse_options($parts);

                if ($jsonOutput)
                {
                        $commandOptions['json'] = true;
                }

                $success = console_execute_command($replCommand, $commandOptions, $currentSocket, false);
                if (!$success)
                {
                        fwrite(STDERR, "Command '{$replCommand}' failed." . PHP_EOL);
                }
        }
}

$arguments = $_SERVER['argv'] ?? array();
array_shift($arguments);
$command = 'help';
if (!empty($arguments) && strpos($arguments[0], '--') !== 0)
{
        $command = strtolower(array_shift($arguments));
}
$options = console_parse_options($arguments);

$socketPath = $options['socket'] ?? (__DIR__ . '/../runtime/universe.sock');

switch ($command)
{
        case 'status':
        case 'snapshot':
        case 'shutdown':
        case 'ping':
        case 'hierarchy':
        case 'advance':
                $success = console_execute_command($command, $options, $socketPath);
                if (!$success)
                {
                        exit(1);
                }
                break;

        case 'repl':
                console_run_repl($socketPath, $options);
                break;

        case 'help':
                console_print_usage();
                break;

        default:
                fwrite(STDERR, "Unknown command '{$command}'." . PHP_EOL);
                console_print_usage();
                exit(1);
}

?>
