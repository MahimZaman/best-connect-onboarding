$ = jQuery;

class Renderer {
  showField(id) {
    const $field = $('[data-field-id="' + id + '"]');

    $field.removeClass("bcob-hidden");
    $field.data("hidden", false);
  }

  hideField(id) {
    const $field = $('[data-field-id="' + id + '"]');

    $field.addClass("bcob-hidden");
    $field.data("hidden", true);
  }

  enableField(id) {
    $('[data-field-id="' + id + '"]')
      .find(":input")
      .prop("disabled", false);
  }

  disableField(id) {
    $('[data-field-id="' + id + '"]')
      .find(":input")
      .prop("disabled", true);
  }

  getValue(id) {
    const $inputs = $('[data-field-id="' + id + '"]').find(":input");

    if (!$inputs.length) {
      return "";
    }

    if ($inputs.first().is(":checkbox")) {
      const values = $inputs
        .filter(":checked")
        .map(function () {
          const $input = $(this);

          return String($input.attr("data-value") || $input.val() || "").trim();
        })
        .get()
        .filter(Boolean);

      return values.length ? values : "";
    }

    if ($inputs.first().is(":radio")) {
      const $checked = $inputs.filter(":checked");

      if (!$checked.length) {
        return "";
      }

      return String($checked.attr("data-value") || $checked.val() || "").trim();
    }

    return $inputs.first().val() ?? "";
  }

  setValue(id, value) {
    $('[data-field-id="' + id + '"]')
      .find(":input")
      .val(value);
  }

  compare(value, operator, expected) {
    value = value ?? "";

    switch (operator) {
      case "==":
        return value == expected;

      case "!=":
        return value != expected;

      case ">":
        return Number(value) > Number(expected);

      case "<":
        return Number(value) < Number(expected);

      case ">=":
        return Number(value) >= Number(expected);

      case "<=":
        return Number(value) <= Number(expected);

      case "contains":
        return String(value).includes(expected);

      case "starts_with":
        return String(value).startsWith(expected);

      case "ends_with":
        return String(value).endsWith(expected);

      case "empty":
        return value === "" || value == null;

      case "not_empty":
        return value !== "" && value != null;
    }

    return false;
  }

  evaluateConditions() {
    if (!Array.isArray(BCOB.form.conditions)) {
      return;
    }

    const self = this;

    BCOB.form.conditions.forEach(function (condition) {
      let matched = condition.relation === "and";

      condition.rules.forEach(function (rule) {
        const result = self.compare(
          self.getValue(rule.field),
          rule.operator,
          rule.value,
        );

        if (condition.relation === "and") {
          matched = matched && result;
        } else {
          matched = matched || result;
        }
      });

      if (condition.action === "show") {
        if (matched) {
          self.showField(condition.target);
          self.enableField(condition.target);
        } else {
          self.hideField(condition.target);
          self.disableField(condition.target);
        }
      } else {
        if (matched) {
          self.hideField(condition.target);
          self.disableField(condition.target);
        } else {
          self.showField(condition.target);
          self.enableField(condition.target);
        }
      }
    });
  }
}
