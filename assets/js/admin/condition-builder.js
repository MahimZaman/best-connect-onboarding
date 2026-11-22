$ = jQuery;

var BCOBConditionBuilder = {
  template: "",

  init: function () {
    if (!window.BCOB.form.conditions) {
      window.BCOB.form.conditions = [];
    }

    this.template = $(".bcob-condition-builder").first().prop("outerHTML");

    this.populate();

    this.bindEvents();

    this.render();
  },

  bindEvents: function () {
    var self = this;

    // Add Rule
    $(document).on("click", ".bcob-add-rule", function (e) {
      e.preventDefault();

      var $builder = $(this).closest(".bcob-condition-builder");

      var $rule = $builder.find(".bcob-condition-rule").first().clone();

      $rule.find(".bcob-rule-field").val("");
      $rule.find(".bcob-rule-operator").val("==");
      $rule.find(".bcob-rule-value").val("");

      $builder.find(".bcob-condition-rules").append($rule);

      self.populate();
    });

    // Delete Rule
    $(document).on("click", ".bcob-rule-delete", function (e) {
      e.preventDefault();

      var $builder = $(this).closest(".bcob-condition-builder");

      if ($builder.find(".bcob-condition-rule").length == 1) {
        return;
      }

      $(this).closest(".bcob-condition-rule").remove();
    });

    // Add Condition
    $(document).on("click", ".bcob-add-condition", function (e) {
      e.preventDefault();

      var condition = self.readCondition($(".bcob-condition-builder").first());

      if (!condition) {
        return;
      }

      window.BCOB.form.conditions.push(condition);

      var $builder = $(".bcob-condition-builder").first();

      $builder.find(".bcob-condition-target").val("");
      $builder.find(".bcob-condition-action").val("show");
      $builder.find(".bcob-condition-relation").val("and");

      $builder.find(".bcob-condition-rule:not(:first)").remove();

      $builder
        .find(".bcob-condition-rule")
        .first()
        .find(".bcob-rule-field")
        .val("");
      $builder
        .find(".bcob-condition-rule")
        .first()
        .find(".bcob-rule-operator")
        .val("==");
      $builder
        .find(".bcob-condition-rule")
        .first()
        .find(".bcob-rule-value")
        .val("");

      self.render();
    });

    // Update Condition
    $(document).on("click", ".bcob-update-condition", function (e) {
      e.preventDefault();

      var $button = $(this);
      var index = $button.closest(".bcob-condition-item").data("index");

      var condition = self.readCondition(
        $button.closest(".bcob-condition-builder"),
      );

      if (!condition) {
        return;
      }

      $button
        .prop("disabled", true)
        .html('<i class="ri-loader-4-line ri-spin"></i> Updating...');

      window.BCOB.form.conditions[index] = condition;

      setTimeout(function () {
        $(".bcob-save-form").trigger("click");

        $button
          .removeClass("button-primary")
          .addClass("button-secondary")
          .html('<i class="ri-check-line"></i> Updated');

        setTimeout(function () {
          $button
            .prop("disabled", false)
            .removeClass("button-secondary")
            .addClass("button-primary")
            .html('<i class="ri-save-line"></i> Update');
        }, 1000);
      }, 300);
    });

    // Delete Condition
    $(document).on("click", ".bcob-condition-delete", function (e) {
      e.preventDefault();

      if (
        !confirm(
          "Delete this condition? This change will be saved immediately.",
        )
      ) {
        return;
      }

      var index = $(this).closest(".bcob-condition-item").data("index");

      window.BCOB.form.conditions.splice(index, 1);

      self.render();

      $(".bcob-save-form").trigger("click");
    });
  },

  readCondition: function ($builder) {
    var condition = {
      target: $builder.find(".bcob-condition-target").val(),
      action: $builder.find(".bcob-condition-action").val(),
      relation: $builder.find(".bcob-condition-relation").val(),
      rules: [],
    };

    if (!condition.target) {
      return false;
    }

    $builder.find(".bcob-condition-rule").each(function () {
      var field = $(this).find(".bcob-rule-field").val();

      if (!field) {
        return;
      }

      condition.rules.push({
        field: field,
        operator: $(this).find(".bcob-rule-operator").val(),
        value: $(this).find(".bcob-rule-value").val(),
      });
    });

    if (!condition.rules.length) {
      return false;
    }

    return condition;
  },

  populate: function () {
    var targets = '<option value="">Select Step / Field</option>';
    var fields = '<option value="">Select Field</option>';

    $.each(window.BCOB.form.steps, function (_, step) {
      targets +=
        '<option value="' + step.id + '">Step : ' + step.title + "</option>";

      $.each(step.fields, function (_, field) {
        targets +=
          '<option value="' +
          field.id +
          '">Field : ' +
          field.label +
          "</option>";

        fields +=
          '<option value="' + field.id + '">' + field.label + "</option>";
      });
    });

    $(".bcob-condition-target").each(function () {
      var value = $(this).val();

      $(this).html(targets).val(value);
    });

    $(".bcob-rule-field").each(function () {
      var value = $(this).val();

      $(this).html(fields).val(value);
    });
  },

  render: function () {
    var self = this;
    var html = "";

    $.each(window.BCOB.form.conditions, function (index, condition) {
      html += '<div class="bcob-condition-item" data-index="' + index + '">';

      html += self.template;

      html += "</div>";
    });

    $(".bcob-current-conditions").html(html);

    self.populate();

    $(".bcob-current-conditions .bcob-condition-item").each(function (i) {
      var condition = window.BCOB.form.conditions[i];
      var $builder = $(this).find(".bcob-condition-builder");
      $builder.find(".bcob-add-condition").remove();

      $builder
        .find(".bcob-condition-actions")
        .append(
          '<button type="button" class="button button-primary bcob-update-condition"><i class="ri-save-line"></i> Update</button>',
        );

      $builder
        .find(".bcob-condition-actions")
        .append(
          '<button type="button" class="button button-link-delete bcob-condition-delete"><i class="ri-delete-bin-line"></i> Delete</button>',
        );

      $builder.find(".bcob-condition-target").val(condition.target);
      $builder.find(".bcob-condition-action").val(condition.action);
      $builder.find(".bcob-condition-relation").val(condition.relation);

      var $rules = $builder.find(".bcob-condition-rules");

      $rules.find(".bcob-condition-rule:not(:first)").remove();

      $.each(condition.rules, function (r, rule) {
        var $rule;

        if (r === 0) {
          $rule = $rules.find(".bcob-condition-rule").first();
        } else {
          $rule = $rules.find(".bcob-condition-rule").first().clone();
          $rules.append($rule);
        }

        $rule.find(".bcob-rule-field").val(rule.field);
        $rule.find(".bcob-rule-operator").val(rule.operator);
        $rule.find(".bcob-rule-value").val(rule.value);
      });
    });
  },
};

$(function () {
  BCOBConditionBuilder.init();
  $(document).on("click", '[data-screen="conditions"]', function () {
    BCOBConditionBuilder.populate();
  });
});
