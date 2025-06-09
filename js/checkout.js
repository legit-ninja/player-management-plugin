/**
 * File: checkout.js
 * Description: Manages client-side interactions on InterSocker product pages, injecting player and day selection fields into the WC variations form for ICZ’s booking system.
 * Purpose: Enhances the booking experience by dynamically adding player selection and day selection UI elements on product pages.
 * Alignment with Booking Systems:
 *  - ICZ-Specific: Injects custom fields for player and day selection, critical for ICZ’s Camp and Course booking flow.
 *  - Generic Booking Systems: Reusable for event booking systems needing dynamic form field injection on product pages.
 * Dependencies: jQuery, WC
 * Updates (2025-05-15, 23:45 EDT):
 *  - Added client-side login check before fetching players.
 *  - Limited total fetch attempts to prevent infinite loop.
 *  - Ensured loop breaks definitively after max attempts.
 *  - Kept WC session readiness check and nonce refresh.
 * Testing:
 *  - Verify player fetch only proceeds if user is logged in.
 *  - Confirm total fetch attempts are capped and loop stops.
 *  - Check player selection is populated (e.g., Lukas Jr Mueller).
 *  - Ensure no 400 errors in console.
 */

jQuery(document).ready(function ($) {
  console.log("InterSocker: Document ready, preparing to inject fields");

  // Flags and settings
  let isInjecting = false;
  let injectionAttempt = 0;
  let fetchAttempt = 0;
  const maxInjections = 3;
  const maxFetchAttempts = 2; // Total attempts (initial + 1 retry)
  let observer = null;
  const debounceDelay = 2000;

  // Refresh nonce before making AJAX calls
  function refreshNonce(callback) {
    $.ajax({
      url: intersockerCheckout.ajax_url,
      type: "POST",
      data: {
        action: "intersocker_refresh_nonce",
      },
      success: function (response) {
        if (response.success && response.data.nonce) {
          intersockerCheckout.nonce = response.data.nonce;
          console.log(
            "InterSocker: Nonce refreshed:",
            intersockerCheckout.nonce
          );
          if (callback) callback();
        } else {
          console.error("InterSocker: Failed to refresh nonce:", response);
          if (callback) callback(new Error("Nonce refresh failed"));
        }
      },
      error: function (xhr) {
        console.error("InterSocker: Error refreshing nonce:", xhr.responseText);
        if (callback) callback(new Error("Nonce refresh error"));
      },
    });
  }

  // Check if user is logged in client-side
  function checkUserLogin(callback) {
    $.ajax({
      url: intersockerCheckout.ajax_url,
      type: "POST",
      data: {
        action: "intersocker_check_user_login",
      },
      success: function (response) {
        if (response.success && response.data.is_logged_in) {
          console.log("InterSocker: User is logged in client-side");
          callback(true);
        } else {
          console.log("InterSocker: User not logged in client-side");
          callback(false);
        }
      },
      error: function (xhr) {
        console.error(
          "InterSocker: Error checking user login:",
          xhr.responseText
        );
        callback(false);
      },
    });
  }

  function fetchPlayers($form, $playerSelection) {
    fetchAttempt++;
    console.log(
      `InterSocker: Fetching players for logged-in user, attempt ${fetchAttempt}/${maxFetchAttempts}`
    );
    refreshNonce((error) => {
      if (error) {
        console.error(
          "InterSocker: Aborting fetch due to nonce refresh failure"
        );
        retryFetchPlayers($form, $playerSelection);
        return;
      }
      console.log(
        "InterSocker: Sending AJAX with nonce:",
        intersockerCheckout.nonce
      );
      $.ajax({
        url: intersockerCheckout.ajax_url,
        type: "POST",
        data: {
          action: "intersocker_get_player_details",
          nonce: intersockerCheckout.nonce,
        },
        success: function (response) {
          if (response.success && response.data.players) {
            const $playerSelect = $playerSelection.find(".player-select");
            if ($playerSelect.length) {
              $playerSelect.empty();
              $playerSelect.append(
                $("<option>").val("").text("Select a player")
              );
              response.data.players.forEach((player, index) => {
                if (player.first_name && player.last_name) {
                  $playerSelect.append(
                    $("<option>")
                      .val(index)
                      .text(`${player.first_name} ${player.last_name}`)
                  );
                }
              });
              console.log(
                "InterSocker: Populated player dropdown with",
                response.data.players.length,
                "players"
              );
              $playerSelect.data("fetched", true);
              $form.data("players-fetched", true);
              // Disconnect observer after successful population
              if (observer) {
                observer.disconnect();
                console.log(
                  "InterSocker: MutationObserver disconnected after successful player fetch"
                );
              }
            } else {
              console.error(
                "InterSocker: Player select element not found after fetch"
              );
              displayErrorMessage("Player selection UI not found.");
            }
          } else {
            console.error(
              "InterSocker: Failed to fetch players:",
              response.data?.message || "Unknown error"
            );
            retryFetchPlayers($form, $playerSelection);
          }
        },
        error: function (xhr) {
          console.error(
            "InterSocker: AJAX error fetching players:",
            xhr.status,
            xhr.responseText || "Undefined error"
          );
          retryFetchPlayers($form, $playerSelection);
        },
        complete: function () {
          isInjecting = false;
          if (injectionAttempt >= maxInjections && observer) {
            observer.disconnect();
            console.log(
              "InterSocker: MutationObserver disconnected after max injections"
            );
          }
        },
      });
    });
  }

  function retryFetchPlayers($form, $playerSelection) {
    if (fetchAttempt < maxFetchAttempts) {
      console.log(
        `InterSocker: Retrying player fetch, attempt ${fetchAttempt}/${maxFetchAttempts}`
      );
      setTimeout(() => {
        fetchPlayers($form, $playerSelection);
      }, 1000); // Retry after 1 second
    } else {
      console.error("InterSocker: Max fetch attempts reached, stopping");
      displayErrorMessage(
        "Unable to load players after multiple attempts. Please try refreshing the page."
      );
      // Disconnect observer to prevent loop
      if (observer) {
        observer.disconnect();
        console.log(
          "InterSocker: MutationObserver disconnected after max fetch attempts"
        );
      }
    }
  }

  function InjectFields() {
    if (isInjecting) {
      console.log("InterSocker: InjectFields skipped, already injecting");
      return;
    }

    const $form = $("form.variations_form.cart");
    if (!$form.length || $form.hasClass("processing")) {
      console.log(
        "InterSocker: Form not ready or processing, skipping injection"
      );
      isInjecting = false;
      return;
    }

    // Skip if player select exists and is populated
    const $playerSelect = $form.find(
      "#intersocker-player-selection .player-select"
    );
    if ($playerSelect.length && $playerSelect.find("option").length > 1) {
      console.log("InterSocker: Player select populated, skipping injection");
      isInjecting = false;
      return;
    }

    isInjecting = true;
    injectionAttempt++;
    console.log(
      `InterSocker: InjectFields called, attempt ${injectionAttempt}/${maxInjections}`
    );

    const $variationsTable = $form.find("table.variations");
    const $addToCartButton = $form.find("button.single_add_to_cart_button");
    const existingPlayerValue =
      $variationsTable.find(".player-select").val() || "";

    // Inject player selection
    if (!$variationsTable.find("#intersocker-player-selection").length) {
      const $playerRow = $("<tr>")
        .append($("<td>").text("Player Selection").css({ fontWeight: "bold" }))
        .append(
          $("<td>").append(
            $("<div>")
              .attr("id", "intersocker-player-selection")
              .addClass("intersocker-protected")
              .append(
                $("<div>")
                  .attr("id", "intersocker-player-content")
                  .append(
                    $("<select>")
                      .addClass("player-select")
                      .append($("<option>").val("").text("Select a player"))
                      .val(existingPlayerValue)
                  )
              )
          )
        );
      $variationsTable.append($playerRow);
      console.log(
        "InterSocker: Injected player selection into variations table"
      );
    } else {
      console.log(
        "InterSocker: Player selection already exists, skipping injection"
      );
    }

    // Inject day selection for Camps
    if (!$variationsTable.find("#intersocker-day-selection").length) {
      const $dayRow = $("<tr>")
        .css("display", "none")
        .attr("id", "intersocker-day-selection")
        .addClass("intersocker-protected")
        .append($("<td>").text("Day Selection").css({ fontWeight: "bold" }))
        .append(
          $("<td>")
            .append($("<div>").addClass("intersocker-day-checkboxes"))
            .append($("<div>").addClass("error-message"))
        );
      $variationsTable.append($dayRow);
      console.log("InterSocker: Injected day selection into variations table");
    } else {
      console.log(
        "InterSocker: Day selection already exists, skipping injection"
      );
    }

    // Inject notification for full week selection
    if (!$form.find(".intersocker-day-notification").length) {
      $form.append(
        $("<div>").addClass("intersocker-day-notification").css({
          color: "#e63946",
          marginTop: "10px",
        })
      );
    }

    // Inject custom price display
    const $priceContainer = $form.find(".single_variation .price");
    if (
      !$priceContainer.length &&
      !$addToCartButton.prev("#intersocker-price-display").length &&
      !$form.find("#intersocker-price-display").length
    ) {
      console.log(
        "InterSocker: Price container or Add to Cart button not found, appending to form"
      );
      $addToCartButton.before(
        $("<div>")
          .attr("id", "intersocker-price-display")
          .addClass("intersocker-protected")
          .css({ fontSize: "1.2em", color: "#e63946", marginTop: "10px" })
      );
    } else if (
      $priceContainer.length &&
      !$priceContainer.find("#intersocker-price-display").length
    ) {
      $priceContainer.append(
        $("<div>")
          .attr("id", "intersocker-price-display")
          .addClass("intersocker-protected")
          .css({ fontSize: "1.2em", color: "#e63946", marginTop: "10px" })
      );
      console.log(
        "InterSocker: Injected custom price display into price container"
      );
    } else {
      console.log(
        "InterSocker: Custom price display already exists, skipping injection"
      );
    }

    // Disable Add to Cart button by default
    $addToCartButton.prop("disabled", true);
    console.log("InterSocker: Add to Cart button disabled by default");

    // Fetch players for logged-in users
    const isLoggedIn = intersockerCheckout && intersockerCheckout.user_id > 0;
    const $playerSelection = $form.find("#intersocker-player-selection");
    $playerSelection.toggle(isLoggedIn);
    if (isLoggedIn && !$form.data("players-fetched")) {
      checkUserLogin((loggedIn) => {
        if (loggedIn) {
          fetchPlayers($form, $playerSelection);
        } else {
          console.log("InterSocker: User not logged in, skipping player fetch");
          displayErrorMessage("Please log in to view players.");
          isInjecting = false;
          if (observer) {
            observer.disconnect();
            console.log(
              "InterSocker: MutationObserver disconnected due to login failure"
            );
          }
        }
      });
    } else {
      isInjecting = false;
      console.log(
        "InterSocker: Skipping player fetch, user not logged in or already fetched"
      );
    }
  }

  // Display error message to user
  function displayErrorMessage(message) {
    const $errorDiv = $("<div>")
      .addClass("intersocker-error-message")
      .text(message)
      .css({ color: "#e63946", marginTop: "10px" });
    $("#intersocker-player-selection").after($errorDiv);
  }

  // Wait for WC session initialization or fallback to delay
  function initInjection() {
    // Check if WC session is ready (custom event or WC variable)
    if (
      typeof wc_cart_params !== "undefined" ||
      typeof wc_checkout_params !== "undefined"
    ) {
      console.log("InterSocker: WC session ready, initiating injection");
      InjectFields();
    } else {
      console.log("InterSocker: WC session not ready, falling back to delay");
      setTimeout(() => {
        InjectFields();
      }, 5000); // Fallback to 5 seconds
    }
  }

  // Listen for a custom WC event or use fallback
  $(document).on("wc_session_ready", function () {
    console.log("InterSocker: WC session ready event fired");
    initInjection();
  });

  // Fallback to wp_loaded or delay
  $(document).on("wp_loaded", function () {
    console.log("InterSocker: wp_loaded event fired");
    initInjection();
  });

  // Immediate fallback if events don't fire
  setTimeout(() => {
    console.log("InterSocker: Fallback delay reached, initiating injection");
    initInjection();
  }, 5000);

  // Observe DOM changes to re-inject fields if removed (limited attempts)
  const $playerSelection = $("#intersocker-player-selection");
  if ($playerSelection.length && !$("form.cart").data("elementor-observer")) {
    observer = new MutationObserver(
      debounce((mutations) => {
        if (isInjecting || injectionAttempt >= maxInjections) {
          console.log(
            `InterSocker: Skipping MutationObserver (isInjecting: ${isInjecting}, injectionAttempt: ${injectionAttempt}/${maxInjections})`
          );
          if (injectionAttempt >= maxInjections) {
            observer.disconnect();
            console.log(
              "InterSocker: MutationObserver disconnected after max injections"
            );
          }
          return;
        }

        let needsReinjection = false;
        mutations.forEach((mutation) => {
          console.log(
            "InterSocker: Detected DOM change in #intersocker-player-selection:",
            mutation
          );
          const $playerSelect = $playerSelection.find(".player-select");
          if (
            !$playerSelect.length ||
            $playerSelect.find("option").length <= 1
          ) {
            console.log(
              "InterSocker: Player select missing or empty, triggering re-injection"
            );
            needsReinjection = true;
          } else {
            console.log(
              "InterSocker: Player select exists and populated, no re-injection needed"
            );
          }
        });

        if (needsReinjection) {
          console.log(
            `InterSocker: Injection attempt ${injectionAttempt}/${maxInjections}`
          );
          InjectFields();
        }
      }, debounceDelay)
    );

    try {
      observer.observe($playerSelection[0], { childList: true, subtree: true });
      console.log(
        "InterSocker: MutationObserver initialized for #intersocker-player-selection"
      );
    } catch (e) {
      console.error("InterSocker: MutationObserver failed to initialize:", e);
      if (observer) {
        observer.disconnect();
        console.log("InterSocker: MutationObserver disconnected due to error");
      }
    }
  } else {
    console.log(
      "InterSocker: #intersocker-player-selection not found or Elementor observer active, skipping MutationObserver"
    );
  }

  // Debounce function to limit observer frequency
  function debounce(func, wait) {
    let timeout;
    return function (...args) {
      clearTimeout(timeout);
      timeout = setTimeout(() => func.apply(this, args), wait);
    };
  }
});

