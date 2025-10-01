This program is a simulator for the universe and an experiment in object spawning.
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

### Universe generation controls

Every invocation of `universe.php` now builds a fresh procedural blueprint instead of
loading a small set of canned systems. The generator fills the sandbox with a dozen or
more galaxies, each packed with scores of stellar systems and thousands of uniquely
randomized planets. Their environmental conditions are evaluated for habitability using
temperature, atmospheric chemistry, magnetic shielding, gravity, radiation exposure,
resource richness, and climate stability. Habitable worlds spawn multiple autonomous
countries with their own development profiles and population seeding plans.

Use these command-line options with either `start` or `run-once` to steer the generator:

- `--seed=<int>` – produce deterministic galaxies for repeatable tests.
- `--galaxies=<int>` – override the number of galaxies created (defaults to 12–20).
- `--systems-per-galaxy=<int>` – set the baseline system count per galaxy (defaults to 14 with randomized variance).
- `--planets-per-system=<int>` – set the baseline planet count per system (defaults to 20 with randomized variance).

The runtime summary now reports each planet's habitability class (`lush`, `temperate`,
`marginal`, `hostile`, or `barren`) alongside the two dominant environmental factors that
influenced the score so operators can quickly identify promising or precarious worlds.
Command-line deltas are no longer clamped back to hour-long steps, so advanced operators
can experiment with sub-second or even negative time slices when stress testing the
simulation.

### Planetary weather and narrative data

Each generated planet now seeds a constellation of weather systems tuned to its
climate profile. Storm tracks, monsoon cycles, dust fronts, and temperate jet streams
rotate automatically as the simulation advances, with progress tracked through
`Planet::getWeatherSystems()`, `Planet::getCurrentWeather()`, and `Planet::getWeatherHistory()`.
These climate cues feed directly into the planet's descriptive text so the upcoming UI
can present more than a bare name and habitability score.

Stars, planets, countries, and citizens likewise publish narrative descriptions. Stars
summarize their spectral class, temperature, luminosity, and stage; planets weave
climate, resources, and the active weather cycle into a readable synopsis; countries
compile founding lore and recent events; and every person now carries a backstory that
references mentors, rivals, and shared community history. Access these strings via
`SystemObject::getDescription()`, `Country::getDescription()`, and `Person::getBackstory()`
to differentiate entries in upcoming UI layers.

### Relative timekeeping and population longevity

Every planet now maintains its own temporal frame describing how quickly local seconds
pass relative to the simulator baseline, how long days and years last, and how those
durations convert to universal seconds. Use `Planet::getTimekeepingProfile()` to inspect
the relative rate, day and year lengths (in both local and universal units), and the
most recent tick durations. `Country` and `Person` instances automatically translate
incoming universal ticks into their planet's frame so hunger, recovery, training, and
aging all respect local days and years.

Citizen generation now derives life expectancy and senescence thresholds from the host
planet's habitability, the country's development level, and the planet's temporal
dilation. Access `Person::getAgeInYears()`, `Person::getLifeExpectancyYears()`, and
`Person::getSenescenceStartYears()` to surface meaningful age and longevity data in
snapshots or user interfaces. Planetary population summaries and daemon snapshots now
include longevity projections alongside raw population counts so operators can answer
"How long do these people live?" without manual conversions.

For a deeper walkthrough—including how ticks map to universal seconds and how local
years are resolved per planet—see [`docs/TIMEKEEPING.md`](docs/TIMEKEEPING.md).

## Desktop control panel

The `tools/universe_gui.py` script offers a lightweight Tkinter interface for running
`universe.php` commands without relying on a terminal. Launch it with:

```
python3 tools/universe_gui.py
```

> **Prerequisite**
> The GUI depends on the Python `tkinter` module. Install your platform's Tk bindings
> (for example `python3-tk` on Debian/Ubuntu) before launching the interface.

Use the "Run Once" mode to execute batch simulations and review the summarized output
in the integrated log pane, or switch to "Start Daemon" to manage a foreground daemon
instance with custom socket and PID file paths. The GUI keeps the command history in
view so operators can iterate quickly while we work toward a fully art-directed theme.

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
