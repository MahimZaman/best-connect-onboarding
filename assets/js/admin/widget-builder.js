$ = jQuery;

window.BCOB.widgetBuilder = {
  currentWidget: null,

  init() {
    this.bindEvents();

    const step = window.BCOB.form.steps?.[window.BCOB.currentStepIndex];

    if (step?.sidebar?.widgets?.length) {
      this.renderWidgetList();
    }
  },

  bindEvents() {
    const self = this;

    // Add widget
    $(document).on("click", ".bcob-widget-card", function () {
      self.addWidget($(this).data("widget"));
    });

    // Delete
    $(document).on("click", ".bcob-delete-widget", function (e) {
      e.stopPropagation();

      if (!confirm("Delete this widget?")) {
        return;
      }

      const id = $(this).closest(".bcob-widget-item").data("id");
      const step = window.BCOB.form.steps[window.BCOB.currentStepIndex];

      step.sidebar = step.sidebar || {};
      step.sidebar.widgets = step.sidebar.widgets || [];

      step.sidebar.widgets = step.sidebar.widgets.filter(
        (widget) => widget.id !== id,
      );

      window.BCOB.widgetBuilder.renderWidgetList();

      $(".bcob-save-form").trigger("click");
    });

    // Duplicate
    $(document).on("click", ".bcob-duplicate-widget", function (e) {
      e.stopPropagation();

      const id = $(this).closest(".bcob-widget-item").data("id");
      const widgets =
        window.BCOB.form.steps[window.BCOB.currentStepIndex].widgets || [];
      const widget = structuredClone(widgets.find((w) => w.id === id));

      widget.id = "bcob_widget_" + Date.now();

      widgets.push(widget);

      $("#bcob-sidebar-widgets").append(`
                <div class="bcob-widget-item" data-id="${widget.id}">
                    <span>${widget.type.replace("_", " ")}</span>

                    <div class="bcob-widget-actions">
                        <button class="bcob-move-up"><i class="ri-arrow-up-line"></i></button>
                        <button class="bcob-move-down"><i class="ri-arrow-down-line"></i></button>
                        <button class="bcob-duplicate-widget"><i class="ri-file-copy-line"></i></button>
                        <button class="bcob-delete-widget"><i class="ri-delete-bin-line"></i></button>
                    </div>
                </div>
            `);

      $(".bcob-save-form").trigger("click");
    });

    // Move Up
    $(document).on("click", ".bcob-move-up", function (e) {
      e.stopPropagation();

      const item = $(this).closest(".bcob-widget-item");

      item.prev().before(item);
    });

    // Move Down
    $(document).on("click", ".bcob-move-down", function (e) {
      e.stopPropagation();

      const item = $(this).closest(".bcob-widget-item");

      item.next().after(item);
    });

    // Media uploader
    $(document).on("click", ".bcob-media-upload", function () {
      const field = $(this).closest(".bcob-field");

      const input = field.find(".bcob-widget-input");

      const widgetId = input.data("widget");
      const key = input.data("key");

      const widgets =
        window.BCOB.form.steps[window.BCOB.currentStepIndex].sidebar.widgets;

      const widget = widgets.find((w) => w.id === widgetId);

      const frame = wp.media({
        title: "Select Image",
        button: {
          text: "Use Image",
        },
        multiple: false,
      });

      frame.on("select", function () {
        const attachment = frame.state().get("selection").first().toJSON();

        input.val(attachment.id).trigger("change");

        widget.settings[key + "_url"] = attachment.url;

        field.find(".bcob-media-preview").html(`
            <img src="${attachment.url}" alt="">
        `);

        $(".bcob-save-form").trigger("click");
      });

      frame.open();
    });

    $(document).on("click", ".bcob-media-remove", function () {
      const field = $(this).closest(".bcob-field");

      const input = field.find(".bcob-widget-input");

      const widgetId = input.data("widget");
      const key = input.data("key");

      const widgets =
        window.BCOB.form.steps[window.BCOB.currentStepIndex].sidebar.widgets;

      const widget = widgets.find((w) => w.id === widgetId);

      input.val("").trigger("change");

      delete widget.settings[key + "_url"];

      field.find(".bcob-media-preview").empty();

      $(".bcob-save-form").trigger("click");
    });

    // Add repeater row
    $(document).on("click", ".bcob-add-repeater", function () {
      const wrapper = $(this).prev(".bcob-repeater");

      wrapper.append(`
        <div class="bcob-repeater-row">

            <input type="text" class="bcob-repeater-icon" placeholder="Icon">

            <input type="text" class="bcob-repeater-label" placeholder="Label">

            <input type="text" class="bcob-repeater-value" placeholder="Value">

            <button
                type="button"
                class="button bcob-remove-repeater">
                Remove
            </button>

        </div>
    `);

      wrapper.trigger("change");
    });

    // Remove repeater
    $(document).on("click", ".bcob-remove-repeater", function () {
      const wrapper = $(this).closest(".bcob-repeater");

      $(this).closest(".bcob-repeater-row").remove();

      wrapper.trigger("change");
    });

    $(document).on("input change", ".bcob-widget-input", function () {
      const widgetId = $(this).data("widget");
      const key = $(this).data("key");

      const step = window.BCOB.form.steps[window.BCOB.currentStepIndex];

      step.sidebar = step.sidebar || {};
      step.sidebar.widgets = step.sidebar.widgets || [];

      const widgets = step.sidebar.widgets;

      const widget = widgets.find((w) => w.id === widgetId);

      if (!widget) {
        return;
      }

      widget.settings[key] = $(this).val();

      $(".bcob-save-form").trigger("click");
    });

    $(document).on(
      "input change",
      ".bcob-repeater, .bcob-repeater input",
      function () {
        const wrapper = $(this).closest(".bcob-repeater");

        const widgetId = wrapper.data("widget");
        const key = wrapper.data("key");

        const widgets =
          window.BCOB.form.steps[window.BCOB.currentStepIndex].sidebar.widgets;

        const widget = widgets.find((w) => w.id === widgetId);

        if (!widget) {
          return;
        }

        const rows = [];

        wrapper.find(".bcob-repeater-row").each(function () {
          rows.push({
            icon: $(this).find(".bcob-repeater-icon").val(),

            label: $(this).find(".bcob-repeater-label").val(),

            value: $(this).find(".bcob-repeater-value").val(),
          });
        });

        widget.settings[key] = rows;

        $(".bcob-save-form").trigger("click");
      },
    );

    $(document).on("click", ".bcob-widget-toggle", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const widget = $(this).closest(".bcob-widget-item");
      const body = widget.find(".bcob-widget-body");

      if (widget.hasClass("collapsed")) {
        widget.removeClass("collapsed");

        body.css("max-height", body.prop("scrollHeight") + "px");

        setTimeout(() => {
          body.css("max-height", "");
        }, 300);

        $(this)
          .find("i")
          .removeClass("ri-arrow-down-s-line")
          .addClass("ri-arrow-up-s-line");
      } else {
        body.css("max-height", body.prop("scrollHeight") + "px");

        requestAnimationFrame(() => {
          body.css("max-height", "0");
        });

        widget.addClass("collapsed");

        $(this)
          .find("i")
          .removeClass("ri-arrow-up-s-line")
          .addClass("ri-arrow-down-s-line");
      }
    });
  },

  addWidget(type) {
    const widget = structuredClone(bcobWidgets[type].schema);

    widget.fields = structuredClone(bcobWidgets[type].fields);
    widget.id = "bcob_widget_" + Date.now();

    const step = window.BCOB.form.steps[window.BCOB.currentStepIndex];

    step.sidebar = step.sidebar || {};
    step.sidebar.widgets = step.sidebar.widgets || [];
    step.sidebar.widgets.push(widget);

    this.renderWidgetList();

    $(".bcob-save-form").trigger("click");
  },

  renderWidgetList() {
    const step = window.BCOB.form.steps[window.BCOB.currentStepIndex];

    if (!step) {
      return;
    }

    step.sidebar = step.sidebar || {};
    step.sidebar.widgets = step.sidebar.widgets || [];

    const widgets = step.sidebar.widgets;

    let html = "";

    widgets.forEach((widget) => {
      html += `
          <div class="bcob-widget-item bcob-form-group collapsed"
              style="margin-bottom:16px;padding:16px;"
              data-id="${widget.id}">

              <div class="bcob-widget-toolbar">

                  <span class="bcob-widget-drag">
                      <i class="ri-draggable"></i>
                  </span>

                  <span class="bcob-widget-title">
                      ${widget.type.replaceAll("_", " ")}
                  </span>

                  <button
                      type="button"
                      class="bcob-widget-toggle">

                      <i class="ri-arrow-down-s-line"></i>

                  </button>

                  <button
                      type="button"
                      class="bcob-delete-widget">

                      <i class="ri-delete-bin-line"></i>

                  </button>

              </div>

              <div class="bcob-widget-body">
      `;

      widget.fields.forEach((field) => {
        const value = widget.settings[field.key] ?? "";

        if (field.type === "text") {
          html += `
                    <div class="bcob-field">
                        <label>${field.label}</label>
                        <input
                            type="text"
                            class="bcob-widget-input"
                            data-widget="${widget.id}"
                            data-key="${field.key}"
                            value="${value}">
                    </div>
                `;
        } else if (field.type === "number") {
          html += `
                    <div class="bcob-field">
                        <label>${field.label}</label>
                        <input
                            type="number"
                            class="bcob-widget-input"
                            data-widget="${widget.id}"
                            data-key="${field.key}"
                            value="${value}">
                    </div>
                `;
        } else if (field.type === "textarea") {
          html += `
                    <div class="bcob-field">
                        <label>${field.label}</label>
                        <textarea
                            class="bcob-widget-input"
                            data-widget="${widget.id}"
                            data-key="${field.key}">${value}</textarea>
                    </div>
                `;
        } else if (field.type === "media") {
          html += `
        <div class="bcob-field">
            <label>${field.label}</label>

            <input
                type="hidden"
                class="bcob-widget-input"
                data-widget="${widget.id}"
                data-key="${field.key}"
                value="${value}">

            <div class="bcob-media-preview">
                ${
                  widget.settings[field.key + "_url"]
                    ? `<img src="${widget.settings[field.key + "_url"]}" alt="">`
                    : ""
                }
            </div>

            <div class="bcob-media-actions">

                <button
                    type="button"
                    class="button bcob-media-upload">
                    Select Image
                </button>

                <button
                    type="button"
                    class="button bcob-media-remove">
                    Remove
                </button>

            </div>
        </div>
    `;
        } else if (field.type === "repeater") {
          const rows = widget.settings[field.key] || [];

          html += `
        <div class="bcob-field">
            <label>${field.label}</label>

            <div
                class="bcob-repeater"
                data-widget="${widget.id}"
                data-key="${field.key}">
    `;

          rows.forEach((row) => {
            html += `
            <div class="bcob-repeater-row">

                <input
                    type="text"
                    class="bcob-repeater-icon"
                    placeholder="Icon"
                    value="${row.icon || ""}">

                <input
                    type="text"
                    class="bcob-repeater-label"
                    placeholder="Label"
                    value="${row.label || ""}">

                <input
                    type="text"
                    class="bcob-repeater-value"
                    placeholder="Value"
                    value="${row.value || ""}">

                <button
                    type="button"
                    class="button bcob-remove-repeater">
                    Remove
                </button>

            </div>
        `;
          });

          html += `
            </div>

            <button
                type="button"
                class="button bcob-add-repeater">
                Add Row
            </button>

        </div>
    `;
        }
      });

      html += `
              </div>
          </div>
      `;
    });

    $("#bcob-sidebar-widgets").html(html);

    $("#bcob-sidebar-widgets").sortable({
      handle: ".bcob-widget-drag",
      axis: "y",
      containment: "parent",
      tolerance: "pointer",
      cursor: "grabbing",
      placeholder: "bcob-widget-placeholder",
      forcePlaceholderSize: true,
      update: () => {
        const sorted = [];

        $("#bcob-sidebar-widgets .bcob-widget-item").each(function () {
          const id = $(this).data("id");

          sorted.push(widgets.find((w) => w.id === id));
        });

        window.BCOB.form.steps[window.BCOB.currentStepIndex].sidebar.widgets =
          sorted;

        $(".bcob-save-form").trigger("click");
      },
    });
  },
};

$(function () {
  window.BCOB.widgetBuilder.init();
});
