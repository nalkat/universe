# Change Log

## [Unreleased]
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
