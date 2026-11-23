# Release Notes for Easy API


## 6.2.6 - 2026-11-23

-Added an optional daily run time for the job queue (`jobQueueTime`, HH:MM 24h — CP → Easy API → Settings or `config/easyapi.php`). When set, APIs run once per day at/after that time instead of every `jobQueueInterval` minutes. Includes catch-up behaviour: if the queue worker was down at the scheduled time, feeds still run once it comes back. Interval scheduling is unchanged when unset.

## 6.2.5 - 2026-11-23

-Added a `sleepTime` setting (CP → Easy API → Settings, or `config/easyapi.php`): seconds to sleep after each feed element is processed, throttling imports that upload many images/assets so they don't spike server resources. Overridable per API via `apiOptions.<apiId>.sleepTime`. Implemented by listening to Feed Me's `EVENT_STEP_AFTER_ELEMENT_SAVE`, so it only affects Easy API-backed feeds.

## 6.2.4 - 2026-11-23

-Prevented concurrent queue drains from CP "Run" actions: each run previously spawned a detached `queue/run` process that drains the entire queue, so repeated runs (or rapid clicks) created parallel workers executing heavy feed imports simultaneously and could exhaust the server. Spawns now go through `easyapi/apis/run-queue`, which holds a non-blocking file lock — extra spawns exit immediately while the active runner keeps draining.

-Added `spawnQueueRunner` config option (default `true`). Set to `false` on environments with a dedicated queue listener (e.g. Docker `queue/listen`) so only that single worker ever processes jobs.

## 6.2.3 - 2026-11-18

-Fixed OAuth2 requests still failing with 401 after 6.2.2: `getAuthValue()` returned a bare `Bearer <token>` string, which was sent as a header line with no `Authorization:` name and dropped by cURL. It now returns a complete `Authorization: Bearer <token>` header line, matching the contract used by the basic/none auth types.

## 6.2.2 - 2026-11-16

-Fixed auth headers never being sent on feed/API requests: `getAuthValue()` returns an array of header lines, but `getRawData()` concatenated it into a single `Authorization:` header — requests went out as `Authorization: Array` and every authenticated call failed with 401.

-All auth-derived headers are now redacted in the logged cURL command (not just `Authorization:`), so custom-parameter keys (e.g. `X-Api-Key`) don't leak into logs.

-Fixed fetch errors surfacing as `Unknown error` in Feed Me — the real error (`HTTP 401`, cURL failure, etc.) is now reported.

## 6.2.1 - 2026-11-09

-Added missing migration for the postImportHandler column (fixes index page crash on installs upgraded from older versions).

## 4.4.3 - 2024-05-22

-Resolved issue where Feed Me functions were not being called correctly.

## 5.0.3 - 2024-05-27

-Upgraded code for Craft 5.

## 5.1.2 - 2024-07-09

-Added OAuth logic.
-Added API Pagination logic.
-Moved Feed Me event functions to their own class.

## 5.1.5 - 2024-07-23

-Added custom authorization fields to basic authorization.

## 6.1.2 - 2024-07-24

-Creation of a unified version that supports both Craft 5 and Craft 4.
-Add version_compare to twig.

## 6.1.3 - 2024-07-31

-Resolve error when using only custom parameters in basic auth.

## 6.1.4 - 2024-08-12

-Resolve issue when duplicating API it wasn't creating a new Feed.
-Changed Feed Me Feed naming to use the Plugin Name as set in General Settings as the suffix.

## 6.2 - 2026-06-17

-Add orders entry type
-Add post API Feed Import Handler
-Add oAuth 2
-Cache OAuth/OAuth2 access tokens for their lifetime instead of requesting one per API call; credentials are no longer written to logs
-Add bounded cURL connect/transfer timeouts and surface cURL errors instead of "HTTP 0"
-Merge parent element API responses once after the loop instead of per-iteration; failed parent requests are now logged and skipped
-Queue API runs via a detached `queue/run` process so clicking Run API no longer waits on the import (requires a PHP CLI binary and `exec`/`popen`)
-Prevent Feed Me's job description from eagerly fetching the entire feed during `Queue::push()`
-Default Feed Me import jobs to a queue TTR of 14400 seconds (overridable via `config/feed-me.php`)
-Sample the first 5 parent elements on Feed Me setup screens instead of running the full parent loop
-Add `easyapi/apis/run-direct` console command to run an API directly in the console