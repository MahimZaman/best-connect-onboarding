$ = jQuery;

class UploadManager {
  constructor() {
    if (UploadManager.initialized) {
      return;
    }

    UploadManager.initialized = true;
    this.bind();
  }

  bind() {
    const self = this;

    $(document).on(
      "change",
      ".bcob-file-upload input[type='file']",
      function () {
        self.preview(this);

        if (
          this.files.length &&
          window.BCOBGoogleTags &&
          String(this.files[0].type || "").toLowerCase() === "application/pdf"
        ) {
          BCOBGoogleTags.event("file_uploaded", {
            step_number: BCOB.steps?.current || 1,
            step_name:
              BCOB.form?.steps?.[(BCOB.steps?.current || 1) - 1]?.title || "",
          });
        }

        self.autofill(this);
      },
    );

    $(document).on("dragover", ".bcob-file-upload", function (e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).addClass("dragover");
    });

    $(document).on("dragleave", ".bcob-file-upload", function (e) {
      e.preventDefault();
      e.stopPropagation();
      $(this).removeClass("dragover");
    });

    $(document).on("drop", ".bcob-file-upload", function (e) {
      e.preventDefault();
      e.stopPropagation();

      $(this).removeClass("dragover");

      const input = $(this).find("input[type='file']")[0];

      if (!input) {
        return;
      }

      input.files = e.originalEvent.dataTransfer.files;
      $(input).trigger("change");
    });

