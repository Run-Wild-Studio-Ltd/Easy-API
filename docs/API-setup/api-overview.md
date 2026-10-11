---
layout: page
title: Overview
permalink: /setup/overview
---
# API Overview

Go to the main Easy API section via your CP sidebar menu. You'll be presented with a table listing of your saved APIs, or none if you haven't set any up yet. Setup is very similar to a feed in FeedMe, with the addition of being able to select parent elements and parent element groups.

![Start Page](/assets/img/api-list.jpg)

This overview shows the following (field handle for configuration in brackets):

- **Name** (`name`) - Name your API something useful so you'll remember what it does.
- **API URL** (`APIUrl`) The URL to the external API.
- **Type** (`contentType`) - The data type you're importing.
- **Parent Element Type** (`parentElementType`) - The element type you are linking the imported data to.
- **Parent Element Group** (`parentElementGroup`) - Depending on the parent element type chosen. Entries will show Section/Entry Type, Categories will show Group, etc.
- **Element Type** (`elementType`) - The element type you are importing into.
- **Element Group** (`elementGroup`) - Depending on the element type chosen. Entries will show Section/Entry Type, Categories will show Group, etc.
- **Strategy** (`duplicateHandle`) - What import strategy you have chosen: how you'd like to handle duplicate API items (if you're going to be re-running this API).
- **Run API** - Queues the API for import and returns immediately (see _Running an API_ below)
- **Settings (icon)** - Additional settings pane (see below).
- **Delete (icon)** - Delete this API (be careful).

### Settings Panel
Clicking on the settings cog icon will expand additional settings for each API.
- **Debug API** - Opens in a new window and runs the Debug action.
- **API Status** - Takes you to an overview screen showing the process of your API if currently running.
- **Duplicate API** - Duplicates this API and all settings into a new API.

Create a new API by pressing the red _\+ New API_ button in the top-right, or click on the Name column in your table. You'll then be taken to "Create your API".

### Running an API

Clicking **Run API** pushes the import onto Craft's queue and spawns a detached `php craft queue/run` process, so the import executes in the background and the page returns immediately rather than waiting for the run to finish. This matters most for parent-based APIs, which may make hundreds or thousands of sequential API calls. Monitor progress under **Utilities → Queue Manager** or in the Feed Me logs.

Feed Me jobs pushed by Easy API are given a queue time-to-reserve (TTR) of 14400 seconds by default so long imports aren't restarted mid-run. You can override this with a `config/feed-me.php` file setting `queueTtr`.

For an API to run through Feed Me's queued import, its feed must have a field mapping configured. APIs without a field mapping run through the direct path instead, which executes any configured post-import handlers without mapping data into elements.

### Console commands

APIs can also be queued or run from the command line, which is recommended for very large imports:

```bash
# Queue the API as a background job (executes via your normal queue worker)
php craft easyapi/apis/queue <apiId>

# Run the API directly in the console, bypassing the queue
php craft easyapi/apis/run-direct <apiId>
```

`run-direct` executes the same feed pipeline as a queued run (including parent element looping and post-import handlers), so it's a reliable way to run large parent-based imports with visible output.

### Feed Me setup screens

When stepping through Feed Me's feed setup screens (primary element and field mapping), only the first 5 parent elements are requested. These screens only need a representative sample of the data to enumerate available fields, so your full parent set is not looped during configuration. Full runs still process every parent element.

<div style="display: flex; justify-content: space-between">
<a href="/getting-started/configuration">← Configuration</a><a href="/setup/creating">Creating Your API Feed →</a>
</div>