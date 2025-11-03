jQuery(document).ready(function($) {
  const debugEnabled = window.intersoccerPlayer && intersoccerPlayer.debug === "1";
  const $container = $(".intersoccer-player-management");
  const $table = $("#player-table");
  const $message = $container.find(".intersoccer-message");
  const intersoccerState = window.intersoccerState || {};

  /**
   * Translate gender value for display
   */
  function translateGender(genderValue) {
    if (!genderValue || genderValue === 'N/A') {
      return 'N/A';
    }
    const genderNormalized = genderValue.toLowerCase();
    const translations = intersoccerPlayer.i18n && intersoccerPlayer.i18n.gender ? intersoccerPlayer.i18n.gender : {};
    return translations[genderNormalized] || genderValue;
  }

  // Save player (edit or add) with debounce
  function savePlayer($row, isAdd = false) {
    if (intersoccerState.isProcessing) {
      if (debugEnabled) console.log("InterSoccer: Save aborted, processing already in progress");
      return;
    }
    if (isAdd && intersoccerState.isAdding) {
      if (debugEnabled) console.log("InterSoccer: Save aborted, adding already in progress");
      return;
    }
    if (!intersoccerValidateRow($row, isAdd)) {
      if (debugEnabled) console.log("InterSoccer: Validation failed for savePlayer, row inputs:", {
        firstName: $row.find('[name="player_first_name"]').val(),
        lastName: $row.find('[name="player_last_name"]').val(),
        dob: $row.find('[name="player_dob"]').val(),
        gender: $row.find('[name="player_gender"]').val(),
        avsNumber: $row.find('[name="player_avs_number"]').val(),
        medical: $row.next(".medical-row")?.find('[name="player_medical"]').val() || $row.next(".add-player-medical")?.find('[name="player_medical"]').val()
      });
      return;
    }

    intersoccerState.isProcessing = true;
    if (isAdd) intersoccerState.isAdding = true;
    const $submitLink = $row.find(".player-submit");
    $submitLink.addClass("disabled").attr("aria-disabled", "true").find(".spinner").show();

    const index = isAdd ? "-1" : ($row.data("player-index") || $row.attr("data-player-index"));
    const userId = $row.data("user-id") || $row.attr("data-user-id") || intersoccerPlayer.user_id;
    const $medicalRow = isAdd ? $row.next(".add-player-medical") : $row.next(`.medical-row[data-player-index="${index}"]`);
    const firstName = $row.find('[name="player_first_name"]').val().trim();
    const lastName = $row.find('[name="player_last_name"]').val().trim();
    const dob = $row.find('[name="player_dob"]').val().trim();
    const gender = $row.find('[name="player_gender"]').val();
    const avsNumber = $row.find('[name="player_avs_number"]').val().trim() || "0000";
    const medical = $medicalRow.length ? $medicalRow.find('[name="player_medical"]').val().trim() : "";

    if (debugEnabled) {
      console.log("InterSoccer: Preparing AJAX payload for savePlayer:", {
        raw: { firstName, lastName, dob, gender, avsNumber, medical, userId, index },
        encoded: {
          action: isAdd ? "intersoccer_add_player" : "intersoccer_edit_player",
          nonce: intersoccerPlayer.nonce,
          user_id: userId,
          player_index: index,
          player_first_name: encodeURIComponent(firstName),
          player_last_name: encodeURIComponent(lastName),
          player_dob: dob,
          player_gender: gender,
          player_avs_number: avsNumber,
          player_medical: encodeURIComponent(medical),
          is_admin: "1"
        },
        preload_players_before: JSON.stringify(intersoccerPlayer.preload_players),
        medical_conditions_encoded: encodeURIComponent(medical)
      });
    }

    if (!userId || userId <= 0) {
      console.error("InterSoccer: Invalid userId for savePlayer:", userId);
      $message.text("Error: Invalid user ID.").show();
      setTimeout(() => $message.hide(), 10000);
      intersoccerState.isProcessing = false;
      if (isAdd) intersoccerState.isAdding = false;
      $submitLink.removeClass("disabled").attr("aria-disabled", "false").find(".spinner").hide();
      return;
    }

    if (!isAdd && (index === undefined || index < 0)) {
      console.error("InterSoccer: Invalid player index for savePlayer:", index);
      $message.text("Error: Invalid player index.").show();
      setTimeout(() => $message.hide(), 10000);
      intersoccerState.isProcessing = false;
      $submitLink.removeClass("disabled").attr("aria-disabled", "false").find(".spinner").hide();
      return;
    }

    const action = isAdd ? "intersoccer_add_player" : "intersoccer_edit_player";
    const data = {
      action: action,
      nonce: intersoccerPlayer.nonce,
      user_id: userId,
      player_user_id: userId,
      player_first_name: encodeURIComponent(firstName),
      player_last_name: encodeURIComponent(lastName),
      player_dob: dob,
      player_gender: gender,
      player_avs_number: avsNumber,
      player_medical: encodeURIComponent(medical),
      is_admin: "1"
    };
    if (!isAdd) data.player_index = index;

    const startTime = Date.now();
    $.ajax({
      url: intersoccerPlayer.ajax_url,
      type: "POST",
      data: data,
      success: (response) => {
        const endTime = Date.now();
        if (debugEnabled) {
          console.log("InterSoccer: savePlayer AJAX completed at", new Date(endTime).toISOString(), "Duration:", (endTime - startTime), "ms", "Response:", {
            success: response.success,
            message: response.data?.message,
            player: response.data?.player,
            medical_conditions: response.data?.player?.medical_conditions,
            preload_players_after: JSON.stringify(intersoccerPlayer.preload_players)
          });
        }

        if (response.success) {
          $message.text(response.data.message).show();
          setTimeout(() => $message.hide(), 10000);

          // Update preload_players and DOM attributes with the returned player data
          if (!isAdd && response.data.player) {
            const editIndex = index.toString();
            intersoccerPlayer.preload_players[editIndex] = response.data.player;
            $row.attr("data-medical-conditions", encodeURIComponent(response.data.player.medical_conditions || ""));
            if (debugEnabled) console.log("InterSoccer: Updated preload_players and DOM after edit:", {
              preload_players: JSON.stringify(intersoccerPlayer.preload_players),
              data_medical_conditions: $row.attr("data-medical-conditions")
            });
          } else if (isAdd && response.data.player) {
            const newIndex = Object.keys(intersoccerPlayer.preload_players).length.toString();
            intersoccerPlayer.preload_players[newIndex] = response.data.player;
            $row.attr("data-medical-conditions", encodeURIComponent(response.data.player.medical_conditions || ""));
            if (debugEnabled) console.log("InterSoccer: Updated preload_players and DOM after add:", {
              preload_players: JSON.stringify(intersoccerPlayer.preload_players),
              data_medical_conditions: $row.attr("data-medical-conditions")
            });
          }

          const player = response.data.player;
          if (isAdd) {
            $table.find(".no-players").remove();
            const $newRow = $(`
              <tr data-player-index="${player.player_index || newIndex}" 
                  data-user-id="${player.user_id || userId}" 
                  data-first-name="${player.first_name || "N/A"}" 
                  data-last-name="${player.last_name || "N/A"}" 
                  data-dob="${player.dob || "N/A"}" 
                  data-gender="${player.gender || "N/A"}" 
                  data-avs-number="${player.avs_number || "N/A"}"
                  data-event-count="${player.event_count || 0}"
                  data-canton="${player.canton || ""}"
                  data-city="${player.city || ""}"
                  data-creation-timestamp="${player.creation_timestamp || ""}"
                  data-medical-conditions="${encodeURIComponent(player.medical_conditions || "")}">
                  <td class="display-user-id">${player.user_id || userId}</td>
                  <td class="display-canton">${player.canton || ""}</td>
                  <td class="display-city">${player.city || ""}</td>
                  <td class="display-first-name">${player.first_name || "N/A"}</td>
                  <td class="display-last-name">${player.last_name || "N/A"}</td>
                  <td class="display-dob">${player.dob || "N/A"}</td>
                  <td class="display-gender">${translateGender(player.gender || "N/A")}</td>
                  <td class="display-avs-number">${player.avs_number || "N/A"}</td>
                  <td class="display-medical-conditions">${(player.medical_conditions || '').substring(0, 20) + ((player.medical_conditions || '').length > 20 ? '...' : '')}</td>
                  <td class="display-event-count">${player.event_count || 0}</td>
                  <td class="actions">
                      <a href="#" class="edit-player" data-index="${player.player_index || newIndex}" data-user-id="${player.user_id || userId}" aria-label="Edit player ${player.first_name || ""}" aria-expanded="false">Edit</a>
                      <a href="#" class="delete-player" data-index="${player.player_index || newIndex}" data-user-id="${player.user_id || userId}" aria-label="Delete player ${player.first_name || ""}">Delete</a>
                  </td>
              </tr>
            `);
            $table.append($newRow);
            $(".add-player-section").removeClass("active").hide();
            $(".add-player-medical").removeClass("active").hide();
            $(".toggle-add-player").attr("aria-expanded", "false");
            $(".add-player-section input, .add-player-section select, .add-player-section textarea").val("");
            $(".add-player-section .error-message").hide();
          } else {
            if (debugEnabled) console.log("InterSoccer: Edit mode, response data:", response.data);
            if (typeof player === "undefined" && response.data.message === "No changes detected, player data unchanged") {
              const firstName = $row.attr("data-first-name") || "N/A";
              const lastName = $row.attr("data-last-name") || "N/A";
              const dob = $row.attr("data-dob") || "N/A";
              const gender = $row.attr("data-gender") || "N/A";
              const avsNumber = $row.attr("data-avs-number") || "N/A";
              const eventCount = $row.attr("data-event-count") || 0;
              const canton = $row.data("canton") || "";
              const city = $row.data("city") || "";
              const medicalConditions = $row.attr("data-medical-conditions") || "";
              const creationTimestamp = $row.attr("data-creation-timestamp") || "";
              const pastEvents = $row.find(".display-past-events").html() || "No past events.";

              $row.find(".display-user-id").text(userId);
              $row.find(".display-canton").text(canton);
              $row.find(".display-city").text(city);
              $row.find(".display-first-name").text(firstName);
              $row.find(".display-last-name").text(lastName);
              $row.find(".display-dob").text(dob);
              $row.find(".display-gender").text(gender);
              $row.find(".display-avs-number").text(avsNumber);
              $row.find(".display-medical-conditions").text(decodeURIComponent(medicalConditions).substring(0, 20) + (decodeURIComponent(medicalConditions).length > 20 ? "..." : ""));
              $row.find(".display-creation-date").text(creationTimestamp ? new Date(creationTimestamp * 1000).toISOString().split("T")[0] : "N/A");
              $row.find(".display-past-events").html(pastEvents);
            } else {
              $row.attr("data-user-id", player.user_id || userId);
              $row.attr("data-first-name", player.first_name || "N/A");
              $row.attr("data-last-name", player.last_name || "N/A");
              $row.attr("data-dob", player.dob || "N/A");
              $row.attr("data-gender", player.gender || "N/A");
              $row.attr("data-avs-number", player.avs_number || "N/A");
              $row.attr("data-event-count", player.event_count || 0);
              $row.attr("data-canton", player.canton || "");
              $row.attr("data-city", player.city || "");
              $row.attr("data-creation-timestamp", player.creation_timestamp || "");
              $row.attr("data-medical-conditions", encodeURIComponent(player.medical_conditions || ""));
              $row.find(".display-user-id").text(player.user_id || userId);
              $row.find(".display-canton").text(player.canton || "");
              $row.find(".display-city").text(player.city || "");
              $row.find(".display-first-name").text(player.first_name || "N/A");
              $row.find(".display-last-name").text(player.last_name || "N/A");
              $row.find(".display-dob").text(player.dob || "N/A");
              $row.find(".display-gender").text(translateGender(player.gender || "N/A"));
              $row.find(".display-avs-number").text(player.avs_number || "N/A");
              $row.find(".display-medical-conditions").text((player.medical_conditions || '').substring(0, 20) + ((player.medical_conditions || '').length > 20 ? '...' : ''));
              $row.find(".display-creation-date").text(player.creation_timestamp ? new Date(player.creation_timestamp * 1000).toISOString().split("T")[0] : "N/A");
              $row.find(".display-past-events").html(player.past_events && player.past_events.length ? player.past_events.map(event => event.name + (event.date && event.venue ? ` (${event.date}, ${event.venue})` : '')).join('<br>') : "No past events.");
            }
            $table.find(`.medical-row[data-player-index="${index}"]`).remove();
            intersoccerState.editingIndex = null;
            $table.find(".edit-player").removeClass("disabled").attr("aria-disabled", "false");
            if (typeof intersoccerApplyFilters === "function") {
              if (debugEnabled) console.log("InterSoccer: Applying filters after save");
              intersoccerApplyFilters();
            }
          }
        } else {
          console.error("InterSoccer: AJAX response failure:", response.data?.message);
          $message.text(response.data.message || "Failed to save player.").show();
          setTimeout(() => $message.hide(), 10000);
          if (response.data.new_nonce) {
            intersoccerPlayer.nonce = response.data.new_nonce;
            if (debugEnabled) console.log("InterSoccer: Updated nonce from server response:", intersoccerPlayer.nonce);
          }
        }
      },
      error: (xhr) => {
        const endTime = Date.now();
        console.error("InterSoccer: AJAX error at", new Date(endTime).toISOString(), "Status:", xhr.status, "Response:", xhr.responseText, "Response JSON:", JSON.stringify(xhr.responseJSON));
        if (xhr.status === 403 && !intersoccerState.nonceRetryAttempted) {
          if (debugEnabled) console.log("InterSoccer: 403 error detected, attempting nonce refresh");
          intersoccerState.nonceRetryAttempted = true;
          intersoccerRefreshNonce().then(() => {
            if (debugEnabled) console.log("InterSoccer: Retrying AJAX with refreshed nonce:", intersoccerPlayer.nonce);
            data.nonce = intersoccerPlayer.nonce;
            $.ajax(this);
          }).catch((error) => {
            console.error("InterSoccer: Nonce refresh failed:", error);
            $message.text("Error: Failed to refresh security token. Please reload the page.").show();
            setTimeout(() => $message.hide(), 10000);
            intersoccerState.nonceRetryAttempted = false;
          });
        } else {
          $message.text("Error: Unable to save player - " + (xhr.responseText || "Unknown error")).show();
          setTimeout(() => $message.hide(), 10000);
          intersoccerState.nonceRetryAttempted = false;
        }
      },
      complete: () => {
        intersoccerState.isProcessing = false;
        if (isAdd) intersoccerState.isAdding = false;
        $submitLink.removeClass("disabled").attr("aria-disabled", "false").find(".spinner").hide();
        if (debugEnabled) console.log("InterSoccer: Save operation completed");
      }
    });
  }

  // Function to populate edit form
  function populateEditForm($row, player, index) {
    if (!player) {
      console.error("InterSoccer: Player data not found for index:", index);
      $message.text("Error: Could not load player data for editing.").show();
      setTimeout(() => $message.hide(), 10000);
      $row.removeClass("editing");
      intersoccerState.editingIndex = null;
      return;
    }

    const firstName = player.first_name || "N/A";
    const lastName = player.last_name || "N/A";
    const dob = player.dob || "N/A";
    const dobParts = dob && dob !== "N/A" ? dob.split("-") : ["", "", ""];
    const dobYear = dobParts[0] || "";
    const dobMonth = dobParts[1] || "";
    const dobDay = dobParts[2] || "";
    const gender = player.gender || "N/A";
    const avsNumber = player.avs_number || "0000";
    const eventCount = player.event_count || 0;
    const canton = player.canton || "";
    const city = player.city || "";
    const medical = player.medical_conditions ? decodeURIComponent(player.medical_conditions) : "";
    const creationTimestamp = player.creation_timestamp || "";
    const pastEvents = player.past_events && player.past_events.length ? player.past_events : [];

    if (debugEnabled) {
      console.log("InterSoccer: Populating edit form with data:", {
        firstName, lastName, dob, dobYear, dobMonth, dobDay, gender, avsNumber, eventCount, canton, city, medical, creationTimestamp, pastEvents,
        data_medical_conditions: $row.attr("data-medical-conditions"),
        decoded_medical_conditions: decodeURIComponent($row.attr("data-medical-conditions") || "")
      });
    }

    $row.find(".display-user-id").html(`<a href="/wp-admin/user-edit.php?user_id=${player.user_id || intersoccerPlayer.user_id}" aria-label="Edit user profile">${player.user_id || intersoccerPlayer.user_id}</a>`);
    $row.find(".display-canton").html(`<span class="display-canton">${canton}</span>`);
    $row.find(".display-city").html(`<span class="display-city">${city}</span>`);
    $row.find(".display-first-name").html(`
      <input type="text" name="player_first_name" value="${firstName}" required aria-required="true" maxlength="50">
      <span class="error-message" style="display: none;"></span>
    `);
    $row.find(".display-last-name").html(`
      <input type="text" name="player_last_name" value="${lastName}" required aria-required="true" maxlength="50">
      <span class="error-message" style="display: none;"></span>
    `);
    $row.find(".display-dob").html(`
      <input type="text" name="player_dob" class="date-picker" value="${dob}" required aria-required="true" maxlength="10">
      <span class="error-message" style="display: none;"></span>
    `);
    $row.find(".display-gender").html(`
      <select name="player_gender" required aria-required="true">
          <option value="">Select Gender</option>
          <option value="male" ${gender === "male" ? "selected" : ""}>Male</option>
          <option value="female" ${gender === "female" ? "selected" : ""}>Female</option>
          <option value="other" ${gender === "other" ? "selected" : ""}>Other</option>
      </select>
      <span class="error-message" style="display: none;"></span>
    `);
    $row.find(".display-avs-number").html(`
      <input type="text" name="player_avs_number" value="${avsNumber}" aria-required="true" maxlength="50">
      <span class="avs-instruction">No AVS? Enter foreign insurance number or "0000" and email us the insurance details.</span>
      <span class="error-message" style="display: none;"></span>
    `);
    $row.find(".display-medical-conditions").html(`
      <span class="display-medical-conditions">${decodeURIComponent(medical).substring(0, 20) + (decodeURIComponent(medical).length > 20 ? "..." : "")}</span>
    `);
    $row.find(".display-event-count").html(`<span class="display-event-count">${eventCount}</span>`);
    $row.find(".display-creation-date").html(`
      <span class="display-creation-date">${creationTimestamp ? new Date(creationTimestamp * 1000).toISOString().split("T")[0] : "N/A"}</span>
    `);
    $row.find(".display-past-events").html(`
      <span class="display-past-events">${pastEvents.length ? pastEvents.map(event => event.name + (event.date && event.venue ? ` (${event.date}, ${event.venue})` : '')).join('<br>') : "No past events."}</span>
    `);
    $row.find(".actions").html(`
      <a href="#" class="player-submit" aria-label="Save Player">Save</a> /
      <a href="#" class="cancel-edit" aria-label="Cancel Edit">Cancel</a> /
      <a href="#" class="delete-player" data-index="${index}" data-user-id="${player.user_id || intersoccerPlayer.user_id}" aria-label="Delete player ${firstName || ""}">Delete</a>
    `);

    // Insert medical row for the edited player
    if (intersoccerState.editingIndex === index) {
      const $medicalRow = $(`
        <tr class="medical-row active" data-player-index="${index}">
          <td colspan="11">
            <label for="player_medical_${index}">Medical Conditions:</label>
            <textarea id="player_medical_${index}" name="player_medical" maxlength="500" aria-describedby="medical-instructions-${index}">${decodeURIComponent(medical)}</textarea>
            <span id="medical-instructions-${index}" class="screen-reader-text">Optional field for medical conditions.</span>
            <span class="error-message" style="display: none;"></span>
          </td>
        </tr>
      `);
      $row.after($medicalRow);
      $table.find(`.medical-row[data-player-index="${index}"]:not(:first)`).remove();
    }

    // Disable edit buttons on other rows and focus on first input
    $table.find(`tr[data-player-index]`).not($row).each(function () {
      const $otherRow = $(this);
      const otherIndex = $otherRow.data("player-index") || $otherRow.attr("data-player-index");
      if (parseInt(otherIndex) !== parseInt(index)) {
        $otherRow.find(".edit-player").addClass("disabled").attr("aria-disabled", "true");
      }
    });
    $row.find('[name="player_first_name"]').focus();

    $(this).attr("aria-expanded", "true");
    if (debugEnabled) console.log("InterSoccer: Edit form populated for player index:", index);
  }

  // Cancel edit
  function cancelEdit($row, index, userId) {
    if (intersoccerState.isProcessing) {
      if (debugEnabled) console.log("InterSoccer: Cancel aborted, processing in progress");
      return;
    }

    if (debugEnabled) console.log("InterSoccer: Cancel edit for player index:", index, "userId:", userId);

    if (intersoccerPlayer.preload_players && intersoccerPlayer.preload_players[index]) {
      const player = intersoccerPlayer.preload_players[index];
      if (!player) {
        console.error("InterSoccer: Preloaded player data not found for index:", index);
        $message.text("Error: Could not load player data for cancel.").show();
        setTimeout(() => $message.hide(), 10000);
        $row.removeClass("editing");
        intersoccerState.editingIndex = null;
        return;
      }

      const firstName = player.first_name || $row.attr("data-first-name") || "N/A";
      const lastName = player.last_name || $row.attr("data-last-name") || "N/A";
      const dob = player.dob || $row.attr("data-dob") || "N/A";
      const gender = player.gender || $row.attr("data-gender") || "N/A";
      const avsNumber = player.avs_number || $row.attr("data-avs-number") || "N/A";
      const eventCount = player.event_count || $row.attr("data-event-count") || 0;
      const canton = player.canton || $row.data("canton") || "";
      const city = player.city || $row.data("city") || "";
      const medical = player.medical_conditions || "";
      const creationTimestamp = player.creation_timestamp || $row.attr("data-creation-timestamp") || "";
      const pastEvents = player.past_events && player.past_events.length ? player.past_events.map(event => event.name + (event.date && event.venue ? ` (${event.date}, ${event.venue})` : '')).join('<br>') : "No past events.";

      setTimeout(() => {
        $row.find(".display-user-id").html(`<a href="/wp-admin/user-edit.php?user_id=${userId}" aria-label="Edit user profile">${userId}</a>`);
        $row.find(".display-canton").html(`<span class="display-canton">${canton}</span>`);
        $row.find(".display-city").html(`<span class="display-city">${city}</span>`);
        $row.find(".display-first-name").html(`<span class="display-first-name">${firstName}</span>`);
        $row.find(".display-last-name").html(`<span class="display-last-name">${lastName}</span>`);
        $row.find(".display-dob").html(`<span class="display-dob">${dob}</span>`);
        $row.find(".display-gender").html(`<span class="display-gender">${gender}</span>`);
        $row.find(".display-avs-number").html(`<span class="display-avs-number">${avsNumber}</span>`);
        $row.find(".display-medical-conditions").html(`<span class="display-medical-conditions">${decodeURIComponent(medical).substring(0, 20) + (decodeURIComponent(medical).length > 20 ? "..." : "") || ""}</span>`);
        $row.find(".display-event-count").html(`<span class="display-event-count">${eventCount}</span>`);
        $row.find(".display-creation-date").html(`<span class="display-creation-date">${creationTimestamp ? new Date(creationTimestamp * 1000).toISOString().split("T")[0] : "N/A"}</span>`);
        $row.find(".display-past-events").html(`<span class="display-past-events">${pastEvents}</span>`);
        $row.find(".actions").html(`
          <a href="#" class="edit-player" data-index="${index}" data-user-id="${userId}" aria-label="Edit player ${firstName || ""}" aria-expanded="false">Edit</a>
          <a href="#" class="delete-player" data-index="${index}" data-user-id="${userId}" aria-label="Delete player ${firstName || ""}">Delete</a>
        `);

        $row.removeClass("editing");
        $table.find(`.medical-row[data-player-index="${index}"]`).remove();
        intersoccerState.editingIndex = null;
        $table.find(".edit-player").removeClass("disabled").attr("aria-disabled", "false");

        $message.text("Edit canceled.").show();
        setTimeout(() => $message.hide(), 5000);
        if (debugEnabled) console.log("InterSoccer: Edit canceled for player index:", index);
      }, isMobile() ? 200 : 100);
    } else {
      window.fetchPlayerData(userId, index, (player) => {
        if (!player) {
          $row.removeClass("editing");
          intersoccerState.editingIndex = null;
          return;
        }

        const firstName = player.first_name || $row.attr("data-first-name") || "N/A";
        const lastName = player.last_name || $row.attr("data-last-name") || "N/A";
        const dob = player.dob || $row.attr("data-dob") || "N/A";
        const gender = player.gender || $row.attr("data-gender") || "N/A";
        const avsNumber = player.avs_number || $row.attr("data-avs-number") || "N/A";
        const eventCount = player.event_count || $row.attr("data-event-count") || 0;
        const canton = player.canton || $row.data("canton") || "";
        const city = player.city || $row.data("city") || "";
        const medical = player.medical_conditions || "";
        const creationTimestamp = player.creation_timestamp || $row.attr("data-creation-timestamp") || "";
        const pastEvents = player.past_events && player.past_events.length ? player.past_events.map(event => event.name + (event.date && event.venue ? ` (${event.date}, ${event.venue})` : '')).join('<br>') : "No past events.";

        setTimeout(() => {
          $row.find(".display-user-id").html(`<a href="/wp-admin/user-edit.php?user_id=${userId}" aria-label="Edit user profile">${userId}</a>`);
          $row.find(".display-canton").html(`<span class="display-canton">${canton}</span>`);
          $row.find(".display-city").html(`<span class="display-city">${city}</span>`);
          $row.find(".display-first-name").html(`<span class="display-first-name">${firstName}</span>`);
          $row.find(".display-last-name").html(`<span class="display-last-name">${lastName}</span>`);
          $row.find(".display-dob").html(`<span class="display-dob">${dob}</span>`);
          $row.find(".display-gender").html(`<span class="display-gender">${gender}</span>`);
          $row.find(".display-avs-number").html(`<span class="display-avs-number">${avsNumber}</span>`);
          $row.find(".display-medical-conditions").html(`<span class="display-medical-conditions">${decodeURIComponent(medical).substring(0, 20) + (decodeURIComponent(medical).length > 20 ? "..." : "") || ""}</span>`);
          $row.find(".display-event-count").html(`<span class="display-event-count">${eventCount}</span>`);
          $row.find(".display-creation-date").html(`<span class="display-creation-date">${creationTimestamp ? new Date(creationTimestamp * 1000).toISOString().split("T")[0] : "N/A"}</span>`);
          $row.find(".display-past-events").html(`<span class="display-past-events">${pastEvents}</span>`);
          $row.find(".actions").html(`
            <a href="#" class="edit-player" data-index="${index}" data-user-id="${userId}" aria-label="Edit player ${firstName || ""}" aria-expanded="false">Edit</a>
            <a href="#" class="delete-player" data-index="${index}" data-user-id="${userId}" aria-label="Delete player ${firstName || ""}">Delete</a>
          `);

          $row.removeClass("editing");
          $table.find(`.medical-row[data-player-index="${index}"]`).remove();
          intersoccerState.editingIndex = null;
          $table.find(".edit-player").removeClass("disabled").attr("aria-disabled", "false");

          $message.text("Edit canceled.").show();
          setTimeout(() => $message.hide(), 5000);
          if (debugEnabled) console.log("InterSoccer: Edit canceled for player index:", index);
        }, isMobile() ? 200 : 100);
      });
    }
  }

  // Delete player
  $container.on("click", ".delete-player", function (e) {
    e.preventDefault();
    if (intersoccerState.isProcessing) {
      if (debugEnabled) console.log("InterSoccer: Delete aborted, processing in progress");
      return;
    }

    const $row = $(this).closest("tr");
    let index = $row.data("player-index") || $row.attr("data-player-index");
    
    // Fallback: try to get index from the button itself
    if (index === undefined || index === null) {
      index = $(this).data("index") || $(this).attr("data-index");
    }
    
    if (index === undefined || index === null) {
      console.error("InterSoccer: Could not find player-index for row", $row, "or button", this);
      return;
    }
    const indexStr = String(index);
    const userId = $row.data("user-id") || $row.attr("data-user-id") || intersoccerPlayer.user_id;

    if (!confirm("Are you sure you want to delete this player?")) {
      if (debugEnabled) console.log("InterSoccer: Delete canceled by user");
      return;
    }

    intersoccerState.isProcessing = true;
    $(this).addClass("disabled").attr("aria-disabled", "true");

    if (debugEnabled) console.log("InterSoccer: Initiating delete for player index:", index, "userId:", userId);

    $.ajax({
      url: intersoccerPlayer.ajax_url,
      type: "POST",
      data: {
        action: "intersoccer_delete_player",
        nonce: intersoccerPlayer.nonce,
        user_id: userId,
        player_user_id: userId,
        player_index: index,
        is_admin: "1"
      },
      success: (response) => {
        if (response.success) {
          $row.remove();
          $table.find(`.medical-row[data-player-index="${index}"]`).remove();
          $message.text(response.data.message).show();
          setTimeout(() => $message.hide(), 10000);
          if (debugEnabled) console.log("InterSoccer: Player deleted successfully:", response.data);

          // Update table state
          if ($table.find("tr[data-player-index]").length === 0) {
            $table.append('<tr class="no-players"><td colspan="11">No players added yet.</td></tr>');
          }
        } else {
          console.error("InterSoccer: Failed to delete player:", response.data?.message);
          $message.text(response.data.message || "Failed to delete player.").show();
          setTimeout(() => $message.hide(), 10000);
          if (response.data.new_nonce) {
            intersoccerPlayer.nonce = response.data.new_nonce;
            if (debugEnabled) console.log("InterSoccer: Updated nonce from server response:", intersoccerPlayer.nonce);
          }
        }
      },
      error: (xhr) => {
        console.error("InterSoccer: AJAX error deleting player:", xhr.status, xhr.responseText);
        if (xhr.status === 403 && !intersoccerState.nonceRetryAttempted) {
          if (debugEnabled) console.log("InterSoccer: 403 error detected, attempting nonce refresh");
          intersoccerState.nonceRetryAttempted = true;
          intersoccerRefreshNonce().then(() => {
            if (debugEnabled) console.log("InterSoccer: Retrying AJAX with refreshed nonce:", intersoccerPlayer.nonce);
            $.ajax({
              url: intersoccerPlayer.ajax_url,
              type: "POST",
              data: {
                action: "intersoccer_delete_player",
                nonce: intersoccerPlayer.nonce,
                user_id: userId,
                player_user_id: userId,
                player_index: index,
                is_admin: "1"
              },
              success: (response) => {
                if (response.success) {
                  $row.remove();
                  $table.find(`.medical-row[data-player-index="${index}"]`).remove();
                  $message.text(response.data.message).show();
                  setTimeout(() => $message.hide(), 10000);
                  if ($table.find("tr[data-player-index]").length === 0) {
                    $table.append('<tr class="no-players"><td colspan="11">No players added yet.</td></tr>');
                  }
                  if (debugEnabled) console.log("InterSoccer: Player deleted successfully after nonce refresh:", response.data);
                } else {
                  console.error("InterSoccer: Failed to delete player after nonce refresh:", response.data?.message);
                  $message.text(response.data.message || "Failed to delete player.").show();
                  setTimeout(() => $message.hide(), 10000);
                }
              },
              error: (xhr) => {
                console.error("InterSoccer: AJAX error after nonce refresh:", xhr.status, xhr.responseText);
                $message.text("Error: Unable to delete player - " + (xhr.responseText || "Unknown error")).show();
                setTimeout(() => $message.hide(), 10000);
              },
              complete: () => {
                intersoccerState.nonceRetryAttempted = false;
                intersoccerState.isProcessing = false;
                $(this).removeClass("disabled").attr("aria-disabled", "false");
                if (debugEnabled) console.log("InterSoccer: Delete operation completed after nonce refresh");
              }
            });
          }).catch((error) => {
            console.error("InterSoccer: Nonce refresh failed:", error);
            $message.text("Error: Failed to refresh security token. Please reload the page.").show();
            setTimeout(() => $message.hide(), 10000);
            intersoccerState.nonceRetryAttempted = false;
            intersoccerState.isProcessing = false;
            $(this).removeClass("disabled").attr("aria-disabled", "false");
          });
        } else {
          $message.text("Error: Unable to delete player - " + (xhr.responseText || "Unknown error")).show();
          setTimeout(() => $message.hide(), 10000);
          intersoccerState.nonceRetryAttempted = false;
          intersoccerState.isProcessing = false;
          $(this).removeClass("disabled").attr("aria-disabled", "false");
        }
      },
      complete: () => {
        intersoccerState.isProcessing = false;
        $(this).removeClass("disabled").attr("aria-disabled", "false");
        if (debugEnabled) console.log("InterSoccer: Delete operation completed");
      }
    });
  },

  // Toggle add player section
  $container.on("click.toggleAdd", ".toggle-add-player", function (e) {
    e.preventDefault();
    const currentTime = Date.now();
    if (currentTime - intersoccerState.lastToggleClickTime < intersoccerState.toggleDebounceMs) {
      if (debugEnabled) console.log("InterSoccer: Toggle click debounced");
      return;
    }
    intersoccerState.lastToggleClickTime = currentTime;

    if (intersoccerState.isProcessing) {
      if (debugEnabled) console.log("InterSoccer: Toggle add player aborted, processing in progress");
      return;
    }

    const $addSection = $(".add-player-section");
    const $addMedical = $(".add-player-medical");
    const isVisible = $addSection.is(":visible");

    if (debugEnabled) {
      console.log("InterSoccer: Toggle add player triggered, current visibility:", isVisible, "event:", {
        isTrusted: e.isTrusted,
        type: e.type,
        target: e.target.tagName,
        class: e.target.className
      });
    }

    if (!isVisible) {
      $addSection.addClass("active").show();
      $addMedical.addClass("active").show();
      $addSection.find("input, select, textarea").val("");
      $addSection.find(".error-message").hide();
      $addMedical.find(".error-message").hide();
      $addSection.find('[name="player_first_name"]').focus();
      $(this).attr("aria-expanded", "true");
      if (debugEnabled) console.log("InterSoccer: Add player section shown");
    } else {
      $addSection.removeClass("active").hide();
      $addMedical.removeClass("active").hide();
      $(this).attr("aria-expanded", "false");
      if (debugEnabled) console.log("InterSoccer: Add player section hidden");
    }
  }));

  // Cancel add
  $container.on("click", ".cancel-add", function (e) {
    e.preventDefault();
    if (intersoccerState.isProcessing) {
      if (debugEnabled) console.log("InterSoccer: Cancel add aborted, processing in progress");
      return;
    }

    const $addSection = $(".add-player-section");
    const $addMedical = $(".add-player-medical");
    $addSection.removeClass("active").hide();
    $addMedical.removeClass("active").hide();
    $addSection.find("input, select, textarea").val("");
    $addSection.find(".error-message").hide();
    $addMedical.find(".error-message").hide();
    $(".toggle-add-player").attr("aria-expanded", "false").focus();

    $message.text("Add player canceled.").show();
    setTimeout(() => $message.hide(), 5000);
    if (debugEnabled) console.log("InterSoccer: Add player canceled");
  });

  // Apply filters
  window.intersoccerApplyFilters = function() {
    const searchTerm = $("#player-search").val().toLowerCase().trim();
    const $rows = $("#player-table tr[data-player-index]");

    $rows.each(function() {
      const $row = $(this);
      const firstName = $row.find(".display-first-name").text().toLowerCase();
      const lastName = $row.find(".display-last-name").text().toLowerCase();
      const isVisible = !searchTerm || firstName.includes(searchTerm) || lastName.includes(searchTerm);
      $row.toggle(isVisible);
    });

    if (debugEnabled) console.log("InterSoccer: Applied name search filter, term:", searchTerm);
  };

  $("#player-search").on("input", function() {
    intersoccerApplyFilters();
  });

  // Bind admin-specific events
  $container.on("click", ".player-submit", function (e) {
    e.preventDefault();
    const currentTime = Date.now();
    if (currentTime - intersoccerState.lastClickTime < intersoccerState.clickDebounceMs) {
      if (debugEnabled) console.log("InterSoccer: Save click debounced");
      return;
    }
    intersoccerState.lastClickTime = currentTime;

    const $row = $(this).closest("tr").length ? $(this).closest("tr") : $(this).closest(".add-player-section");
    const isAdd = $row.hasClass("add-player-section");
    savePlayer($row, isAdd);
  });

  $container.on("click", ".edit-player", function (e) {
    e.preventDefault();
    const currentTime = Date.now();
    if (currentTime - intersoccerState.lastEditClickTime < intersoccerState.editClickDebounceMs) {
      if (debugEnabled) console.log("InterSoccer: Edit click debounced");
      return;
    }
    intersoccerState.lastEditClickTime = currentTime;

    if (intersoccerState.isProcessing || intersoccerState.editingIndex !== null) {
      if (debugEnabled) console.log("InterSoccer: Edit aborted, processing or already editing");
      return;
    }

    const $row = $(this).closest("tr");
    let index = $row.data("player-index") || $row.attr("data-player-index");
    
    // Fallback: try to get index from the button itself
    if (index === undefined || index === null) {
      index = $(this).data("index") || $(this).attr("data-index");
    }
    
    if (index === undefined || index === null) {
      console.error("InterSoccer: Could not find player-index for row", $row, "or button", this);
      return;
    }
    const indexStr = String(index);
    const userId = $row.data("user-id") || $row.attr("data-user-id") || intersoccerPlayer.user_id;
    intersoccerState.editingIndex = indexStr;

    if (debugEnabled) {
      console.log("InterSoccer: Initiating edit for player index:", indexStr, "userId:", userId);
    }

    if (intersoccerPlayer.preload_players && intersoccerPlayer.preload_players[indexStr]) {
      populateEditForm($row, intersoccerPlayer.preload_players[indexStr], indexStr);
    } else {
      window.fetchPlayerData(userId, indexStr, (player) => {
        if (player) {
          populateEditForm($row, player, indexStr);
        }
      });
    }
  });

  $container.on("click", ".cancel-edit", function (e) {
    e.preventDefault();
    const $row = $(this).closest("tr");
    let index = $row.data("player-index") || $row.attr("data-player-index");
    
    // Fallback: try to get index from the button itself
    if (index === undefined || index === null) {
      index = $(this).data("index") || $(this).attr("data-index");
    }
    
    const userId = $row.data("user-id") || $row.attr("data-user-id") || intersoccerPlayer.user_id;
    cancelEdit($row, index, userId);
  });

  if (debugEnabled) console.log("InterSoccer: Initialized name search filter");
});