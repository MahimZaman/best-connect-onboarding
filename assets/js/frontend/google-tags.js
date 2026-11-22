(function ($, window) {
  "use strict";

  window.dataLayer = window.dataLayer || [];

  const GoogleTags = {
    context: {},

    gtm_loaded: false,
    ga4_loaded: false,

    setContext: function (data) {
      if (!$.isPlainObject(data)) {
        return;
      }

      ["form_id", "form_name", "transaction_id"].forEach((key) => {
        if (Object.prototype.hasOwnProperty.call(data, key)) {
          this.context[key] = data[key];
        }
      });
    },

    getConfig: function () {
      return window.BCOBTracking || {};
    },

    getGlobalParams: function () {
      const form = window.BCOB?.form || {};
      const context = this.context || {};

      const formType = String(form.settings?.form_type || "").trim();

      let customerType = null;

      if (formType === "b2c") {
        customerType = "b2c";
      } else if (formType === "b2b") {
        customerType = "b2b";
      } else if (formType === "both") {
        customerType = null;
      }

      return {
        form_id: form.id ?? context.form_id ?? null,
        form_name: form.title ?? context.form_name ?? null,
        customer_type: customerType,
      };
    },

    getCurrentStep: function () {
      const current = Number(window.BCOB?.steps?.current || 1);
      const steps = Array.isArray(window.BCOB?.form?.steps)
        ? window.BCOB.form.steps
        : [];

      const step = steps[current - 1] || {};

      return {
        step_number: current,
        step_name: step.title || "",
      };
    },

    getTransactionId: function () {
      if (window.BCOB?.checkout?.transaction_id) {
        return window.BCOB.checkout.transaction_id;
      }

      if (this.context.transaction_id) {
        return this.context.transaction_id;
      }

      let transactionId = "";

      try {
        transactionId =
          window.sessionStorage.getItem("bcob_tracking_transaction_id") || "";
      } catch (error) {
        transactionId = "";
      }

      if (!transactionId) {
        if (window.crypto?.randomUUID) {
          transactionId = window.crypto.randomUUID();
        } else {
          transactionId =
            "bcob-" +
            Date.now().toString(36) +
            "-" +
            Math.random().toString(36).slice(2);
        }

        try {
          window.sessionStorage.setItem(
            "bcob_tracking_transaction_id",
            transactionId,
          );
        } catch (error) {
          // Session storage is optional.
        }
      }

      if (window.BCOB?.checkout) {
        window.BCOB.checkout.transaction_id = transactionId;
      }

      this.context.transaction_id = transactionId;

      return transactionId;
    },

    consentAllowed: function () {
      const config = this.getConfig();

      if (config.enabled === false) {
        return false;
      }

      if (window.BCOB?.form?.settings?.enable_tracking === false) {
        return false;
      }

      if (!config.consent_gating) {
        return true;
      }

      const condition = String(config.consent_condition || "").trim();

      if (!condition) {
        return false;
      }

      try {
        return Boolean(
          Function('"use strict"; return Boolean(' + condition + ");")(),
        );
      } catch (error) {
        console.error("[BCOB GTM] Consent check failed.", error);
        return false;
      }
    },

    loadGTM: function () {
      const config = this.getConfig();
      const containerId = String(config.gtm_container_id || "").trim();

      if (!containerId || this.gtm_loaded) {
        return;
      }

      window.dataLayer = window.dataLayer || [];

      window.dataLayer.push({
        "gtm.start": new Date().getTime(),
        event: "gtm.js",
      });

      const script = document.createElement("script");
      script.async = true;
      script.src =
        "https://www.googletagmanager.com/gtm.js?id=" +
        encodeURIComponent(containerId);

      document.head.appendChild(script);

      this.gtm_loaded = true;
    },

    loadGA4: function () {
      const config = this.getConfig();
      const measurementId = String(config.ga4_measurement_id || "").trim();

      if (!measurementId || this.ga4_loaded) {
        return;
      }

      window.dataLayer = window.dataLayer || [];

      window.gtag =
        window.gtag ||
        function () {
          window.dataLayer.push(arguments);
        };

      const script = document.createElement("script");
      script.async = true;
      script.src =
        "https://www.googletagmanager.com/gtag/js?id=" +
        encodeURIComponent(measurementId);

      document.head.appendChild(script);

      window.gtag("js", new Date());
      window.gtag("config", measurementId);

      this.ga4_loaded = true;
    },

    push: function (event) {
      try {
        if ($.type(event) !== "object") {
          return false;
        }

        if (!event.event || $.trim(event.event) === "") {
          return false;
        }

        this.setContext(event);

        if (!this.consentAllowed()) {
          return false;
        }

        const payload = Object.assign({}, this.getGlobalParams(), event);

        if (payload.step_number === undefined || payload.step_number === null) {
          const step = this.getCurrentStep();
          payload.step_number = step.step_number;
          payload.step_name = step.step_name;
        }

        window.dataLayer.push(payload);

        const config = this.getConfig();

        if (config.gtm_container_id) {
          this.loadGTM();
        } else if (config.ga4_measurement_id) {
          this.loadGA4();

          if (typeof window.gtag === "function") {
            const eventParams = Object.assign({}, payload);
            delete eventParams.event;

            window.gtag(payload.event, eventParams);
          }
        }

        // if (window.console && console.log) {
        //   console.log("[BCOB GTM]", payload);
        // }

        return true;
      } catch (error) {
        //console.error("[BCOB GTM] Failed to push event.", error);

        return false;
      }
    },

    event: function (name, data) {
      try {
        if (typeof name !== "string" || $.trim(name) === "") {
          //console.warn("[BCOB GTM] Invalid event name.", name);
          return false;
        }

        data = $.isPlainObject(data) ? data : {};

        data.event = $.trim(name);

        return this.push(data);
      } catch (error) {
        //console.error("[BCOB GTM] Failed to create event.", error);

        return false;
      }
    },
  };

  window.BCOBGoogleTags = GoogleTags;
})(jQuery, window);
