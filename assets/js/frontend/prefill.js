jQuery(function ($) {
  "use strict";

  function collectValues() {
    const values = {};

    $("#bcob-form :input[name]").each(function () {
      const $input = $(this);

      if ($input.is(":disabled") || $input.is(":file")) {
        return;
      }

      const name = String($input.attr("name") || "").replace(/\[\]$/, "");

      if (!name) {
        return;
      }

      const field = getField(name);

      if ($input.is(":checkbox")) {
        if (!$input.is(":checked")) {
          return;
        }

        let value = String($input.val() || "").trim();

        if (field?.type === "advanced_checkbox") {
          value = getAdvancedCheckboxValue($input);
        }

        if (!Array.isArray(values[name])) {
          values[name] = [];
        }

        values[name].push(value);

        return;
      }

      if ($input.is(":radio")) {
        if (!$input.is(":checked")) {
          return;
        }

        if (field?.type === "radio" || field?.type === "advanced_radio") {
          values[name] = getRadioValue($input);
        } else {
          values[name] = $input.val();
        }

        return;
      }

      values[name] = $input.val();
    });

    return values;
  }

  function addButton() {
    if (!BCOBPrefill.is_admin || $(".bcob-prefill-generate").length) {
      return;
    }

    $("body").append(`
      <button
        type="button"
        class="bcob-prefill-generate"
      >
        <i class="ri-link-m"></i>
        Generate Pre-fill Link
      </button>
    `);

    const $button = $(".bcob-prefill-generate");

    $button.css({
      position: "fixed",
      right: "24px",
      bottom: "24px",
      zIndex: 999999999,
      display: "flex",
      alignItems: "center",
      gap: "8px",
      padding: "12px 16px",
      border: "0",
      borderRadius: "10px",
      background: "linear-gradient(180deg, #ff852e 0%, #ff6b00 100%)",
      color: "#2a2a2a",
      boxShadow: "0 8px 25px rgba(0,0,0,.18)",
      cursor: "pointer",
      fontSize: "13px",
      fontWeight: "600",
    });

    $button.off("click.bcobPrefill").on("click.bcobPrefill", function (e) {
      e.preventDefault();
      e.stopPropagation();

      generate();
    });
  }

  function createPrefillLink(formId, membership, portalIndex, expiry) {
    const $button = $(".bcob-prefill-generate");

    $button.prop("disabled", true);
    $button.css("opacity", ".6");

    $.ajax({
      url: BCOBPrefill.ajax_url,
      type: "POST",
      data: {
        action: "bcob_create_prefill",
        nonce: BCOBPrefill.nonce,
        form_id: formId,
        membership: membership,
        portal_index: portalIndex,
        page_url: window.location.href,
        expiry: expiry,
        values: JSON.stringify(collectValues()),
      },
      success: function (response) {
        if (!response.success) {
          alert(
            response.data?.message || "Unable to generate the pre-fill link.",
          );
          return;
        }

        showGeneratedLink(response.data.url);
      },
      error: function (xhr) {
        console.error("Prefill AJAX error:", xhr.responseText);

        alert("Unable to generate the pre-fill link.");
      },
      complete: function () {
        $button.prop("disabled", false);
        $button.css("opacity", "1");
      },
    });
  }

  function showGeneratedLink(url) {
    const $modal = $(`
    <div class="bcob-prefill-modal">
      <div class="bcob-prefill-modal__backdrop"></div>

      <div class="bcob-prefill-modal__box">

        <div class="bcob-prefill-modal__header">
          <div>
            <span>Pre-fill Link</span>
            <h3>Link generated</h3>
          </div>

          <button
            type="button"
            class="bcob-prefill-modal__close"
          >
            <i class="ri-close-line"></i>
          </button>
        </div>

        <div class="bcob-prefill-generated">
          <input
            type="text"
            readonly
            value="${$("<div>").text(url).html()}"
          >

          <button type="button" class="bcob-prefill-copy-result">
            <i class="ri-file-copy-line"></i>
            Copy
          </button>
        </div>

        <p>
          The link has been copied to your clipboard.
        </p>

      </div>
    </div>
  `);

    $("body").append($modal);

    $modal.on(
      "click",
      ".bcob-prefill-modal__close, .bcob-prefill-modal__backdrop",
      function () {
        $modal.remove();
      },
    );

    $modal.on("click", ".bcob-prefill-copy-result", function () {
      navigator.clipboard.writeText(url);

      $(this).html('<i class="ri-check-line"></i> Copied');
    });
  }

  function generate() {    
    const $portal = $('input[name="bcob_membership"]:checked');

    if (!$portal.length) {
      alert("Please select a portal first.");
      return;
    }

    const formId = $portal.data("form") || $("#bcob-form").data("form-id");

    const membership = $portal.val();
    const portalIndex = $portal.data("portal");

    if (!formId || !membership) {
      alert("The current portal could not be identified.");
      return;
    }

    showExpiryModal(function (expiry) {
      createPrefillLink(formId, membership, portalIndex, expiry);
    });
  }

  function loadPrefill() {
    const token = new URLSearchParams(window.location.search).get("lead");

    if (!token || typeof BCOBMembership === "undefined") {
      return;
    }

    $.post(BCOBPrefill.ajax_url, {
      action: "bcob_get_prefill",
      token: token,
    }).done(function (response) {
      if (!response.success) {
        return;
      }

      const data = response.data;

      const $portal = $('input[name="bcob_membership"]').filter(function () {
        return (
          String($(this).val()) === String(data.membership) &&
          String($(this).data("form")) === String(data.form_id) &&
          String($(this).data("portal")) === String(data.portal_index)
        );
      });

      if (!$portal.length) {
        return;
      }

      $(document).one("bcob:frontend-ready", function () {
        applyPrefill(data.values);
      });

      $portal.prop("checked", true).trigger("change");
    });
  }

  function getField(fieldId) {
    if (!Array.isArray(BCOB.form?.steps)) {
      return null;
    }

    for (const step of BCOB.form.steps) {
      if (!Array.isArray(step.fields)) {
        continue;
      }

      const field = step.fields.find(function (field) {
        return String(field.id) === String(fieldId);
      });

      if (field) {
        return field;
      }
    }

    return null;
  }

  function normalizeValues(value) {
    if (Array.isArray(value)) {
      return value.map(function (item) {
        return String(item).trim();
      });
    }

    return [String(value ?? "").trim()];
  }

  function getRadioValue($input) {
    const $label = $input.closest(".bcob-radio-card");

    const label = $.trim($label.find(".bcob-radio-card__label").text());

    return label || String($input.val()).trim();
  }

  function getAdvancedCheckboxValue($input) {
    return String($input.attr("data-value") || $input.val() || "").trim();
  }

  function setCheckbox($fields, value, field) {
    const selected = normalizeValues(value);

    $fields.each(function () {
      const $input = $(this);

      const inputValue =
        field?.type === "advanced_checkbox"
          ? String($input.attr("data-value") || "").trim()
          : String($input.val() || "").trim();

      $input.prop("checked", selected.includes(inputValue));
    });
  }

  function showExpiryModal(callback) {
    $(".bcob-prefill-modal").remove();

    const $modal = $(`
    <div class="bcob-prefill-modal">
      <div class="bcob-prefill-modal__backdrop"></div>

      <div class="bcob-prefill-modal__box">

        <div class="bcob-prefill-modal__header">
          <div>
            <span>Pre-fill Link</span>
            <h3>Choose expiration</h3>
          </div>

          <button
            type="button"
            class="bcob-prefill-modal__close"
          >
            <i class="ri-close-line"></i>
          </button>
        </div>

        <div class="bcob-prefill-modal__options">

          <button type="button" data-expiry="1_hour">
            <strong>1 Hour</strong>
            <span>Expires after one hour</span>
          </button>

          <button type="button" data-expiry="24_hours">
            <strong>24 Hours</strong>
            <span>Expires after one day</span>
          </button>

          <button type="button" data-expiry="1_week">
            <strong>1 Week</strong>
            <span>Expires after seven days</span>
          </button>

          <button type="button" data-expiry="1_month">
            <strong>1 Month</strong>
            <span>Expires after one month</span>
          </button>

          <button type="button" data-expiry="1_year">
            <strong>1 Year</strong>
            <span>Expires after one year</span>
          </button>

        </div>

      </div>
    </div>
  `);

    $("body").append($modal);

    $modal.on(
      "click",
      ".bcob-prefill-modal__close, .bcob-prefill-modal__backdrop",
      function () {
        $modal.remove();
      },
    );

    $modal.on("click", ".bcob-prefill-modal__options button", function () {
      const expiry = $(this).data("expiry");

      $modal.remove();

      callback(expiry);
    });
  }

  function setRadio($fields, value) {
    const selected = normalizeValues(value)[0];

    $fields.prop("checked", false);

    $fields.each(function () {
      const $input = $(this);

      const inputValue = getRadioValue($input);

      if (inputValue === selected) {
        $input.prop("checked", true);
      }
    });
  }

  function applyPrefill(values) {
    if (!values || !$.isPlainObject(values)) {
      return;
    }

    Object.keys(values).forEach(function (name) {
      const value = values[name];

      let $fields = $('[name="' + name + '"]');

      if (!$fields.length) {
        $fields = $('[name="' + name + '[]"]');
      }

      if (!$fields.length) {
        return;
      }

      const field = getField(name);

      if ($fields.first().is(":checkbox")) {
        setCheckbox($fields, value, field);
      } else if ($fields.first().is(":radio")) {
        setRadio($fields, value);
      } else {
        $fields.val(value);
      }

      $fields.trigger("input");
      $fields.trigger("change");
    });

    if (BCOB.renderer) {
      BCOB.renderer.evaluateConditions();
    }

    if (BCOB.validator) {
      BCOB.validator.validateCurrentStep();
    }

    if (BCOB.submission) {
      BCOB.submission.collect();
    }
  }

  $(document).on("click", ".bcob-prefill-generate", function (e) {
    e.preventDefault();
    e.stopPropagation();

    generate();
  });

  addButton();

  try {
    loadPrefill();
  } catch (error) {
    console.error("BCOB Prefill load error:", error);
  }
});
