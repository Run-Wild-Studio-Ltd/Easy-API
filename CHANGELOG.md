# Release Notes for Easy API


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