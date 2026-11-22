$ = jQuery;

class StorageManager {
  constructor() {
    this.key = "bcob_form_" + $("#bcob-form").data("form-id");
  }

  save() {
    const values = {};

    $("#bcob-form-element :input[name]").each(function () {
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

        const value = String(
          $input.attr("data-value") || $input.val() || "",
        ).trim();

        values[name].push(value);

        return;
      }

      if ($input.is(":radio") && !$input.is(":checked")) {
        return;
      }

      values[name] = $input.val();
    });

    sessionStorage.setItem(this.key, JSON.stringify(values));
  }

  restore() {
    let data = sessionStorage.getItem(this.key);

    if (!data) {
      return;
    }

    try {
      data = JSON.parse(data);
    } catch (error) {
      sessionStorage.removeItem(this.key);
      return;
    }

    Object.keys(data).forEach(function (key) {
      let $fields = $('[name="' + key + '"]');

      if (!$fields.length) {
        $fields = $('[name="' + key + '[]"]');
      }

      if (!$fields.length) {
        return;
      }

      const value = data[key];

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

        return;
      }

      if ($fields.first().is(":radio")) {
        $fields.prop("checked", false);

        $fields.each(function () {
          const $input = $(this);

          if (String($input.val()) === String(value)) {
            $input.prop("checked", true);
          }
        });

        return;
      }

      $fields.val(value);
    });
  }

  clear() {
    sessionStorage.removeItem(this.key);
  }
}
