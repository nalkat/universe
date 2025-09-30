This program is a simulater for the universe and an experiment in object spawning.
The purpose of this appliation is to create objects which create and run other objects
which in turn create other objects, and so on and so forth. This will allow for a great
many different types of things possible, from simulating clients for load testing, for
studying how differing objects with a common ancestors interact with each other.

## Running the universe daemon

The `universe.php` executable now operates as a daemon. Use the `start` command to launch
it in the background and the companion console utility to interact with the running
simulation.

```
php universe.php start --delta=3600 --interval=1 --auto-steps=1
```

The daemon listens on a UNIX socket located at `runtime/universe.sock` by default. Use
the console client to query status, fetch snapshots, advance the simulation, or issue a
shutdown.

```
php tools/universe_console.php status
php tools/universe_console.php snapshot
php tools/universe_console.php advance --steps=5 --delta=1800
php tools/universe_console.php shutdown
```

Pass `--no-daemonize` to `universe.php start` to run the service in the foreground (useful
when developing locally). To run a single batch of steps and print a summary, use the
`run-once` command instead:

```
php universe.php run-once --steps=5 --delta=7200
```
