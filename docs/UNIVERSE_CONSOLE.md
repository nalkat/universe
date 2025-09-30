# Universe Console Guide

The universe daemon exposes a small command channel over a UNIX domain socket. The
`tools/universe_console.php` helper lets you interact with the running simulation from
another terminal while the daemon continues to advance in the background.

## Prerequisites

1. Start the daemon (see the [README](../README.md#running-the-universe-daemon)) so that
the socket file exists. By default the daemon writes the socket to
   `runtime/universe.sock` inside the repository.
2. Ensure PHP has permission to create and read from that directory. When running inside
   a container the repository root already contains the `runtime/` directory created by
   the daemon bootstrap.

## One-off commands

The console accepts the following commands. Pass `--socket=/custom/path.sock` if you
configured the daemon to listen somewhere else, and append `--json` to receive the raw
JSON payload that the daemon returns.

```bash
php tools/universe_console.php status        # current tick, active galaxies, uptime, etc.
php tools/universe_console.php snapshot      # summary of the hierarchical object graph
php tools/universe_console.php advance --steps=10 --delta=3600
php tools/universe_console.php shutdown      # ask the daemon to terminate gracefully
php tools/universe_console.php ping          # low-level health check
php tools/universe_console.php hierarchy     # inspect galaxies/systems/planets/countries
```

`advance` accepts the same `steps` and `delta` arguments as the daemon itself. The
console validates the values and falls back to sensible defaults when they are omitted.

## Interactive shell

For longer sessions the `repl` command keeps the process running and lets you issue
multiple commands without restarting PHP each time:

```bash
php tools/universe_console.php repl
```

Once inside the shell you can:

- Type any of the commands listed above (for example `status` or `advance --steps=3`).
- Type `socket /path/to/other.sock` to point the console at a different daemon instance.
- Type `json on` or `json off` to toggle pretty-printed JSON responses.
- Type `help` to print the usage summary or `quit`/`exit` to leave the shell.

Each command opens a short-lived connection, waits for the daemon response, and prints a
human-readable table or JSON payload depending on the output mode. Errors are reported
inline without terminating the console so you can correct typos and try again.

## Exploring the object hierarchy

Use the `hierarchy` command when you need a structured overview of the currently running
simulation. By default the daemon returns galaxies, their systems, and the planets within
each system. You can tailor the depth and focus:

```bash
# show galaxies, systems, planets, and country summaries
php tools/universe_console.php hierarchy --depth=4

# zoom in to a single system and include individual people
php tools/universe_console.php hierarchy --path="galaxy:andromeda/system:sol" --include-people=1 --depth=5
```

`--path` accepts slash-separated selectors (`galaxy:<name>/system:<name>/planet:<name>/country:<name>/person:<name>`). The
console normalizes names in the same way the simulation does, so you can use spaces or
mixed case freely. Increase `--depth` to reveal deeper levels (galaxies → systems →
planets → countries → people) and append `--json` to receive the raw payload for custom
tooling.

## Troubleshooting

- If the console reports `Unable to connect to universe daemon`, verify that the daemon
  is running and that the socket path is correct. The interactive shell preserves the
  most recent value you supply with the `socket` command.
- When running on systems without UNIX domain sockets you can adjust the daemon and
  console to use TCP by editing the configuration constants in `config.php`. The console
  simply forwards whatever path or address you provide.

