class BCOBPaymentManager {
  constructor() {
    this.stripe = null;
    this.elements = null;
    this.payment_element = null;
    this.client_secret = null;
    this.payment_signature = null;
    this.tracking_started = false;
  }

  async refresh(amount, currency = "usd", type = "one_time") {
    $("button.bcob-submit").css({
      pointerEvents: "none",
      opacity: 0.6,
    });
    const interval = $("#bcob-payment-element").data("interval") || "month";

    const interval_count = Number(
      $("#bcob-payment-element").data("interval-count") || 1,
    );

    const signature = [
      Number(amount),
      String(currency || "").toLowerCase(),
      String(type || "one_time"),
      String(interval),
      interval_count,
    ].join("|");

    if (
      this.payment_signature === signature &&
      this.payment_element &&
      this.client_secret
    ) {
      return;
    }

    this.payment_signature = signature;

    this.destroy();

    await this.init(amount, currency, type, interval, interval_count);
    $("button.bcob-submit").css({
      pointerEvents: "all",
      opacity: 1,
    });
  }

  async init(
    amount,
    currency = "usd",
    type = "one_time",
    interval = "month",
    interval_count = 1,
  ) {
    try {
      const config = await this.get_config();

      const checkout = {
        ...(BCOB.checkout || {}),
        payment: {
          ...(BCOB.checkout?.payment || {}),
          type,
          interval,
          interval_count,
        },
      };

      const response = await this.prepare_payment(checkout);

      this.client_secret = response.client_secret;

      BCOB.checkout.lead_id = response.lead_id;

      BCOB.checkout.lead_token = response.lead_token;

      BCOB.checkout.payment_return_signature =
        response.payment_return_signature || "";

      BCOB.checkout.payment = {
        ...BCOB.checkout.payment,
        ...response.payment,
        type,
        interval,
        interval_count,
      };

      this.stripe = Stripe(config.publishable_key);

      this.elements = this.stripe.elements({
        clientSecret: this.client_secret,

        appearance: {
          theme: "stripe",

          variables: {
            colorPrimary: "#ff7a00",
            colorText: "#202124",
            colorTextSecondary: "#5f6368",
            colorDanger: "#dc2626",
            colorBackground: "#ffffff",
            borderRadius: "12px",
            spacingUnit: "6px",
            fontFamily: "Inter, system-ui, sans-serif",
            fontSizeBase: "15px",
          },

          rules: {
            ".Block": {
              border: "1px solid #E5E7EB",
              borderRadius: "12px",
              boxShadow: "none",
            },

            ".Tab": {
              borderRadius: "10px",
            },

            ".Input": {
              border: "1px solid #D1D5DB",
              borderRadius: "10px",
              boxShadow: "none",
              padding: "12px",
            },

            ".Input:focus": {
              borderColor: "#ff7a00",
              boxShadow: "0 0 0 3px rgba(255,122,0,.15)",
            },

            ".Label": {
              color: "#202124",
              fontWeight: "600",
            },

            ".Error": {
              color: "#dc2626",
            },
          },
        },
      });

      this.payment_element = this.elements.create("payment", {
        fields: {
          billingDetails: "auto",
        },
      });

      this.payment_element.mount("#bcob-payment-element");

      if (
        !this.tracking_started &&
        window.BCOBGoogleTags &&
        BCOBGoogleTags.getGlobalParams().customer_type === "b2c"
      ) {
        this.tracking_started = true;

        BCOBGoogleTags.event("payment_started", {
          value: Number(amount),

          currency: String(currency || "").toUpperCase(),

          transaction_id: BCOBGoogleTags.getTransactionId(),
        });
      }
    } catch (error) {
      this.show_error(error.message || "Unable to initialize Stripe.");
    }
  }

  async get_config() {
    const response = await jQuery.post(BCOBStripe.ajax_url, {
      action: "bcob_payment_config",

      nonce: BCOBStripe.nonce,
    });

    if (!response.success) {
      throw new Error(response.data.message);
    }

    return response.data;
  }

  async prepare_payment(checkout) {
    const response = await jQuery.post(BCOBStripe.ajax_url, {
      action: "bcob_prepare_payment",

      nonce: BCOBStripe.nonce,

      submission: JSON.stringify(checkout),

      page_url: window.location.href,
    });

    if (!response.success) {
      throw new Error(response.data.message);
    }

    return response.data;
  }

  async confirm() {
    if (!this.stripe || !this.elements || !this.client_secret) {
      this.show_error("Stripe is not ready.");

      return false;
    }

    const submit_result = await this.elements.submit();

    if (submit_result.error) {
      this.show_error(submit_result.error.message);

      return false;
    }

    const return_url = new URL(window.location.href);

    return_url.searchParams.set("bcob_payment_return", "1");

    return_url.searchParams.set(
      "payment_return_signature",
      BCOB.checkout?.payment_return_signature || "",
    );

    const result = await this.stripe.confirmPayment({
      elements: this.elements,

      clientSecret: this.client_secret,

      confirmParams: {
        return_url: return_url.toString(),
      },

      redirect: "if_required",
    });

    if (result.error) {
      await this.mark_failed();

      this.show_error(result.error.message);

      return false;
    }

    const paymentIntentId =
      result.paymentIntent?.id ||
      BCOB.checkout?.payment?.payment_intent_id ||
      "";

    if (paymentIntentId) {
      BCOB.checkout.payment.payment_intent_id = paymentIntentId;

      /*
       * The PaymentIntent may have succeeded in Stripe,
       * but the local payment record can still be pending.
       * Synchronize it before the final form submission.
       */
      const syncResponse = await jQuery.post(BCOBStripe.ajax_url, {
        action: "bcob_sync_payment",

        nonce: BCOBStripe.nonce,

        payment_intent: paymentIntentId,

        lead_token: BCOB.checkout?.lead_token || "",
      });

      if (!syncResponse.success) {
        this.show_error(
          syncResponse.data?.message || "Unable to verify the payment.",
        );

        return false;
      }

      BCOB.checkout.payment.status = syncResponse.data.status;
    }

    return result;
  }

  async mark_failed() {
    const payment_intent = BCOB.checkout?.payment?.payment_intent_id;

    if (!payment_intent) {
      return;
    }

    try {
      await jQuery.post(BCOBStripe.ajax_url, {
        action: "bcob_payment_failed",

        nonce: BCOBStripe.nonce,

        payment_intent,

        lead_token: BCOB.checkout?.lead_token || "",
      });
    } catch (error) {
      // Stripe webhook remains authoritative.
    }
  }

  clear_error() {
    jQuery("#bcob-payment-error").empty();
  }

  show_error(message) {
    jQuery("#bcob-payment-error").html(message);
  }

  destroy() {
    if (this.payment_element) {
      this.payment_element.destroy();

      this.payment_element = null;
    }

    this.elements = null;
    this.stripe = null;
    this.client_secret = null;
  }
}

window.BCOBPaymentManager = BCOBPaymentManager;
