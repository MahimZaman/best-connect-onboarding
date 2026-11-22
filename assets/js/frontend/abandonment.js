jQuery(function ($) {
  "use strict";

  let token = "";
  let timer = null;
  let lastEmail = "";

  function getFormId() {
    return Number(BCOB.form?.id || $("#bcob-form").data("form-id") || 0);
  }

  function setToken(value) {
    token = value || "";

    if (token && BCOB.checkout && typeof BCOB.checkout === "object") {
      BCOB.checkout.abandonment_token = token;
    }
  }

  function getEmailField() {
    let $field = $('#bcob-form input[type="email"]').first();

    if ($field.length) {
      return $field;
    }

    if (!Array.isArray(BCOB.form?.steps)) {
      return $();
    }

    for (const step of BCOB.form.steps) {
      if (!Array.isArray(step.fields)) {
        continue;
      }

      const field = step.fields.find(function (item) {
        return item?.type === "email";
      });

      if (field) {
        return $('[name="' + String(field.id).replace(/"/g, '\\"') + '"]');
      }
    }

    return $();
  }

  function ensureConsent() {
    if (!BCOBAbandonment.enabled) {
      return;
    }

    const $email = getEmailField();

    if (!$email.length || !BCOBAbandonment.consent_text) {
      return;
    }

    if ($("#bcob-abandonment-consent").length) {
      return;
    }

    const $consent = $(
      '<label class="bcob-abandonment-consent">' +
        '<input type="checkbox" id="bcob-abandonment-consent">' +
        "<span></span>" +
        "</label>",
    );

    $consent.find("span").text(BCOBAbandonment.consent_text);

    const $field = $email.closest(".bcob-field");

    if ($field.length) {
      $field.append($consent);
    } else {
      $email.after($consent);
    }
  }

  function hasConsent() {
    return $("#bcob-abandonment-consent").prop("checked") === true;
  }

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

      if ($input.is(":checkbox")) {
        if (!$input.is(":checked")) {
          return;
        }

        if (!Array.isArray(values[name])) {
          values[name] = [];
        }

        values[name].push($input.attr("data-value") || $input.val());

        return;
      }

      if ($input.is(":radio") && !$input.is(":checked")) {
        return;
      }

      values[name] = $input.val();
    });

    return values;
  }

  function save() {
    if (!BCOBAbandonment.enabled) {
      return;
    }

    if (!hasConsent()) {
      return;
    }
    const $email = getEmailField();

    if (!$email.length) {
      return;
    }

    const email = $.trim(String($email.val() || ""));

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      return;
    }

    if (!getFormId()) {
      return;
    }

    if (email !== lastEmail) {
      lastEmail = email;
    }

    $.post(BCOBAbandonment.ajax_url, {
      action: "bcob_abandonment_save",
      nonce: BCOBAbandonment.nonce,
      consent: 1,
      token: token,
      email: email,
      form_id: getFormId(),
      step: Number(BCOB.steps?.current || 1),
      page_url: window.location.href.split("?")[0],
      values: JSON.stringify(collectValues()),
    }).done(function (response) {
      if (response.success && response.data?.token) {
        setToken(response.data.token);
      }
    });
  }

  function scheduleSave() {
    clearTimeout(timer);

    timer = setTimeout(function () {
      save();
    }, 500);
  }

  function resume() {
    const resumeToken = new URLSearchParams(window.location.search).get(
      "resume",
    );

    if (!resumeToken) {
      return;
    }

    $.post(BCOBAbandonment.ajax_url, {
      action: "bcob_abandonment_resume",
      nonce: BCOBAbandonment.nonce,
      token: resumeToken,
    }).done(function (response) {
      if (!response.success || !response.data) {
        return;
      }

      setToken(response.data.token || resumeToken);

      $(document).one("bcob:frontend-ready", function () {
        restore(
          response.data.values,
          response.data.current_step,
          response.data.consent_granted,
          response.data.consent_version,
        );
      });
    });
  }

  function restore(values, step, consentGranted, consentVersion) {
    ensureConsent();

    if (
      consentGranted === true &&
      String(consentVersion || "") ===
        String(BCOBAbandonment.consent_version || "")
    ) {
      $("#bcob-abandonment-consent").prop("checked", true);
    }

    if (!values || !$.isPlainObject(values)) {
      return;
    }

    Object.keys(values).forEach(function (name) {
      let $fields = $('[name="' + name + '"]');

      if (!$fields.length) {
        $fields = $('[name="' + name + '[]"]');
      }

      if (!$fields.length) {
        return;
      }

      const value = values[name];

      if ($fields.first().is(":checkbox")) {
        const selected = Array.isArray(value)
          ? value.map(String)
          : [String(value)];

        $fields.each(function () {
          const $input = $(this);

          const inputValue = String(
            $input.attr("data-value") || $input.val() || "",
          );

          $input.prop("checked", selected.includes(inputValue));
        });
      } else if ($fields.first().is(":radio")) {
        $fields.prop("checked", false);

        $fields.each(function () {
          const $input = $(this);

          if (String($input.val()) === String(value)) {
            $input.prop("checked", true);
          }
        });
      } else {
        $fields.val(value);
      }

      $fields.trigger("input").trigger("change");
    });

    if (BCOB.submission?.collect) {
      BCOB.submission.collect();
    }

    if (BCOB.steps && typeof BCOB.steps.goto === "function") {
      BCOB.steps.goto(Number(step) || 1);
    }

    if (BCOB.renderer) {
      BCOB.renderer.evaluateConditions();
    }

    if (BCOB.validator) {
      BCOB.validator.validateCurrentStep();
    }
  }

  $(document).on(
    "input change",
    '#bcob-form input[type="email"]',
    scheduleSave,
  );

  $(document).on("input change", "#bcob-form :input", function () {
    if (token) {
      scheduleSave();
    }
  });

  $(document).on("bcob:frontend-ready", function () {
    ensureConsent();
  });

  $(document).on("change", "#bcob-abandonment-consent", function () {
    if (this.checked) {
      scheduleSave();
    }
  });

  ensureConsent();

  resume();
});
