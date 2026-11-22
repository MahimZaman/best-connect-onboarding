class CouponManager {
  constructor() {
    this.$wrapper = $(".bcob-coupon");

    if (!this.$wrapper.length) {
      return;
    }

    this.$input = this.$wrapper.find(".bcob-coupon__input");
    this.$button = this.$wrapper.find(".bcob-coupon__apply");
    this.$message = this.$wrapper.find(".bcob-coupon__message");

    if (!Array.isArray(BCOB.checkout.discount)) {
      BCOB.checkout.discount = [];
    }

    this.bind();
    this.render();
  }

  bind() {
    this.$button.on("click", () => {
      if (this.hasCoupon()) {
        this.removeCoupon();
      } else {
        this.applyCoupon();
      }
    });
  }

  hasCoupon() {
    return this.$button.data("coupon") !== undefined;
  }

  loading(state) {
    this.$button.prop("disabled", state);

    if (state) {
      this.originalText = this.$button.text();
      this.$button.html('<span class="bcob-loader"></span>');
    } else {
      this.$button.text(this.originalText);
    }
  }

  getSessionId() {
    const key = "bcob_coupon_session_id";

    try {
      let sessionId = window.sessionStorage.getItem(key) || "";

      if (!sessionId) {
        if (window.crypto?.randomUUID) {
          sessionId = window.crypto.randomUUID();
        } else {
          sessionId =
            "bcob-" +
            Date.now().toString(36) +
            "-" +
            Math.random().toString(36).slice(2);
        }

        window.sessionStorage.setItem(key, sessionId);
      }

      return sessionId;
    } catch (error) {
      return "";
    }
  }

  applyCoupon() {
    const code = this.$input.val().trim();

    if (!code.length) {
      this.showMessage("Please enter a coupon.", "error");
      return;
    }

    this.loading(true);

    $.ajax({
      url: BCOBCouponLocals.ajax_url,
      type: "POST",
      dataType: "json",
      data: {
        action: "bcob_apply_coupon",
        nonce: BCOBCouponLocals.nonce,
        coupon: code,
        subtotal: BCOB.checkout.subtotal,
        form_id: Number(BCOB.form?.id || BCOB.membership?.form || 0),
        session_id: this.getSessionId(),
      },
    })
      .done((response) => {
        this.loading(false);

        if (!response.success) {
          this.showMessage("Unable to apply coupon.", "error");
          $(".bcob-coupon__message").removeClass("success");
          $(".bcob-coupon__input").removeClass("success");
          $(".bcob-coupon__message").addClass("error");
          $(".bcob-coupon__input").addClass("error");
          return;
        }

        const coupon = response.data;

        BCOB.checkout.discount.push({
          code: coupon.code,
          amount: Number(coupon.amount),
          message: response.message,
        });

        this.$button.text("Entfernen").data("coupon", coupon.code);

        this.$input.prop("readonly", true);

        $(".bcob-coupon__message").addClass("success");
        $(".bcob-coupon__input").addClass("success");
        $(".bcob-coupon__message").removeClass("error");
        $(".bcob-coupon__input").removeClass("error");

        this.render();

        const discountTotal = (BCOB.checkout.discount || []).reduce(
          (total, item) => total + Number(item.amount || 0),
          0,
        );

        if (window.BCOBGoogleTags) {
          BCOBGoogleTags.event("discount_applied", {
            discount_code: coupon.code,
            value: Math.max(
              0,
              Number(BCOB.checkout.subtotal || 0) - discountTotal,
            ),
            currency: BCOB.currency,
          });
        }

        BCOB.submission.collect();
      })
      .fail(() => {
        this.loading(false);

        this.showMessage("Unable to apply coupon.", "error");

        $(".bcob-coupon__message").removeClass("success");

        $(".bcob-coupon__input").removeClass("success");

        $(".bcob-coupon__message").addClass("error");

        $(".bcob-coupon__input").addClass("error");
      });
  }

  removeCoupon() {
    const code = this.$button.data("coupon");

    BCOB.checkout.discount = BCOB.checkout.discount.filter((item) => {
      return item.code !== code;
    });

    this.$button.removeData("coupon").text("Anwenden");

    this.$input.prop("readonly", false).val("");

    $(".bcob-coupon__message").removeClass("success");
    $(".bcob-coupon__input").removeClass("success");
    $(".bcob-coupon__message").removeClass("error");
    $(".bcob-coupon__input").removeClass("error");

    this.render();

    BCOB.submission.collect();
  }

  render() {
    if (!BCOB.checkout.discount.length) {
      this.$message.empty();
      return;
    }

    const html = BCOB.checkout.discount.map((item) => {
      return `
        <div class="bcob-coupon__item">
            ${item.message}
        </div>
    `;
    });

    this.$message.html(html.join(""));
  }

  showMessage(message, type = "") {
    this.$message.html(`<div class="bcob-coupon__${type}">${message}</div>`);
  }
}

window.BCOBCoupon = new CouponManager();
