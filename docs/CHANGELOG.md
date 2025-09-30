# Change Log

## [Unreleased]
- Documented simulation tasks and readiness goals for hierarchical spawning.
- Replaced the EnVision dependency with a built-in telemetry module and updated consumers to reference the new metrics container.
- Planned improvements for universe simulation orchestration.
- Implemented hierarchical environment checks and readiness gating for countries and people.
- Added a reusable UniverseSimulator orchestrator and updated the executable to drive galaxies, systems, and civilizations.
- Ensured the UniverseSimulator tracks system registries per galaxy to avoid undefined index warnings during bootstrap.
