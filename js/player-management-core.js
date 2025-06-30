(function ($) {
  // Dependency checks
  if (typeof $ === "undefined") {
    console.error("InterSoccer: jQuery is not loaded. Player management disabled.");
    return;
  }
  if (
    !window.intersoccerPlayer ||
    !intersoccerPlayer.ajax_url ||
    !intersoccerPlayer.nonce
  ) {
    console.error("InterSoccer: intersoccerPlayer data not initialized. Player management disabled.");
    return;
  }

  // Check for container
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
  const isAdmin = intersoccerPlayer.is_admin === "1";
  const debugEnabled = intersoccerPlayer.debug === "1";

  // Toggle Add Attendee section
  $container.on("click", ".toggle-add-player", function (e) {
    e.preventDefault();
    const $section = $(".add-player-section");
    const isVisible = $section.hasClass("active");
    $section.toggleClass("active", !isVisible);
    $(this).attr("aria-expanded", !isVisible);
    if (!isVisible) {
      $("#player_first_name").focus();
    } else {
      $(
        ".add-player-section input, .add-player-section select, .add-player-section textarea"
      ).val("");
      $(".add-player-section .error-message").hide();
    }
  });

  // Cancel Add
  $container.on("click", ".cancel-add", function (e) {
    e.preventDefault();
    $(".add-player-section").removeClass("active");
    $(".toggle-add-player").attr("aria-expanded", "false").focus();
    $(
      ".add-player-section input, .add-player-section select, .add-player-section textarea"
    ).val("");
    $(".add-player-section .error-message").hide();
  });

  // Validation
  window.intersoccerValidateRow = function ($row, isAdd = false) {
    let isValid = true;
    $row.find(".error-message").hide();
    const $medicalRow = isAdd
      ? $row.next(".add-player-medical")
      : $row.next(
          `.medical-row[data-player-index="${$row.data("player-index")}"]`
        );

    const $userId = $row.find('[name="player_user_id"]');
    const $firstName = $row.find('[name="player_first_name"]');
    const $lastName = $row.find('[name="player_last_name"]');
    const $dobDay = $row.find('[name="player_dob_day"]');
    const $dobMonth = $row.find('[name="player_dob_month"]');
    const $dobYear = $row.find('[name="player_dob_year"]');
    const $gender = $row.find('[name="player_gender"]');
    const $avsNumber = $row.find('[name="player_avs_number"]');
    const $medical = isAdd
      ? $medicalRow.find('[name="player_medical"]')
      : $medicalRow.find('[name="player_medical"]');

    const userId = $userId?.val()?.trim();
    const firstName = $firstName?.val()?.trim();
    const lastName = $lastName?.val()?.trim();
    const dobDay = $dobDay?.val();
    const dobMonth = $dobMonth?.val();
    const dobYear = $dobYear?.val();
    const gender = $gender?.val();
    const avsNumber = $avsNumber?.val()?.trim();
    const medical = (
      $medicalRow?.length
        ? $medicalRow.find('[name="player_medical"]').val()
        : ""
    )?.trim();

    if (isAdmin && isAdd && (!userId || userId <= 0)) {
      $userId.next(".error-message").text("Valid user ID required.").show();
      isValid = false;
    }
    if (
      !firstName ||
      firstName.length > 50 
    ) {
      $firstName
        .next(".error-message")
        .text("Valid first name required (max 50 chars, letters only).")
        .show();
      isValid = false;
    }
    if (
      !lastName ||
      lastName.length > 50
    ) {
      $lastName
        .next(".error-message")
        .text("Valid last name required (max 50 chars, letters only).")
        .show();
      isValid = false;
    }
    if (isAdd || (dobDay && dobMonth && dobYear)) {
      const dob = `${dobYear}-${dobMonth}-${dobDay}`;
      const dobDate = new Date(dob);
      const serverDate = new Date(intersoccerPlayer.server_time);
      if (isNaN(dobDate.getTime()) || dobDate > serverDate) {
        $dobDay.next(".error-message").text("Invalid date of birth.").show();
        isValid = false;
      } else {
        const age =
          serverDate.getFullYear() -
          dobDate.getFullYear() -
          (serverDate.getMonth() < dobDate.getMonth() ||
          (serverDate.getMonth() === dobDate.getMonth() &&
            serverDate.getDate() < dobDate.getDate())
            ? 1
            : 0);
        if (age < 2 || age > 13) {
          $dobDay
            .next(".error-message")
            .text("Player must be 2-13 years old.")
            .show();
          isValid = false;
        }
      }
    }
    if (isAdd || gender) {
      if (!["male", "female", "other"].includes(gender)) {
        $gender.next(".error-message").text("Invalid gender selection.").show();
        isValid = false;
      }
    }
    if (avsNumber && avsNumber.length < 6) {
      $avsNumber
        .next(".error-message")
        .text("Valid AVS number required.")
        .show();
      isValid = false;
    }
    if (medical && medical.length > 500) {
      $medical
        .next(".error-message")
        .text("Medical conditions must be under 500 chars.")
        .show();
      isValid = false;
    }

    return isValid;
  };

  // Refresh nonce (one retry)
  window.intersoccerRefreshNonce = function () {
    if (debugEnabled) {
      console.log("InterSoccer: Refreshing nonce");
    }
    return new Promise((resolve, reject) => {
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
