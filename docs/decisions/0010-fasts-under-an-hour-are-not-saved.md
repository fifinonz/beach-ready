# 10. Fasts under an hour aren't saved

## Context

The rule so far was that every fast is saved to history, including one ended early: the "Ending early is normal" domain rule in CLAUDE.md. In production that saved a 0-minute fast: someone started a fast and ended it straight away. It appeared in History as "Today · ended early · 0m", counted towards "fasts this week" and the streak, and pulled the weekly average down.

A fast that lasted a few minutes isn't a fast. It's usually a mis-tap, a change of mind, or someone trying the app.

## Decision

- **A fast ended within its first 60 minutes isn't saved.** The server deletes it, and its reminders with it, instead of ending it. The response says `counted: false`.
- **Anything of an hour or more is saved as before,** marked "ended early" if it stopped before the goal. No other wording changes now. Showing whether a fast reached its goal is a later feature (in the README as "Goals").
- **The message is gentle and encourages keeping going.** In the first hour, the End fast sheet says: "Fasts shorter than an hour aren't saved, so this one won't count yet. Keep going and it will." "Keep going" is the main button and "End without saving" the secondary one. There's no feeling question, since nothing is saved. Afterwards, Today says the fast wasn't saved and that you can start again whenever you're ready. Nothing implies failure.
- **The limit lives in one place on each side.** It's `Fast::MIN_COUNTED_MINUTES` on the server, mirrored in the End fast sheet for the message. The server decides.
- **Fasts already saved are left alone.** The rule applies from this release on; existing short fasts can be removed by hand if needed.

## Trade-off

- **An hour is an arbitrary line.** It's long enough to rule out mistakes and short enough that a real attempt counts. It's a single constant if it needs to move.
- **The previous eating window reminder stays cancelled.** Starting a fast cancels it, and an unsaved fast doesn't bring it back. That's a small loss, and only for someone who starts and abandons a fast within an hour.
- **No trace is kept.** The data export won't show fasts under an hour, which is consistent with them not being fasts.
