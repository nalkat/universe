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
- `--workers=<int|auto>` – configure how many PHP workers advance galaxies in parallel (defaults to auto-detected CPU count).

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

All of this prose now lives in a repository-local metadata cache managed by
`MetadataStore`. By default the simulator connects to the bundled PostgreSQL
database specified in `config/metadata.php`, storing descriptions and chronicle
entries once and referencing them by ID to dramatically reduce in-memory pressure.
When the database is unavailable the store falls back to
`runtime/meta/metadata.sqlite`, ensuring lore survives even on minimal setups. In
both modes the cache deduplicates entries, enforces chronicle limits, and keeps
description fetches hot via an in-process cache.

### Parallel stepping and SMP utilization

Universe ticks now fan out across multiple worker threads when the PHP `parallel`
extension is available. Use `--workers=<int|auto>` (or the GUI's Workers field) to set
the pool size—by default the simulator matches the detected CPU count. When multi-core
support is unavailable the simulator automatically falls back to sequential stepping and
logs a warning so operators can adjust their environment.

### Macro-scale dynamics and transit objects

Galaxies no longer drift as isolated islands. Every tick the universe samples nearby
pairs and applies tidal nudges when their halos overlap, logging the interaction when the
overlap is significant. Random events can now trigger direct galactic collisions that
record chronicle entries on both participants, spawn debris plumes that travel between
galaxies, and slightly displace their centers to model the ensuing gravitational chaos.

Stars are likewise eligible for dramatic mass-loss events. When a primary sheds material
the hosting system updates planetary velocities, chronicles the disturbance, and ejects
worlds that can no longer remain bound. Those unbound bodies become `TransitObject`
instances—ballistic travelers that retain their mass, radius, and lore while streaking
toward interstellar space.

Transit objects are tracked at two scales:

- **Intergalactic wayfarers** emerge from collisions or exploratory launches and travel
  between galaxy centers using randomly selected propulsion (solar sails, fusion torches,
  antimatter wakes, or magnetic ramjets) and hull geometries (needle hulls, latticework
  frames, etc.).
- **Intersystem couriers** originate from system residents and carve new routes across
  local space with propulsion types suitable for shorter hops (fusion torches, ion drives,
  quantum spinnakers, and more).

Each `TransitObject` updates its position, velocity, and descriptive text as it advances.
Arrival events are chronicled on the destination galaxy or system so operators can see
when debris, probes, or refugees reach their new homes.

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

The Universe Browser window now leads the experience with simulation controls available
as a separate floating window (**Window ▸ Simulation Controls** or the toolbar button).
The detached panel exposes PHP binary, timing, and world-generation settings without
obscuring the catalog, while a dedicated console window handles command output so the
main browser remains responsive.

### Exploration features

- **Simulation controls** – The floating control panel ships with dedicated Run,
  Pause/Resume, Stop, and Reset actions plus quick access to catalog reloads and console
  cleanup. A persistent status indicator in the main window reports when commands are
  running, paused, completed, or stopping so operators always know the simulator's state
  at a glance.
- **Hierarchical atlas** – The browser renders galaxies, systems, planets,
  countries, cities, and citizens with chronicle excerpts, net-worth summaries, and
  planetary life breakdowns grouped by kingdom and phylum for rapid triage.
- **Procedural portraits** – VisualForge paints PNG portraits for galaxies, systems,
  stars, planets, countries, cities, residents, and catalogued materials. Images are
  stored in the metadata database (PostgreSQL by default with SQLite fallback) with
  generator prompts, resolutions, and timestamps so the browser can surface provenance
  alongside the artwork while falling back to analytic maps for geographic layers.
- **Dedicated console** – Command output streams into a resizable console window so
  long-running runs no longer fight the catalog panes. The console retains recent
  history and supports one-click clearing without resetting the browser.
- **Map overlays** – Country selections now sketch territorial bounds and city markers
  on the canvas, city views render a local population map with individual resident dots,
  and person entries highlight their coordinates on the global projection when location
  data is available.
- **Economic telemetry** – Planet, country, and person panels surface aggregate net
  worth, per-capita wealth, and other ledger details so you can spot thriving or
  struggling communities at a glance.
- **Catalog search** – A search box filters the hierarchy by name or summary, helping you
  jump directly to the systems, planets, or citizens you're interested in without
  manually expanding each branch.
- **Async catalog loading** – Catalog requests now run in the background with inline
  status updates, eliminating GUI freezes when fetching massive universes or when the
  PHP CLI emits diagnostic output alongside JSON.

### Portrait generation pipeline

- **Metadata-backed assets** – `utility/class_metadataStore.php` now records the
  generator responsible for each image (`VisualForge`, `tools/artisan.py`, or custom
  pipelines), the originating prompt, output resolution, creation timestamp, and any
  supplemental attributes. Catalog exports surface these fields alongside base64 image
  data so UI layers can display provenance without additional lookups.
- **`tools/artisan.py`** – A new Python helper that prefers Hugging Face diffusers
  pipelines to synthesize entity portraits. When diffusers or GPU acceleration are
  unavailable the tool falls back to a deterministic gradient renderer so operators can
  still seed the metadata store with lightweight placeholders. Invoke it with
  `python3 tools/artisan.py "luminous spiral galaxy" --format=json` to emit a
  ready-to-ingest JSON payload or add `--output` to write PNG files directly.

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
