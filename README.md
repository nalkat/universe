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
shutdown. See [docs/UNIVERSE_CONSOLE.md](docs/UNIVERSE_CONSOLE.md) for a deeper tour of
the console, including the new interactive shell mode.

```
php tools/universe_console.php status
php tools/universe_console.php snapshot
php tools/universe_console.php hierarchy --depth=4
php tools/universe_console.php advance --steps=5 --delta=1800
php tools/universe_console.php shutdown
php tools/universe_console.php repl           # stay connected and run multiple commands
```

Pass `--no-daemonize` to `universe.php start` to run the service in the foreground (useful
when developing locally). To run a single batch of steps and print a summary, use the
`run-once` command instead:

```
php universe.php run-once --steps=5 --delta=7200
```

## Matter and ecology scaffolding

The simulator now includes foundational classes for cataloguing matter and living habitats:

- `Particle`, `Element`, and `Compound` describe the building blocks of chemistry and expose helpers for rest energy, isotopes, and bonding.
- `Plant`, `Animal`, and `Insect` extend the `Life` base class to model hunger, growth, metamorphosis, and resilience feedback loops.
- `Structure`, `Habitat`, `Nest`, `Burrow`, `Settlement`, `City`, and `House` organize living spaces, resource flows, and infrastructure conditions so emerging populations have places to inhabit.
- `Habitat` specializations embrace natural and artificial engineering alike, letting insect hives, burrows, or experimental refuges evolve traits, fail safely, and adapt to new pressures.
- `Taxonomy` offers reusable classification metadata to keep species aligned with their biological context.

These classes provide the categorization framework required before the simulation begins generating complex civilizations.

Use the `hierarchy` console command to explore the currently running universe. Adjust `--path` (for example,
`galaxy:andromeda/system:sol`) and `--depth` to focus on specific regions, and add `--include-people=1` to surface individual
citizens when needed.
