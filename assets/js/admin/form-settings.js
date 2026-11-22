(function ($) {
  "use strict";

  const Settings = {
    init() {
      this.cache();
      this.bind();
      this.load();
      this.renderTags();
      this.renderContractFields();
      this.loadContracts();
    },

    cache() {
      this.form = window.bcob.form;
      if (!this.form.settings || Array.isArray(this.form.settings)) {
        this.form.settings = {};
      }

      this.settings = this.form.settings;

      this.$tracking = $("#bcob_enable_tracking");
      this.$formType = $("#bcob_form_type");
      this.$withdrawalFormUrl = $("#bcob_withdrawal_form_url");
      this.$adminEmail = $("#bcob_admin_email");
      this.$adminSubject = $("#bcob_admin_subject");
      this.$customerSubject = $("#bcob_customer_subject");
      this.$companyName = $("#bcob_company_name");
      this.$senderName = $("#bcob_sender_name");
      this.$senderEmail = $("#bcob_sender_email");

      this.$tags = $("#bcob-merge-tags");
      this.$search = $("#bcob-merge-search");

      this.$contractRules = $("#bcob-contract-rule-list");
      this.$contractTemplate = $(".bcob-contract-rule-template");
      this.$relation = $("#bcob-contract-relation");

      this.activeEditor = null;
    },

    bind() {
      const self = this;

      $(document)
        .on("input change", ".bcob-setting", () => self.save())

        .on("keyup", "#bcob-merge-search", function () {
          const value = this.value.toLowerCase();

          self.$tags.children().each(function () {
            $(this).toggle($(this).text().toLowerCase().includes(value));
          });
        })

        .on("click", ".bcob-merge-tag", function () {
          const $button = $(this);

          const tag = $button.attr("data-tag") || $.trim($button.text());

          const originalText =
            $button.attr("data-label") || $.trim($button.text());

          if (!tag || !navigator.clipboard) {
            return;
          }

          navigator.clipboard.writeText(tag).then(() => {
            $button.addClass("copied").text("Copied!");

            setTimeout(() => {
              $button.removeClass("copied").text(originalText);
            }, 1200);
          });
        })
        .on("click", "#bcob-add-contract-rule", () => {
          self.addContractCondition();

          self.saveContracts();
        })

        .on("click", ".bcob-remove-contract-rule", function () {
          $(this).closest(".bcob-contract-rule").remove();

          self.saveContracts();
        })

        .on(
          "change input",
          ".bcob-contract-field,.bcob-contract-operator,.bcob-contract-value,.bcob-contract-preview,#bcob-contract-relation",
          () => self.saveContracts(),
        )

        .on("click", ".bcob-select-contract-pdf", function () {
          self.openPdfUploader($(this));
        })
        .on("click", ".bcob-map-contract-fields", function () {
          const row = $(this).closest(".bcob-contract-rule");

          if (!window.BCOB || !window.BCOB.PDFMapping) {
            return;
          }

          window.BCOB.PDFMapping.open(row, {
            onSave: function (mappings) {
              row.data("contract-mappings", mappings);

              self.saveContracts();
            },
          });
        });

      if (typeof tinymce !== "undefined") {
        const watch = setInterval(function () {
          const admin = tinymce.get("bcob_admin_email_template");
          const customer = tinymce.get("bcob_customer_email_template");

          if (!admin || !customer) {
            return;
          }

          clearInterval(watch);

          [admin, customer].forEach(function (editor) {
            editor.on("focus", function () {
              Settings.activeEditor = editor;
            });

            editor.on("change keyup", function () {
              Settings.save();
            });
          });

          if (Settings.settings.admin_template) {
            admin.setContent(Settings.settings.admin_template);
          }

          if (Settings.settings.customer_template) {
            customer.setContent(Settings.settings.customer_template);
          }
        }, 300);
      }
    },

    load() {
      this.$tracking.prop("checked", !!this.settings.enable_tracking);
      this.$adminEmail.val(this.settings.admin_email || "");
      this.$adminSubject.val(this.settings.admin_subject || "");
      this.$customerSubject.val(this.settings.customer_subject || "");
      this.$companyName.val(this.settings.company_name || "");
      this.$senderName.val(this.settings.sender_name || "");
      this.$senderEmail.val(this.settings.sender_email || "");
      this.$formType.val(this.settings.form_type || "b2c");
      this.$withdrawalFormUrl.val(this.settings.withdrawal_form_url || "");

      if (typeof tinymce !== "undefined") {
        const admin = tinymce.get("bcob_admin_email_template");
        const customer = tinymce.get("bcob_customer_email_template");

        if (admin && this.settings.admin_template) {
          admin.setContent(this.settings.admin_template);
        }

        if (customer && this.settings.customer_template) {
          customer.setContent(this.settings.customer_template);
        }
      }
    },

    save() {
      this.settings.enable_tracking = this.$tracking.is(":checked");
      this.settings.admin_email = this.$adminEmail.val();
      this.settings.admin_subject = this.$adminSubject.val();
      this.settings.customer_subject = this.$customerSubject.val();
      this.settings.company_name = this.$companyName.val().trim();
      this.settings.sender_name = this.$senderName.val().trim();
      this.settings.sender_email = this.$senderEmail.val().trim();
      this.settings.form_type = this.$formType.val();
      this.settings.withdrawal_form_url = this.$withdrawalFormUrl.val().trim();

      if (typeof tinymce !== "undefined") {
        const admin = tinymce.get("bcob_admin_email_template");
        const customer = tinymce.get("bcob_customer_email_template");

        if (admin) {
          this.settings.admin_template = admin.getContent();
        }

        if (customer) {
          this.settings.customer_template = customer.getContent();
        }
      }

      this.saveContracts();
    },

    renderTags() {
      let html = "";

      (this.form.steps || []).forEach(function (step) {
        (step.fields || []).forEach(function (field) {
          html += `
                <button
                    type="button"
                    class="bcob-merge-tag"
                    data-tag="{{${field.id}}}"
                    data-label="${field.label}"
                    title="Click to copy">
                    ${field.label}
                </button>
            `;
        });
      });

      this.$tags.html(html);
    },

    renderContractFields() {
      let options = '<option value="">Select Field</option>';

      (this.form.steps || []).forEach(function (step) {
        (step.fields || []).forEach(function (field) {
          const ignore = ["heading", "paragraph", "divider", "html"];

          if (ignore.includes(field.type)) {
            return;
          }

          options += `
                <option value="${field.id}">
                    ${field.label}
                </option>
            `;
        });
      });

      $(".bcob-contract-field").html(options);
    },

    // From here starts the contract methods

    addContractCondition(data = {}) {
      const row = this.$contractTemplate
        .clone(true)
        .removeClass("bcob-contract-rule-template")
        .addClass("bcob-contract-rule")
        .show();

      row.find("input").val("");

      row.find("select").prop("selectedIndex", 0);

      row.find(".bcob-contract-field").val(data.field || "");

      row.find(".bcob-contract-operator").val(data.operator || "=");

      row.find(".bcob-contract-value").val(data.value || "");

      row.find(".bcob-contract-pdf-id").val(data.pdf?.id || "");

      row
        .find(".bcob-contract-preview")
        .prop("checked", data.pdf?.preview !== false);

      row.data(
        "contract-mappings",
        Array.isArray(data.pdf?.mappings) ? data.pdf.mappings : [],
      );

      row.find(".bcob-map-contract-fields").prop("disabled", !data.pdf?.id);

      const mappingCount = Array.isArray(data.pdf?.mappings)
        ? data.pdf.mappings.length
        : 0;

      row
        .find(".bcob-contract-mapping-count")
        .text(
          mappingCount
            ? `${mappingCount} field${mappingCount === 1 ? "" : "s"} mapped`
            : "No fields mapped",
        );

      row
        .find(".bcob-contract-pdf-name")
        .text(data.pdf?.title || "No PDF Selected");

      if (data.pdf?.title) {
        row.find(".bcob-contract-pdf-info small").text("PDF Selected");

        row
          .find(".bcob-select-contract-pdf")
          .html('<i class="ri-refresh-line"></i> Change PDF');
      }

      this.$contractRules.append(row);
    },

    openPdfUploader(button) {
      const frame = wp.media({
        title: "Select Contract PDF",

        library: {
          type: "application/pdf",
        },

        button: {
          text: "Use PDF",
        },

        multiple: false,
      });

      frame.on("select", () => {
        const file = frame.state().get("selection").first().toJSON();

        const row = button.closest(".bcob-contract-rule");

        button.prop("disabled", true);

        button.html(
          '<i class="ri-loader-4-line ri-spin"></i> Processing PDF...',
        );

        row.find(".bcob-map-contract-fields").prop("disabled", true);

        $.ajax({
          url: window.bcobPdfMapping.ajaxUrl,

          type: "POST",

          dataType: "json",

          data: {
            action: "bcob_normalize_contract_pdf",

            nonce: window.bcobPdfMapping.nonce,

            pdf_id: file.id,
          },

          success: (response) => {
            if (
              !response ||
              !response.success ||
              !response.data ||
              !response.data.id
            ) {
              const message =
                response && response.data
                  ? response.data
                  : "Unable to normalize PDF.";

              window.alert(message);

              button.prop("disabled", false);

              button.html('<i class="ri-refresh-line"></i> Change PDF');

              return;
            }

            const normalized = response.data;

            row.find(".bcob-contract-pdf-id").val(normalized.id);

            row.find(".bcob-contract-preview").prop("checked", true);

            row.find(".bcob-contract-pdf-id").attr("data-url", normalized.url);

            row
              .find(".bcob-contract-pdf-name")
              .text(normalized.title || file.title);

            row.find(".bcob-contract-pdf-info small").text("PDF Selected");

            button.html('<i class="ri-refresh-line"></i> Change PDF');

            button.prop("disabled", false);

            row.find(".bcob-map-contract-fields").prop("disabled", false);

            /*
             * A newly selected PDF must start
             * with an empty mapping.
             */
            row.data("contract-mappings", []);

            row.find(".bcob-contract-mapping-count").text("No fields mapped");

            /*
             * Persist the normalized attachment ID,
             * not the original modern PDF ID.
             */
            this.saveContracts();
          },

          error: () => {
            window.alert("Unable to normalize PDF.");

            button.prop("disabled", false);

            button.html('<i class="ri-refresh-line"></i> Change PDF');
          },
        });
      });

      frame.open();
    },

    saveContracts() {
      this.settings.contracts = {
        relation: this.$relation.val(),

        conditions: [],
      };

      this.$contractRules.find(".bcob-contract-rule").each((i, row) => {
        row = $(row);

        const field = row.find(".bcob-contract-field").val();
        const value = row.find(".bcob-contract-value").val().trim();
        const pdfId = row.find(".bcob-contract-pdf-id").val();

        if (!field || !pdfId) {
          return;
        }

        const mappings = row.data("contract-mappings") || [];
        const preview = row.find(".bcob-contract-preview").is(":checked");

        this.settings.contracts.conditions.push({
          field,
          operator: row.find(".bcob-contract-operator").val(),
          value,
          pdf: {
            id: pdfId,
            title: row.find(".bcob-contract-pdf-name").text().trim(),
            mappings,
            preview,
          },
        });
      });
    },

    loadContracts() {
      if (!this.settings.contracts) {
        this.settings.contracts = {
          relation: "AND",
          conditions: [],
        };
      }

      this.$relation.val(this.settings.contracts.relation || "AND");

      if (!this.settings.contracts.conditions.length) {
        return;
      }

      this.$contractRules.empty();

      this.settings.contracts.conditions.forEach((condition) => {
        this.addContractCondition(condition);
      });
    },

    // End of contract methods

    insert(tag) {
      if (this.activeEditor) {
        this.activeEditor.insertContent(tag);

        return;
      }

      const editor = document.activeElement;

      if (!editor) {
        return;
      }

      const start = editor.selectionStart;
      const end = editor.selectionEnd;

      editor.value =
        editor.value.substring(0, start) + tag + editor.value.substring(end);

      editor.selectionStart = editor.selectionEnd = start + tag.length;

      $(editor).trigger("input");
    },
  };

  $(function () {
    Settings.init();
  });
})(jQuery);
