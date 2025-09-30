# Temporal Frames and Longevity

The simulator tracks time in a universal frame (used by the core integrators) and
planet-specific local frames (used by civilizations). This document summarizes the
key touchpoints so operators and tool builders can interpret ages, years, and tick
lengths without digging through the PHP source.

## Universal ticks

- Each simulation step advances the universe by the `--delta` value supplied on
  the CLI. Both `run-once` and `start` default to `3600` seconds (one universal
  hour), but any positive or negative value is accepted. `UniverseSimulator::run`
  forwards this delta directly to `Universe::advance`, so **one tick equals the
  delta number of universal seconds**.
- The base `Life` class stores `age` in universal seconds. Every call to
  `Life::tick($deltaTime)` adds the supplied delta to `age`, so raw ages are
  expressed in the simulator's baseline seconds.

## Planetary timekeeping

- `Planet::generateTimekeepingProfile()` assigns each planet:
  - `relative_rate`: how quickly local seconds flow compared to the universal
    frame (values range from 0.4× to 2.5× by default).
  - `hours_per_day`, `day_length_local`, and `year_length_local`: the length of
    local days and years measured in local seconds.
- `Planet::getTimekeepingProfile()` exposes conversions for local days/years to
  universal seconds, the last tick durations, and a natural-language summary.
- `Planet::convertUniversalToLocalSeconds()` multiplies universal seconds by the
  planet's `relative_rate`, while `convertLocalToUniversalSeconds()` performs the
  inverse. These helpers ensure downstream systems consume time in the correct
  frame without duplicating math.

## Country and citizen cadence

- When `Country::tick()` executes, it converts the incoming universal delta to
  local seconds before advancing development, economies, weather, and residents.
- `Person::tick()` mirrors this behaviour: hunger, recovery, and skill training
  use the planet's day length, and aging/senescence rely on the local year length
  resolved via `Person::resolveLocalYearLengthSeconds()`.

## Ages, years, and life expectancy

- `Person::getAgeInYears()` divides a resident's universal age (after conversion
  into local seconds) by their planet's local year length so ages are always
  reported in **planetary years**.
- `Planet::summarizePopulationLongevity()` aggregates the local-year life
  expectancy and senescence start ages for every inhabitant and includes the
  planet's local day/year durations in both local and universal seconds. When the
  population is empty, `Planet::estimateBaseLongevity()` projects life expectancy
  using habitability, climate variance, and relative time dilation.

Armed with these definitions, snapshot consumers can confidently answer
"How long do these people live?" in whichever frame—local or universal—is most
useful for their interface.
