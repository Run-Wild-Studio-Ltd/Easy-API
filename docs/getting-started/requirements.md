---
layout: page
title: Requirements
permalink: /getting-started/requirements/
---

# Requirements

### Craft CMS
Easy API requires Craft CMS 4.0 or greater and FeedMe 5.3.0 or greater.

### PHP
Easy API requires PHP 8.0.2 or greater.

### Background processing
Clicking **Run API** queues the import and spawns a detached `php craft queue/run` process so long-running imports execute outside the web request. This requires a PHP CLI binary on the server and the `exec`/`popen` functions to be enabled in `php.ini`. If these are unavailable the import falls back to Craft's standard web queue runner, which may be subject to web server timeouts on large parent-based feeds.

<div style="display: flex; justify-content: space-between">
<a href="/getting-started/installation-setup">← Installation & Setup</a><a href="/getting-started/configuration">Configuration →</a>
</div>