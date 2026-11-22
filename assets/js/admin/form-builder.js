$ = jQuery;

window.BCOB = {
  fields: window.bcobFields || {},

  currentStepIndex: 0,

  form: {},

  init() {
    this.form =
      bcob.form && Object.keys(bcob.form).length
        ? bcob.form
        : {
            title: "",
            settings: {},
            conditions: [],
            steps: [
              {
                id: this.generateStepId(),
                title: "Step 1",
                description: "",
                showSidebar: false,
                sidebar: {
                  widgets: [],
                },
                fields: [],
              },
            ],
          };

    this.currentStepIndex = 0;
    this.initTabs();
    this.bindEvents();
    this.initSortable();
    this.populateForm();
  },

  bindEvents() {
    $(document).on("click", ".bcob-add-field", this.onAddField.bind(this));
    $(document).on(
      "input",
      "[data-field='label']",
      this.onFieldLabelChange.bind(this),
    );
    $(document).on(
      "click",
      ".bcob-field-toggle",
      this.onFieldToggle.bind(this),
    );
    $(document).on(
      "click",
      ".bcob-field-delete",
      this.onFieldDelete.bind(this),
    );
    $(document).on(
      "click",
      ".bcob-field-duplicate",
      this.onFieldDuplicate.bind(this),
    );
    $(document).on("click", ".bcob-add-step", this.onAddStep.bind(this));
    $(document).on("click", ".bcob-step-item", this.onStepClick.bind(this));
    $(document).on("click", ".bcob-tab", this.onTabClick.bind(this));
    $(document).on("click", ".bcob-delete-step", this.onDeleteStep.bind(this));
    $(document).on(
      "click",
      ".bcob-duplicate-step",
      this.onDuplicateStep.bind(this),
    );
    $(document).on(
      "change",
      "#bcob-step-sidebar",
      this.onStepSidebarToggle.bind(this),
    );
    $(document).on(
      "input",
      "#bcob-step-title",
      this.onStepTitleChange.bind(this),
    );
    $(document).on(
      "input",
      "#bcob-step-description",
      this.onStepDescriptionChange.bind(this),
    );

    $(document).on("click", ".bcob-save-form", this.saveForm.bind(this));
    $(document).on(
      "input",
      ".bcob-form-title-input",
      this.onFormTitleChange.bind(this),
    );
    $(document).on(
      "input change",
      ".bcob-field-body [data-field]",
      this.onFieldSettingChange.bind(this),
    );

    // Forms Admin Page
    $(document).on("click", ".bcob-delete-form", this.onDeleteForm.bind(this));
    $(document).on(
      "click",
      ".bcob-duplicate-form",
      this.onDuplicateForm.bind(this),
    );
    $(document).on("click", ".bcob-shortcode", function () {
      const $this = $(this);
      const text = $.trim($this.contents().last().text());

      navigator.clipboard.writeText(text);

      $this
        .addClass("copied")
        .find("i")
        .removeClass("ri-file-copy-line")
        .addClass("ri-check-line");

      const original = text;

      $this.contents().last()[0].textContent = " Copied";

      setTimeout(function () {
        $this
          .removeClass("copied")
          .find("i")
          .removeClass("ri-check-line")
          .addClass("ri-file-copy-line");

        $this.contents().last()[0].textContent = " " + original;
      }, 1200);
    });
  },

  onDuplicateForm(e) {
    e.preventDefault();

    const button = $(e.currentTarget);

    if (button.hasClass("is-loading")) {
      return;
    }

    const icon = button.find("i");
    const original = icon.attr("class");

    button.addClass("is-loading");
    icon.removeClass().addClass("ri-loader-4-line ri-spin");

    $.ajax({
      url: bcob.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "bcob_duplicate_form",
        nonce: bcob.nonce,
        form_id: button.data("id"),
      },

      success: (response) => {
        if (!response.success) {
          icon.removeClass().addClass(original);
          button.removeClass("is-loading");

          this.showNotice(response.data, "error");
          return;
        }

        this.showNotice("Form duplicated.", "success");

        setTimeout(() => {
          location.reload();
        }, 500);
      },

      error: () => {
        icon.removeClass().addClass(original);
        button.removeClass("is-loading");

        this.showNotice("Unable to duplicate form.", "error");
      },
    });
  },

  onDeleteForm(e) {
    e.preventDefault();

    const button = $(e.currentTarget);

    if (button.hasClass("is-loading")) {
      return;
    }

    if (!window.confirm("Delete this form?")) {
      return;
    }

    const row = button.closest("tr");
    const icon = button.find("i");
    const originalClass = icon.attr("class");

    button.addClass("is-loading");
    icon.removeClass().addClass("ri-loader-4-line ri-spin");

    $.ajax({
      url: bcob.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "bcob_delete_form",
        nonce: bcob.nonce,
        form_id: button.data("id"),
      },

      success: (response) => {
        if (!response.success) {
          icon.removeClass().addClass(originalClass);
          button.removeClass("is-loading");

          this.showNotice(response.data || "Unable to delete form.", "error");
          return;
        }

        row.css("background", "#ffecec");

        row.fadeOut(250, function () {
          $(this).remove();
        });

        this.showNotice("Form deleted.", "success");
      },

      error: () => {
        icon.removeClass().addClass(originalClass);
        button.removeClass("is-loading");

        this.showNotice("Something went wrong.", "error");
      },
    });
  },

  showNotice(message, type = "success") {
    let notice = $(".bcob-notice");

    if (!notice.length) {
      notice = $('<div class="bcob-notice"></div>');
      $(".wrap").prepend(notice);
    }

    notice
      .removeClass("success error")
      .addClass(type)
      .text(message)
      .stop(true, true)
      .fadeIn(200);

    setTimeout(() => {
      notice.fadeOut(300);
    }, 2500);
  },

  populateForm() {
    if (!this.form || !this.form.steps || !this.form.steps.length) {
      return;
    }

    this.currentStepIndex = 0;

    // Form
    $(".bcob-form-title-input").val(this.form.title || "");

    // Global Settings
    Object.entries(this.form.settings || {}).forEach(([key, value]) => {
      const input = $(`[data-setting="${key}"]`);

      if (!input.length) {
        return;
      }

      if (input.is(":checkbox")) {
        input.prop("checked", !!value);
      } else {
        input.val(value);
      }
    });

    // Global Conditions
    if (typeof this.renderConditions === "function") {
      this.renderConditions();
    }

    this.renderAll();
  },

  onFormTitleChange(e) {
    this.form.title = $(e.currentTarget).val();
  },

  onStepTitleChange(e) {
    const value = $(e.currentTarget).val();

    this.getCurrentStep().title = value;

    $(
      `.bcob-step-item[data-index="${this.currentStepIndex}"] .bcob-step-title`,
    ).text(value || `Step ${this.currentStepIndex + 1}`);
  },

  onStepDescriptionChange(e) {
    this.getCurrentStep().description = $(e.currentTarget).val();
  },

  onStepSidebarToggle(e) {
    const checked = $(e.currentTarget).is(":checked");

    this.getCurrentStep().showSidebar = checked;

    $(".bcob-widget-sidebar").toggleClass("bcob-sidebar-disabled", !checked);
  },

  onDeleteStep(e) {
    e.stopPropagation();

    const index = $(e.currentTarget).closest(".bcob-step-item").data("index");

    this.deleteStep(index);
  },

  deleteStep(index) {
    if (this.form.steps.length === 1) {
      alert("At least one step is required.");
      return;
    }

    this.form.steps.splice(index, 1);

    if (this.currentStepIndex >= this.form.steps.length) {
      this.currentStepIndex = this.form.steps.length - 1;
    }

    this.renderAll();
  },

  onFieldDuplicate(e) {
    const button = $(e.currentTarget);
    const card = button.closest(".bcob-field-card");
    const id = card.data("id");

    this.duplicateField(id);
  },

  onTabClick(e) {
    const tab = $(e.currentTarget);
    const screen = tab.data("screen");

    $(".bcob-tab").removeClass("active");
    tab.addClass("active");

    const current = $(".bcob-screen:visible");
    const next = $(`.bcob-screen[data-screen="${screen}"]`);

    if (current[0] === next[0]) {
      return;
    }

    current.animate(
      {
        opacity: 0,
        left: "-20px",
      },
      150,
      function () {
        current.hide().css({
          opacity: 1,
          left: 0,
        });

        next
          .css({
            display: "block",
            opacity: 0,
            left: "20px",
            position: "relative",
          })
          .animate(
            {
              opacity: 1,
              left: 0,
            },
            150,
          );
      },
    );

    this.updateSidebarState();
  },

  updateSidebarState() {
    const builderScreen = $(".bcob-tab.active").data("screen") === "builder";

    const stepSidebar = this.getCurrentStep().showSidebar;

    $(".bcob-widget-sidebar").toggleClass(
      "bcob-sidebar-disabled",
      !(builderScreen && stepSidebar),
    );
  },

  initTabs() {
    $(".bcob-screen").hide().first().show();
    $(".bcob-tab").first().addClass("active");

    $(".bcob-step-sidebar, .bcob-widget-sidebar").removeClass(
      "bcob-sidebar-disabled",
    );
  },

  renderAll() {
    this.renderSteps();
    this.renderStep();
    this.renderFields();
  },

  renderStep() {
    const step = this.getCurrentStep();

    $("#bcob-step-title").val(step.title);
    $("#bcob-step-description").val(step.description);
    $("#bcob-step-sidebar").prop("checked", step.showSidebar);

    // Later:
    // render sidebar widgets
    // render sidebar settings

    $("#bcob-step-sidebar").prop("checked", step.showSidebar);

    $(".bcob-widget-sidebar").toggleClass(
      "bcob-sidebar-disabled",
      !step.showSidebar,
    );
  },

  initSortable() {
    $("#bcob-step-fields").sortable({
      axis: "y",
      handle: ".bcob-field-drag",
      containment: "parent",
      tolerance: "pointer",
      placeholder: "bcob-sort-placeholder",
      forcePlaceholderSize: true,

      update: (event, ui) => {
        const fields = [];

        $("#bcob-step-fields .bcob-field-card").each((i, el) => {
          fields.push(this.getField($(el).data("id")));
        });

        this.form.steps[this.currentStepIndex].fields = fields;
      },
    });

    $(".bcob-step-list").sortable({
      axis: "y",
      containment: "parent",
      tolerance: "pointer",
      handle: ".bcob-step-drag",
      placeholder: "bcob-sort-placeholder",
      forcePlaceholderSize: true,
      tolerance: "pointer",
      helper: "clone",
      distance: 5,

      update: () => {
        const steps = [];

        $(".bcob-step-item").each((i, el) => {
          const id = $(el).data("id");

          steps.push(this.form.steps.find((step) => step.id === id));
        });

        this.form.steps = steps;

        this.renderAll();
      },
    });
  },

  onAddStep() {
    this.addStep();
  },

  addStep() {
    const step = {
      id: this.generateStepId(),
      title: `Step ${this.form.steps.length + 1}`,
      description: "",
      showSidebar: false,
      fields: [],
    };

    this.form.steps.push(step);

    this.currentStepIndex = this.form.steps.length - 1;

    this.renderAll();
  },

  onDuplicateStep(e) {
    e.stopPropagation();

    const index = $(e.currentTarget).closest(".bcob-step-item").data("index");

    this.duplicateStep(index);
  },

  duplicateStep(index) {
    const step = structuredClone(this.form.steps[index]);

    step.id = this.generateStepId();

    step.fields = step.fields.map((field) => ({
      ...field,
      id: this.generateFieldId(),
    }));

    this.form.steps.splice(index + 1, 0, step);

    this.currentStepIndex = index + 1;

    this.renderAll();
  },

  onStepClick(e) {
    if ($(e.target).closest(".bcob-step-actions").length) {
      return;
    }

    this.switchStep($(e.currentTarget).data("index"));
    $(document).find('[data-screen="builder"]').trigger("click");
  },

  renderSteps() {
    const container = $(".bcob-step-list");

    container.empty();

    this.form.steps.forEach((step, index) => {
      container.append(`
            <div
                class="bcob-step-item ${index === this.currentStepIndex ? "active" : ""}"
                data-id="${step.id}" data-index="${index}">
                <span class="bcob-step-drag">
                    <i class="ri-draggable"></i>
                </span>

                <div class="bcob-step-left">

                    <span class="bcob-step-number">
                        ${index + 1}
                    </span>

                    <span class="bcob-step-title">
                        ${step.title}
                    </span>

                </div>

                <div class="bcob-step-actions">

                    <button
                        type="button"
                        class="bcob-duplicate-step">

                        <i class="ri-file-copy-line"></i>

                    </button>

                    <button
                        type="button"
                        class="bcob-delete-step">

                        <i class="ri-delete-bin-line"></i>

                    </button>

                </div>

            </div>
        `);
    });
  },

  generateStepId() {
    return (
      "bcob_step_" +
      Date.now().toString(36) +
      "_" +
      Math.random().toString(36).substring(2, 10)
    );
  },

  switchStep(index) {
    this.currentStepIndex = index;
    window.BCOB.widgetBuilder.renderWidgetList();

    this.renderAll();
  },

  getCurrentStep() {
    return this.form.steps[this.currentStepIndex];
  },

  onFieldDelete(e) {
    const button = $(e.currentTarget);
    const card = button.closest(".bcob-field-card");
    const id = card.data("id");

    this.removeField(id);
  },

  onFieldToggle(e) {
    const field = $(e.currentTarget);
    const card = field.closest(".bcob-field-card");
    card.find(".bcob-field-body").slideToggle();
  },

  onFieldSettingChange(e) {
    const input = $(e.currentTarget);
    const card = input.closest(".bcob-field-card");
    const field = this.getField(card.data("id"));

    if (!field) {
      return;
    }

    const key = input.data("field");

    let value;

    if (input.is(":checkbox")) {
      value = input.is(":checked");
    } else {
      value = input.val();
    }

    // Convert textarea options into schema
    if (key === "options") {
      if (input.hasClass("bcob-advanced-options-builder")) {
        if (window.BCOB.advancedOptionsBuilder) {
          value = window.BCOB.advancedOptionsBuilder.get(input);
        } else {
          value = [];
        }
      } else {
        value = value
          .split("\n")
          .map((item) => item.trim())
          .filter(Boolean)
          .map((item) => ({
            label: item,
            value: item
              .toLowerCase()
              .replace(/[^a-z0-9]+/g, "_")
              .replace(/^_+|_+$/g, ""),
          }));
      }
    }

    field[key] = value;

    if (key === "label") {
      card.find(".bcob-field-name").text(value || field.type.toUpperCase());
    }
  },

  onFieldLabelChange(e) {
    const input = $(e.currentTarget);
    const card = input.closest(".bcob-field-card");
    const id = card.data("id");
    const value = input.val();

    this.updateField(id, "label", value);

    card
      .find(".bcob-field-name")
      .text(value || card.find(".bcob-field-type").text());
  },

  onAddField(event) {
    const type = $(event.currentTarget).data("type");

    this.addField(type);
  },

  addField(type) {
    if (!this.fields[type]) {
      return;
    }

    const field = this.createField(type);

    this.form.steps[this.currentStepIndex].fields.push(field);

    const card = this.renderField(field);

    $("#bcob-step-fields").append(card);

    this.initHtmlEditor(card);
  },

  createField(type) {
    const field = structuredClone(this.fields[type]);
    field.id = this.generateFieldId();

    return field;
  },

  generateFieldId() {
    return (
      "bcob_field_" +
      Date.now().toString(36) +
      "_" +
      Math.random().toString(36).substring(2, 10)
    );
  },

  renderFields() {
    const container = $("#bcob-step-fields");

    container.empty();

    this.form.steps[this.currentStepIndex].fields.forEach((field) => {
      const card = this.renderField(field);

      container.append(card);

      this.initHtmlEditor(card);
    });
  },

  hydrateSettings(settings, field) {
    settings.find("[data-field]").each((_, el) => {
      const input = $(el);
      const key = input.data("field");
      if (key === "field_key") {
        input.val(field.id);
        return;
      }

      if (!(key in field)) {
        return;
      }

      if (input.is(":checkbox")) {
        input.prop("checked", field[key]);
      } else if (input.is(":radio")) {
        settings
          .find(`[data-field="${key}"][value="${field[key]}"]`)
          .prop("checked", true);
      } else {
        let value = field[key];

        if (key === "options") {
          if (input.hasClass("bcob-advanced-options-builder")) {
            if (window.BCOB.advancedOptionsBuilder) {
              window.BCOB.advancedOptionsBuilder.init(input, field[key] || []);
            }

            return;
          }

          if (Array.isArray(field[key])) {
            value = field[key]
              .map((option) => option.label ?? option.value ?? "")
              .join("\n");
          } else {
            value = "";
          }

          input.val(value).trigger("change");

          return;
        }

        input.val(value).trigger("change");
      }
    });
  },

  renderField(field) {
    const settings = $($("#bcob-settings-" + field.type).html());

    this.hydrateSettings(settings, field);

    const card = $(`
        <div
            class="bcob-field-card"
            data-id="${field.id}"
            data-type="${field.type}">

            <div class="bcob-field-header">

                <div class="bcob-field-header-left">

                    <span class="bcob-field-drag">
                        <i class="ri-draggable"></i>
                    </span>

                    <i class="${field.icon}"></i>

                    <span class="bcob-field-name">
                        ${field.label}
                    </span>

                </div>

                <div class="bcob-field-header-right">

                    <span class="bcob-field-type">
                        ${field.type.toUpperCase()}
                    </span>

                    <button
                        type="button"
                        class="bcob-field-delete">

                        <i class="ri-delete-bin-line"></i>

                    </button>

                    <button
                        type="button"
                        class="bcob-field-toggle">

                        <i class="ri-expand-up-down-line"></i>

                    </button>

                </div>

            </div>

            <div class="bcob-field-body"></div>

        </div>
    `);

    card.find(".bcob-field-body").append(settings);

    return card;
  },

  initHtmlEditor(card) {
    const textarea = card.find('textarea[data-field="html"]');

    if (!textarea.length) {
      return;
    }

    const editorId = "bcob_html_" + card.data("id");

    textarea.attr("id", editorId);

    if (tinymce.get(editorId)) {
      return;
    }

    wp.editor.initialize(editorId, {
      tinymce: {
        height: 300,
        branding: false,
      },
      quicktags: true,
      mediaButtons: false,
    });

    const wait = setInterval(() => {
      const editor = tinymce.get(editorId);

      if (!editor) {
        return;
      }

      clearInterval(wait);

      editor.on("change keyup", () => {
        editor.save();
        textarea.trigger("input").trigger("change");
      });
    }, 50);
  },

  getField(id) {
    return this.form.steps[this.currentStepIndex].fields.find(
      (field) => field.id === id,
    );
  },

  updateField(id, key, value) {
    const field = this.getField(id);

    if (!field) {
      return;
    }

    field[key] = value;
  },

  removeField(id) {
    this.form.steps[this.currentStepIndex].fields = this.form.steps[
      this.currentStepIndex
    ].fields.filter((field) => field.id !== id);

    const editorId = "bcob_html_" + id;

    if (window.wp?.editor && tinymce.get(editorId)) {
      wp.editor.remove(editorId);
    }

    $(`.bcob-field-card[data-id="${id}"]`).remove();
  },

  duplicateField(id) {
    const field = this.getField(id);

    if (!field) {
      return;
    }

    const duplicate = structuredClone(field);

    duplicate.id = this.generateFieldId();

    const index = this.form.steps[this.currentStepIndex].fields.findIndex(
      (f) => f.id === id,
    );

    this.form.steps[this.currentStepIndex].fields.splice(
      index + 1,
      0,
      duplicate,
    );

    const duplicateCard = this.renderField(duplicate);

    $(`.bcob-field-card[data-id="${id}"]`).after(duplicateCard);

    this.initHtmlEditor(duplicateCard);
  },

  saveForm(e) {
    e.preventDefault();
    const status = $(".bcob-save-status");

    status
      .removeClass("is-success is-error")
      .addClass("is-saving")
      .html(
        `
        <i class="ri-loader-4-line ri-spin"></i>
        Saving...
    `,
      )
      .stop(true, true)
      .fadeIn(150);

    $.ajax({
      url: bcob.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "bcob_save_form",
        nonce: bcob.nonce,
        form_id: bcob.form_id || 0,
        schema: JSON.stringify(this.getSchema()),
      },

      success: (response) => {
        if (!response.success) {
          alert(response.data);
          return;
        }

        // New form
        if (bcob.form_id == 0 && response.data.redirect) {
          window.location.href = response.data.redirect;
          return;
        }
        // Existing form
        bcob.form_id = response.data.form_id;

        status.removeClass("is-saving is-error").addClass("is-success").html(`
        <i class="ri-checkbox-circle-line"></i>
        Saved
    `);

        setTimeout(() => {
          status.fadeOut(300);
        }, 2000);
      },

      error: (err) => {
        status.removeClass("is-saving is-success").addClass("is-error").html(`
        <i class="ri-close-circle-line"></i>
        Unable to save form.
    `);

        setTimeout(() => {
          status.fadeOut(300);
        }, 3000);
      },
    });
  },

  getSchema() {
    return this.form;
  },
};

$(function () {
  BCOB.init();
});
