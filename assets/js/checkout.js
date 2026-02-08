(function ($) {
  "use strict";

  function formatPrice(amount) {
    const num = parseFloat(amount) || 0;
    return "৳ " + num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
  }

  function unique(arr) {
    return Array.from(new Set(arr.filter(Boolean)));
  }

  function normalizePhoneValue(value) {
    let v = (value || "").replace(/\D/g, "");
    if (v.startsWith("880")) v = "0" + v.slice(3);
    if (v.length && !v.startsWith("0")) v = "0" + v;
    if (v.length > 11) v = v.slice(0, 11);
    return v;
  }

  function initContainer($container) {
    const config = {
      product_id: parseInt($container.data("product-id"), 10) || 0,
      delivery: {
        dhaka: parseFloat($container.data("delivery-dhaka")) || 70,
        outside: parseFloat($container.data("delivery-outside")) || 130,
      },
      charge_payload: $container.data("charge-payload") || "",
      charge_hash: $container.data("charge-hash") || "",
      enable_quantity: parseInt($container.data("enable-quantity"), 10) === 1,
      whatsapp: String($container.data("whatsapp-number") || "").trim(),
    };

    const state = {
      product: null,
      variations: [],
      attributeMeta: {}, // slug -> {label, options}
      selectedAttributes: {}, // slug -> option
      selectedVariation: null,
      selectedDelivery: "dhaka",
      qty: 1,
      isLoading: false,
    };

    const $priceValue = $container.find(".rpc-price-value");
    const $totalPrice = $container.find(".rpc-total-price");
    const $deliveryChargeLabel = $container.find(".rpc-delivery-charge");
    const $variationInfo = $container.find(".rpc-variation-info");
    const $attrsWrap = $container.find(".rpc-attributes-wrapper");
    const $qtyWrap = $container.find(".rpc-qty-wrapper");
    const $qtyInput = $container.find(".rpc-qty-input");
    const $qtyLabelRow = $container.find(".rpc-qty-row");
    const $qtyLabel = $container.find(".rpc-qty-label");
    const $form = $container.find(".rpc-order-form");
    const $submitBtn = $container.find(".rpc-submit-btn");
    const $message = $container.find(".rpc-message");
    const $successActions = $container.find(".rpc-success-actions");

    function setQty(q) {
      let qty = parseInt(q, 10);
      if (isNaN(qty) || qty < 1) qty = 1;
      if (qty > 20) qty = 20;
      state.qty = qty;
      $qtyInput.val(qty);
      $qtyLabel.text(String(qty));
      updateTotal();
    }

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

    function setSubmitEnabled(enabled) {
      $submitBtn.prop("disabled", !enabled || state.isLoading);
    }

    function getBasePrice() {
      if (state.selectedVariation && state.selectedVariation.price)
        return parseFloat(state.selectedVariation.price);
      if (state.product && state.product.price)
        return parseFloat(state.product.price);
      return 1200;
    }

    function updateTotal() {
      const base = getBasePrice();
      const delivery =
        config.delivery[state.selectedDelivery] || config.delivery.dhaka;
      const total = base * state.qty + delivery;

      $priceValue.text(formatPrice(base));
      $deliveryChargeLabel.text("৳ " + delivery);
      $totalPrice.text(formatPrice(total));
    }

    function renderAttributesUI() {
      // If simple product or no attributes, hide wrapper
      const attrSlugs = Object.keys(state.attributeMeta);
      if (attrSlugs.length === 0) {
        $attrsWrap.html("");
        return;
      }

      // Build grid
      const html = attrSlugs
        .map((slug) => {
          const meta = state.attributeMeta[slug] || {};
          const label = meta.label || slug;
          return `
            <div class="rpc-form-group">
              <label class="rpc-form-label">${label}</label>
              <select class="rpc-form-input rpc-attr-select" data-attr="${slug}">
                <option value="">নির্বাচন করুন</option>
              </select>
            </div>
          `;
        })
        .join("");

      $attrsWrap.html(`<div class="rpc-attributes-grid">${html}</div>`);
      updateAllAttributeOptions();
    }

    function variationMatchesSelection(variation, selection) {
      if (!variation.attributes) return false;
      for (const slug in selection) {
        const expected = selection[slug];
        if (!expected) continue;

        const found = variation.attributes.find(
          (a) => String(a.name).toLowerCase() === String(slug).toLowerCase(),
        );
        if (!found) return false;
        if (String(found.option) !== String(expected)) return false;
      }
      return true;
    }

    function findExactVariation() {
      const slugs = Object.keys(state.attributeMeta);
      if (slugs.length === 0) return null;

      // all selected?
      for (const s of slugs) {
        if (!state.selectedAttributes[s]) return null;
      }

      return (
        state.variations.find((v) =>
          variationMatchesSelection(v, state.selectedAttributes),
        ) || null
      );
    }

    function getAllowedOptionsForAttr(attrSlug) {
      // Allowed options given other selected attrs
      const otherSelection = { ...state.selectedAttributes };
      delete otherSelection[attrSlug];

      const matches = state.variations.filter((v) =>
        variationMatchesSelection(v, otherSelection),
      );

      const options = [];
      matches.forEach((v) => {
        const a = (v.attributes || []).find(
          (x) =>
            String(x.name).toLowerCase() === String(attrSlug).toLowerCase(),
        );
        if (a && a.option) options.push(a.option);
      });

      return unique(options);
    }

    function updateAllAttributeOptions() {
      const $selects = $container.find(".rpc-attr-select");

      $selects.each(function () {
        const $sel = $(this);
        const slug = String($sel.data("attr") || "");
        const allowed = getAllowedOptionsForAttr(slug);

        const current = $sel.val();
        $sel.empty();
        $sel.append(`<option value="">নির্বাচন করুন</option>`);
        allowed.forEach((opt) => {
          $sel.append(`<option value="${opt}">${opt}</option>`);
        });

        // Keep selected if still allowed
        if (current && allowed.includes(current)) {
          $sel.val(current);
        } else {
          $sel.val("");
          state.selectedAttributes[slug] = "";
        }
      });

      // Determine variation
      const exact = findExactVariation();
      if (exact) {
        state.selectedVariation = exact;
        $variationInfo.show().text(buildSelectionText());
        setSubmitEnabled(true);
      } else {
        state.selectedVariation = null;

        // If partial selection leads to zero matches, show hint
        const anyMatches = state.variations.some((v) =>
          variationMatchesSelection(v, state.selectedAttributes),
        );
        if (
          !anyMatches &&
          Object.values(state.selectedAttributes).some(Boolean)
        ) {
          $variationInfo
            .show()
            .text("এই কম্বিনেশনটি পাওয়া যাচ্ছে না / স্টক নেই");
          setSubmitEnabled(false);
        } else {
          $variationInfo.hide();
          setSubmitEnabled(true);
        }
      }

      updateTotal();
    }

    function buildSelectionText() {
      const pairs = [];
      Object.keys(state.attributeMeta).forEach((slug) => {
        const meta = state.attributeMeta[slug] || {};
        const label = meta.label || slug;
        const val = state.selectedAttributes[slug];
        if (val) pairs.push(`${label}: ${val}`);
      });
      return pairs.length ? `নির্বাচিত: ${pairs.join(", ")}` : "";
    }

    function setupEvents() {
      // Delivery click
      $container.on("click", ".rpc-delivery-option", function () {
        $container.find(".rpc-delivery-option").removeClass("selected");
        $(this).addClass("selected");
        state.selectedDelivery = $(this).data("value") || "dhaka";
        updateTotal();
      });

      // Dynamic attribute change
      $container.on("change", ".rpc-attr-select", function () {
        const slug = String($(this).data("attr") || "");
        state.selectedAttributes[slug] = $(this).val();
        updateAllAttributeOptions();
      });

      // Qty events
      $container.on("click", ".rpc-qty-plus", function () {
        setQty(state.qty + 1);
      });
      $container.on("click", ".rpc-qty-minus", function () {
        setQty(state.qty - 1);
      });
      $qtyInput.on("input", function () {
        setQty($(this).val());
      });

      // Phone normalize
      $container.find(".rpc-phone").on("input", function () {
        $(this).val(normalizePhoneValue($(this).val()));
      });

      // Submit
      $form.on("submit", submitOrder);
    }

    function useFallbackSimple() {
      // If product cannot load, keep minimal form with qty and totals
      $attrsWrap.html("");
      state.variations = [];
      state.attributeMeta = {};
      state.selectedAttributes = {};
      state.selectedVariation = null;
      updateTotal();
    }

    function buildAttributeMetaFromPayload(payload) {
      const meta = {};

      // 1) From payload.attributes (label + slug + options)
      (payload.attributes || []).forEach((a) => {
        const slug = String(a.slug || "").toLowerCase();
        if (!slug) return;
        meta[slug] = {
          label: a.label || slug,
          options: a.options || [],
        };
      });

      // 2) Ensure slugs that exist in variations are present
      const slugsFromVars = {};
      (payload.variations || []).forEach((v) => {
        (v.attributes || []).forEach((x) => {
          const s = String(x.name || "").toLowerCase();
          if (!s) return;
          slugsFromVars[s] = true;
        });
      });

      Object.keys(slugsFromVars).forEach((slug) => {
        if (!meta[slug]) {
          meta[slug] = { label: slug, options: [] };
        }
      });

      // Fill options from variations (only in-stock variations are returned by PHP)
      Object.keys(meta).forEach((slug) => {
        const opts = [];
        (payload.variations || []).forEach((v) => {
          const a = (v.attributes || []).find(
            (x) => String(x.name).toLowerCase() === slug,
          );
          if (a && a.option) opts.push(a.option);
        });
        meta[slug].options = unique(opts);
      });

      return meta;
    }

    function loadProduct() {
      if (config.product_id <= 0) return useFallbackSimple();

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
            state.product = res.data.product || null;
            state.variations = res.data.variations || [];

            // Simple product: remove attribute UI
            if (
              !state.product ||
              state.product.type !== "variable" ||
              state.variations.length === 0
            ) {
              $attrsWrap.html("");
              state.attributeMeta = {};
              state.selectedAttributes = {};
              state.selectedVariation = null;
              updateTotal();
              hideLoading();
              return;
            }

            state.attributeMeta = buildAttributeMetaFromPayload(res.data);

            // initialize selection state
            state.selectedAttributes = {};
            Object.keys(state.attributeMeta).forEach(
              (slug) => (state.selectedAttributes[slug] = ""),
            );

            renderAttributesUI();
            updateTotal();
          } else {
            useFallbackSimple();
          }
          hideLoading();
        },
        error: function () {
          useFallbackSimple();
          hideLoading();
        },
      });
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

      const phone = $phone.val().trim();
      if (phone && !/^01[3-9]\d{8}$/.test(phone)) {
        err($phone, "সঠিক মোবাইল নম্বর দিন");
      }

      // If variable product, require attribute selection
      if (state.product && state.product.type === "variable") {
        const slugs = Object.keys(state.attributeMeta);
        slugs.forEach((slug) => {
          if (!state.selectedAttributes[slug]) {
            err(
              $container.find(`.rpc-attr-select[data-attr="${slug}"]`),
              "নির্বাচন করুন",
            );
          }
        });

        if (!state.selectedVariation) {
          ok = false;
          $variationInfo.show().text("দয়া করে সঠিক কম্বিনেশন নির্বাচন করুন");
        }
      }

      return ok;
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
        delivery_zone: state.selectedDelivery,
        quantity: state.qty,

        // signed charge info
        charge_payload: config.charge_payload,
        charge_hash: config.charge_hash,
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

          // success actions
          $successActions.html("");
          if (config.whatsapp) {
            const wa = config.whatsapp.replace(/\D/g, "");
            const waText = encodeURIComponent(
              `Order: ${res.data.order_number}`,
            );
            $successActions
              .html(
                `<a class="rpc-wa-btn" target="_blank" rel="noopener" href="https://wa.me/${wa}?text=${waText}">WhatsApp Support</a>`,
              )
              .show();
          }

          // Redirect to Thank You page (optional)
          if (res.data && res.data.success_redirect && res.data.thankyou_url) {
            setTimeout(() => {
              window.location.href = res.data.thankyou_url;
            }, 800);
            return;
          }

          // Reset
          $form[0].reset();
          state.qty = 1;
          $qtyInput.val(1);
          $qtyLabel.text("1");
          if (state.product && state.product.type === "variable") {
            Object.keys(state.selectedAttributes).forEach(
              (k) => (state.selectedAttributes[k] = ""),
            );
            $container.find(".rpc-attr-select").val("");
            updateAllAttributeOptions();
          }
          $variationInfo.hide();
          updateTotal();
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

    // Boot UX toggles
    if (config.enable_quantity) {
      $qtyWrap.show();
      $qtyLabelRow.show();
    }

    setupEvents();
    loadProduct();
    updateTotal();
    setQty(1);
  }

  $(document).ready(function () {
    $(".rpc-checkout-container").each(function () {
      initContainer($(this));
    });
  });
})(jQuery);
