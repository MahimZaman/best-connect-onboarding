class OrderReview {
  constructor() {
    this.render();
  }

  render() {
    const checkout = BCOB.checkout;

    let html = `
            <div class="bcob-summary__row">
                <div class="bcob-summary__label bcob-summary__label--accent">
                    IHR PAUSCHALHONORAR
                </div>
            </div>
        `;

    if (checkout.products.length) {
      checkout.products.forEach((product) => {
        html += `
                <div class="bcob-summary__row">
                    <div class="bcob-summary__label">
                        ${product.name}
                    </div>

                    <div class="bcob-summary__value">
                        ${BCOB.price(product.price)}
                    </div>
                </div>
            `;
      });
    }

    if (checkout.discount.length) {
      checkout.discount.forEach((discount) => {
        html += `
                <div class="bcob-summary__row">
                    <div class="bcob-summary__label">
                        ${discount.message}
                    </div>

                    <div class="bcob-summary__value">
                        - ${BCOB.price(discount.amount)}
                    </div>
                </div>
            `;
      });
    }

    html += `
            <div class="bcob-summary__divider"></div>

            <div class="bcob-summary__row bcob-summary__row--total">
                <div class="bcob-summary__label">
                    Heute zu zahlen
                </div>

                <div class="bcob-summary__total">
                    ${BCOB.price(checkout.total)}
                </div>
            </div>
        `;

    $(".bcob-summary").html(html);
    $(".bcob-pay-today__price").html(BCOB.price(checkout.total));
  }
}
