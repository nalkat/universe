# Change Log

## [Unreleased]
- Added per-planet timekeeping profiles with local day/year lengths and relative flow rates, surfaced via planet descriptions and simulator snapshots.
- Rebased country and citizen simulation to local planetary time so hunger, recovery, and aging honor each world's cadence and expose life expectancy in local years.
- Added narrative descriptions to celestial bodies and settlements to support richer UI labeling, including dynamic star, planet, and country summaries.
- Modeled persistent planetary weather systems that cycle through climate-informed patterns and feed into each world's descriptive text.
- Wove collaborative citizen backstories using shared country chronicles so every person now references their community and relationships.
- Documented simulation tasks and readiness goals for hierarchical spawning.
- Replaced the EnVision dependency with a built-in telemetry module and updated consumers to reference the new metrics container.
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
- Ensured settlement dependencies load before cities so simulator bootstrapping no longer triggers fatal errors.
- Automatically create required runtime and logging directories when launching the simulator.
- Removed PHP execution time and memory limits so the simulator can freely scale to massive procedurally generated worlds.
- Procedurally generate galaxies, stellar systems, and planets at launch with reproducible seeds and CLI controls for galaxy,
  system, and planet counts.
- Added multi-factor planetary habitability scoring, classification labels, and richer summary output that highlights the
  environmental drivers supporting or undermining each world.
- Increased the default galaxy, system, and planet counts so each run spawns thousands of unique worlds, and left run/daemon delta values unconstrained for high-frequency experimentation.
- Documented universal vs. planetary time units in `docs/TIMEKEEPING.md` so UI and telemetry consumers can interpret ages,
  ticks, and longevity consistently.
