(function ($) {
  "use strict";

  class BCOBPDFReview {
    constructor() {
      this.wrapper = null;
      this.pdfDoc = null;
      this.pdfjsLib = null;

      this.init();
    }

    init() {
      this.bindEvents();
    }

    bindEvents() {
      $(document)
        .off("click.bcobPdfReview", ".bcob-pdf-review__confirm")
        .on("click.bcobPdfReview", ".bcob-pdf-review__confirm", (e) => {
          e.preventDefault();
          this.confirm();
        });

      $(document)
        .off("click.bcobPdfReview", ".bcob-pdf-review__close")
        .on("click.bcobPdfReview", ".bcob-pdf-review__close", (e) => {
          e.preventDefault();
          this.close();
        });
    }

    async open(data) {
      if (!data || !Array.isArray(data.contracts) || !data.contracts.length) {
        this.error("Kein Vertrag wurde gefunden.");
        return;
      }

      if (!window.BCOBPDFJS || typeof window.BCOBPDFJS.load !== "function") {
        this.error("PDF.js konnte nicht geladen werden.");

        return;
      }

      this.wrapper = this.createWrapper(data);

      $("body").append(this.wrapper);

      $("html, body").css("overflow", "hidden");

      try {
        this.pdfjsLib = await window.BCOBPDFJS.load();

        await this.renderPdf(data.contracts[0]);
      } catch (error) {
        console.error("BCOB PDF Review:", error);

        this.error(error.message || "Der Vertrag konnte nicht geladen werden.");

        this.close();
      }
    }

    createWrapper(data) {
      const $wrapper = $(`
        <div
          class="bcob-pdf-review"
          data-lead-id=""
        >
          <div class="bcob-pdf-review__header">
            <div>
              <h2 class="bcob-pdf-review__title">
                Vertrag prüfen
              </h2>

              <p class="bcob-pdf-review__description">
                Bitte prüfen Sie alle Angaben direkt im Vertrag.
                Sie können die vorhandenen Felder bearbeiten.
              </p>
            </div>

            <button
              type="button"
              class="bcob-pdf-review__close"
              aria-label="Schließen"
            >
              ×
            </button>
          </div>

          <div class="bcob-pdf-review__body">
            <div class="bcob-pdf-review__contract">
              <h3 class="bcob-pdf-review__contract-title"></h3>

              <div class="bcob-pdf-review__viewer"></div>
            </div>
          </div>

          <div class="bcob-pdf-review__footer">
            <p class="bcob-pdf-review__notice">
              Bitte prüfen Sie den gesamten Vertrag vor der Bestätigung.
            </p>

            <button
              type="button"
              class="bcob-pdf-review__confirm"
            >
              <span class="bcob-pdf-review__confirm-text">
                Vertrag bestätigen
              </span>

              <span class="bcob-pdf-review__spinner"></span>
            </button>
          </div>
        </div>
      `);

      $wrapper.data("lead-id", data.lead_id || "");

      $wrapper.data("lead-token", data.lead_token || "");

      return $wrapper;
    }

    async renderPdf(contract) {
      if (!contract.url) {
        throw new Error("Keine PDF-URL vorhanden.");
      }

      this.wrapper
        .find(".bcob-pdf-review__contract-title")
        .text(contract.title || "Vertrag");

      if (!this.pdfjsLib) {
        throw new Error("PDF.js ist nicht verfügbar.");
      }

      const loadingTask = this.pdfjsLib.getDocument({
        url: contract.url,

        disableAutoFetch: false,

        disableStream: false,

        isEvalSupported: false,
      });

      this.pdfDoc = await loadingTask.promise;

      const $viewer = this.wrapper.find(".bcob-pdf-review__viewer");

      for (
        let pageNumber = 1;
        pageNumber <= this.pdfDoc.numPages;
        pageNumber++
      ) {
        await this.renderPage(pageNumber, $viewer);
      }

      $viewer.data("attachment-id", contract.attachment_id);
    }

    async renderPage(pageNumber, $viewer) {
      const page = await this.pdfDoc.getPage(pageNumber);

      const scale = 1.5;

      const viewport = page.getViewport({
        scale,
      });

      const $page = $(`
        <div class="bcob-pdf-page">
          <canvas></canvas>
          <div class="bcob-pdf-fields"></div>
        </div>
      `);

      const canvas = $page.find("canvas")[0];

      const context = canvas.getContext("2d");

      canvas.width = Math.floor(viewport.width);

      canvas.height = Math.floor(viewport.height);

      $page.css({
        width: viewport.width + "px",

        height: viewport.height + "px",
      });

      const $fields = $page.find(".bcob-pdf-fields");

      $fields.css({
        width: viewport.width + "px",

        height: viewport.height + "px",
      });

      $viewer.append($page);

      await page.render({
        canvasContext: context,

        viewport,
      }).promise;

      const annotations = await page.getAnnotations({
        intent: "display",
      });

      annotations
        .filter((annotation) => annotation.subtype === "Widget")
        .forEach((annotation) => {
          this.renderField(annotation, viewport, $fields);
        });
    }

    renderField(annotation, viewport, $container) {
      const name = annotation.fieldName || annotation.id || "";

      if (!name) {
        return;
      }

      const rect = viewport.convertToViewportRectangle(annotation.rect);

      const left = Math.min(rect[0], rect[2]);

      const top = Math.min(rect[1], rect[3]);

      const width = Math.abs(rect[2] - rect[0]);

      const height = Math.abs(rect[3] - rect[1]);

      const fieldType = annotation.fieldType || "";

      const fieldValue = annotation.fieldValue ?? "";

      let $input = null;

      if (fieldType === "Tx") {
        const multiline = (annotation.fieldFlags || 0) & 4096;

        $input = $(multiline ? "<textarea>" : "<input>");

        if (!multiline) {
          $input.attr("type", "text");
        }

        $input.val(fieldValue);
      }

      if (fieldType === "Ch") {
        const options = Array.isArray(annotation.options)
          ? annotation.options
          : [];

        $input = $("<select>");

        options.forEach((option) => {
          const exportValue =
            typeof option === "object"
              ? (option.exportValue ?? option.displayValue ?? "")
              : option;

          const displayValue =
            typeof option === "object"
              ? (option.displayValue ?? option.exportValue ?? "")
              : option;

          $input.append(
            $("<option>", {
              value: exportValue,

              text: displayValue,
            }),
          );
        });

        $input.val(fieldValue);
      }

      if (fieldType === "Btn") {
        const radio = annotation.radioButton === true;

        const pushButton = annotation.pushButton === true;

        if (pushButton) {
          return;
        }

        if (radio) {
          $input = $("<input>", {
            type: "radio",
          });

          $input.attr("name", "bcob-pdf-radio-" + name);

          $input.attr(
            "value",
            annotation.buttonValue || annotation.exportValue || "",
          );

          $input.prop(
            "checked",
            String(fieldValue) ===
              String(annotation.buttonValue || annotation.exportValue || ""),
          );
        } else {
          $input = $("<input>", {
            type: "checkbox",
          });

          const onValue =
            annotation.buttonValue || annotation.exportValue || "Yes";

          $input.attr("value", onValue);

          $input.prop(
            "checked",
            String(fieldValue) !== "Off" && String(fieldValue) !== "",
          );
        }
      }

      if (!$input) {
        return;
      }

      $input.attr("data-bcob-pdf-field", name);

      $input.attr("data-bcob-pdf-type", fieldType);

      if (fieldType === "Btn") {
        $input.attr(
          "data-bcob-pdf-button-value",
          annotation.buttonValue || annotation.exportValue || "",
        );
      }

      $input.css({
        position: "absolute",

        left: left + "px",

        top: top + "px",

        width: width + "px",

        height: height + "px",

        margin: 0,

        boxSizing: "border-box",

        zIndex: 10,
      });

      if (
        fieldType === "Btn" &&
        (annotation.radioButton || !annotation.pushButton)
      ) {
        const size = Math.min(Math.max(width, height), 22);

        $input.css({
          width: size + "px",

          height: size + "px",

          left: left + Math.max(0, (width - size) / 2) + "px",

          top: top + Math.max(0, (height - size) / 2) + "px",
        });
      }

      $input.on("input.bcobPdfField change.bcobPdfField", () => {
        this.updateFieldState($input);
      });

      $container.append($input);
    }

    updateFieldState($input) {
      $input.attr("data-bcob-pdf-dirty", "1");
    }

    collectFields() {
      const fields = {};

      this.wrapper.find("[data-bcob-pdf-field]").each(function () {
        const $input = $(this);

        const name = $input.attr("data-bcob-pdf-field");

        const type = $input.attr("data-bcob-pdf-type");

        if (!name) {
          return;
        }

        if (type === "Btn") {
          if ($input.attr("type") === "radio") {
            if (!$input.is(":checked")) {
              return;
            }

            fields[name] = $input.val();

            return;
          }

          fields[name] = $input.is(":checked");

          return;
        }

        fields[name] = $input.val();
      });

      return fields;
    }

    async confirm() {
      if (!this.wrapper) {
        return;
      }

      const $button = this.wrapper.find(".bcob-pdf-review__confirm");

      const attachmentId = this.wrapper
        .find(".bcob-pdf-review__viewer")
        .data("attachment-id");

      if (!attachmentId) {
        this.error("Der Vertrag konnte nicht verarbeitet werden.");

        return;
      }

      this.wrapper.addClass("is-loading");

      $button.prop("disabled", true);

      try {
        const fields = this.collectFields();

        const formData = new FormData();

        formData.append("action", "bcob_confirm_pdf");

        formData.append("nonce", BCOBPdfReview.nonce);

        formData.append("lead_id", this.wrapper.data("lead-id"));

        formData.append("lead_token", this.wrapper.data("lead-token"));

        formData.append("attachment_id", attachmentId);

        formData.append("fields", JSON.stringify(fields));

        const response = await $.ajax({
          url: BCOBPdfReview.ajax_url,

          type: "POST",

          data: formData,

          processData: false,

          contentType: false,
        });

        if (!response.success) {
          throw new Error(
            response.data?.message ||
              "Der Vertrag konnte nicht bestätigt werden.",
          );
        }

        if (response.data?.redirect) {
          window.location.href = response.data.redirect;

          return;
        }

        if (response.data?.message) {
          this.error(response.data.message);
        }

        this.close();
      } catch (error) {
        console.error("BCOB PDF confirmation:", error);

        this.error(
          error.message || "Der Vertrag konnte nicht bestätigt werden.",
        );

        $button.prop("disabled", false);

        this.wrapper.removeClass("is-loading");
      }
    }

    close() {
      if (!this.wrapper) {
        return;
      }

      this.wrapper.remove();

      this.wrapper = null;

      this.pdfDoc = null;

      this.pdfjsLib = null;

      $("html, body").css("overflow", "");
    }

    error(message) {
      window.alert(message);
    }
  }

  window.BCOBPDFReview = new BCOBPDFReview();
})(jQuery);