    $(document).on("click", ".bcob-upload-remove", function () {
      const wrapper = $(this).closest(".bcob-field");
      const input = wrapper.find(".bcob-file-upload input[type='file']")[0];
      const index = parseInt($(this).data("index"));

      self.removeFile(input, index);
    });
  }

  autofill(input) {
    const file = input.files[0];

    if (!file || !$(input).data("aifiller")) {
      return;
    }

    const aiSupportedFields = [
      "text",
      "textarea",
      "email",
      "tel",
      "number",
      "date",
      "time",
      "datetime",
      "select",
      "radio",
      "checkbox",
      "advanced-radio",
      "advanced-checkbox",
    ];

    const fields = [];

    BCOB.form.steps.forEach((step) => {
      step.fields.forEach((field) => {
        if (
          !field.id ||
          !field.label ||
          !aiSupportedFields.includes(field.type)
        ) {
          return;
        }

        fields.push({
          id: field.id,
          type: field.type,
          label: field.label,
          placeholder: field.placeholder || "",
          options: [
            "radio",
            "checkbox",
            "advanced-radio",
            "advanced-checkbox",
            "select",
          ].includes(field.type)
            ? field.options
            : [],
        });
      });
    });

    const formData = new FormData();

    formData.append("action", "bcob_ai_extract");
    formData.append("nonce", BCOBUploader.nonce);
    formData.append("file", file);
    formData.append("language", document.documentElement.lang || "de");
    formData.append("fields", JSON.stringify(fields));

    const previousFocus = document.activeElement;

    const $overlay = $(`
        <div
            class="bcob-ai-overlay"
            role="dialog"
            aria-modal="true"
            aria-labelledby="bcob-ai-overlay-title"
            aria-describedby="bcob-ai-overlay-status"
            tabindex="-1">
            <div class="bcob-ai-overlay__box">
                <div
                    class="bcob-ai-spinner"
                    aria-hidden="true"></div>

                <h3 id="bcob-ai-overlay-title">
                    Reading your document
                </h3>

                <p
                    id="bcob-ai-overlay-status"
                    role="status"
                    aria-live="polite"
                    aria-atomic="true">
                    Uploading document...
                </p>
            </div>
        </div>
    `);

    if (!$(".bcob-ai-overlay").length) {
      $("body").append($overlay);
      $overlay.trigger("focus");
    }

    const closeAiOverlay = () => {
      $(".bcob-ai-overlay").remove();

      if (previousFocus && document.contains(previousFocus)) {
        $(previousFocus).trigger("focus");
      }
    };

    const messages = [
      "Uploading document...",
      "Reading document...",
      "Extracting information...",
      "Matching form fields...",
      "Almost done...",
    ];

    let index = 0;

    const timer = setInterval(() => {
      if (index < messages.length - 1) {
        index++;
        $(".bcob-ai-overlay p").text(messages[index]);
      } else {
        clearInterval(timer);
      }
    }, 1800);

    $.ajax({
      url: BCOBUploader.ajax_url,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,

      success(response) {
        clearInterval(timer);

        closeAiOverlay();

        if (!response.success) {
          alert(response.data?.message || "Unable to process document.");
          return;
        }

        Object.entries(response.data).forEach(([fieldId, value]) => {
          const field = fields.find((f) => f.id === fieldId);

          if (!field) {
            return;
          }

          const $field = $('[name="' + fieldId + '"]');

          if (!$field.length) {
            return;
          }

          switch (field.type) {
            case "checkbox":
              const checkboxValues = (
                Array.isArray(value)
                  ? value
                  : String(value)
                      .split(",")
                      .map((v) => v.trim())
                      .filter(Boolean)
              ).map(String);

              $field.each(function () {
                const $input = $(this);

                const inputValue = String(
                  $input.attr("data-value") || $input.val() || "",
                ).trim();

                $input.prop("checked", checkboxValues.includes(inputValue));
              });

              break;

            case "radio":
              $field
                .prop("checked", false)
                .filter('[value="' + value + '"]')
                .prop("checked", true);

              break;

            case "select":

            case "dropdown":
              $field.val(value).trigger("change");

              break;

            default:
              $field.val(value);
          }

          $field.trigger("input");
          $field.trigger("change");
          if (value) {
            $field.closest(".bcob-field").addClass("is-aifilled");
          }
        });

        if (BCOB.validator) {
          BCOB.validator.validateCurrentStep();
        }

        BCOB.renderer.evaluateConditions();

        if (window.BCOBGoogleTags) {
          BCOBGoogleTags.event("ai_extracted", {
            step_number: BCOB.steps?.current || 1,
            step_name:
              BCOB.form?.steps?.[(BCOB.steps?.current || 1) - 1]?.title || "",
          });
        }
      },

      error(xhr) {
        clearInterval(timer);

        closeAiOverlay();

        console.error(xhr);

        alert("Unable to communicate with the AI server.");
      },
    });
  }

  preview(input) {
    const wrapper = $(input).closest(".bcob-field");
    const list = wrapper.find(".bcob-upload-list");
    const meta = wrapper.find(".bcob-file-upload__meta");

    list.empty();

    if (!input.files.length) {
      meta.text(meta.data("default") || meta.text());
      return;
    }

    if (input.files.length === 1) {
      meta.text(input.files[0].name);
    } else {
      meta.text(input.files.length + " Dateien ausgewählt");
    }

    [...input.files].forEach((file, index) => {
      let icon = "ri-file-line";

      if (file.type.startsWith("image/")) {
        icon = "ri-image-line";
      } else if (file.type === "application/pdf") {
        icon = "ri-file-pdf-line";
      }

      list.append(`
            <div class="bcob-upload-item">
                <div class="bcob-upload-file">
                    <div class="bcob-upload-file__left">
                        <div class="bcob-upload-file__icon">
                            <i class="${icon}"></i>
                        </div>

                        <div class="bcob-upload-file__content">
                            <div class="bcob-upload-name">${file.name}</div>
                            <div class="bcob-upload-size">${this.size(file.size)}</div>
                        </div>
                    </div>

                    <button
                        type="button"
                        class="bcob-upload-remove"
                        data-index="${index}">
                        <i class="ri-delete-bin-6-line"></i>
                    </button>
                </div>
            </div>
        `);
    });
  }

  removeFile(input, index) {
    const dt = new DataTransfer();

    [...input.files].forEach((file, i) => {
      if (i != index) {
        dt.items.add(file);
      }
    });

    input.files = dt.files;

    $(input).trigger("change");
  }

  size(bytes) {
    if (bytes < 1024) return bytes + " B";
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + " KB";
    return (bytes / 1024 / 1024).toFixed(2) + " MB";
  }
}

UploadManager.initialized = false;
