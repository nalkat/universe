<?php // 7.3.0-dev

require_once __DIR__ . '/../EnVision/class_envision.php';
require_once __DIR__ . '/../config.php';

function console_print_usage () : void
{
        echo "Universe console usage:" . PHP_EOL;
        echo "  php tools/universe_console.php status [--socket=path]" . PHP_EOL;
        echo "  php tools/universe_console.php snapshot [--socket=path]" . PHP_EOL;
        echo "  php tools/universe_console.php advance [--steps=1] [--delta=3600] [--socket=path]" . PHP_EOL;
        echo "  php tools/universe_console.php shutdown [--socket=path]" . PHP_EOL;
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

function console_connect (string $socketPath)
{
        $client = @stream_socket_client('unix://' . $socketPath, $errno, $errstr, 2);
        if (!$client)
        {
                        fwrite(STDERR, "Unable to connect to universe daemon at {$socketPath}: {$errstr}" . PHP_EOL);
                        exit(1);
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
                echo str_repeat(' ', $indent) . strval($data) . PHP_EOL;
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
                $client = console_connect($socketPath);
                $response = console_send_command($client, $command);
                fclose($client);
                if (!$response['ok'])
                {
                        fwrite(STDERR, ($response['error'] ?? 'Daemon error') . PHP_EOL);
                        exit(1);
                }
                console_pretty_print($response);
                break;

        case 'advance':
                $steps = isset($options['steps']) ? max(1, intval($options['steps'])) : 1;
                $delta = isset($options['delta']) ? max(1.0, floatval($options['delta'])) : 3600.0;
                $client = console_connect($socketPath);
                $response = console_send_command($client, 'advance', array(
                        'steps' => $steps,
                        'delta_time' => $delta
                ));
                fclose($client);
                if (!$response['ok'])
                {
                        fwrite(STDERR, ($response['error'] ?? 'Daemon error') . PHP_EOL);
                        exit(1);
                }
                console_pretty_print($response);
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
