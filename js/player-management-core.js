"use strict";
(function ($) {
  if (typeof $ === "undefined") {
    console.error("InterSoccer: jQuery is not loaded. Player management disabled.");
    return;
  }

  const $container = $(".intersoccer-player-management");
  if (!$container.length) {
    console.error("InterSoccer: Player management container (.intersoccer-player-management) not found.");
    return;
  }

  const $table = $("#player-table");
  const $message = $container.find(".intersoccer-message");
  window.intersoccerState = window.intersoccerState || {};
  intersoccerState.isProcessing = false;
  intersoccerState.isAdding = false;
  intersoccerState.editingIndex = null;
  intersoccerState.nonceRetryAttempted = false;
  intersoccerState.lastClickTime = 0;
  intersoccerState.clickDebounceMs = 300;
  intersoccerState.lastEditClickTime = 0;
  intersoccerState.editClickDebounceMs = 500;
  const isAdmin = window.intersoccerPlayer ? intersoccerPlayer.is_admin === "1" : false;
  const debugEnabled = window.intersoccerPlayer ? intersoccerPlayer.debug === "1" : false;

  if (!window.intersoccerPlayer || !intersoccerPlayer.ajax_url || !intersoccerPlayer.nonce) {
    if (window.console && console.debug) {
      console.debug("InterSoccer: player-management-core skipping initialization; intersoccerPlayer data missing.", window.intersoccerPlayer || {});
    }
    return;
  }

  // Validation moved to player-management.js for consolidation
  // Use window.intersoccerValidateRow from there

  // Toggle Add Attendee section
  $container.on("click", ".toggle-add-player", function (e) {
    e.preventDefault();  // Ensure no scroll
    e.stopPropagation();
    const $section = $(".add-player-section");
    const $medicalSection = $(".add-player-medical");
    const isVisible = $section.hasClass("active");

    if (!isVisible) {
        $section.addClass("active").show();
        $medicalSection.addClass("active").show();
        $("#player_first_name").focus();
    } else {
        $section.removeClass("active").hide();
        $medicalSection.removeClass("active").hide();
        $(".add-player-section input, .add-player-section select, .add-player-section textarea").val("");
        $(".add-player-section .error-message").hide();
    }

    $(this).attr("aria-expanded", !isVisible);
    if (debugEnabled) console.log("InterSoccer: Toggled add player section, visible:", !isVisible);
  });

  // Cancel Add
  $container.on("click", ".cancel-add", function (e) {
    e.preventDefault();
    $(".add-player-section").removeClass("active");
    $(".toggle-add-player").attr("aria-expanded", "false").focus();
    $(".add-player-section input, .add-player-section select, .add-player-section textarea").val("");
    $(".add-player-section .error-message").hide();
    if (debugEnabled) console.log("InterSoccer: Canceled add player");
  });

  // Refresh nonce (one retry)
  window.intersoccerRefreshNonce = function () {
    if (debugEnabled) {
      console.log("InterSoccer: Refreshing nonce");
    }
    return new Promise((resolve, reject) => {
      if (!intersoccerPlayer || !intersoccerPlayer.nonce_refresh_url) {
        reject(new Error("InterSoccer: Cannot refresh nonce, intersoccerPlayer or nonce_refresh_url missing"));
        return;
      }
      $.ajax({
        url: intersoccerPlayer.nonce_refresh_url,
        type: "POST",
        data: { action: "intersoccer_refresh_nonce" },
        success: (response) => {
          if (response.success && response.data.nonce) {
            intersoccerPlayer.nonce = response.data.nonce;
            if (debugEnabled) {
              console.log("InterSoccer: Nonce refreshed:", intersoccerPlayer.nonce);
            }
            resolve();
          } else {
            console.error("InterSoccer: Nonce refresh failed:", response);
            reject(
              new Error(
                "Nonce refresh failed: " +
                  (response.data?.message || "Unknown error")
              )
            );
          }
        },
        error: (xhr) => {
          console.error(
            "InterSoccer: Nonce refresh error:",
            xhr.status,
            xhr.responseText
          );
          reject(new Error("Nonce refresh error: " + xhr.statusText));
        },
      });
    });
  };
})(jQuery);