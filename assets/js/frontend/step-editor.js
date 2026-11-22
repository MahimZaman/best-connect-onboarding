$ = jQuery;

window.BCOB = window.BCOB || {};

BCOB.StepEditor = {
  eventsBound: false,

  init() {
    this.bindEvents();
    this.refresh();
  },

  refresh() {
    const values = BCOB.checkout?.values || {};
    const $membership = $(`
    <div class="bcob-step-summary bcob-step-summary--membership">
        <div class="bcob-step-summary__header">
            <div class="bcob-step-summary__title">Anmeldung als</div>

            <button type="button" class="bcob-step-summary__edit-membership">
                Bearbeiten
            </button>
        </div>

        <div class="bcob-step-summary__card">
          <div class="bcob-step-summary__row">
              <div class="bcob-step-summary__label">Anmeldung als</div>

              <div class="bcob-step-summary__value">
                  ${BCOB.membership?.title || ""}
              </div>
          </div>
        </div>
    </div>
`);

    $(".bcob-step-editor").find(".bcob-step-summary--membership").remove();

    $(".bcob-step-editor").prepend($membership);

    $(".bcob-step-summary")
      .not(".bcob-step-summary--membership")
      .each(function () {
        const $section = $(this);

        let hasValues = false;

        $section.find(".bcob-step-summary__row").each(function () {
          const $row = $(this);

          const key = $row.data("field");

          const field = values[key];

          if (!field) {
            $row.hide();
            return;
          }

          let value = field.value;

          if (Array.isArray(value)) {
            value = value.join(", ");
          }

          if (value === null || value === undefined) {
            value = "";
          }

          value = String(value).trim();

          if (value === "") {
            $row.hide();
            return;
          }

          hasValues = true;

          $row.find(".bcob-step-summary__label").text(field.label);
          $row.find(".bcob-step-summary__value").text(value);

          $row.show();
        });

        $section.toggle(hasValues);
      });
  },

  bindEvents() {
    if (this.eventsBound) {
      return;
    }

    this.eventsBound = true;

    $(document).on("click", ".bcob-step-summary__edit", function () {
      const step = Number($(this).data("step"));
      BCOB.steps.goto(step);
    });
  },
};
