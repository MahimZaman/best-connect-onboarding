(function ($) {
  "use strict";

  window.BCOB = window.BCOB || {};

  window.BCOB.PDFMapping = {
    modal: null,
    currentRow: null,
    currentOptions: null,

    pdfDocument: null,
    pdfPage: 1,
    pdfPageCount: 1,
    pdfScale: 1.0,

    pdfFields: [],
    existingMappings: [],

    init() {
      this.modal = $("#bcob-pdf-mapping-modal");

      if (!this.modal.length) {
        return;
      }

      this.bindEvents();
    },

    bindEvents() {
      const self = this;

      $(document)
        .on("click", ".bcob-pdf-mapping-close", function () {
          self.close();
        })

        .on("click", ".bcob-pdf-mapping-cancel", function () {
          self.close();
        })

        .on("click", ".bcob-pdf-mapping-overlay", function () {
          self.close();
        })

        .on("click", ".bcob-pdf-mapping-save", function () {
          self.save();
        })

        .on(
          "change input",
          ".bcob-pdf-mapping-form-field," +
            ".bcob-pdf-mapping-condition-operator," +
            ".bcob-pdf-mapping-condition-value," +
            ".bcob-pdf-radio-map-value",
          function () {
            self.updateSummary();
          },
        )

        .on("click", ".bcob-pdf-mapping-prev-page", function () {
          self.previousPage();
        })

        .on("click", ".bcob-pdf-mapping-next-page", function () {
          self.nextPage();
        })

        .on("click", ".bcob-pdf-mapping-zoom-in", function () {
          self.zoomIn();
        })

        .on("click", ".bcob-pdf-mapping-zoom-out", function () {
          self.zoomOut();
        });

      $(document).on("keydown", function (event) {
        if (!self.isOpen()) {
          return;
        }

        if (event.key === "Escape") {
          self.close();
        }
      });
    },

    /**
     * Open mapper for a specific contract row.
     *
     * @param {jQuery} row
     * @param {Object} options
     */
    open(row, options = {}) {
      if (!row || !row.length) {
        return;
      }

      const pdfId = parseInt(row.find(".bcob-contract-pdf-id").val(), 10);

      if (!pdfId) {
        alert("Please select a PDF first.");
        return;
      }

      this.currentRow = row;
      this.currentOptions = options;

      this.existingMappings = row.data("contract-mappings") || [];

      this.pdfDocument = null;
      this.pdfPage = 1;
      this.pdfPageCount = 1;
      this.pdfScale = 1.0;
      this.pdfFields = [];

      this.resetUI();

      this.modal.removeAttr("hidden");

      $("body").addClass("bcob-pdf-mapping-open");

      this.loadFields(pdfId);
    },

    close() {
      if (!this.modal) {
        return;
      }

      this.modal.attr("hidden", true);

      $("body").removeClass("bcob-pdf-mapping-open");

      this.currentRow = null;
      this.currentOptions = null;
      this.pdfDocument = null;
      this.pdfFields = [];
      this.existingMappings = [];
    },

    isOpen() {
      return this.modal && this.modal.length && !this.modal.is("[hidden]");
    },

    resetUI() {
      this.modal.find(".bcob-pdf-mapping-contract-name").text("");

      this.modal.find(".bcob-pdf-mapping-fields").html(`
          <div class="bcob-pdf-mapping-loading">
            <i class="ri-loader-4-line ri-spin"></i>
            <span>Reading PDF fields...</span>
          </div>
        `);

      this.modal.find(".bcob-pdf-mapping-status").html(`
          <i class="ri-loader-4-line ri-spin"></i>
          Loading PDF...
        `);

      this.modal.find(".bcob-pdf-mapping-field-count").text("0");

      this.modal.find(".bcob-pdf-mapping-summary").text("No fields mapped.");

      this.modal.find(".bcob-pdf-mapping-page").text("1 / 1");

      this.modal.find(".bcob-pdf-mapping-zoom-value").text("100%");

      const canvas = document.getElementById("bcob-pdf-mapping-canvas");

      if (canvas) {
        const context = canvas.getContext("2d");

        context.clearRect(0, 0, canvas.width, canvas.height);

        canvas.width = 0;
        canvas.height = 0;
      }

      this.modal.find(".bcob-pdf-mapping-pdf-loader").show();
    },

    loadFields(pdfId) {
      const self = this;

      $.ajax({
        url: bcobPdfMapping.ajaxUrl,

        type: "POST",

        dataType: "json",

        data: {
          action: "bcob_get_pdf_acroform_fields",
          nonce: bcobPdfMapping.nonce,
          pdf_id: pdfId,
        },

        success(response) {
          if (!response || !response.success) {
            self.showError(
              response && response.data
                ? response.data
                : "Unable to read PDF fields.",
            );

            return;
          }

          const data = response.data || {};

          self.pdfFields = Array.isArray(data.fields) ? data.fields : [];

          self.modal
            .find(".bcob-pdf-mapping-contract-name")
            .text(data.title || "");

          self.modal.find(".bcob-pdf-mapping-status").html(`
              <i class="ri-checkbox-circle-line"></i>
              ${self.pdfFields.length} AcroForm field${
                self.pdfFields.length === 1 ? "" : "s"
              } detected
            `);

          self.renderFields();

          if (data.url) {
            self.loadPdf(data.url);
          } else {
            self.showPdfError("The PDF URL could not be determined.");
          }
        },

        error(xhr) {
          console.error("BCOB PDF Mapping:", xhr);

          self.showError("Unable to communicate with the server.");
        },
      });
    },

    renderFields() {
      const container = this.modal.find(".bcob-pdf-mapping-fields");

      container.empty();

      this.modal
        .find(".bcob-pdf-mapping-field-count")
        .text(this.pdfFields.length);

      if (!this.pdfFields.length) {
        container.html(`
            <div class="bcob-pdf-mapping-empty">
                <i class="ri-file-warning-line"></i>
                <strong>No AcroForm fields found</strong>
                <span>
                    This PDF does not contain editable
                    AcroForm fields.
                </span>
            </div>
        `);

        this.updateSummary();
        return;
      }

      const formFields = this.getFormFields();

      this.pdfFields.forEach((pdfField, index) => {
        const existing = this.findExistingMapping(pdfField.name);

        container.append(
          this.renderMappingRow(pdfField, index, formFields, existing),
        );
      });

      this.updateSummary();
    },

    renderMappingRow(pdfField, index, formFields, existing) {
      const type = String(pdfField.type || "").toLowerCase();

      let controls = "";

      switch (type) {
        case "checkbox":
          controls = this.renderCheckboxMapping(pdfField, formFields, existing);
          break;

        case "radio":
          controls = this.renderRadioMapping(pdfField, formFields, existing);
          break;

        default:
          controls = this.renderStandardMapping(pdfField, formFields, existing);
          break;
      }

      const optionsHtml =
        pdfField.options && pdfField.options.length
          ? this.renderPdfOptions(pdfField.options)
          : "";

      return `
        <div
            class="bcob-pdf-mapping-row bcob-pdf-mapping-type-${this.escapeHtml(type)}"
            data-pdf-field-index="${index}">

            <div class="bcob-pdf-mapping-field-info">

                <div class="bcob-pdf-mapping-field-icon">
                    <i class="${this.getFieldIcon(type)}"></i>
                </div>

                <div class="bcob-pdf-mapping-field-details">

                    <strong>
                        ${this.escapeHtml(pdfField.name)}
                    </strong>

                    <span>
                        ${this.escapeHtml(this.getFieldTypeLabel(type))}
                    </span>

                    ${optionsHtml}

                </div>

            </div>

            <div class="bcob-pdf-mapping-arrow">
                <i class="ri-arrow-right-line"></i>
            </div>

            <div class="bcob-pdf-mapping-form-control">
                ${controls}
            </div>

        </div>
    `;
    },

    renderStandardMapping(pdfField, formFields, existing) {
      return `
        <select class="bcob-pdf-mapping-form-field">
            ${this.buildFormFieldOptions(formFields, existing)}
        </select>
    `;
    },

    renderCheckboxMapping(pdfField, formFields, existing) {
      const formOptions = this.buildFormFieldOptions(formFields, existing);

      const condition = existing?.condition || {};

      const operator = condition.operator || "=";
      const expectedValue = condition.value || "";

      const checkedValue = existing?.checked_value || pdfField.on_value || "";

      const uncheckedValue =
        existing?.unchecked_value || pdfField.off_value || "Off";

      return `
        <div class="bcob-pdf-mapping-special">

            <label>
                <span>Form Field</span>

                <select class="bcob-pdf-mapping-form-field">
                    ${formOptions}
                </select>
            </label>

            <label>
                <span>Check when</span>

                <select class="bcob-pdf-mapping-condition-operator">
                    <option value="=" ${
                      operator === "=" ? "selected" : ""
                    }>Equals</option>

                    <option value="!=" ${
                      operator === "!=" ? "selected" : ""
                    }>Does not equal</option>

                    <option value="contains" ${
                      operator === "contains" ? "selected" : ""
                    }>Contains</option>

                    <option value="not_contains" ${
                      operator === "not_contains" ? "selected" : ""
                    }>Does not contain</option>

                    <option value="truthy" ${
                      operator === "truthy" ? "selected" : ""
                    }>Has a value</option>
                </select>
            </label>

            <label class="bcob-pdf-mapping-condition-value-wrap">
                <span>Expected Value</span>

                <input
                    type="text"
                    class="bcob-pdf-mapping-condition-value"
                    value="${this.escapeAttribute(expectedValue)}"
                >
            </label>

            <div class="bcob-pdf-mapping-pdf-values">

                <div>
                    <span>Checked value</span>
                    <code>${this.escapeHtml(checkedValue)}</code>
                </div>

                <div>
                    <span>Unchecked value</span>
                    <code>${this.escapeHtml(uncheckedValue)}</code>
                </div>

            </div>

        </div>
    `;
    },

    renderRadioMapping(pdfField, formFields, existing) {
      const formOptions = this.buildFormFieldOptions(formFields, existing);

      const valueMap = existing?.value_map || {};

      const options = Array.isArray(pdfField.options) ? pdfField.options : [];

      let optionRows = "";

      options.forEach((pdfOption) => {
        const mappedValue =
          valueMap[pdfOption] !== undefined ? valueMap[pdfOption] : "";

        optionRows += `
            <div class="bcob-pdf-radio-map-row">

                <div class="bcob-pdf-radio-pdf-value">
                    <i class="ri-radio-button-line"></i>

                    <code>
                        ${this.escapeHtml(pdfOption)}
                    </code>
                </div>

                <i class="ri-arrow-right-line"></i>

                <input
                    type="text"
                    class="bcob-pdf-radio-map-value"
                    data-pdf-option="${this.escapeAttribute(pdfOption)}"
                    value="${this.escapeAttribute(mappedValue)}"
                    placeholder="Submitted value"
                >

            </div>
        `;
      });

      return `
        <div class="bcob-pdf-mapping-special">

            <label>
                <span>Form Field</span>

                <select class="bcob-pdf-mapping-form-field">
                    ${formOptions}
                </select>
            </label>

            <div class="bcob-pdf-radio-map">

                <div class="bcob-pdf-radio-map-header">
                    <span>PDF Option</span>
                    <span>Submitted Value</span>
                </div>

                ${optionRows}

            </div>

        </div>
    `;
    },

    buildFormFieldOptions(formFields, existing) {
      const selected =
        existing && existing.form_field ? String(existing.form_field) : "";

      let html = `
        <option value="">
            Do not map
        </option>

        <option
            value="ip-timing-token"
            ${selected === "ip-timing-token" ? "selected" : ""}>
            IP/Timing/Token
        </option>
    `;

      formFields.forEach((field) => {
        const fieldId = String(field.id);

        html += `
            <option
                value="${this.escapeAttribute(fieldId)}"
                ${selected === fieldId ? "selected" : ""}>
                ${this.escapeHtml(field.label)}
                ${field.type ? ` (${this.escapeHtml(field.type)})` : ""}
            </option>
        `;
      });

      return html;
    },

    renderPdfOptions(options) {
      const cleanOptions = options
        .map((option) => {
          if (Array.isArray(option)) {
            return option.join(" → ");
          }

          return option;
        })
        .filter(Boolean);

      if (!cleanOptions.length) {
        return "";
      }

      return `
        <small class="bcob-pdf-mapping-options">
          Options:
          ${cleanOptions
            .map((option) => `<span>${this.escapeHtml(option)}</span>`)
            .join("")}
        </small>
      `;
    },

    getFormFields() {
      const fields = [];

      const form = window.bcob && window.bcob.form ? window.bcob.form : {};

      (form.steps || []).forEach((step) => {
        (step.fields || []).forEach((field) => {
          const ignored = ["heading", "paragraph", "divider", "html"];

          if (ignored.includes(field.type)) {
            return;
          }

          fields.push({
            id: String(field.id),
            label: field.label || field.id || "Unnamed Field",
            type: field.type || "",
          });
        });
      });

      return fields;
    },

    findExistingMapping(pdfFieldName) {
      return this.existingMappings.find(
        (mapping) => String(mapping.pdf_field) === String(pdfFieldName),
      );
    },

    getFieldIcon(type) {
      switch (String(type).toLowerCase()) {
        case "checkbox":
          return "ri-checkbox-line";

        case "radio":
          return "ri-radio-button-line";

        case "dropdown":
        case "choice":
        case "listbox":
          return "ri-list-check-3";

        case "signature":
          return "ri-edit-line";

        default:
          return "ri-input-method-line";
      }
    },

    getFieldTypeLabel(type) {
      switch (String(type).toLowerCase()) {
        case "text":
          return "Text";

        case "checkbox":
          return "Checkbox";

        case "radio":
          return "Radio";

        case "dropdown":
          return "Dropdown";

        case "listbox":
          return "List";

        case "signature":
          return "Signature";

        default:
          return String(type || "Unknown");
      }
    },

    save() {
      if (!this.currentRow) {
        return;
      }

      const mappings = [];

      this.modal.find(".bcob-pdf-mapping-row").each((index) => {
        const row = $(this.modal.find(".bcob-pdf-mapping-row")[index]);

        const pdfFieldIndex = parseInt(row.attr("data-pdf-field-index"), 10);

        if (Number.isNaN(pdfFieldIndex) || !this.pdfFields[pdfFieldIndex]) {
          return;
        }

        const pdfField = this.pdfFields[pdfFieldIndex];

        const formField = String(
          row.find(".bcob-pdf-mapping-form-field").val() || "",
        );

        if (!formField) {
          return;
        }

        const mapping = {
          pdf_field: String(pdfField.name || ""),
          pdf_type: String(pdfField.type || ""),
          form_field: formField,
        };

        const pdfType = String(pdfField.type || "").toLowerCase();

        if (pdfType === "checkbox") {
          const operator =
            row.find(".bcob-pdf-mapping-condition-operator").val() || "=";

          const conditionValue =
            row.find(".bcob-pdf-mapping-condition-value").val() || "";

          mapping.condition = {
            operator: String(operator),
            value: String(conditionValue),
          };

          mapping.checked_value = String(
            pdfField.on_value ||
              pdfField.options?.find(
                (value) => String(value).toLowerCase() !== "off",
              ) ||
              "",
          );

          mapping.unchecked_value = String(
            pdfField.off_value ||
              pdfField.options?.find(
                (value) => String(value).toLowerCase() === "off",
              ) ||
              "Off",
          );
        } else if (pdfType === "radio") {
          mapping.value_map = {};

          row.find(".bcob-pdf-radio-map-value").each(function () {
            const input = $(this);

            const pdfOption = String(input.attr("data-pdf-option") || "");

            const submittedValue = String(input.val() || "").trim();

            if (pdfOption && submittedValue) {
              mapping.value_map[pdfOption] = submittedValue;
            }
          });
        }

        mappings.push(mapping);
      });

      this.currentRow.data("contract-mappings", mappings);

      this.currentRow
        .find(".bcob-contract-mapping-count")
        .text(
          mappings.length
            ? `${mappings.length} field${mappings.length === 1 ? "" : "s"} mapped`
            : "No fields mapped",
        );

      this.currentRow.trigger("bcob:contract-mapping-saved", [mappings]);

      if (
        this.currentOptions &&
        typeof this.currentOptions.onSave === "function"
      ) {
        this.currentOptions.onSave(mappings);
      }

      const formSaveButton = $(".bcob-save-form").first();

      if (formSaveButton.length) {
        formSaveButton.trigger("click");
      }
      this.close();
    },

    updateSummary() {
      const total = this.pdfFields.length;

      const mapped = this.modal
        .find(".bcob-pdf-mapping-form-field")
        .filter(function () {
          return $(this).val();
        }).length;

      if (!total) {
        this.modal
          .find(".bcob-pdf-mapping-summary")
          .text("No fields detected.");

        return;
      }

      this.modal
        .find(".bcob-pdf-mapping-summary")
        .text(`${mapped} of ${total} field${total === 1 ? "" : "s"} mapped.`);
    },

    async loadPdf(url) {
      if (!window.BCOBPDFJS || typeof window.BCOBPDFJS.load !== "function") {
        this.showPdfError("PDF.js loader is unavailable.");

        return;
      }

      this.showPdfLoader();

      try {
        const pdfjsLib = await window.BCOBPDFJS.load();

        const loadingTask = pdfjsLib.getDocument({
          url: url,

          isEvalSupported: false,
        });

        const pdf = await loadingTask.promise;

        this.pdfDocument = pdf;
        this.pdfPageCount = pdf.numPages;
        this.pdfPage = 1;
        this.pdfScale = 1.0;

        this.renderPage();
      } catch (error) {
        this.showPdfError("Unable to render the PDF preview.");
      }
    },

    renderPage() {
      if (!this.pdfDocument) {
        return;
      }

      const self = this;

      this.pdfDocument.getPage(this.pdfPage).then(function (page) {
        const canvas = document.getElementById("bcob-pdf-mapping-canvas");

        if (!canvas) {
          return;
        }

        const context = canvas.getContext("2d");

        const viewport = page.getViewport({
          scale: self.pdfScale,
        });

        canvas.width = viewport.width;
        canvas.height = viewport.height;

        context.clearRect(0, 0, canvas.width, canvas.height);

        page
          .render({
            canvasContext: context,
            viewport: viewport,
          })
          .promise.then(function () {
            self.modal.find(".bcob-pdf-mapping-pdf-loader").hide();

            self.modal
              .find(".bcob-pdf-mapping-page")
              .text(`${self.pdfPage} / ${self.pdfPageCount}`);

            self.modal
              .find(".bcob-pdf-mapping-zoom-value")
              .text(`${Math.round(self.pdfScale * 100)}%`);

            return page.getAnnotations({
              intent: "display",
            });
          })
          .then(function (annotations) {
            self.renderAcroFormOverlays(canvas, viewport, annotations);
          })
          .catch(function (error) {
            console.error("BCOB PDF annotation overlay:", error);
          });
      });
    },

    renderAcroFormOverlays(canvas, viewport, annotations) {
      const wrap = this.modal.find(".bcob-pdf-mapping-canvas-wrap");

      if (!wrap.length) {
        return;
      }

      let layer = wrap.find(".bcob-pdf-mapping-field-overlay-layer");

      if (!layer.length) {
        layer = $('<div class="bcob-pdf-mapping-field-overlay-layer"></div>');

        wrap.append(layer);
      }

      layer.empty();

      layer.css({
        left: `${canvas.offsetLeft}px`,
        top: `${canvas.offsetTop}px`,
        width: `${canvas.width}px`,
        height: `${canvas.height}px`,
      });

      if (!Array.isArray(annotations)) {
        return;
      }

      annotations.forEach((annotation) => {
        if (!annotation || !annotation.rect) {
          return;
        }

        const fieldName =
          annotation.fieldName || annotation.alternativeText || "";

        if (!fieldName) {
          return;
        }

        const point1 = viewport.convertToViewportPoint(
          annotation.rect[0],
          annotation.rect[1],
        );

        const point2 = viewport.convertToViewportPoint(
          annotation.rect[2],
          annotation.rect[3],
        );

        const left = Math.min(point1[0], point2[0]);

        const top = Math.min(point1[1], point2[1]);

        const width = Math.abs(point2[0] - point1[0]);

        const height = Math.abs(point2[1] - point1[1]);

        if (width <= 0 || height <= 0) {
          return;
        }

        const type = annotation.fieldType || "";

        const overlay = $(`
      <div
        class="bcob-pdf-mapping-field-overlay"
        data-field-name="${this.escapeAttribute(fieldName)}"
        data-field-type="${this.escapeAttribute(type)}">

        <span class="bcob-pdf-mapping-field-overlay-label">
          ${this.escapeHtml(fieldName)}
        </span>

      </div>
    `);

        overlay.css({
          left: `${left}px`,
          top: `${top}px`,
          width: `${width}px`,
          height: `${height}px`,
        });

        layer.append(overlay);
      });
    },

    previousPage() {
      if (!this.pdfDocument || this.pdfPage <= 1) {
        return;
      }

      this.pdfPage--;

      this.showPdfLoader();

      this.renderPage();
    },

    nextPage() {
      if (!this.pdfDocument || this.pdfPage >= this.pdfPageCount) {
        return;
      }

      this.pdfPage++;

      this.showPdfLoader();

      this.renderPage();
    },

    zoomIn() {
      if (!this.pdfDocument) {
        return;
      }

      this.pdfScale = Math.min(this.pdfScale + 0.15, 2.5);

      this.showPdfLoader();

      this.renderPage();
    },

    zoomOut() {
      if (!this.pdfDocument) {
        return;
      }

      this.pdfScale = Math.max(this.pdfScale - 0.15, 0.5);

      this.showPdfLoader();

      this.renderPage();
    },

    showPdfLoader() {
      this.modal.find(".bcob-pdf-mapping-pdf-loader").show();
    },

    showError(message) {
      this.modal.find(".bcob-pdf-mapping-fields").html(`
          <div class="bcob-pdf-mapping-error">
            <i class="ri-error-warning-line"></i>
            <strong>Unable to read PDF</strong>
            <span>
              ${this.escapeHtml(message)}
            </span>
          </div>
        `);

      this.modal.find(".bcob-pdf-mapping-status").html(`
          <i class="ri-error-warning-line"></i>
          Unable to load PDF fields
        `);

      this.showPdfError(message);
    },

    showPdfError(message) {
      this.modal.find(".bcob-pdf-mapping-pdf-loader").show().html(`
          <i class="ri-error-warning-line"></i>
          <span>
            ${this.escapeHtml(message)}
          </span>
        `);
    },

    escapeAttribute(value) {
      return String(value == null ? "" : value)
        .replace(/&/g, "&amp;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
    },

    escapeHtml(value) {
      return $("<div>")
        .text(value == null ? "" : String(value))
        .html();
    },
  };

  $(function () {
    window.BCOB.PDFMapping.init();
  });
})(jQuery);
