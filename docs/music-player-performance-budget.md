# Music Player Performance Budget

Date: 2026-06-30

Target budget: click on Play to audible audio in less than 300ms on a standard production connection after the first metadata response is warm.

Implemented changes:

- Audio element now uses `preload="metadata"` instead of `preload="none"` so duration and stream metadata can be prepared without forcing full file download.
- Ready playback payloads are cached in memory by `play_url`; repeat same-track/queue interactions no longer refetch metadata that was already loaded in the current page session.
- Playback payload cache is intentionally limited to `200 OK` responses with `state: "ready"` so login, locked, forbidden, and playback-error states can recover after auth/access changes.
- Cover art is applied after image load with a lightweight loading state, avoiding a blank/half-painted artwork swap in the player shell.
- Rapid next clicks are debounced at 220ms to avoid overlapping playback metadata requests.
- Queue rendering no longer opens the track list automatically, reducing layout expansion during initial playback.

Production baseline measured on current production:

Environment:

- URL: `https://renyrenteria.com`
- Date: 2026-06-30
- Network: Codex runtime to production over HTTPS, no browser cache, using `curl -L`
- Scope: public playable tracks only; no authenticated Royal/Purchased content

Results:

| Resource | URL / source | Status | Size | Total | TTFB | Notes |
| --- | --- | ---: | ---: | ---: | ---: | --- |
| Home HTML | `/` | 200 | 36.1 KB | 9.581s | n/a | Initial uncached HTML was slow from this runtime. |
| Album playback JSON | `/music/play/5` | 200 | 12.9 KB | 367ms | 367ms | Returns 27-track queue. Warm repeat: 363ms. |
| Single playback JSON | `/music/play/2` | 200 | 1.1 KB | 317ms | 316ms | Returns one playable MP3 track. |
| Single playback JSON | `/music/play/3` | 200 | 983 B | 424ms | 424ms | Returns one playable MP3 track. |
| Album audio HEAD | WAV from `/music/play/5` | 200 | 27.7 MB | 262ms | 262ms | `Accept-Ranges: bytes` present. |
| Album audio range | first 2 bytes of WAV | 206 | 2 B | 262ms | 261ms | Confirms streaming/range support. |
| Single audio HEAD | MP3 from `/music/play/2` | 200 | 2.0 MB | 271ms | 270ms | `Accept-Ranges: bytes` present. |
| Single audio range | first 2 bytes of MP3 | 206 | 2 B | 304ms | 304ms | Confirms streaming/range support. |
| Album cover HEAD | JPG from `/music/play/5` | 200 | 113 KB | 261ms | 261ms | Already lazy-loaded on the page. |
| Single cover HEAD | JPG from `/music/play/2` | 200 | 30 KB | 265ms | 265ms | Already lazy-loaded on the page. |

Performance impact vs. current production baseline:

- Current production refetches playback JSON on repeated same-track/queue interactions. Observed production metadata cost is 317-424ms per playback JSON request, depending on track.
- This PR removes that playback JSON request after the first ready payload for the same `play_url`, so warm repeat interactions save one network request and the observed 317-424ms metadata wait.
- The measured audio endpoints support `Accept-Ranges: bytes` and `206 Partial Content`; the player does not need to wait for full audio downloads. The large album WAV is 27.7 MB but can stream.
- The target `<300ms` click-to-audible budget is realistic for warm in-session interactions because cached metadata leaves the audio range request as the main network step, measured at 262-304ms from this runtime.

Browser UAT status:

- Playwright MCP was attempted for visual/browser timing validation, but the runtime returned `user cancelled`.
- Browser confirmation of close/restore/queue behavior and audible timing remains a final QA gate before production deployment.

Reproduction commands:

```bash
curl -L https://renyrenteria.com -o /tmp/reny-prod-home.html -w '%{http_code} %{time_total} %{url_effective}\n'
curl -L https://renyrenteria.com/music/play/5 -H 'Accept: application/json' -o /tmp/reny-prod-play-5.json -w 'play5 http=%{http_code} total=%{time_total} ttfb=%{time_starttransfer} size=%{size_download}\n'
curl -L https://renyrenteria.com/music/play/2 -H 'Accept: application/json' -o /tmp/reny-prod-play-2.json -w 'play2 http=%{http_code} total=%{time_total} ttfb=%{time_starttransfer} size=%{size_download}\n'
curl -L -I "$(jq -r '.audio_url' /tmp/reny-prod-play-5.json)"
curl -L -r 0-1 "$(jq -r '.audio_url' /tmp/reny-prod-play-5.json)" -o /tmp/reny-prod-audio-5-range.bin -w 'audio5-range http=%{http_code} total=%{time_total} ttfb=%{time_starttransfer} size=%{size_download}\n'
```
