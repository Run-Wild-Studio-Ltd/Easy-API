---
layout: page
title: Creating your API Feed
permalink: /setup/creating
---
# Creating your API Feed

Setting up your API feed involves configuring various fields, much like a feed created through FeedMe. Aside from the standard FeedMe fields, you have the option of selecting a parent element to allow nested calls to subsequent API data. Although each field is quite self-explanatory, additional details are provided below:

![Start Page](/assets/img/api-setup.jpg)

### Name

Set a name for your API feed to easily track your imports.

### Target Site

For multi-site Craft installations, choose a **Target Site** where elements will be initially saved. Content will then propagate to other sites based on **Translation Method** settings.

### API URL

Specify the API URL. It can be absolute, relative (to the web root), and may utilize aliases. For dynamic URL updates per parent entity record, insert the URL relative to another entity and select Parent Element Type and Parent Element Id Field.

For example: https://www.api-feed.com/param/another-param/{{ id }}/more-param

### Parent Element Type

Select the element type when fetching data from an API related to another entity. For more information on Mapping your element types, please refer to the FeedMe documentation.

### Parent Element Id Field

Choose the field used from the parent element to determine the API URL for subsequent feed imports where a feed is reliant on unique identifiers contained in the parent element. This field corresponds to the dynamic Twig element in the example URL above.

<div class="alert alert-primary">
  Parent-based APIs make one API request per parent element, so large parent sets produce long-running imports. OAuth/OAuth2 access tokens are cached and reused for their lifetime rather than re-requested per parent, responses are merged once after the loop completes, and any individual parent request that fails is logged and skipped rather than aborting the run. For large parent sets, prefer running via the queue or console commands rather than waiting on a browser request.
</div>

### Content Type

Set the Content Type to match the data type being imported. Your options are:

- JSON
- XML

### Authorization

Enter the authorization public key for the API call.

### Element Type

Specify the element type where you want to import your API content. For more information on Mapping your element types, please refer to the FeedMe documentation.

### Use API from front end

Check this box to enable Easy API entry from the front end for live calls. This will disable the various content importing fields and setup such as the job queue and element types.

### Use Job Queue

Enable Craft’s job queue if you want continuous data fetching from the API to update your site.

### Job Queue Process Order

Enter an integer to determine the processing sequence of Easy API entries when using job queue. Jobs are processed in ascending order, i.e. 1 will be processed, then 2 etc. This will allow parent API calls to be processed before subsequent API calls which are reliant on data contained in the parent element.

### Import Strategy

Define how Easy API should handle similar elements during import. Options include creating new elements, updating existing ones, disabling missing elements, and more.

<div class="alert alert-primary">
  Matching behavior is determined by a <a href="/setup/mapping#unique-identifiers">unique identifier</a>, configured later.
</div>

You can select from any combination of the following (handles for configuration in brackets), as per FeedMe's default behavior:

- **Create new elements** (`add`) - Adds new elements if they do not already exist (as determined by a _unique identifier_). If an element _does_ exist, it will only be updated if **Update existing elements** is enabled.
- **Update existing elements** (`update`) - Updates elements that match the _unique identifier_. If no existing element matches, one will be only be created if **Create new elements** is also enabled.
- **Disable missing elements** (`disable`) - Disables elements that are not updated by this API.
- **Disable missing elements in the target site** (`disableForSite`) - Disables elements that are not updated by this API, but only in the API’s target site.
- **Delete missing elements** (`delete`) - Deletes elements that are not updated by this API. **Be careful when deleting**.
- **Update search indexes** (`updateSearchIndexes`) - Whether search indexes should be updated.

Setting the import strategy in a configuration file requires a nested array in the following format:
```php
<?php
  'apiOptions' => [
      '1' => [
          'duplicateHandle' => ['add', 'update', 'delete'],
          'updateSearchIndexes' => true,
      ]
  ]
```

Click **Save & Continue** to proceed to the Primary Element screen, or simply **Save** to continue making changes on this screen. A FeedMe feed with " - Easy API" appended to the feed name will be created. From this point, we revert to FeedMe to process the remaining import tasks. Please refer to their excellent documentation for further guidance: <a href="https://docs.craftcms.com/feed-me/v4/" target="_blank">FeedMe Documentation</a>.

![Start Page](/assets/img/feedme-feeds.jpg)

<div style="display: flex; justify-content: space-between">
<a href="/setup/overview">← Feed Setup</a><a href="/setup/templates">Using in Your Templates →</a>
</div>