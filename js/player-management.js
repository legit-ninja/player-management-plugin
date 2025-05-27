/**
 * File: player-management.js
 * Description: Manages the player management table on the WooCommerce My Account page at /my-account/manage-players/ and admin dashboard at /wp-admin/admin.php?page=intersoccer-players. Handles adding, editing, deleting players, validates inputs (e.g., AVS number, age), manages Medical Conditions field positioning, and supports admin filters (Region, Age-Group, Venue) based on product attributes. Ensures responsive design, accessibility, and secure AJAX handling with nonce refreshing.
 * Dependencies: jQuery (checked)
 */

(function ($) {
  // Dependency checks
  if (typeof $ === "undefined") {
    console.error(
      "InterSoccer: jQuery is not loaded. Player management disabled."
    );
    return;
  }
  if (
    !window.intersoccerPlayer ||
    !intersoccerPlayer.ajax_url ||
    !intersoccerPlayer.nonce
  ) {
    console.error(
      "InterSoccer: intersoccerPlayer data not initialized. Player management disabled."
    );
    return;
  }

  // Check for container
  const $container = $(".intersoccer-player-management");
  if (!$container.length) {
    console.error(
      "InterSoccer: Player management container (.intersoccer-player-management) not found."
    );
    return;
  }

  const $table = $("#player-table");
  const $message = $container.find(".intersoccer-message");
  let isProcessing = false;
  let isAdding = false;
  let editingIndex = null;
  let nonceRetryAttempted = false;
  let lastClickTime = 0;
  const clickDebounceMs = 300;
  const editClickDebounceMs = 500;
  let lastEditClickTime = 0;
  const isAdmin = intersoccerPlayer.is_admin === "1";

  // Log user role for debugging
  $.ajax({
    url: intersoccerPlayer.ajax_url,
    type: "POST",
    data: { action: "intersoccer_get_user_role" },
    success: (response) => {
      console.log(
        "InterSoccer: User role:",
        response.data?.role,
        "User ID:",
        intersoccerPlayer.user_id,
        "Is Admin:",
        isAdmin
      );
    },
    error: (xhr) => {
      console.error(
        "InterSoccer: Failed to fetch user role:",
        xhr.status,
        xhr.responseText
      );
    },
  });

  // Toggle Add Attendee section
  $container.on("click", ".toggle-add-player", function (e) {
    e.preventDefault();
    console.log("InterSoccer: Toggle Add Player clicked");
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
    console.log("InterSoccer: Cancel Add clicked");
    $(".add-player-section").removeClass("active");
    $(".toggle-add-player").attr("aria-expanded", "false").focus();
    $(
      ".add-player-section input, .add-player-section select, .add-player-section textarea"
    ).val("");
    $(".add-player-section .error-message").hide();
  });

  // Validation
  function validateRow($row, isAdd = false) {
    console.log("InterSoccer: Validating row, isAdd:", isAdd);
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

    console.log("InterSoccer: Input elements found:", {
      userId: $userId.length,
      firstName: $firstName.length,
      lastName: $lastName.length,
      dobDay: $dobDay.length,
      dobMonth: $dobMonth.length,
      dobYear: $dobYear.length,
      gender: $gender.length,
      avsNumber: $avsNumber.length,
      medical: $medical.length,
    });

    const userId = $userId.val()?.trim();
    const firstName = $firstName.val().trim();
    const lastName = $lastName.val().trim();
    const dobDay = $dobDay.val();
    const dobMonth = $dobMonth.val();
    const dobYear = $dobYear.val();
    const gender = $gender.val();
    const avsNumber = $avsNumber.val().trim();
    const medical = $medical.length ? $medical.val().trim() : "";

    console.log("InterSoccer: Validation input:", {
      userId,
      firstName,
      lastName,
      dobDay,
      dobMonth,
      dobYear,
      gender,
      avsNumber,
      medical,
    });

    if (isAdmin && isAdd && (!userId || userId <= 0)) {
      $userId.next(".error-message").text("Valid user ID required.").show();
      console.log("InterSoccer: User ID validation failed:", userId);
      isValid = false;
    }
    if (
      !firstName ||
      firstName.length > 50 ||
      !/^[a-zA-Z\s\-\p{L}]+$/.test(firstName)
    ) {
      $firstName
        .next(".error-message")
        .text("Valid first name required (max 50 chars, letters only).")
        .show();
      console.log("InterSoccer: First name validation failed:", firstName);
      isValid = false;
    }
    if (
      !lastName ||
      lastName.length > 50 ||
      !/^[a-zA-Z\s\-\p{L}]+$/.test(lastName)
    ) {
      $lastName
        .next(".error-message")
        .text("Valid last name required (max 50 chars, letters only).")
        .show();
      console.log("InterSoccer: Last name validation failed:", lastName);
      isValid = false;
    }
    if (isAdd || (dobDay && dobMonth && dobYear)) {
      const dob = `${dobYear}-${dobMonth}-${dobDay}`;
      const dobDate = new Date(dob);
      const today = new Date("2025-05-22");
      if (isNaN(dobDate.getTime()) || dobDate > today) {
        $dobDay.next(".error-message").text("Invalid date of birth.").show();
        console.log("InterSoccer: Invalid DOB:", dob);
        isValid = false;
      } else {
        const age =
          today.getFullYear() -
          dobDate.getFullYear() -
          (today.getMonth() < dobDate.getMonth() ||
          (today.getMonth() === dobDate.getMonth() &&
            today.getDate() < dobDate.getDate())
            ? 1
            : 0);
        console.log("InterSoccer: Calculated age:", age);
        if (age < 2 || age > 13) {
          $dobDay
            .next(".error-message")
            .text("Player must be 2-13 years old.")
            .show();
          console.log("InterSoccer: Age out of range:", age);
          isValid = false;
        }
      }
    }
    if (isAdd || gender) {
      if (!["male", "female", "other"].includes(gender)) {
        $gender.next(".error-message").text("Invalid gender selection.").show();
        console.log("InterSoccer: Invalid gender:", gender);
        isValid = false;
      }
    }
    if (avsNumber.length < 6 ) {
      $avsNumber
        .next(".error-message")
        .text("Valid AVS number required.")
        .show();
      console.log("InterSoccer: AVS number validation failed:", avsNumber);
      isValid = false;
    }
    if (medical.length > 500) {
      $medical
        .next(".error-message")
        .text("Medical conditions must be under 500 chars.")
        .show();
      console.log("InterSoccer: Medical conditions too long:", medical.length);
      isValid = false;
    }

    console.log("InterSoccer: Row validation result:", isValid);
    return isValid;
  }

  // Refresh nonce (one retry)
  function refreshNonce() {
    console.log("InterSoccer: Refreshing nonce");
    return new Promise((resolve, reject) => {
      $.ajax({
        url: intersoccerPlayer.nonce_refresh_url,
        type: "POST",
        data: { action: "intersoccer_refresh_nonce" },
        success: (response) => {
          if (response.success && response.data.nonce) {
            intersoccerPlayer.nonce = response.data.nonce;
            console.log(
              "InterSoccer: Nonce refreshed:",
              intersoccerPlayer.nonce
            );
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
  }

  // Apply filters to the table (updated for Region, Age-Group, Venue)
  function applyFilters() {
    const regionFilter = $("#filter-region").val();
    const ageGroupFilter = $("#filter-age-group").val();
    const venueFilter = $("#filter-venue").val();

    console.log(
      "InterSoccer: Applying filters - Region:",
      regionFilter,
      "Age-Group:",
      ageGroupFilter,
      "Venue:",
      venueFilter
    );

    $table.find("tr[data-player-index]").each(function () {
      const $row = $(this);
      const eventRegions = ($row.data("event-regions") || "").split(",");
      const eventAgeGroups = ($row.data("event-age-groups") || "").split(",");
      const eventVenues = ($row.data("event-venues") || "").split(",");

      let showRow = true;

      // Region filter
      if (regionFilter && !eventRegions.includes(regionFilter)) {
        showRow = false;
      }

      // Age-Group filter
      if (ageGroupFilter && !eventAgeGroups.includes(ageGroupFilter)) {
        showRow = false;
      }

      // Venue filter
      if (venueFilter && !eventVenues.includes(venueFilter)) {
        showRow = false;
      }

      $row.toggle(showRow);
    });
  }

  // Initialize filters
  if (isAdmin) {
    $("#filter-region, #filter-age-group, #filter-venue").on(
      "change",
      function () {
        applyFilters();
      }
    );
  }

  // Save player (add or edit)
  function savePlayer($row, isAdd = false) {
    console.log("InterSoccer: savePlayer called, isAdd:", isAdd);
    if (isProcessing) {
      console.log("InterSoccer: Processing, ignoring save");
      return;
    }
    if (isAdd && isAdding) {
      console.log(
        "InterSoccer: Already adding a player, ignoring duplicate save"
      );
      return;
    }
    if (!validateRow($row, isAdd)) {
      console.log("InterSoccer: Validation failed");
      return;
    }

    isProcessing = true;
    if (isAdd) {
      isAdding = true;
      console.log("InterSoccer: Setting isAdding flag to true");
    }
    const $submitLink = $row.find(".player-submit");
    $submitLink
      .addClass("disabled")
      .attr("aria-disabled", "true")
      .find(".spinner")
      .show();

    const index = isAdd ? "-1" : $row.data("player-index");
    const userId =
      isAdmin && isAdd
        ? $row.find('[name="player_user_id"]').val().trim()
        : $row.data("user-id") || intersoccerPlayer.user_id;
    const $medicalRow = isAdd
      ? $row.next(".add-player-medical")
      : $row.next(`.medical-row[data-player-index="${index}"]`);
    const firstName = $row.find('[name="player_first_name"]').val().trim();
    const lastName = $row.find('[name="player_last_name"]').val().trim();
    const dobDay = $row.find('[name="player_dob_day"]').val();
    const dobMonth = $row.find('[name="player_dob_month"]').val();
    const dobYear = $row.find('[name="player_dob_year"]').val();
    const dob =
      dobDay && dobMonth && dobYear ? `${dobYear}-${dobMonth}-${dobDay}` : "";
    const gender = $row.find('[name="player_gender"]').val();
    const avsNumber = $row.find('[name="player_avs_number"]').val().trim();
    const medical = (
      $medicalRow.length
        ? $medicalRow.find('[name="player_medical"]').val()
        : ""
    ).trim();

    const action = isAdd ? "intersoccer_add_player" : "intersoccer_edit_player";
    const data = {
      action: action,
      nonce: intersoccerPlayer.nonce,
      user_id: intersoccerPlayer.user_id,
      player_user_id: userId,
      player_first_name: firstName,
      player_last_name: lastName,
      player_dob: dob,
      player_gender: gender,
      player_avs_number: avsNumber,
      player_medical: medical,
      is_admin: isAdmin ? "1" : "0",
    };
    if (!isAdd) {
      data.player_index = index;
    }

    console.log(
      "InterSoccer: Sending AJAX request:",
      action,
      "Nonce:",
      intersoccerPlayer.nonce,
      "Data:",
      data
    );

    $.ajax({
      url: intersoccerPlayer.ajax_url,
      type: "POST",
      data: data,
      success: (response) => {
        console.log("InterSoccer: AJAX success:", response);
        if (response.success) {
          $message.text(response.data.message).show();
          setTimeout(() => $message.hide(), 10000);
          const player = response.data.player;

          if (isAdd) {
            // Add new row
            $table.find(".no-players").remove();
            const newIndex = $table.find("tr[data-player-index]").length;
            $table.find(`tr[data-player-index="${newIndex}"]`).remove();
            const existingPlayer = $table
              .find("tr[data-player-index]")
              .filter(function () {
                return (
                  $(this).find(".display-first-name").text() ===
                    player.first_name &&
                  $(this).find(".display-last-name").text() ===
                    player.last_name &&
                  $(this).find(".display-dob").text() === player.dob
                );
              });
            if (existingPlayer.length) {
              console.log(
                "InterSoccer: Duplicate player data detected, removing and re-adding:",
                player.first_name,
                player.last_name
              );
              existingPlayer.remove();
            }
            console.log(
              "InterSoccer: DOM state before insertion, row count:",
              $table.find("tr[data-player-index]").length
            );
            const $newRow = $(`
              <tr data-player-index="${newIndex}" 
                  data-user-id="${player.user_id || userId}" 
                  data-first-name="${player.first_name || "N/A"}" 
                  data-last-name="${player.last_name || "N/A"}" 
                  data-dob="${player.dob || "N/A"}" 
                  data-gender="${player.gender || "N/A"}" 
                  data-avs-number="${player.avs_number || "N/A"}"
                  data-event-count="${player.event_count || 0}"
                  data-region="${
                    $row.find(".display-region").text() || "Unknown"
                  }"
                  data-creation-timestamp="${player.creation_timestamp || ""}"
                  data-event-regions=""
                  data-event-age-groups=""
                  data-event-venues="">
                  ${
                    isAdmin
                      ? `<td class="display-user-id"><a href="/wp-admin/user-edit.php?user_id=${
                          player.user_id || userId
                        }" aria-label="Edit user profile">${
                          player.user_id || userId
                        }</a></td>`
                      : ""
                  }
                  ${
                    isAdmin
                      ? `<td class="display-region">${
                          $row.find(".display-region").text() || "Unknown"
                        }</td>`
                      : ""
                  }
                  <td class="display-first-name">${
                    player.first_name || "N/A"
                  }</td>
                  <td class="display-last-name">${
                    player.last_name || "N/A"
                  }</td>
                  <td class="display-dob">${player.dob || "N/A"}</td>
                  <td class="display-gender">${player.gender || "N/A"}</td>
                  <td class="display-avs-number">${
                    player.avs_number || "N/A"
                  }</td>
                  <td class="display-event-count">${
                    player.event_count || 0
                  }</td>
                  ${
                    isAdmin
                      ? `<td class="display-medical-conditions">${
                          medical
                            ? medical.substring(0, 20) +
                              (medical.length > 20 ? "..." : "")
                            : ""
                        }</td>`
                      : ""
                  }
                  ${
                    isAdmin
                      ? `<td class="display-creation-date">${
                          player.creation_timestamp
                            ? new Date(player.creation_timestamp * 1000)
                                .toISOString()
                                .split("T")[0]
                            : "N/A"
                        }</td>`
                      : ""
                  }
                  ${
                    isAdmin
                      ? `<td class="display-past-events">No past events.</td>`
                      : ""
                  }
                  <td class="actions">
                      <a href="#" class="edit-player" 
                         data-index="${newIndex}" 
                         data-user-id="${player.user_id || userId}" 
                         aria-label="Edit player ${player.first_name || ""}" 
                         aria-expanded="false">
                          Edit
                      </a>
                  </td>
              </tr>
            `);
            $table.append($table.find(".add-player-section"));
            $table.append($table.find(".add-player-medical"));
            console.log(
              "InterSoccer: Reordered .add-player-section and .add-player-medical to table end"
            );
            $table.find(".add-player-section").before($newRow);
            $newRow
              .detach()
              .appendTo(
                $table
                  .find(".add-player-section")
                  .parent()
                  .children()
                  .eq(newIndex)
              );
            console.log(
              "InterSoccer: $newRow appended and detached, index:",
              newIndex
            );
            const rowsWithIndex = $table.find(
              `tr[data-player-index="${newIndex}"]`
            );
            if (rowsWithIndex.length > 1) {
              console.log(
                "InterSoccer: Duplicate data-player-index detected post-insertion, removing extra:",
                newIndex
              );
              rowsWithIndex.slice(1).remove();
            }
            console.log(
              "InterSoccer: DOM state after insertion, row count:",
              $table.find("tr[data-player-index]").length
            );
            $(".add-player-section").removeClass("active");
            $(".toggle-add-player").attr("aria-expanded", "false");
            $(
              ".add-player-section input, .add-player-section select, .add-player-section textarea"
            ).val("");
            $(".add-player-section .error-message").hide();
          } else {
            // Update existing row
            console.log("InterSoccer: Updating player row, index:", index);
            $row.attr("data-user-id", player.user_id || userId);
            $row.attr("data-first-name", player.first_name || "N/A");
            $row.attr("data-last-name", player.last_name || "N/A");
            $row.attr("data-dob", player.dob || "N/A");
            $row.attr("data-gender", player.gender || "N/A");
            $row.attr("data-avs-number", player.avs_number || "N/A");
            $row.attr("data-event-count", player.event_count || 0);
            $row.attr(
              "data-creation-timestamp",
              player.creation_timestamp || ""
            );
            if (isAdmin) {
              $row
                .find(".display-user-id")
                .html(
                  `<a href="/wp-admin/user-edit.php?user_id=${
                    player.user_id || userId
                  }" aria-label="Edit user profile">${
                    player.user_id || userId
                  }</a>`
                );
              $row
                .find(".display-region")
                .text($row.data("region") || "Unknown");
            }
            $row.find(".display-first-name").text(player.first_name || "N/A");
            $row.find(".display-last-name").text(player.last_name || "N/A");
            $row.find(".display-dob").text(player.dob || "N/A");
            $row.find(".display-gender").text(player.gender || "N/A");
            $row.find(".display-avs-number").text(player.avs_number || "N/A");
            $row.find(".display-event-count").text(player.event_count || 0);
            if (isAdmin) {
              $row
                .find(".display-medical-conditions")
                .text(
                  medical
                    ? medical.substring(0, 20) +
                        (medical.length > 20 ? "..." : "")
                    : ""
                );
              $row
                .find(".display-creation-date")
                .text(
                  player.creation_timestamp
                    ? new Date(player.creation_timestamp * 1000)
                        .toISOString()
                        .split("T")[0]
                    : "N/A"
                );
              $row.find(".display-past-events").text("No past events."); // Past events are server-side rendered
            }
            $row.find(".actions").html(`
              <a href="#" class="edit-player" 
                 data-index="${index}" 
                 data-user-id="${player.user_id || userId}" 
                 aria-label="Edit player ${player.first_name || ""}" 
                 aria-expanded="false">
                  Edit
              </a>
            `);
            // Remove all medical rows after saving
            const removedCount = $table.find(".medical-row").length;
            $table.find(".medical-row").remove();
            console.log(
              "InterSoccer: Removed all medical rows after Save, count:",
              removedCount
            );
            editingIndex = null;
            $table
              .find(".edit-player")
              .removeClass("disabled")
              .attr("aria-disabled", "false");
          }
          // Re-apply filters after adding/updating a row
          if (isAdmin) {
            applyFilters();
          }
        } else {
          console.error(
            "InterSoccer: AJAX response failure:",
            response.data?.message
          );
          $message
            .text(response.data.message || "Failed to save player.")
            .show();
          setTimeout(() => $message.hide(), 10000);
        }
      },
      error: (xhr) => {
        console.error(
          "InterSoccer: AJAX error:",
          xhr.status,
          "Response:",
          xhr.responseText
        );
        if (xhr.status === 403 && !nonceRetryAttempted) {
          console.log(
            "InterSoccer: 403 error, attempting nonce refresh (once)"
          );
          nonceRetryAttempted = true;
          refreshNonce()
            .then(() => {
              console.log(
                "InterSoccer: Retrying AJAX with new nonce:",
                intersoccerPlayer.nonce
              );
              data.nonce = intersoccerPlayer.nonce;
              $.ajax(this);
            })
            .catch((error) => {
              console.error("InterSoccer: Nonce refresh failed:", error);
              $message.text("Error: Failed to refresh security token.").show();
              setTimeout(() => $message.hide(), 10000);
              nonceRetryAttempted = false;
            });
        } else {
          console.error(
            "InterSoccer: Non-403 AJAX error:",
            xhr.status,
            xhr.responseText
          );
          $message
            .text(
              "Error: Unable to save player - " +
                (xhr.responseText || "Unknown error")
            )
            .show();
          setTimeout(() => $message.hide(), 10000);
          nonceRetryAttempted = false;
        }
      },
      complete: () => {
        console.log("InterSoccer: AJAX complete");
        isProcessing = false;
        if (isAdd) {
          isAdding = false;
          console.log("InterSoccer: Resetting isAdding flag to false");
        }
        $submitLink
          .removeClass("disabled")
          .attr("aria-disabled", "false")
          .find(".spinner")
          .hide();
      },
    });
  }

  // Save player (edit or add) with debounce
  $container.on("click", ".player-submit", function (e) {
    e.preventDefault();
    const currentTime = Date.now();
    if (currentTime - lastClickTime < clickDebounceMs) {
      console.log("InterSoccer: Save Player click debounced, ignoring");
      return;
    }
    lastClickTime = currentTime;
    console.log("InterSoccer: Save Player binding checked");
    if ($(this).hasClass("disabled")) {
      console.log("InterSoccer: Save button disabled, ignoring click");
      return;
    }
    const $row = $(this).closest("tr");
    const isAdd = $row.hasClass("add-player-section");
    console.log("InterSoccer: Save Player clicked, isAdd:", isAdd);
    savePlayer($row, isAdd);
  });

  // Edit player with debounce
  $container.on("click", ".edit-player", function (e) {
    e.preventDefault();
    const currentTime = Date.now();
    if (currentTime - lastEditClickTime < editClickDebounceMs) {
      console.log("InterSoccer: Edit Player click debounced, ignoring");
      return;
    }
    lastEditClickTime = currentTime;

    console.log("InterSoccer: Edit Player binding checked");
    if (isProcessing || editingIndex !== null) {
      console.log(
        "InterSoccer: Edit ignored, processing or another row being edited, current editingIndex:",
        editingIndex
      );
      return;
    }

    const index = $(this).data("index");
    const userId = $(this).data("user-id") || intersoccerPlayer.user_id;
    console.log(
      "InterSoccer: Edit Player clicked, index:",
      index,
      "userId:",
      userId
    );
    editingIndex = index;

    const $row = $table.find(`tr[data-player-index="${index}"]`);
    const firstName = $row.attr("data-first-name") || "N/A";
    const lastName = $row.attr("data-last-name") || "N/A";
    const dob = $row.attr("data-dob") || "N/A";
    const dobParts = dob !== "N/A" ? dob.split("-") : ["", "", ""];
    const dobYear = dobParts[0];
    const dobMonth = dobParts[1];
    const dobDay = dobParts[2];
    const gender = $row.attr("data-gender") || "N/A";
    const avsNumber = $row.attr("data-avs-number") || "N/A";
    const eventCount = $row.attr("data-event-count") || 0;
    const region = $row.data("region") || "Unknown";
    const medical =
      $row
        .next(`.medical-row[data-player-index="${index}"]`)
        .find('[name="player_medical"]')
        .val() || "";
    const creationTimestamp = $row.attr("data-creation-timestamp") || "";
    const gracePeriodDays = 365;
    const currentTimestamp = Math.floor(Date.now() / 1000);
    const isWithinGracePeriod =
      creationTimestamp &&
      currentTimestamp - parseInt(creationTimestamp) <
        gracePeriodDays * 24 * 60 * 60;

    console.log(
      "InterSoccer: Rendering Edit row, index:",
      index,
      "FirstName:",
      firstName,
      "LastName:",
      lastName,
      "DOB:",
      dob,
      "Gender:",
      gender,
      "AVSNumber:",
      avsNumber,
      "EventCount:",
      eventCount,
      "Region:",
      region,
      "Medical:",
      medical,
      "WithinGracePeriod:",
      isWithinGracePeriod
    );

    // Remove all existing medical rows to prevent duplicates
    const removedCount = $table.find(".medical-row").length;
    $table.find(".medical-row").remove();
    console.log(
      "InterSoccer: Removed all existing medical rows before adding new one, count:",
      removedCount
    );

    // Ensure the add-player-section and add-player-medical are at the end
    const $addPlayerSection = $table.find(".add-player-section");
    const $addPlayerMedical = $table.find(".add-player-medical");
    if ($addPlayerSection.length) {
      $addPlayerSection.detach();
      $table.append($addPlayerSection);
    }
    if ($addPlayerMedical.length) {
      $addPlayerMedical.detach();
      $table.append($addPlayerMedical);
    }

    let colIndex = 0;
    if (isAdmin) {
      $row
        .find("td")
        .eq(colIndex++)
        .html(
          `<a href="/wp-admin/user-edit.php?user_id=${userId}" aria-label="Edit user profile">${userId}</a>`
        );
      $row
        .find("td")
        .eq(colIndex++)
        .html(`<span class="display-region">${region}</span>`);
    }
    $row.find("td").eq(colIndex++).html(`
      <input type="text" name="player_first_name" value="${
        firstName === "N/A" ? "" : firstName
      }" required aria-required="true" maxlength="50">
      <span class="error-message" style="display: none;"></span>
    `);
    $row.find("td").eq(colIndex++).html(`
      <input type="text" name="player_last_name" value="${
        lastName === "N/A" ? "" : lastName
      }" required aria-required="true" maxlength="50">
      <span class="error-message" style="display: none;"></span>
    `);
    $row
      .find("td")
      .eq(colIndex++)
      .html(
        isWithinGracePeriod
          ? `
        <select name="player_dob_day" required aria-required="true">
            <option value="">Day</option>
            ${Array.from({ length: 31 }, (_, i) => i + 1)
              .map(
                (day) =>
                  `<option value="${String(day).padStart(2, "0")}" ${
                    dobDay === String(day).padStart(2, "0") ? "selected" : ""
                  }>${day}</option>`
              )
              .join("")}
        </select>
        <select name="player_dob_month" required aria-required="true">
            <option value="">Month</option>
            <option value="01" ${
              dobMonth === "01" ? "selected" : ""
            }>January</option>
            <option value="02" ${
              dobMonth === "02" ? "selected" : ""
            }>February</option>
            <option value="03" ${
              dobMonth === "03" ? "selected" : ""
            }>March</option>
            <option value="04" ${
              dobMonth === "04" ? "selected" : ""
            }>April</option>
            <option value="05" ${
              dobMonth === "05" ? "selected" : ""
            }>May</option>
            <option value="06" ${
              dobMonth === "06" ? "selected" : ""
            }>June</option>
            <option value="07" ${
              dobMonth === "07" ? "selected" : ""
            }>July</option>
            <option value="08" ${
              dobMonth === "08" ? "selected" : ""
            }>August</option>
            <option value="09" ${
              dobMonth === "09" ? "selected" : ""
            }>September</option>
            <option value="10" ${
              dobMonth === "10" ? "selected" : ""
            }>October</option>
            <option value="11" ${
              dobMonth === "11" ? "selected" : ""
            }>November</option>
            <option value="12" ${
              dobMonth === "12" ? "selected" : ""
            }>December</option>
        </select>
        <select name="player_dob_year" required aria-required="true">
            <option value="">Year</option>
            ${Array.from({ length: 2023 - 2012 + 1 }, (_, i) => 2023 - i)
              .map(
                (year) =>
                  `<option value="${year}" ${
                    dobYear === String(year) ? "selected" : ""
                  }>${year}</option>`
              )
              .join("")}
        </select>
        <span class="error-message" style="display: none;"></span>
      `
          : `<span class="display-dob">${dob}</span>`
      );
    $row
      .find("td")
      .eq(colIndex++)
      .html(
        isWithinGracePeriod
          ? `
        <select name="player_gender" required aria-required="true">
            <option value="">Select Gender</option>
            <option value="male" ${
              gender === "male" ? "selected" : ""
            }>Male</option>
            <option value="female" ${
              gender === "female" ? "selected" : ""
            }>Female</option>
            <option value="other" ${
              gender === "other" ? "selected" : ""
            }>Other</option>
        </select>
        <span class="error-message" style="display: none;"></span>
      `
          : `<span class="display-gender">${gender}</span>`
      );
    $row.find("td").eq(colIndex++).html(`
      <input type="text" name="player_avs_number" value="${
        avsNumber === "N/A" ? "" : avsNumber
      }" required aria-required="true" maxlength="16" pattern="756\.\\d{4}\\.\\d{4}\\.\\d{2}">
      <span class="error-message" style="display: none;"></span>
    `);
    $row
      .find("td")
      .eq(colIndex++)
      .html(`<span class="display-event-count">${eventCount}</span>`);
    if (isAdmin) {
      $row
        .find("td")
        .eq(colIndex++)
        .html(
          `<span class="display-medical-conditions">${
            medical
              ? medical.substring(0, 20) + (medical.length > 20 ? "..." : "")
              : ""
          }</span>`
        );
      $row
        .find("td")
        .eq(colIndex++)
        .html(
          `<span class="display-creation-date">${
            creationTimestamp
              ? new Date(creationTimestamp * 1000).toISOString().split("T")[0]
              : "N/A"
          }</span>`
        );
      $row
        .find("td")
        .eq(colIndex++)
        .html(`<span class="display-past-events">No past events.</span>`); // Past events are server-side rendered
    }
    $row.find(".actions").html(`
      <a href="#" class="player-submit" aria-label="Save Player">Save</a> /
      <a href="#" class="cancel-edit" aria-label="Cancel Edit">Cancel</a> /
      <a href="#" class="delete-player" aria-label="Delete player ${
        firstName || ""
      }">Delete</a>
    `);

    // Insert Medical Conditions row for the current player only
    if (editingIndex === index) {
      const $medicalRow = $(`
        <tr class="medical-row active" data-player-index="${index}">
          <td colspan="${isAdmin ? 11 : 7}">
            <label for="player_medical_${index}">Medical Conditions:</label>
            <textarea id="player_medical_${index}" name="player_medical" maxlength="500" aria-describedby="medical-instructions-${index}">${medical}</textarea>
            <span id="medical-instructions-${index}" class="screen-reader-text">Optional field for medical conditions.</span>
            <span class="error-message" style="display: none;"></span>
          </td>
        </tr>
      `);
      console.log("InterSoccer: Adding medical row for Edit, index:", index);
      // Ensure the medical row is inserted directly after the player's row
      $row.after($medicalRow);

      // Verify the DOM structure after insertion
      const $nextRow = $row.next();
      if (
        !$nextRow.hasClass("medical-row") ||
        $nextRow.data("player-index") != index
      ) {
        console.error(
          "InterSoccer: Medical row insertion failed, re-inserting"
        );
        $table.find(`.medical-row[data-player-index="${index}"]`).remove();
        $row.after($medicalRow);
      }
    } else {
      console.log(
        "InterSoccer: Skipped adding medical row, editingIndex:",
        editingIndex,
        "does not match index:",
        index
      );
    }

    $table
      .find(".edit-player")
      .not(this)
      .addClass("disabled")
      .attr("aria-disabled", "true");
    $(this).attr("aria-expanded", "true");

    // Debug: Log the number of medical rows after adding
    console.log(
      "InterSoccer: Medical rows after Edit, count:",
      $table.find(".medical-row").length
    );
  });

  // Cancel edit
  $container.on("click", ".cancel-edit", function (e) {
    e.preventDefault();
    console.log("InterSoccer: Cancel Edit binding checked");
    const $row = $(this).closest("tr");
    const index = $row.data("player-index");
    const userId = $row.data("user-id") || intersoccerPlayer.user_id;

    const firstName = $row.attr("data-first-name") || "N/A";
    const lastName = $row.attr("data-last-name") || "N/A";
    const dob = $row.attr("data-dob") || "N/A";
    const gender = $row.attr("data-gender") || "N/A";
    const avsNumber = $row.attr("data-avs-number") || "N/A";
    const eventCount = $row.attr("data-event-count") || 0;
    const region = $row.data("region") || "Unknown";
    const creationTimestamp = $row.attr("data-creation-timestamp") || "";
    const medicalDisplay =
      $row.find(".display-medical-conditions").text() || "";

    console.log(
      "InterSoccer: Restoring row on Cancel, index:",
      index,
      "UserId:",
      userId,
      "FirstName:",
      firstName,
      "LastName:",
      lastName,
      "DOB:",
      dob,
      "Gender:",
      gender,
      "AVSNumber:",
      avsNumber,
      "EventCount:",
      eventCount,
      "Region:",
      region,
      "MedicalDisplay:",
      medicalDisplay
    );

    // Fallback to AJAX if data attributes are missing
    if (
      firstName === "N/A" ||
      lastName === "N/A" ||
      dob === "N/A" ||
      gender === "N/A" ||
      avsNumber === "N/A"
    ) {
      console.log("InterSoccer: Data attributes missing, fetching from server");
      $.ajax({
        url: intersoccerPlayer.ajax_url,
        type: "POST",
        data: {
          action: "intersoccer_get_player",
          nonce: intersoccerPlayer.nonce,
          user_id: intersoccerPlayer.user_id,
          player_user_id: userId,
          player_index: index,
          is_admin: isAdmin ? "1" : "0",
        },
        success: (response) => {
          if (response.success && response.data.player) {
            const player = response.data.player;
            console.log("InterSoccer: Fetched player data:", player);
            $row.attr("data-user-id", player.user_id || userId);
            $row.attr("data-first-name", player.first_name || "N/A");
            $row.attr("data-last-name", player.last_name || "N/A");
            $row.attr("data-dob", player.dob || "N/A");
            $row.attr("data-gender", player.gender || "N/A");
            $row.attr("data-avs-number", player.avs_number || "N/A");
            $row.attr("data-event-count", player.event_count || 0);
            $row.attr("data-region", player.region || "Unknown");
            $row.attr(
              "data-creation-timestamp",
              player.creation_timestamp || ""
            );
            if (isAdmin) {
              $row
                .find(".display-user-id")
                .html(
                  `<a href="/wp-admin/user-edit.php?user_id=${
                    player.user_id || userId
                  }" aria-label="Edit user profile">${
                    player.user_id || userId
                  }</a>`
                );
              $row.find(".display-region").text(player.region || "Unknown");
            }
            $row.find(".display-first-name").text(player.first_name || "N/A");
            $row.find(".display-last-name").text(player.last_name || "N/A");
            $row.find(".display-dob").text(player.dob || "N/A");
            $row.find(".display-gender").text(player.gender || "N/A");
            $row.find(".display-avs-number").text(player.avs_number || "N/A");
            $row.find(".display-event-count").text(player.event_count || 0);
            if (isAdmin) {
              $row
                .find(".display-medical-conditions")
                .text(
                  player.medical_conditions
                    ? player.medical_conditions.substring(0, 20) +
                        (player.medical_conditions.length > 20 ? "..." : "")
                    : ""
                );
              $row
                .find(".display-creation-date")
                .text(
                  player.creation_timestamp
                    ? new Date(player.creation_timestamp * 1000)
                        .toISOString()
                        .split("T")[0]
                    : "N/A"
                );
              $row.find(".display-past-events").text("No past events."); // Past events are server-side rendered
            }
          } else {
            console.error(
              "InterSoccer: Failed to fetch player data:",
              response.data?.message
            );
          }
        },
        error: (xhr) => {
          console.error(
            "InterSoccer: Error fetching player data:",
            xhr.status,
            xhr.responseText
          );
        },
      });
    }

    let colIndex = 0;
    if (isAdmin) {
      $row
        .find("td")
        .eq(colIndex++)
        .html(
          `<a href="/wp-admin/user-edit.php?user_id=${userId}" aria-label="Edit user profile">${userId}</a>`
        );
      $row
        .find("td")
        .eq(colIndex++)
        .html(`<span class="display-region">${region}</span>`);
    }
    $row
      .find("td")
      .eq(colIndex++)
      .html(`<span class="display-first-name">${firstName}</span>`);
    $row
      .find("td")
      .eq(colIndex++)
      .html(`<span class="display-last-name">${lastName}</span>`);
    $row
      .find("td")
      .eq(colIndex++)
      .html(`<span class="display-dob">${dob}</span>`);
    $row
      .find("td")
      .eq(colIndex++)
      .html(`<span class="display-gender">${gender}</span>`);
    $row
      .find("td")
      .eq(colIndex++)
      .html(`<span class="display-avs-number">${avsNumber}</span>`);
    $row
      .find("td")
      .eq(colIndex++)
      .html(`<span class="display-event-count">${eventCount}</span>`);
    if (isAdmin) {
      $row
        .find("td")
        .eq(colIndex++)
        .html(
          `<span class="display-medical-conditions">${medicalDisplay}</span>`
        );
      $row
        .find("td")
        .eq(colIndex++)
        .html(
          `<span class="display-creation-date">${
            creationTimestamp
              ? new Date(creationTimestamp * 1000).toISOString().split("T")[0]
              : "N/A"
          }</span>`
        );
      $row
        .find("td")
        .eq(colIndex++)
        .html(`<span class="display-past-events">No past events.</span>`); // Past events are server-side rendered
    }
    $row.find(".actions").html(`
      <a href="#" class="edit-player" 
         data-index="${index}" 
         data-user-id="${userId}" 
         aria-label="Edit player ${firstName || ""}" 
         aria-expanded="false">
          Edit
      </a>
    `);
    // Remove all medical rows after canceling
    const removedCount = $table.find(".medical-row").length;
    $table.find(".medical-row").remove();
    console.log(
      "InterSoccer: Removed all medical rows on Cancel Edit, count:",
      removedCount
    );

    editingIndex = null;
    $table
      .find(".edit-player")
      .removeClass("disabled")
      .attr("aria-disabled", "false");

    // Re-apply filters after canceling
    if (isAdmin) {
      applyFilters();
    }
  });

  // Delete player
  $container.on("click", ".delete-player", function (e) {
    e.preventDefault();
    console.log("InterSoccer: Delete Player binding checked");
    if (isProcessing) {
      console.log("InterSoccer: Processing, ignoring click");
      return;
    }
    const $row = $(this).closest("tr");
    const index = $row.data("player-index");
    const userId = $row.data("user-id") || intersoccerPlayer.user_id;
    if (!confirm("Are you sure you want to delete this player?")) {
      return;
    }

    isProcessing = true;
    $.ajax({
      url: intersoccerPlayer.ajax_url,
      type: "POST",
      data: {
        action: "intersoccer_delete_player",
        nonce: intersoccerPlayer.nonce,
        user_id: intersoccerPlayer.user_id,
        player_user_id: userId,
        player_index: index,
        is_admin: isAdmin ? "1" : "0",
      },
      success: (response) => {
        console.log("InterSoccer: Delete AJAX success:", response);
        if (response.success) {
          $message.text(response.data.message).show();
          setTimeout(() => $message.hide(), 10000);
          $row.remove();
          const $medicalRow = $table.find(
            `tr.medical-row[data-player-index="${index}"]`
          );
          if ($medicalRow.length) {
            console.log(
              "InterSoccer: Removing medical row for player index:",
              index
            );
            $medicalRow.remove();
          } else {
            console.log(
              "InterSoccer: No medical row found for player index:",
              index
            );
          }
          if (!$table.find("tr[data-player-index]").length) {
            $table.find(".no-players").remove();
            $table
              .find(".add-player-section")
              .before(
                `<tr class="no-players"><td colspan="${
                  isAdmin ? 11 : 7
                }">No attendees added yet.</td></tr>`
              );
          }
          editingIndex = null;
          $table
            .find(".edit-player")
            .removeClass("disabled")
            .attr("aria-disabled", "false");
          // Re-apply filters after deleting
          if (isAdmin) {
            applyFilters();
          }
        } else {
          console.error(
            "InterSoccer: Delete AJAX response failure:",
            response.data?.message
          );
          $message
            .text(response.data.message || "Failed to delete player.")
            .show();
          setTimeout(() => $message.hide(), 10000);
        }
      },
      error: (xhr) => {
        console.error(
          "InterSoccer: Delete AJAX error:",
          xhr.status,
          xhr.responseText
        );
        if (xhr.status === 403 && !nonceRetryAttempted) {
          console.log(
            "InterSoccer: 403 error, attempting nonce refresh (once)"
          );
          nonceRetryAttempted = true;
          refreshNonce()
            .then(() => {
              console.log(
                "InterSoccer: Retrying AJAX with new nonce:",
                intersoccerPlayer.nonce
              );
              data.nonce = intersoccerPlayer.nonce;
              $.ajax(this);
            })
            .catch((error) => {
              console.error("InterSoccer: Nonce refresh failed:", error);
              $message.text("Error: Failed to refresh security token.").show();
              setTimeout(() => $message.hide(), 10000);
              nonceRetryAttempted = false;
            });
        } else {
          console.error(
            "InterSoccer: Non-403 AJAX error:",
            xhr.status,
            xhr.responseText
          );
          $message
            .text(
              "Error: Unable to delete player - " +
                (xhr.responseText || "Unknown error")
            )
            .show();
          setTimeout(() => $message.hide(), 10000);
          nonceRetryAttempted = false;
        }
      },
      complete: () => {
        console.log("InterSoccer: Delete AJAX complete");
        isProcessing = false;
        nonceRetryAttempted = false;
      },
    });
  });

  console.log("InterSoccer: player-management.js fully loaded");
})(jQuery);
