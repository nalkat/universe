# Change Log

## [Unreleased]
- Exported dynamics snapshots (position, velocity, nearby bodies, and tick cadence) for
  stars, planets, and systems so catalog consumers can visualize orbital motion without
  querying the simulation directly.
- Animated the GUI visual tab with per-tick motion for the selected object and its
  neighbors, rendering velocity vectors and trails while keeping the rest of the catalog
  static for smooth browsing.
- Calibrated the dynamics renderer to plot per-tick displacement arrows with viewport-aware
  clamping so both slow and fast bodies remain legible on the canvas.
- Added metadata database controls to the control panel that toggle between PostgreSQL
  and SQLite backends, updating `config/metadata.php` before each run to keep CLI and GUI
  sessions aligned.
- Surfaced GUI error messaging when metadata configuration writes fail so operators know
  when database selection changes were not persisted.
- Pruned expired chronicle rows when narrative handles exceed retention limits so massive
  run-once sessions no longer leave the metadata database bloated or stall subsequent
  catalog loads.
- Extended the metadata store with generator, prompt, attribute, and resolution columns so
  exported images now carry provenance and can be filtered or regenerated without manual
  bookkeeping.
- Added `tools/artisan.py`, a Python art helper that prefers diffusers pipelines when
  available and falls back to deterministic procedural renders so portrait generation can
  be automated even on hosts without GPU acceleration.
- Reframed the Tkinter GUI around a toolbar-driven Universe Browser with tabbed detail
  panes, a dedicated console window, and metadata overlays on portraits, keeping
  exploration responsive while simulation controls live in a separate floating panel.
- Modeled galactic tidal interactions, collision chronicles, and debris plumes while introducing `TransitObject` tracking for intergalactic wayfarers and intersystem couriers.
- Added stellar mass-loss events that adjust planetary orbits, chronicle destabilization, and eject unbound worlds into tracked transit streams.
- Hardened random event generation to skip rolls when no catalog objects exist and to bound affected-object sampling so catalog queries no longer crash with invalid `random_int` ranges.
- Persisted narrative descriptions and chronicles in a repository-local SQLite metadata store so simulator objects share cached lore without inflating in-memory payloads.
- Added busy waits and retry guards to the SQLite metadata store so concurrent catalog builds and simulations no longer emit `database is locked` warnings.
- Stored VisualForge-generated PNG portraits for galaxies, systems, planets, settlements, residents, and material catalog entries inside the metadata database so every object now carries reusable imagery.
- Re-centered the GUI around the Universe Browser with a menu-driven Simulation Control Panel, background catalog loading shortcuts, and automatic portrait rendering while retaining analytic maps for geographic layers.
- Upgraded the metadata store to prefer the bundled PostgreSQL database (with automatic SQLite fallback) so lore persistence scales under heavy parallel workloads without locking contention.
- Added entity-scoped metadata keys, description updates, and chronicle pruning so the SQLite lore store stays compact and the GUI catalog remains responsive during large runs.
- Added configurable `--workers` support and parallel galaxy advancement to exploit multi-core CPUs when the PHP `parallel` extension is available.
- Offloaded GUI catalog loading to a background worker with inline status updates so large catalogs no longer freeze the control panel.
- Added pause/resume/stop controls, a persistent status indicator, and a reset workflow to the Tkinter control panel so operators can manage and monitor long-running simulations without leaving the GUI.
- Added catalog search tooling alongside country territory overlays, city population maps, resident dots, and planetary life breakdown summaries grouped by kingdom and phylum.
- Surfaced per-planet, per-country, and per-person net-worth metrics and coordinate metadata throughout the catalog to anchor upcoming economic visualizations.
- Added per-planet timekeeping profiles with local day/year lengths and relative flow rates, surfaced via planet descriptions and simulator snapshots.
- Rebased country and citizen simulation to local planetary time so hunger, recovery, and aging honor each world's cadence and expose life expectancy in local years.
- Added narrative descriptions to celestial bodies and settlements to support richer UI labeling, including dynamic star, planet, and country summaries.
- Modeled persistent planetary weather systems that cycle through climate-informed patterns and feed into each world's descriptive text.
- Wove collaborative citizen backstories using shared country chronicles so every person now references their community and relationships.
- Documented simulation tasks and readiness goals for hierarchical spawning.
- Replaced the legacy external telemetry dependency with a built-in module and updated consumers to reference the new metrics container.
- Removed shared-environment and EnVision-era scaffolding so the simulator relies solely on in-repo libraries and runtime paths.
- Slowed the Tkinter control panel defaults to one-step, one-minute ticks with a built-in delay so catalog exploration no longer races the simulation.
- Planned improvements for universe simulation orchestration.
- Implemented hierarchical environment checks and readiness gating for countries and people.
- Added a reusable UniverseSimulator orchestrator and updated the executable to drive galaxies, systems, and civilizations.
- Ensured the UniverseSimulator tracks system registries per galaxy to avoid undefined index warnings during bootstrap.
- Modeled personal resilience growth and national adaptation investments so populations can harden against famine and disasters over time.
- Defined material, chemical, ecological, and settlement scaffolding to classify non-sentient matter and living habitats.
- Expanded structural modelling with adaptive habitats, experimental nests, and burrows that capture natural engineering successes and failures.
- Built a hierarchy inspector command for the console so operators can navigate galaxies, systems, planets, and populations while the daemon runs.
- Modernized `universe.php` to leverage PHP 8.3 enum-based command parsing and stricter type handling.
- Introduced a Tkinter desktop control panel to run simulator commands without relying on the console-only workflow.
- Extended the desktop control panel with a browsable universe catalog, tick delay controls, and auto-refreshing object visuals.
- Added a startup guard that explains how to install Tk support when the GUI is launched on systems without the `tkinter` module.
- Ensured settlement dependencies load before cities so simulator bootstrapping no longer triggers fatal errors.
- Automatically create required runtime and logging directories when launching the simulator.
- Updated legacy logger and utility helpers to use explicit nullable types and high-resolution timestamps compatible with PHP 8.3.
- Removed PHP execution time and memory limits so the simulator can freely scale to massive procedurally generated worlds.
- Procedurally generate galaxies, stellar systems, and planets at launch with reproducible seeds and CLI controls for galaxy,
  system, and planet counts.
- Added multi-factor planetary habitability scoring, classification labels, and richer summary output that highlights the
  environmental drivers supporting or undermining each world.
- Increased the default galaxy, system, and planet counts so each run spawns thousands of unique worlds, and left run/daemon delta values unconstrained for high-frequency experimentation.
- Documented universal vs. planetary time units in `docs/TIMEKEEPING.md` so UI and telemetry consumers can interpret ages,
  ticks, and longevity consistently.
- Swapped the GUI console and control panel launchers to toggles that reflect open state,
  making it obvious when auxiliary windows are already visible and providing one-click
  close actions.
