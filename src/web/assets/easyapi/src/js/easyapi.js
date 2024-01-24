/* globals Craft, $ */

// ==========================================================================

// Easy API Plugin for Craft CMS

// ==========================================================================

$(function () {
  if (typeof Craft.EasyApi === typeof undefined) {
    Craft.EasyApi = {};
  }

  // Settings pane toggle for apis index
  $(document).on('click', '#apis .settings', function (e) {
    e.preventDefault();

    var row = $(this).parents('tr').data('id') + '-settings';
    var $settingsRow = $('tr[data-settings-id="' + row + '"] .settings-pane');

    $settingsRow.toggle();
  });

  // Change the import strategy for Users
  var $disableLabel = $(
    'input[name="duplicateHandle[]"][value="disable"]'
  ).next('label');
  var originalDisableLabel = $disableLabel.text();
  var $disableInstructions = $disableLabel.siblings('.instructions');
  var originalDisableInstructions = $disableInstructions.text();

  // Toggle various field when changing element type
  $(document).on('change', '#elementType', function () {
    $('.element-select').hide();

    var value = $(this).val().replace(/\\/g, '-');
    $('.element-select[data-type="' + value + '"]').show();

    if (value === 'craft-elements-User') {
      $disableLabel.text(Craft.t('easyapi', 'Suspend missing users'));
      $disableInstructions.text(
        Craft.t('easyapi', 'Suspends any users that are missing from the api.')
      );
    } else {
      $disableLabel.text(originalDisableLabel);
      $disableInstructions.text(originalDisableInstructions);
    }
  });

  $('#elementType').trigger('change');
  console.log('change');

  // Toggle various field when changing element type
  $(document).on('change', '#parentElementType', function () {
    $('.parent-element-select').hide();

    var value = $(this).val().replace(/\\/g, '-');
    
    if (value != '') {
      $('.parent-element-select[data-type="parent-' + value + '"]').show();
    }

    if (value === 'craft-elements-User') {
      $disableLabel.text(Craft.t('easyapi', 'Suspend missing users'));
      $disableInstructions.text(
        Craft.t('easyapi', 'Suspends any users that are missing from the api.')
      );
    } else {
      $disableLabel.text(originalDisableLabel);
      $disableInstructions.text(originalDisableInstructions);
    }
  });

  $('#parentElementType').trigger('change');

  // Toggle the Entry Type field when changing the section select
  $(document).on('change', '.element-parent-group select', function () {
    var sections = $(this).parents('.element-sub-group').data('items') || {};
    var groupId = $(this).val();
    var entryType = 'item_' + groupId;
    var entryTypes = sections[entryType] || [];

    var currentValue = $('.element-child-group select').val();

    var newOptions =
      '<option value="">' + Craft.t('easyapi', 'None') + '</option>';
    $.each(entryTypes, function (index, value) {
      if (index) {
        newOptions += '<option value="' + index + '">' + value + '</option>';
      }
    });

    $('.element-child-group select').html(newOptions);

    // Select the first non-empty, or pre-selected
    if (currentValue) {
      $('.element-child-group select').val(currentValue);
    } else {
      $($('.element-child-group select').children()[1]).attr('selected', true);
    }
  });

  $('.element-parent-group select:visible').trigger('change');

  // Toggle the Entry Type field when changing the section select
  $(document).on('change', '.parent-element-parent-group select', function () {
    var sections = $(this).parents('.parent-element-sub-group').data('items') || {};
    var groupId = $(this).val();
    var entryType = 'item_' + groupId;
    var entryTypes = sections[entryType] || [];

    var currentValue = $('.parent-element-child-group select').val();

    var newOptions =
      '<option value="">' + Craft.t('easyapi', 'None') + '</option>';
    $.each(entryTypes, function (index, value) {
      if (index) {
        newOptions += '<option value="' + index + '">' + value + '</option>';
      }
    });

    $('.parent-element-child-group select').html(newOptions);

    // Select the first non-empty, or pre-selected
    if (currentValue) {
      $('.parent-element-child-group select').val(currentValue);
    } else {
      $($('.parent-element-child-group select').children()[1]).attr('selected', true);
    }
  });

  $('.parent-element-parent-group select:visible').trigger('change');

  //
  // Field Mapping
  //

  // For field-mapping, auto-select Title if no unique checkboxes are set
  if ($('.easyapi-uniques').length) {
    var checked = $('.easyapi-uniques input[type="checkbox"]:checked').length;

    if (!checked) {
      $('.easyapi-uniques input[type="checkbox"]:first').prop('checked', true);
    }
  }

  // for categories and entries - only show "create if they do not exist" if matching done by "title"
  // for users - only show "create if they do not exist" if matching done by "email"
  $(
    '.categories-field-match select, .entries-field-match select, .users-field-match select'
  ).on('change', function (e) {
    var match = 'title';
    var $selectParent = $(this).parent();
    var $elementCreateContainer = $(this)
      .parents('.field-extra-settings')
      .find('.element-create');

    if ($selectParent.hasClass('users-field-match')) {
      match = 'email';
    }

    if ($(this).val() === match) {
      $elementCreateContainer.show();
    } else {
      $elementCreateContainer.find('input').prop('checked', false);
      $('.field-extra-settings .element-create input').trigger('change');
      $elementCreateContainer.hide();
    }
  });

  // On-load, hide/show element create container
  $('.categories-field-match select').trigger('change');
  $('.entries-field-match select').trigger('change');
  $('.users-field-match select').trigger('change');

  // For Assets, only show the upload options if we decide to upload
  $('.assets-uploads input').on('change', function (e) {
    var $options = $(this).parents('.field-extra-settings').find('.select');
    var $label = $(this)
      .parents('.field-extra-settings')
      .find('.asset-label-hide');

    if ($(this).prop('checked')) {
      $label.css({opacity: 1, visibility: 'visible'});
      $options.css({opacity: 1, visibility: 'visible'});
    } else {
      $label.css({opacity: 0, visibility: 'hidden'});
      $options.css({opacity: 0, visibility: 'hidden'});
    }
  });

  // On-load, hide/show upload options
  $('.assets-uploads input').trigger('change');

  // For elements, show the grouping select(s)
  $('.field-extra-settings .element-create input').on('change', function (e) {
    var $container = $(this)
      .parents('.field-extra-settings')
      .find('.element-groups');

    if ($(this).prop('checked')) {
      $container.show();
    } else {
      $container.hide();
    }
  });

  $('.field-extra-settings .element-create input').trigger('change');

  // Toggle various field when changing element type
  $('.field-extra-settings .element-group-section select').on(
    'change',
    function (e) {
      var $container = $(this)
        .parents('.field-extra-settings')
        .find('.element-group-entrytype');
      var sections = $container.data('items');

      // var sections = $(this).parents('.element-sub-group').data('items');
      var entryType = 'item_' + $(this).val();
      var entryTypes = [];
      if (sections !== undefined) {
        var entryTypes = sections[entryType];
      }

      var newOptions = '';
      $.each(entryTypes, function (index, value) {
        if (index) {
          newOptions += '<option value="' + index + '">' + value + '</option>';
        }
      });

      $container.find('select').html(newOptions);
    }
  );

  $('.field-extra-settings .element-group-section select').trigger('change');

  // Selectize inputs
  $('.easyapi-mapping .selectize select').selectize({
    allowEmptyOption: true,
  });

  // Help with sub-element field toggle
  $('.subelement-toggle label').on('click', function (e) {
    var $lightswitch = $(this)
      .parents('.subelement-toggle')
      .find('.lightswitch')
      .data('lightswitch');

    $lightswitch.toggle();
  });

  // Show initially hidden element sub-fields. A little tricky because they're in a table, and all equal siblings
  $('.subelement-toggle .lightswitch').on('change', function (e) {
    var $lightswitch = $(this).data('lightswitch');
    var $tr = $(this).parents('tr');
    var $directSiblings = $tr.nextUntil(':not(.element-sub-field)');

    $directSiblings.toggle();
  });

  // If we have any element sub-fields that are being mapped, we want to show the panel to notify users they're mapping stuff
  $('.element-sub-field').each(function (index, element) {
    var mappingValue = $(this).find('.col-map select').val();
    var defaultValue = $(this).find('.col-default input').val();

    var rowValues = [mappingValue, defaultValue];
    var rowHasValue = false;

    // Check for inputs and selects which have a value
    $.each(rowValues, function (i, v) {
      if (v != '' && v != 'noimport' && v !== undefined) {
        rowHasValue = true;
      }
    });

    if (rowHasValue) {
      var $parentRow = $(this)
        .prevUntil(':not(.element-sub-field)')
        .addBack()
        .prev();
      var $lightswitch = $parentRow.find('.lightswitch').data('lightswitch');

      $lightswitch.turnOn();
    }
  });

  //
  // Logs
  //
  $(document).on('click', '.log-detail-link', function (e) {
    e.preventDefault();

    var key = $(this).data('key');

    $('tr[data-key="' + key + '"]').toggleClass('hidden');
  });

  // Allow multiple submit actions, that trigger different actions as required
  $(document).on('click', 'input[data-action]', function (e) {
    var $form = $(this).parents('form');
    var action = $(this).data('action');

    $form.find('input[name="action"]').val(action);
    $form.submit();
  });

  $(document).on('change', '.log-type-form .select', function (e) {
    e.preventDefault();

    $(this).parents('form').submit();
  });
});
