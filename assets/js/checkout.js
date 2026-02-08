(function ($) {
  "use strict";

  function formatPrice(amount) {
    const num = parseFloat(amount) || 0;
    return "৳ " + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function updateSelect($select, options, placeholder) {
    $select.empty();
    $select.append(`<option value="">${placeholder}</option>`);
    options.forEach((opt) => {
      if (opt) $select.append(`<option value="${opt}">${opt}</option>`);
    });
  }

  function initContainer($container) {
    const config = {
      product_id: parseInt($container.data("product-id"), 10) || 0,
      delivery: {
        dhaka: parseFloat($container.data("delivery-dhaka")) || 70,
        outside: parseFloat($container.data("delivery-outside")) || 130,
      },
    };

    const state = {
      product: null,
      variations: [],
      selectedVariation: null,
      selectedDelivery: "dhaka",
      isLoading: false,
    };

    const $priceValue = $container.find(".rpc-price-value");
    const $totalPrice = $container.find(".rpc-total-price");
    const $deliveryChargeLabel = $container.find(".rpc-delivery-charge");
    const $colorSelect = $container.find(".rpc-color-select");
    const $sizeSelect = $container.find(".rpc-size-select");
    const $variationInfo = $container.find(".rpc-variation-info");
    const $form = $container.find(".rpc-order-form");
    const $submitBtn = $container.find(".rpc-submit-btn");
    const $message = $container.find(".rpc-message");

    function showLoading(text) {
      $submitBtn
        .prop("disabled", true)
        .html(`<span class="rpc-loading"></span> ${text}`);
    }

    function hideLoading() {
      $submitBtn.prop("disabled", false).text("অর্ডার সম্পন্ন করুন");
    }

    function showMessage(text, type) {
      $message.removeClass("success error").addClass(type).text(text).show();
      setTimeout(() => $message.hide(), 10000);
    }

    function updateTotal() {
      let total = 0;

      if (state.selectedVariation && state.selectedVariation.price) {
        total += parseFloat(state.selectedVariation.price);
      } else if (state.product && state.product.price) {
        total += parseFloat(state.product.price);
      } else {
        total += 1200;
      }

      total += config.delivery[state.selectedDelivery] || config.delivery.dhaka;

      $totalPrice.text(formatPrice(total));
      $deliveryChargeLabel.text(
        "৳ " +
          (config.delivery[state.selectedDelivery] || config.delivery.dhaka),
      );
    }

    function useFallbackData() {
      updateSelect(
        $colorSelect,
        ["লাল", "নীল", "কালো", "সাদা", "সবুজ"],
        "রং নির্বাচন করুন",
      );
      updateSelect(
        $sizeSelect,
        ["S", "M", "L", "XL", "XXL"],
        "সাইজ নির্বাচন করুন",
      );
      $priceValue.text("৳ ১,২০০");
      updateTotal();
    }

    function updateVariationSelects(variations) {
      const colors = new Set();
      const sizes = new Set();

      variations.forEach((v) => {
        if (!v.attributes) return;

        v.attributes.forEach((a) => {
          const name = (a.name || "").toLowerCase();
          if (name.includes("color") || name.includes("pa_color"))
            colors.add(a.option);
          if (name.includes("size") || name.includes("pa_size"))
            sizes.add(a.option);
        });
      });

      updateSelect($colorSelect, Array.from(colors), "রং নির্বাচন করুন");
      updateSelect($sizeSelect, Array.from(sizes), "সাইজ নির্বাচন করুন");
    }

    function updateProductInfo(payload) {
      const price =
        payload.product.price || payload.product.regular_price || "1200";
      $priceValue.text(formatPrice(price));

      state.variations = payload.variations || [];

      if (state.variations.length > 0) {
        updateVariationSelects(state.variations);
      } else if (payload.attributes && payload.attributes.length > 0) {
        // fallback attributes
        payload.attributes.forEach((attr) => {
          const label = (attr.label || "").toLowerCase();
          const slug = (attr.slug || "").toLowerCase();
          if (label.includes("color") || slug.includes("color")) {
            updateSelect($colorSelect, attr.options || [], "রং নির্বাচন করুন");
          }
          if (label.includes("size") || slug.includes("size")) {
            updateSelect($sizeSelect, attr.options || [], "সাইজ নির্বাচন করুন");
          }
        });
      } else {
        useFallbackData();
      }

      updateTotal();
    }

    function loadProduct() {
      if (config.product_id <= 0) return useFallbackData();

      showLoading("পণ্য লোড হচ্ছে...");

      $.ajax({
        url: rpc_ajax.ajax_url,
        type: "POST",
        dataType: "json",
        data: {
          action: "rpc_get_product",
          nonce: rpc_ajax.nonce,
          product_id: config.product_id,
        },
        success: function (res) {
          if (res && res.success && res.data) {
            state.product = res.data.product;
            updateProductInfo(res.data);
          } else {
            useFallbackData();
          }
          hideLoading();
        },
        error: function () {
          useFallbackData();
          hideLoading();
        },
      });
    }

    function findVariation() {
      const color = ($colorSelect.val() || "").trim();
      const size = ($sizeSelect.val() || "").trim();

      if (!color || !size) {
        state.selectedVariation = null;
        $variationInfo.hide();
        updateTotal();
        return;
      }

      const match = state.variations.find((v) => {
        if (!v.attributes) return false;

        let hasColor = false;
        let hasSize = false;

        v.attributes.forEach((a) => {
          const name = (a.name || "").toLowerCase();
          if (
            (name.includes("color") || name.includes("pa_color")) &&
            a.option === color
          )
            hasColor = true;
          if (
            (name.includes("size") || name.includes("pa_size")) &&
            a.option === size
          )
            hasSize = true;
        });

        return hasColor && hasSize;
      });

      if (match) {
        state.selectedVariation = match;
        $priceValue.text(formatPrice(match.price));
        $variationInfo.show().text(`নির্বাচিত: ${color}, ${size}`);
      } else {
        state.selectedVariation = null;
        $variationInfo.hide();
      }

      updateTotal();
    }

    function resetForm() {
      $form[0].reset();
      $colorSelect.val("");
      $sizeSelect.val("");
      $variationInfo.hide();
      state.selectedVariation = null;
      state.selectedDelivery = "dhaka";
      updateTotal();
    }

    function validateForm() {
      let ok = true;

      $container.find(".rpc-form-input").removeClass("error");
      $container.find(".rpc-error").remove();

      function err($el, msg) {
        $el.addClass("error");
        $el.after(
          `<div class="rpc-error" style="color:#dc2626;font-size:14px;margin-top:5px;">${msg}</div>`,
        );
        ok = false;
      }

      const $name = $container.find(".rpc-first-name");
      const $phone = $container.find(".rpc-phone");
      const $addr = $container.find(".rpc-address");

      if (!$name.val().trim()) err($name, "নাম আবশ্যক");
      if (!$phone.val().trim()) err($phone, "মোবাইল নম্বর আবশ্যক");
      if (!$addr.val().trim()) err($addr, "ঠিকানা আবশ্যক");

      // only require color/size if selects have options beyond placeholder
      if ($colorSelect.find("option").length > 1 && !$colorSelect.val())
        err($colorSelect, "রং নির্বাচন করুন");
      if ($sizeSelect.find("option").length > 1 && !$sizeSelect.val())
        err($sizeSelect, "সাইজ নির্বাচন করুন");

      const phone = $phone.val().trim();
      if (phone && !/^01[3-9]\d{8}$/.test(phone)) {
        err($phone, "সঠিক মোবাইল নম্বর দিন");
      }

      return ok;
    }

    function normalizePhone($input) {
      let value = ($input.val() || "").replace(/\D/g, "");
      if (value.startsWith("880")) value = "0" + value.slice(3);
      if (!value.startsWith("0") && value.length) value = "0" + value;
      if (value.length > 11) value = value.substring(0, 11);
      $input.val(value);
    }

    async function submitOrder(e) {
      e.preventDefault();
      if (state.isLoading) return;
      if (!validateForm()) return;

      const orderData = {
        first_name: $container.find(".rpc-first-name").val().trim(),
        phone: $container.find(".rpc-phone").val().trim(),
        address: $container.find(".rpc-address").val().trim(),
        email: $container.find(".rpc-email").val().trim(),
        product_id: config.product_id,
        variation_id: state.selectedVariation ? state.selectedVariation.id : 0,

        // IMPORTANT: send only delivery zone (server computes charge)
        delivery_zone: state.selectedDelivery,
      };

      try {
        state.isLoading = true;
        showLoading("অর্ডার তৈরি হচ্ছে...");

        const res = await $.ajax({
          url: rpc_ajax.ajax_url,
          method: "POST",
          dataType: "json",
          data: {
            action: "rpc_create_order",
            nonce: rpc_ajax.nonce,
            ...orderData,
          },
        });

        if (res && res.success) {
          showMessage(
            `🎉 অর্ডার সফল হয়েছে! অর্ডার নম্বর: ${res.data.order_number}`,
            "success",
          );
          resetForm();
        } else {
          const msg =
            res && res.data && res.data.message
              ? res.data.message
              : "Order failed";
          throw new Error(msg);
        }
      } catch (err) {
        showMessage(`অর্ডার জমা দিতে সমস্যা: ${err.message}`, "error");
      } finally {
        state.isLoading = false;
        hideLoading();
      }
    }

    // Events (scoped)
    $colorSelect.on("change", findVariation);
    $sizeSelect.on("change", findVariation);

    $container.on("click", ".rpc-delivery-option", function () {
      $container.find(".rpc-delivery-option").removeClass("selected");
      $(this).addClass("selected");
      state.selectedDelivery = $(this).data("value") || "dhaka";
      updateTotal();
    });

    $container.find(".rpc-phone").on("input", function () {
      normalizePhone($(this));
    });

    $form.on("submit", submitOrder);

    // Boot
    loadProduct();
    updateTotal();
  }

  $(document).ready(function () {
    $(".rpc-checkout-container").each(function () {
      initContainer($(this));
    });
  });
})(jQuery);
