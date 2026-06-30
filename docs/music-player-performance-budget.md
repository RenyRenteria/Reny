# Music Player Performance Budget

Date: 2026-06-30

Target budget: click on Play to audible audio in less than 300ms on a standard production connection after the first metadata response is warm.

Implemented changes:

- Audio element now uses `preload="metadata"` instead of `preload="none"` so duration and stream metadata can be prepared without forcing full file download.
- Playback payloads are cached in memory by `play_url`; toggling play, pause, shuffle, next, and previous no longer refetch metadata that was already loaded in the current page session.
- Cover art is applied after image load with a lightweight loading state, avoiding a blank/half-painted artwork swap in the player shell.
- Rapid next clicks are debounced at 220ms to avoid overlapping playback metadata requests.
- Queue rendering no longer opens the track list automatically, reducing layout expansion during initial playback.

Production measurement checklist:

1. Open production in Chrome with DevTools Network and Performance tabs.
2. Use a clean profile, disable cache for baseline, and select Fast 4G throttling.
3. Start recording, click Play on a playable track, stop when audio becomes audible.
4. Record `play_click_to_audio_audible_ms`, playback JSON request count, audio request start time, and whether the audio response streams with partial content/range requests.
5. Repeat after one warm play of the same track to confirm metadata cache behavior.

Expected improvement:

- Warm same-track/queue interactions should avoid one playback JSON refetch per interaction.
- Audio startup should begin from metadata preload/streaming behavior instead of waiting for complete file download.
- The production baseline still needs to be captured after deployment for a numeric before/after comparison.
