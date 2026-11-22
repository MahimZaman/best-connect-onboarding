$ = jQuery;

window.BCOB = window.BCOB || {};

(function ($) {
  "use strict";

  class Frontend {
    constructor() {
      this.submissionCompleted = false;
      this.activeForm = null;
    }

    async init() {
      const form = document.getElementById("bcob-form");

      if (!form) {
        return false;
      }

      if (form.dataset.bcobInitialized === "1") {
        return true;
      }

      if (this.activeForm && this.activeForm !== form) {
        this.destroy();
      }

      form.dataset.bcobInitialized = "1";

      this.activeForm = form;
      BCOB.price = this.price.bind(this);
      BCOB.currency = BCOB.price_settings.currency_code;
      BCOB.renderer = new Renderer();
      BCOB.validator = new Validator();
      BCOB.progress = new ProgressManager();
      BCOB.sidebar = new SidebarManager();
      BCOB.storage = new StorageManager();
      BCOB.uploader = new UploadManager();
      BCOB.steps = new StepManager();
      BCOB.payment = new BCOBPaymentManager();
      BCOB.submission = new BCOBSubmission();
      BCOB.coupon = new CouponManager();
      BCOB.orderReview = new OrderReview();

      this.bind();

      BCOB.storage.restore();

      BCOB.progress.refresh();

      BCOB.sidebar.show(1);
      BCOB.submission.init();
      BCOB.submission.handlePaymentReturn();
      BCOB.validator.validateCurrentStep();
      BCOB.StepEditor.init();
      BCOB.renderer.evaluateConditions();

      if (window.BCOBGoogleTags) {
        BCOBGoogleTags.event("step_view", {
          step_number: BCOB.steps.current,
          step_name: BCOB.form?.steps?.[BCOB.steps.current - 1]?.title || "",
        });
      }

      $(document).trigger("bcob:frontend-ready");
    }

    bind() {
      $(window)
        .off("beforeunload.bcobFrontend")
        .on("beforeunload.bcobFrontend", () => {
          if (this.submissionCompleted) {
            return;
          }

          if (BCOB.storage && typeof BCOB.storage.save === "function") {
            BCOB.storage.save();
          }
        });
    }

    destroy() {
      if (BCOB.payment && typeof BCOB.payment.destroy === "function") {
        BCOB.payment.destroy();
      }

      if (BCOB.steps && typeof BCOB.steps.destroy === "function") {
        BCOB.steps.destroy();
      }

      if (BCOB.submission && typeof BCOB.submission.destroy === "function") {
        BCOB.submission.destroy();
      }

      this.activeForm = null;
    }

    price(amount = 0) {
      const settings = BCOB.price_settings;

      amount = Number(amount || 0);

      let formatted = amount.toFixed(settings.decimals);

      let [integer, decimal] = formatted.split(".");

      integer = integer.replace(
        /\B(?=(\d{3})+(?!\d))/g,
        settings.thousand_separator,
      );

      formatted = integer;

      if (settings.decimals > 0) {
        formatted += settings.decimal_separator + decimal;
      }

      if (settings.trim_zeros && settings.decimals > 0) {
        formatted = formatted.replace(
          new RegExp("\\" + settings.decimal_separator + "0+$"),
          "",
        );
      }

      switch (settings.currency_position) {
        case "left":
          return settings.currency + formatted;

        case "left_space":
          return settings.currency + " " + formatted;

        case "right":
          return formatted + settings.currency;

        case "right_space":
          return formatted + " " + settings.currency;

        default:
          return settings.currency + formatted;
      }
    }
  }

  $(function () {
    BCOB.frontend = new Frontend();

    if ($("#bcob-form").length) {
      BCOB.frontend.init();
    }
  });
})(jQuery);
