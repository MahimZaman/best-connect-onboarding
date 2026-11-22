$ = jQuery;

window.BCOB.advancedOptionsBuilder = {
  init(container, options) {
    container = $(container);

    if (!Array.isArray(options)) {
      options = [];
    }

    this.render(container, options);
  },

  render(container, options) {
    let html = '<div class="bcob-options-list">';

    options.forEach((option) => {
      html += this.createOption(option);
    });

    html += "</div>";

    html += `
            <button type="button" class="button button-primary bcob-add-option">
                Add Option
            </button>
        `;

    container.html(html);

    container.find(".bcob-options-list").sortable({
      handle: ".bcob-option-drag",
      items: "> .bcob-option-item",
      containment: "parent",
      axis: "y",
      tolerance: "pointer",
      placeholder: "bcob-sort-placeholder",
      forcePlaceholderSize: true,
    });
  },

  createOption(option = {}) {
    option = {
      image: option.image || "",
      heading: option.heading || "",
      description: option.description || "",
    };

    return `
            <div class="bcob-option-item">

                <span class="bcob-option-drag">
                    <i class="ri-draggable"></i>
                </span>

                <div class="bcob-option-image-wrap">

                    <div
                        class="bcob-option-image"
                        data-id="${option.image}">

                        ${
                          option.image
                            ? `<img src="${option.image}" alt="">`
                            : `<span>Select Image</span>`
                        }

                    </div>

                    <button
                        type="button"
                        class="bcob-remove-image">

                        <i class="ri-close-line"></i>

                    </button>

                </div>
                <div class="bcob-option-content">
                    <input
                        type="text"
                        class="widefat bcob-option-heading"
                        placeholder="Heading"
                        value="${option.heading}">

                    <textarea
                        class="widefat bcob-option-description"
                        placeholder="Description">${option.description}</textarea>
                </div>

                <button
                    type="button"
                    class="button bcob-remove-option">

                    Remove

                </button>

            </div>
        `;
  },

  get(container) {
    const options = [];

    $(container)
      .find(".bcob-option-item")
      .each(function () {
        const row = $(this);

        options.push({
          image: row.find(".bcob-option-image").attr("data-id") || "",

          heading: row.find(".bcob-option-heading").val(),

          description: row.find(".bcob-option-description").val(),
        });
      });

    return options;
  },
};

$(document)
  .on("click", ".bcob-add-option", function () {
    const container = $(this).closest(".bcob-advanced-options-builder");

    container
      .find(".bcob-options-list")
      .append(window.BCOB.advancedOptionsBuilder.createOption());
  })

  .on("click", ".bcob-remove-option", function () {
    $(this).closest(".bcob-option-item").remove();
  });

$(document).on("click", ".bcob-option-image", function () {
  const box = $(this);

  const frame = wp.media({
    title: "Select Image",

    multiple: false,

    library: {
      type: "image",
    },
  });

  frame.on("select", function () {
    const attachment = frame.state().get("selection").first().toJSON();

    box.attr("data-id", attachment.url);

    box.html(`<img src="${attachment.url}">`);
    box.closest(".bcob-option-item").find("input").trigger("change");
  });

  frame.open();
});

$(document).on("click", ".bcob-remove-image", function (e) {
  e.stopPropagation();

  const box = $(this).siblings(".bcob-option-image");

  box.removeAttr("data-id");

  box.html("<span>Select Image</span>");
});
