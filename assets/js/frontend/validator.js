$ = jQuery;

class Validator {
  validateStep(step) {
    let valid = true;

    const self = this;

    $('.bcob-step[data-step="' + step + '"] .bcob-validator').each(function () {
      if (!self.validateField($(this))) {
        valid = false;
      }
    });

    return valid;
  }

  validateCurrentStep() {
    const step = BCOB.steps.current;

    this.validateStep(step);
  }

  focusFirstInvalid(step) {
    const $step = $('.bcob-step[data-step="' + step + '"]');

    const $invalid = $step.find(".bcob-validator.is-invalid").first();

    if (!$invalid.length) {
      return;
    }

    const $input = this.getInput($invalid);

    if ($input.length) {
      $input.trigger("focus");
      return;
    }

    $invalid.attr("tabindex", "-1").trigger("focus");
  }

  getField(id) {
    for (const step of BCOB.form.steps) {
      const field = step.fields.find((f) => f.id == id);

      if (field) {
        return field;
      }
    }

    return null;
  }

  getInput($field) {
    return $field
      .find(":input")
      .filter(function () {
        return $(this).closest(".bcob-validator")[0] === $field[0];
      })
      .first();
  }

  validateRequired($field, type) {
    switch (type) {
      case "checkbox":
      case "advanced_checkbox":
        return $field.find(":checkbox:checked").length > 0;

      case "radio":
      case "advanced_radio":
        return $field.find(":radio:checked").length > 0;

      case "file":
        return ($field.find('input[type="file"]')[0]?.files.length || 0) > 0;

      default:
        return $.trim($field.find(":input").val()) !== "";
    }
  }

  validateField($field) {
    const id = $field.data("field-id");

    if ($field.find(":input").is(":disabled")) {
      return true;
    }

    const field = this.getField(id);

    if (!field) {
      return true;
    }

    const type = field.type;
    const config = BCOB.validation[id] || {};

    const hasValue = this.validateRequired($field, type);

    if (field.required && !hasValue) {
      this.showResult($field, false, "This field is required.");
      return false;
    }

    if (!hasValue) {
      this.showResult($field, true, "", true);
      return true;
    }

    // Field-specific validation
    let result = {
      valid: true,
      message: "",
    };

    if (typeof this[type] !== "function") {
      console.warn("No validator found for:", type);
    } else {
      result = this[type]($field, config);
    }

    this.showResult($field, result.valid, result.message);

    return result.valid;
  }

  showResult($field, valid, message = "", neutral = false) {
    $field.removeClass("is-valid is-invalid");

    const id = String($field.data("field-id") || "");

    const errorId = "bcob-error-" + id;

    const $error = $field.find(".bcob-error");

    const $inputs = $field.find(":input").filter(function () {
      return $(this).closest(".bcob-validator")[0] === $field[0];
    });

    if ($error.length) {
      $error
        .attr({
          id: errorId,
          role: "alert",
          "aria-live": "assertive",
          "aria-atomic": "true",
        })
        .text("");
    }

    $inputs.removeAttr("aria-invalid");

    $inputs.each(function () {
      const $input = $(this);

      const existing = String($input.attr("aria-describedby") || "")
        .split(/\s+/)
        .filter(Boolean)
        .filter((value) => value !== errorId);

      if (neutral || valid) {
        if (existing.length) {
          $input.attr("aria-describedby", existing.join(" "));
        } else {
          $input.removeAttr("aria-describedby");
        }

        return;
      }

      existing.push(errorId);

      $input.attr("aria-describedby", existing.join(" "));
    });

    if (neutral) {
      return;
    }

    if (!valid) {
      $field.addClass("is-invalid");

      $inputs.attr("aria-invalid", "true");

      $error.text(message);

      return;
    }

    $field.addClass("is-valid");

    $inputs.attr("aria-invalid", "false");
  }

  validateForm() {
    let valid = true;

    $(".bcob-validator").each((i, field) => {
      if (!this.validateField($(field))) {
        valid = false;
      }
    });

    return valid;
  }

  // -----------------------
  // Field validators
  // -----------------------

  text() {
    return {
      valid: true,
      message: "",
    };
  }

  textarea($field, config) {
    return this.text($field, config);
  }

  email($field) {
    const value = $.trim($field.find(":input").val());

    return {
      valid: /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
      message: "Please enter a valid email address.",
    };
  }

  number($field) {
    const value = $.trim(this.getInput($field).val());

    return {
      valid: !isNaN(value),
      message: "Please enter a valid number.",
    };
  }

  phone() {
    return {
      valid: true,
      message: "",
    };
  }

  select() {
    return {
      valid: true,
      message: "",
    };
  }

  radio($field, config) {
    return {
      valid: true,
      message: "",
    };
  }

  advanced_radio($field, config) {
    return this.radio($field, config);
  }

  checkbox($field, config) {
    return {
      valid: true,
      message: "",
    };
  }

  advanced_checkbox($field, config) {
    return this.checkbox($field, config);
  }

  file($field, config) {
    return {
      valid: true,
      message: "",
    };
  }

  message($field, config) {
    return {
      valid: true,
      message: "",
    };
  }

  html($field, config) {
    return {
      valid: true,
      message: "",
    };
  }
}
