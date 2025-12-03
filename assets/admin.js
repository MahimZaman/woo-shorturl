jQuery(document).ready(function ($) {
  $("#wsc_product_select").on("change", function () {
    const selected = $(this).find("option:selected");
    const productId = selected.val();
    const productType = selected.data("type");

    if (productType === "variable") {
      $("#wsc_variation_wrapper").show().html("Loading...");

      $.post(
        ajaxurl,
        {
          action: "wsc_get_variations",
          product_id: productId,
        },
        function (response) {
          if (response.success) {
            const options = response.data.map(
              (v) => `<option value="${v.id}">${v.label}</option>`
            );
            $("#wsc_variation_wrapper").html(`
                        <label><strong>Select Variation:</strong></label><br>
                        <select name="wsc_variation" id="wsc_variation_select" required>
                            ${options.join("")}
                        </select>
                    `);
          } else {
            $("#wsc_variation_wrapper").hide();
          }
        }
      );
    } else {
      $("#wsc_variation_wrapper").hide();
    }
  });

  $(".copy-url").on("click", function () {
    const input = $(this).prev("input")[0];
    input.select();
    input.setSelectionRange(0, 99999); // For mobile
    navigator.clipboard.writeText(input.value).then(function () {
      alert("Copied to clipboard!");
    });
  });
});
