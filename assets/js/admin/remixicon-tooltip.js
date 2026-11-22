/**
 * BCOB Remix Icon Picker
 *
 * Requires:
 * - jQuery
 * - Remix Icon CSS
 */

(function ($) {
  "use strict";

  var picker = null;
  var activeField = null;

  var remixIcons = [
    "ri-add-line",
    "ri-arrow-down-line",
    "ri-arrow-left-line",
    "ri-arrow-right-line",
    "ri-arrow-up-line",
    "ri-check-line",
    "ri-close-line",
    "ri-search-line",
    "ri-home-line",
    "ri-user-line",
    "ri-user-fill",
    "ri-settings-line",
    "ri-menu-line",
    "ri-more-line",
    "ri-more-2-line",
    "ri-edit-line",
    "ri-delete-bin-line",
    "ri-save-line",
    "ri-download-line",
    "ri-upload-line",
    "ri-file-line",
    "ri-folder-line",
    "ri-image-line",
    "ri-camera-line",
    "ri-video-line",
    "ri-mail-line",
    "ri-phone-line",
    "ri-map-pin-line",
    "ri-calendar-line",
    "ri-time-line",
    "ri-heart-line",
    "ri-heart-fill",
    "ri-star-line",
    "ri-star-fill",
    "ri-bookmark-line",
    "ri-bookmark-fill",
    "ri-eye-line",
    "ri-eye-off-line",
    "ri-lock-line",
    "ri-lock-unlock-line",
    "ri-key-line",
    "ri-link",
    "ri-external-link-line",
    "ri-share-line",
    "ri-send-plane-line",
    "ri-chat-1-line",
    "ri-message-line",
    "ri-notification-line",
    "ri-information-line",
    "ri-question-line",
    "ri-error-warning-line",
    "ri-alert-line",
    "ri-checkbox-circle-line",
    "ri-close-circle-line",
    "ri-add-circle-line",
    "ri-loader-line",
    "ri-refresh-line",
    "ri-filter-line",
    "ri-sort-asc",
    "ri-sort-desc",
    "ri-list-check",
    "ri-list-unordered",
    "ri-grid-line",
    "ri-layout-grid-line",
    "ri-layout-column-line",
    "ri-layout-row-line",
    "ri-menu-2-line",
    "ri-dashboard-line",
    "ri-bar-chart-line",
    "ri-pie-chart-line",
    "ri-line-chart-line",
    "ri-money-dollar-circle-line",
    "ri-bank-line",
    "ri-shopping-cart-line",
    "ri-shopping-bag-line",
    "ri-price-tag-line",
    "ri-coupon-line",
    "ri-wallet-line",
    "ri-credit-card-line",
    "ri-paypal-line",
    "ri-google-line",
    "ri-facebook-line",
    "ri-instagram-line",
    "ri-twitter-x-line",
    "ri-linkedin-line",
    "ri-youtube-line",
    "ri-whatsapp-line",
    "ri-github-line",
    "ri-wordpress-line",
    "ri-android-line",
    "ri-apple-line",
    "ri-windows-line",
    "ri-chrome-line",
    "ri-firefox-line",
    "ri-edge-line",
    "ri-safari-line",
    "ri-sun-line",
    "ri-moon-line",
    "ri-cloud-line",
    "ri-rainy-line",
    "ri-lightbulb-line",
    "ri-flashlight-line",
    "ri-fire-line",
    "ri-leaf-line",
    "ri-tree-line",
    "ri-earth-line",
    "ri-global-line",
    "ri-wifi-line",
    "ri-bluetooth-line",
    "ri-device-line",
    "ri-computer-line",
    "ri-smartphone-line",
    "ri-tablet-line",
    "ri-printer-line",
    "ri-server-line",
    "ri-database-line",
    "ri-code-line",
    "ri-terminal-box-line",
    "ri-braces-line",
    "ri-bug-line",
    "ri-shield-line",
    "ri-shield-check-line",
    "ri-thumb-up-line",
    "ri-thumb-down-line",
    "ri-emotion-happy-line",
    "ri-emotion-sad-line",
    "ri-mic-line",
    "ri-volume-up-line",
    "ri-volume-mute-line",
    "ri-play-line",
    "ri-pause-line",
    "ri-stop-line",
    "ri-skip-back-line",
    "ri-skip-forward-line",
    "ri-fullscreen-line",
    "ri-zoom-in-line",
    "ri-zoom-out-line",
    "ri-upload-cloud-line",
    "ri-download-cloud-line",
    "ri-attachment-line",
    "ri-pushpin-line",
    "ri-map-line",
    "ri-navigation-line",
    "ri-compass-3-line",
    "ri-truck-line",
    "ri-car-line",
    "ri-bus-line",
    "ri-flight-takeoff-line",
    "ri-home-2-line",
    "ri-building-line",
    "ri-store-line",
    "ri-briefcase-line",
    "ri-graduation-cap-line",
    "ri-book-open-line",
    "ri-file-text-line",
    "ri-file-copy-line",
    "ri-clipboard-line",
    "ri-task-line",
    "ri-todo-line",
    "ri-alarm-line",
    "ri-questionnaire-line",
    "ri-tools-line",
    "ri-hammer-line",
    "ri-wrench-line",
    "ri-paint-brush-line",
    "ri-palette-line",
    "ri-magic-line",
    "ri-crop-line",
    "ri-expand-line",
    "ri-drag-move-line",
    "ri-drag-drop-line",
    "ri-more-fill",
    "ri-close-fill",
    "ri-check-fill",
    "ri-add-fill",
    "ri-arrow-down-fill",
    "ri-arrow-left-fill",
    "ri-arrow-right-fill",
    "ri-arrow-up-fill",
  ];

  function createPicker() {
    if (picker) {
      return picker;
    }

    picker = $(
      '<div class="bcob-remixicon-picker">' +
        '<div class="bcob-remixicon-picker__search">' +
        '<i class="ri-search-line"></i>' +
        '<input type="search" class="bcob-remixicon-picker__input" placeholder="Search icons...">' +
        "</div>" +
        '<div class="bcob-remixicon-picker__grid"></div>' +
        "</div>",
    );

    $("body").append(picker);

    renderIcons("");

    picker.on("input", ".bcob-remixicon-picker__input", function () {
      renderIcons($(this).val());
    });

    picker.on("click", ".bcob-remixicon-picker__item", function (event) {
      event.preventDefault();

      if (!activeField) {
        return;
      }

      var iconClass = $(this).data("icon");

      activeField.val(iconClass).trigger("input").trigger("change");

      closePicker();
    });

    return picker;
  }

  function renderIcons(search) {
    var $grid = picker.find(".bcob-remixicon-picker__grid");
    var query = $.trim(search || "").toLowerCase();

    var filtered = remixIcons.filter(function (icon) {
      return icon.toLowerCase().indexOf(query) !== -1;
    });

    $grid.empty();

    if (!filtered.length) {
      $grid.append(
        '<div class="bcob-remixicon-picker__empty">' +
          "No icons found." +
          "</div>",
      );

      return;
    }

    $.each(filtered, function (index, icon) {
      var $item = $(
        '<button type="button" class="bcob-remixicon-picker__item">' +
          "<i></i>" +
          "<span></span>" +
          "</button>",
      );

      $item.attr("data-icon", icon);
      $item.find("i").addClass(icon);
      $item.find("span").text(icon);

      $grid.append($item);
    });
  }

  function positionPicker() {
    if (!picker || !activeField) {
      return;
    }

    var rect = activeField[0].getBoundingClientRect();

    var fieldHeight = activeField.outerHeight();
    var pickerWidth = picker.outerWidth();
    var pickerHeight = picker.outerHeight();

    var viewportWidth = $(window).width();
    var viewportHeight = $(window).height();

    var top = rect.bottom + 8;
    var left = rect.left;

    if (left + pickerWidth > viewportWidth - 10) {
      left = viewportWidth - pickerWidth - 10;
    }

    if (top + pickerHeight > viewportHeight - 10) {
      top = rect.top - pickerHeight - 8;
    }

    if (top < 10) {
      top = 10;
    }

    if (left < 10) {
      left = 10;
    }

    picker.css({
      top: top,
      left: left,
    });
  }

  function openPicker($field) {
    activeField = $field;

    createPicker();

    picker.show();

    var currentValue = $.trim($field.val());

    picker
      .find(".bcob-remixicon-picker__input")
      .val(currentValue)
      .trigger("input")
      .focus();

    positionPicker();
  }

  function closePicker() {
    if (picker) {
      picker.hide();
    }

    activeField = null;
  }

  // Open picker.
  $(document).on("click", ".bcob-repeater-icon", function (event) {
    event.preventDefault();
    event.stopPropagation();

    openPicker($(this));
  });

  // Close when clicking outside.
  $(document).on("click", function (event) {
    if (!picker || !picker.is(":visible")) {
      return;
    }

    if (
      !$(event.target).closest(".bcob-remixicon-picker").length &&
      !$(event.target).closest(".bcob-repeater-icon").length
    ) {
      closePicker();
    }
  });

  // Keep picker positioned correctly.
  $(window).on("resize scroll", function () {
    if (picker && picker.is(":visible")) {
      positionPicker();
    }
  });
})(jQuery);
