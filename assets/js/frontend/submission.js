class BCOBSubmission {
  constructor() {
    BCOB.checkout = {
      values: {},
      products: [],
      subtotal: 0,
      tax: 0,
      discount: 0,
      amount: 0,
      membership: {
        id: Number(BCOB.membership?.id || 0),
        portal_index: Number(BCOB.membership?.portal_index || 0),
        form: Number(BCOB.membership?.form || BCOB.form?.id || 0),
        context_signature: String(BCOB.membership?.context_signature || ""),
      },
      payment: {
        provider: "stripe",
        type: $("#bcob-payment-element").data("type"),
        interval: $("#bcob-payment-element").data("interval"),
        interval_count: Number(
          $("#bcob-payment-element").data("interval-count") || 1,
        ),
        payment_intent_id: "",
        subscription_id: "",
        customer_id: "",
        price_id: "",
        product_id: "",
        invoice_id: "",
        response: {},
      },
      currency: BCOB.currency,
      form: {
        id: BCOB.form?.id,
      },
    };
  }

  init() {
    this.collect();
    this.bindEvents();
  }

  bindEvents() {
    $(document)
      .off("submit.bcobSubmission", "#bcob-form-element")
      .on(
        "submit.bcobSubmission",
        "#bcob-form-element",
        this.onboard.bind(this),
      );
  }

  destroy() {
    $(document).off("submit.bcobSubmission", "#bcob-form-element");
  }

  async submit() {
    return await $.post(BCOB.ajax_url, {
      action: "bcob_submit",

      nonce: BCOB.nonce,

      submission: JSON.stringify(BCOB.checkout),
      page_url: window.location.href,
    });
  }

  lock() {
    const $button = $("#bcob-submit");

    $button.prop("disabled", true);
    $button.addClass("loading");

    this.showLoading();
  }

  unlock() {
    const $button = $("#bcob-submit");

    $button.prop("disabled", false);
    $button.removeClass("loading");

    this.hideLoading();
  }

  showLoading() {
    if ($("#bcob-submission-loader").length) {
      $("#bcob-submission-loader").addClass("is-active");
      return;
    }

    const loader = `
    <div
      id="bcob-submission-loader"
      class="bcob-submission-loader is-active"
      role="status"
      aria-live="polite"
      aria-busy="true"
    >
      <div class="bcob-submission-loader__box">
        <div class="bcob-submission-loader__spinner"></div>

        <div class="bcob-submission-loader__title">
          Ihre Angaben werden verarbeitet
        </div>

        <div class="bcob-submission-loader__text">
          Bitte warten Sie einen Moment. Ihr Antrag wird erstellt.
        </div>
      </div>
    </div>
  `;

    $("body").append(loader);

    $("body").addClass("bcob-submission-is-loading");

    this.injectLoaderStyles();
  }

  hideLoading() {
    const $loader = $("#bcob-submission-loader");

    if (!$loader.length) {
      $("body").removeClass("bcob-submission-is-loading");
      return;
    }

    $loader.removeClass("is-active");

    setTimeout(() => {
      $loader.remove();
      $("body").removeClass("bcob-submission-is-loading");
    }, 200);
  }

  injectLoaderStyles() {
    if ($("#bcob-submission-loader-styles").length) {
      return;
    }

    const styles = `
    <style id="bcob-submission-loader-styles">
      body.bcob-submission-is-loading {
        overflow: hidden;
      }

      .bcob-submission-loader {
        position: fixed;
        inset: 0;
        z-index: 999999;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: rgba(0, 0, 0, 0.55);
        opacity: 0;
        visibility: hidden;
        transition:
          opacity 0.2s ease,
          visibility 0.2s ease;
      }

      .bcob-submission-loader.is-active {
        opacity: 1;
        visibility: visible;
      }

      .bcob-submission-loader__box {
        width: min(420px, 100%);
        padding: 36px 30px;
        background: #fff;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
      }

      .bcob-submission-loader__spinner {
        width: 42px;
        height: 42px;
        margin: 0 auto 22px;
        border: 4px solid #e5e5e5;
        border-top-color: currentColor;
        border-radius: 50%;
        animation: bcobSubmissionSpin 0.8s linear infinite;
      }

      .bcob-submission-loader__title {
        margin-bottom: 8px;
        font-size: 20px;
        font-weight: 600;
      }

      .bcob-submission-loader__text {
        font-size: 14px;
        line-height: 1.5;
        opacity: 0.7;
      }

      @keyframes bcobSubmissionSpin {
        to {
          transform: rotate(360deg);
        }
      }
    </style>
  `;

    $("head").append(styles);
  }

  success(data) {
    if (BCOB.frontend) {
      BCOB.frontend.submissionCompleted = true;
    }
    if (BCOB.storage) {
      BCOB.storage.clear();
    }
    if (
      data.lead_id &&
      Array.isArray(data.contracts) &&
      data.contracts.length &&
      window.BCOBPDFReview
    ) {
      BCOBPDFReview.open({
        lead_id: data.lead_id,
        lead_token: data.lead_token || "",
        contracts: data.contracts,
      });

      this.unlock();
      return;
    }

    if (data.redirect) {
      window.location.href = data.redirect;
      return;
    }

    location.reload();
  }

  error(message) {
    alert(message);
  }

  async handlePaymentReturn() {
    const url = new URL(window.location.href);

    if (url.searchParams.get("bcob_payment_return") !== "1") {
      return;
    }

    const paymentIntent = url.searchParams.get("payment_intent");

    if (!paymentIntent) {
      return;
    }

    this.lock();

    try {
      const response = await $.post(BCOBStripe.ajax_url, {
        action: "bcob_payment_return",

        nonce: BCOBStripe.nonce,

        payment_intent: paymentIntent,

        payment_return_signature:
          url.searchParams.get("payment_return_signature") || "",
      });

      if (!response.success) {
        this.error(
          response.data?.message ||
            "Die Zahlung konnte nicht bestätigt werden.",
        );

        this.unlock();
        return;
      }

      if (response.data?.processing) {
        this.error(
          response.data.message || "Ihre Zahlung wird noch verarbeitet.",
        );

        this.unlock();
        return;
      }

      /*
       * Remove the Stripe return parameters
       * before continuing with the existing
       * portal/thank-you URL.
       */
      if (response.data?.redirect) {
        window.location.href = response.data.redirect;

        return;
      }

      if (
        response.data?.lead_id &&
        Array.isArray(response.data.contracts) &&
        response.data.contracts.length &&
        window.BCOBPDFReview
      ) {
        BCOBPDFReview.open({
          lead_id: response.data.lead_id,

          lead_token: response.data.lead_token || "",

          contracts: response.data.contracts,
        });

        this.unlock();
      }
    } catch (error) {
      this.error(
        error.message || "Die Zahlung konnte nicht verarbeitet werden.",
      );

      this.unlock();
    }
  }

  async onboard(e) {
    e.preventDefault();

    this.lock();

    try {
      const checkout = await this.collect();

      if (!checkout) {
        this.unlock();
        return;
      }

      const paymentHidden = $("#bcob-payment-element")
        .closest(".bcob-field")
        .data("hidden");

      if ($("#bcob-payment-element").length && !paymentHidden) {
        const payment = await this.processPayment();

        if (!payment) {
          this.unlock();
          return;
        }

        checkout.payment.response = payment;
      }

      const response = await this.submit();

      if (!response.success) {
        this.error(response.data.message);

        this.unlock();
        return;
      }

      if (window.BCOBGoogleTags) {
        const paymentIntentId =
          checkout.payment?.payment_intent_id ||
          checkout.payment?.response?.paymentIntent?.id ||
          "";

        BCOBGoogleTags.event("signup_completed", {
          value: Number(checkout.total || 0),

          currency: String(
            checkout.currency || BCOB.currency || "",
          ).toUpperCase(),

          transaction_id:
            response.data?.transaction_id ||
            response.data?.lead_id ||
            paymentIntentId ||
            BCOBGoogleTags.getTransactionId(),
        });
      }

      this.success(response.data);
    } catch (error) {
      this.error(
        error.message || "Die Übermittlung konnte nicht abgeschlossen werden.",
      );

      this.unlock();
    }
  }

  async collect() {
    if (!window.BCOB) return false;

    const $form = $("#bcob-form");

    if (!$form.length) return false;

    const values = {};

    let valid = true;

    const self = this;

    $form.find(":input[name]").each(function () {
      const $input = $(this);
      const $wrapper = $input.closest(".bcob-field");

      if ($input.is(":disabled")) {
        return;
      }

      let name = String($input.attr("name") || "").replace(/\[\]$/, "");

      if (!name) {
        return;
      }

      const field = self.getField(name);

      if ($input.is(":checkbox")) {
        if (!$input.is(":checked")) {
          return;
        }

        const checkboxValue = String(
          $input.attr("data-value") || $input.val() || "",
        ).trim();

        if (!Array.isArray(values[name])) {
          values[name] = [];
        }

        if (checkboxValue !== "" && !values[name].includes(checkboxValue)) {
          values[name].push(checkboxValue);
        }

        return;
      }

      if ($input.is(":radio")) {
        if (!$input.is(":checked")) {
          return;
        }

        const value = $input.val();

        if (BCOB.validator?.validateField) {
          valid = BCOB.validator.validateField($wrapper);

          if (!valid) {
            return false;
          }
        }

        values[name] = {
          key: name,
          label: field?.label || name,
          type: field?.type || "",
          value,
        };

        return;
      }

      if ($input.is(":file")) {
        const file = $input[0].files?.[0];

        if (!file) {
          values[name] = {
            key: name,
            label: field?.label || name,
            type: field?.type || "",
            value: "",
          };
          return;
        }

        if (BCOB.validator?.validateField) {
          valid = BCOB.validator.validateField($wrapper);

          if (!valid) {
            return false;
          }
        }

        values[name] = {
          key: name,
          label: field?.label || name,
          type: field?.type || "",
          value: file.name,
        };

        return;
      }

      const value = $input.val();

      if (BCOB.validator?.validateField) {
        valid = BCOB.validator.validateField($wrapper);

        if (!valid) {
          return false;
        }
      }

      values[name] = {
        key: name,
        label: field?.label || name,
        type: field?.type || "",
        value,
      };
    });

    if (!valid) {
      return false;
    }

    Object.keys(values).forEach(function (name) {
      if (!Array.isArray(values[name])) {
        return;
      }

      const field = self.getField(name);

      values[name] = {
        key: name,
        label: field?.label || name,
        type: field?.type || "",
        value: values[name],
      };
    });

    BCOB.checkout.values = values;

    this.collectProducts();

    const discount = (BCOB.checkout.discount || []).reduce((total, item) => {
      return total + Number(item.amount || 0);
    }, 0);

    BCOB.checkout.discount_total = discount;

    BCOB.checkout.total = Math.max(
      0,
      Number(BCOB.checkout.subtotal || 0) - discount,
    );

    BCOB.orderReview?.render();

    await this.refreshPaymentIfVisible();

    return BCOB.checkout;
  }

  async refreshPaymentIfVisible() {
    const $payment = $("#bcob-payment-element");

    if (!$payment.length) {
      return;
    }

    /*
     * Payment Element can exist inside a hidden
     * form step. Only initialize/refresh it when
     * its step is actually visible.
     */
    if (!$payment.is(":visible") || $payment.closest(":hidden").length) {
      return;
    }

    const payment = BCOB.checkout?.payment || {};

    if (payment.provider !== "stripe") {
      return;
    }

    await BCOB.payment.refresh(
      BCOB.checkout.total,
      BCOB.currency,
      payment.type,
    );
  }

  async processPayment() {
    const payment = BCOB.checkout.payment || {};

    if (payment.provider !== "stripe") {
      return true;
    }

    return await BCOB.payment.confirm(payment.type);
  }

  getField(key) {
    if (!BCOB.form?.steps) {
      return null;
    }

    key = String(key).replace(/\[\]$/, "");

    for (const step of BCOB.form.steps) {
      if (!Array.isArray(step.fields)) {
        continue;
      }

      const field = step.fields.find((f) => f.id === key);

      if (field) {
        return field;
      }
    }

    return null;
  }

  collectProducts() {
    const memberships = Array.isArray(BCOB.membership.items)
      ? BCOB.membership.items
      : [];

    let subtotal = 0;

    BCOB.checkout.products = memberships.map((item) => {
      const price = Number(item.price ?? 0);

      subtotal += price;

      return {
        ...item,
        price,
      };
    });

    BCOB.checkout.subtotal = subtotal;
    BCOB.checkout.amount =
      subtotal + (BCOB.checkout.tax || 0) - (BCOB.checkout.discount || 0);
  }
}

window.BCOBSubmission = BCOBSubmission;
