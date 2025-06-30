(function ($) {
  if (typeof intersoccerPlayer === "undefined" || typeof intersoccerValidateRow === "undefined") {
    console.error("InterSoccer: Dependencies not loaded. Actions disabled.");
    return;
  }

  const $container = $(".intersoccer-player-management");
  const $table = $("#player-table");
  const $message = $container.find(".intersoccer-message");
  const isAdmin = intersoccerPlayer.is_admin === "1";
  const debugEnabled = intersoccerPlayer.debug === "1";

  // Fetch player data via AJAX
  function fetchPlayerData(userId, index, callback) {
    const startTime = Date.now();
    console.log("InterSoccer: Starting fetchPlayerData for userId:", userId, "index:", index, "at", new Date(startTime).toISOString());
    $.ajax({
      url: intersoccerPlayer.ajax_url,
      type: "POST",
      data: {
        action: "intersoccer_get_player",
        nonce: intersoccerPlayer.nonce,
        user_id: userId,
        player_index: index,
        is_admin: isAdmin ? "1" : "0"
      },
      success: function(response) {
        const endTime = Date.now();
        console.log("InterSoccer: fetchPlayerData completed at", new Date(endTime).toISOString(), "Duration:", (endTime - startTime), "ms");
        if (response.success && response.data.player) {
          if (debugEnabled) console.log("InterSoccer: Fetched player data:", response.data.player);
          callback(response.data.player);
        } else {
          console.error("InterSoccer: Failed to fetch player data:", response.data?.message);
          $message.text("Error: Unable to load player data.").show();
          setTimeout(() => $message.hide(), 10000);
          callback(null);
        }
      },
      error: function(xhr) {
        const endTime = Date.now();
        console.error("InterSoccer: AJAX error fetching player data at", new Date(endTime).toISOString(), "Duration:", (endTime - startTime), "ms", "Status:", xhr.status, "Response:", xhr.responseText);
        $message.text("Error: Failed to load player data - " + (xhr.responseText || "Unknown error")).show();
        setTimeout(() => $message.hide(), 10000);
        callback(null);
      }
    });
  }

  // Save player (add or edit)
  function savePlayer($row, isAdd = false) {
    if (intersoccerState.isProcessing) return;
    if (isAdd && intersoccerState.isAdding) return;
    if (!intersoccerValidateRow($row, isAdd)) return;

    intersoccerState.isProcessing = true;
    if (isAdd) intersoccerState.isAdding = true;
    const $submitLink = $row.find(".player-submit");
    $submitLink.addClass("disabled").attr("aria-disabled", "true").find(".spinner").show();

    const index = isAdd ? "-1" : $row.data("player-index");
    const userId = isAdmin && isAdd ? $row.find('[name="player_user_id"]').val().trim() : $row.data("user-id") || intersoccerPlayer.user_id;
    const $medicalRow = isAdd ? $row.next(".add-player-medical") : $row.next(`.medical-row[data-player-index="${index}"]`);
    const firstName = $row.find('[name="player_first_name"]').val().trim();
    const lastName = $row.find('[name="player_last_name"]').val().trim();
    const dobDay = $row.find('[name="player_dob_day"]').val();
    const dobMonth = $row.find('[name="player_dob_month"]').val();
    const dobYear = $row.find('[name="player_dob_year"]').val();
    const dob = dobDay && dobMonth && dobYear ? `${dobYear}-${dobMonth}-${dobDay}` : "";
    const gender = $row.find('[name="player_gender"]').val();
    const avsNumber = $row.find('[name="player_avs_number"]').val().trim();
    const medical = ($medicalRow.length ? $medicalRow.find('[name="player_medical"]').val() : "").trim();

    const action = isAdd ? "intersoccer_add_player" : "intersoccer_edit_player";
    const data = {
      action: action,
      nonce: intersoccerPlayer.nonce,
      user_id: isAdmin ? userId : intersoccerPlayer.user_id,
      player_user_id: userId,
      player_first_name: firstName,
      player_last_name: lastName,
      player_dob: dob,
      player_gender: gender,
      player_avs_number: avsNumber,
      player_medical: medical,
      is_admin: isAdmin ? "1" : "0",
    };
    if (!isAdd) data.player_index = index;

    const startTime = Date.now();
    console.log("InterSoccer: Starting savePlayer AJAX at", new Date(startTime).toISOString());

    $.ajax({
      url: intersoccerPlayer.ajax_url,
      type: "POST",
      data: data,
      success: (response) => {
        const endTime = Date.now();
        console.log("InterSoccer: savePlayer AJAX completed at", new Date(endTime).toISOString(), "Duration:", (endTime - startTime), "ms");

        if (response.success) {
          $message.text(response.data.message).show();
          setTimeout(() => $message.hide(), 10000);
          console.log("InterSoccer: AJAX success response:", response);

          const player = response.data.player;
          if (isAdd) {
            $table.find(".no-players").remove();
            const newIndex = $table.find("tr[data-player-index]").length;
            $table.find(`tr[data-player-index="${newIndex}"]`).remove();
            const existingPlayer = $table.find("tr[data-player-index]").filter(function () {
              return $(this).find(".display-first-name").text() === player.first_name &&
                     $(this).find(".display-last-name").text() === player.last_name &&
                     $(this).find(".display-dob").text() === player.dob;
            });
            if (existingPlayer.length) existingPlayer.remove();
            const $newRow = $(`
              <tr data-player-index="${newIndex}" 
                  data-user-id="${player.user_id || userId}" 
                  data-first-name="${player.first_name || "N/A"}" 
                  data-last-name="${player.last_name || "N/A"}" 
                  data-dob="${player.dob || "N/A"}" 
                  data-gender="${player.gender || "N/A"}" 
                  data-avs-number="${player.avs_number || "N/A"}"
                  data-event-count="${player.event_count || 0}"
                  data-canton="${$row.find(".display-canton").text() || ""}"
                  data-city="${$row.find(".display-city").text() || ""}"
                  data-creation-timestamp="${player.creation_timestamp || ""}"
                  data-event-regions=""
                  data-event-age-groups=""
                  data-event-types="">
                  ${
                    isAdmin
                      ? `<td class="display-user-id"><a href="/wp-admin/user-edit.php?user_id=${player.user_id || userId}" aria-label="Edit user profile">${player.user_id || userId}</a></td>`
                      : ""
                  }
                  ${
                    isAdmin
                      ? `<td class="display-canton">${$row.find(".display-canton").text() || ""}</td>`
                      : ""
                  }
                  ${
                    isAdmin
                      ? `<td class="display-city">${$row.find(".display-city").text() || ""}</td>`
                      : ""
                  }
                  <td class="display-first-name">${player.first_name || "N/A"}</td>
                  <td class="display-last-name">${player.last_name || "N/A"}</td>
                  <td class="display-dob">${player.dob || "N/A"}</td>
                  <td class="display-gender">${player.gender || "N/A"}</td>
                  <td class="display-avs-number">${player.avs_number || "N/A"}</td>
                  <td class="display-event-count">${player.event_count || 0}</td>
                  ${
                    isAdmin
                      ? `<td class="display-medical-conditions">${medical ? medical.substring(0, 20) + (medical.length > 20 ? "..." : "") : ""}</td>`
                      : ""
                  }
                  ${
                    isAdmin
                      ? `<td class="display-creation-date">${player.creation_timestamp ? new Date(player.creation_timestamp * 1000).toISOString().split("T")[0] : "N/A"}</td>`
                      : ""
                  }
                  ${
                    isAdmin
                      ? `<td class="display-past-events">No past events.</td>`
                      : ""
                  }
                  <td class="actions">
                      <a href="#" class="edit-player" data-index="${newIndex}" data-user-id="${player.user_id || userId}" aria-label="Edit player ${player.first_name || ""}" aria-expanded="false">Edit</a>
                  </td>
              </tr>
            `);
            $table.append($table.find(".add-player-section"));
            $table.append($table.find(".add-player-medical"));
            $table.find(".add-player-section").before($newRow);
            const rowsWithIndex = $table.find(`tr[data-player-index="${newIndex}"]`);
            if (rowsWithIndex.length > 1) rowsWithIndex.slice(1).remove();
            $(".add-player-section").removeClass("active");
            $(".toggle-add-player").attr("aria-expanded", "false");
            $(".add-player-section input, .add-player-section select, .add-player-section textarea").val("");
            $(".add-player-section .error-message").hide();
          } else {
            console.log("InterSoccer: Edit mode, response data:", response.data);
            console.log("InterSoccer: Player defined:", typeof player !== "undefined");
            if (typeof player === "undefined" && response.data.message === "No changes detected, player data unchanged") {
              console.log("InterSoccer: Handling unchanged data case");
              const index = $row.data("player-index");
              const userId = $row.data("user-id") || intersoccerPlayer.user_id;

              const firstName = $row.attr("data-first-name") || "N/A";
              const lastName = $row.attr("data-last-name") || "N/A";
              const dob = $row.attr("data-dob") || "N/A";
              const gender = $row.attr("data-gender") || "N/A";
              const avsNumber = $row.attr("data-avs-number") || "N/A";
              const eventCount = $row.attr("data-event-count") || 0;
              const canton = $row.data("canton") || "";
              const city = $row.data("city") || "";
              const creationTimestamp = $row.attr("data-creation-timestamp") || "";

              let colIndex = 0;
              if (isAdmin) {
                $row.find("td").eq(colIndex++).html(`<a href="/wp-admin/user-edit.php?user_id=${userId}" aria-label="Edit user profile">${userId}</a>`);
                $row.find("td").eq(colIndex++).html(`<span class="display-canton">${canton}</span>`);
                $row.find("td").eq(colIndex++).html(`<span class="display-city">${city}</span>`);
              }
              $row.find("td").eq(colIndex++).html(`<span class="display-first-name">${firstName}</span>`);
              $row.find("td").eq(colIndex++).html(`<span class="display-last-name">${lastName}</span>`);
              $row.find("td").eq(colIndex++).html(`<span class="display-dob">${dob}</span>`);
              $row.find("td").eq(colIndex++).html(`<span class="display-gender">${gender}</span>`);
              $row.find("td").eq(colIndex++).html(`<span class="display-avs-number">${avsNumber}</span>`);
              $row.find("td").eq(colIndex++).html(`<span class="display-event-count">${eventCount}</span>`);
              if (isAdmin) {
                $row.find("td").eq(colIndex++).html(`<span class="display-medical-conditions">${medical ? medical.substring(0, 20) + (medical.length > 20 ? "..." : "") : ""}</span>`);
                $row.find("td").eq(colIndex++).html(`<span class="display-creation-date">${creationTimestamp ? new Date(creationTimestamp * 1000).toISOString().split("T")[0] : "N/A"}</span>`);
                $row.find("td").eq(colIndex++).html(`<span class="display-past-events">No past events.</span>`);
              }
              $row.find(".actions").html(`
                <a href="#" class="edit-player" data-index="${index}" data-user-id="${userId}" aria-label="Edit player ${firstName || ""}" aria-expanded="false">Edit</a>
              `);
              $table.find(`.medical-row[data-player-index="${index}"]`).remove();
              intersoccerState.editingIndex = null;
              $table.find(".edit-player").removeClass("disabled").attr("aria-disabled", "false");
              if (isAdmin && typeof intersoccerApplyFilters === "function") intersoccerApplyFilters();
            } else {
              $row.attr("data-user-id", player.user_id || userId);
              $row.attr("data-first-name", player.first_name || "N/A");
              $row.attr("data-last-name", player.last_name || "N/A");
              $row.attr("data-dob", player.dob || "N/A");
              $row.attr("data-gender", player.gender || "N/A");
              $row.attr("data-avs-number", player.avs_number || "N/A");
              $row.attr("data-event-count", player.event_count || 0);
              $row.attr("data-creation-timestamp", player.creation_timestamp || "");
              if (isAdmin) {
                $row.find(".display-user-id").html(`<a href="/wp-admin/user-edit.php?user_id=${player.user_id || userId}" aria-label="Edit user profile">${player.user_id || userId}</a>`);
                $row.find(".display-canton").text($row.data("canton") || "");
                $row.find(".display-city").text($row.data("city") || "");
              }
              $row.find(".display-first-name").text(player.first_name || "N/A");
              $row.find(".display-last-name").text(player.last_name || "N/A");
              $row.find(".display-dob").text(player.dob || "N/A");
              $row.find(".display-gender").text(player.gender || "N/A");
              $row.find(".display-avs-number").text(player.avs_number || "N/A");
              $row.find(".display-event-count").text(player.event_count || 0);
              if (isAdmin) {
                $row.find(".display-medical-conditions").text(medical ? medical.substring(0, 20) + (medical.length > 20 ? "..." : "") : "");
                $row.find(".display-creation-date").text(player.creation_timestamp ? new Date(player.creation_timestamp * 1000).toISOString().split("T")[0] : "N/A");
                $row.find(".display-past-events").text("No past events.");
              }
              $row.find(".actions").html(`
                <a href="#" class="edit-player" data-index="${index}" data-user-id="${player.user_id || userId}" aria-label="Edit player ${player.first_name || ""}" aria-expanded="false">Edit</a>
              `);
              $table.find(`.medical-row[data-player-index="${index}"]`).remove();
              intersoccerState.editingIndex = null;
              $table.find(".edit-player").removeClass("disabled").attr("aria-disabled", "false");
            }
            if (isAdmin && typeof intersoccerApplyFilters === "function") intersoccerApplyFilters();
          }
        } else {
          console.error("InterSoccer: AJAX response failure:", response.data?.message);
          $message.text(response.data.message || "Failed to save player.").show();
          setTimeout(() => $message.hide(), 10000);
        }
        console.log("InterSoccer: Save complete, editingIndex:", intersoccerState.editingIndex);
      },
      error: (xhr) => {
        console.error("InterSoccer: AJAX error:", xhr.status, "Response:", xhr.responseText);
        if (xhr.status === 403 && !intersoccerState.nonceRetryAttempted) {
          if (debugEnabled) console.log("InterSoccer: 403 error, attempting nonce refresh (once)");
          intersoccerState.nonceRetryAttempted = true;
          intersoccerRefreshNonce().then(() => {
            if (debugEnabled) console.log("InterSoccer: Retrying AJAX with new nonce:", intersoccerPlayer.nonce);
            data.nonce = intersoccerPlayer.nonce;
            $.ajax(this);
          }).catch((error) => {
            console.error("InterSoccer: Nonce refresh failed:", error);
            $message.text("Error: Failed to refresh security token.").show();
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
      },
    });
  }

  // Save player (edit or add) with debounce
  $container.on("click", ".player-submit", function (e) {
    e.preventDefault();
    const currentTime = Date.now();
    if (currentTime - intersoccerState.lastClickTime < intersoccerState.clickDebounceMs) return;
    intersoccerState.lastClickTime = currentTime;
    if ($(this).hasClass("disabled")) return;
    const $row = $(this).closest("tr");
    const isAdd = $row.hasClass("add-player-section");
    savePlayer($row, isAdd);
  });

  // Edit player with debounce
  $container.on("click", ".edit-player", function (e) {
    e.preventDefault();
    const currentTime = Date.now();
    if (currentTime - intersoccerState.lastEditClickTime < intersoccerState.editClickDebounceMs) return;
    intersoccerState.lastEditClickTime = currentTime;

    if (intersoccerState.isProcessing || intersoccerState.editingIndex !== null) return;

    const $row = $(this).closest("tr");
    const index = $row.data("player-index");
    const userId = $row.data("user-id") || intersoccerPlayer.user_id;
    intersoccerState.editingIndex = index;

    // Use preloaded data if available
    if (intersoccerPlayer.preload_players && intersoccerPlayer.preload_players[index]) {
      const startTime = Date.now();
      console.log("InterSoccer: Using preloaded player data at", new Date(startTime).toISOString());
      const player = intersoccerPlayer.preload_players[index];
      const endTime = Date.now();
      console.log("InterSoccer: Preload completed at", new Date(endTime).toISOString(), "Duration:", (endTime - startTime), "ms");

      if (!player) {
        $message.text("Error: Could not load preloaded player data for editing.").show();
        setTimeout(() => $message.hide(), 10000);
        return;
      }

      const firstName = $row.attr("data-first-name") || player.first_name || "N/A";
      const lastName = $row.attr("data-last-name") || player.last_name || "N/A";
      const dob = $row.attr("data-dob") || player.dob || "N/A";
      const dobParts = dob !== "N/A" ? dob.split("-") : ["", "", ""];
      const dobYear = dobParts[0];
      const dobMonth = dobParts[1];
      const dobDay = dobParts[2];
      const gender = $row.attr("data-gender") || player.gender || "N/A";
      const avsNumber = $row.attr("data-avs-number") || player.avs_number || "N/A";
      const eventCount = $row.attr("data-event-count") || player.event_count || 0;
      const canton = $row.data("canton") || "";
      const city = $row.data("city") || "";
      const medical = player.medical_conditions || "";
      const creationTimestamp = $row.attr("data-creation-timestamp") || (player.creation_timestamp ? player.creation_timestamp : "");

      let colIndex = 0;
      if (isAdmin) {
        $row.find("td").eq(colIndex++).html(`<a href="/wp-admin/user-edit.php?user_id=${userId}" aria-label="Edit user profile">${userId}</a>`);
        $row.find("td").eq(colIndex++).html(`<span class="display-canton">${canton}</span>`);
        $row.find("td").eq(colIndex++).html(`<span class="display-city">${city}</span>`);
      }
      $row.find("td").eq(colIndex++).html(`
        <input type="text" name="player_first_name" value="${firstName === "N/A" ? "" : firstName}" required aria-required="true" maxlength="50">
        <span class="error-message" style="display: none;"></span>
      `);
      $row.find("td").eq(colIndex++).html(`
        <input type="text" name="player_last_name" value="${lastName === "N/A" ? "" : lastName}" required aria-required="true" maxlength="50">
        <span class="error-message" style="display: none;"></span>
      `);
      $row.find("td").eq(colIndex++).html(`
        <select name="player_dob_day" required aria-required="true">
            <option value="">Day</option>
            ${Array.from({ length: 31 }, (_, i) => i + 1).map(day => `<option value="${String(day).padStart(2, "0")}" ${dobDay === String(day).padStart(2, "0") ? "selected" : ""}>${day}</option>`).join("")}
        </select>
        <select name="player_dob_month" required aria-required="true">
            <option value="">Month</option>
            ${Array.from({ length: 12 }, (_, i) => i + 1).map(month => `<option value="${String(month).padStart(2, "0")}" ${dobMonth === String(month).padStart(2, "0") ? "selected" : ""}>${new Date(2025, month - 1, 1).toLocaleString('default', { month: 'long' })}</option>`).join("")}
        </select>
        <select name="player_dob_year" required aria-required="true">
            <option value="">Year</option>
            ${Array.from({ length: 2023 - 2011 + 1 }, (_, i) => 2023 - i).map(year => `<option value="${year}" ${dobYear === String(year) ? "selected" : ""}>${year}</option>`).join("")}
        </select>
        <span class="error-message" style="display: none;"></span>
      `);
      $row.find("td").eq(colIndex++).html(`
        <select name="player_gender" required aria-required="true">
            <option value="">Select Gender</option>
            <option value="male" ${gender === "male" ? "selected" : ""}>Male</option>
            <option value="female" ${gender === "female" ? "selected" : ""}>Female</option>
            <option value="other" ${gender === "other" ? "selected" : ""}>Other</option>
        </select>
        <span class="error-message" style="display: none;"></span>
      `);
      $row.find("td").eq(colIndex++).html(`
        <input type="text" name="player_avs_number" value="${avsNumber === "N/A" ? "" : avsNumber}" required aria-required="true" maxlength="16" pattern="756\\.\\d{4}\\.\\d{4}\\.\\d{2}">
        <span class="error-message" style="display: none;"></span>
      `);
      $row.find("td").eq(colIndex++).html(`<span class="display-event-count">${eventCount}</span>`);
      if (isAdmin) {
        $row.find("td").eq(colIndex++).html(`<span class="display-medical-conditions">${medical ? medical.substring(0, 20) + (medical.length > 20 ? "..." : "") : ""}</span>`);
        $row.find("td").eq(colIndex++).html(`<span class="display-creation-date">${creationTimestamp ? new Date(creationTimestamp * 1000).toISOString().split("T")[0] : "N/A"}</span>`);
        $row.find("td").eq(colIndex++).html(`<span class="display-past-events">No past events.</span>`);
      }
      $row.find(".actions").html(`
        <a href="#" class="player-submit" aria-label="Save Player">Save</a> /
        <a href="#" class="cancel-edit" aria-label="Cancel Edit">Cancel</a> /
        <a href="#" class="delete-player" aria-label="Delete player ${firstName || ""}">Delete</a>
      `);

      // Insert medical row for the edited player with existing medical conditions
      if (intersoccerState.editingIndex === index) {
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
        $row.after($medicalRow);
        $table.find(`.medical-row[data-player-index="${index}"]:not(:first)`).remove();
      }

      // Disable edit buttons on other rows only
      $table.find(`tr[data-player-index]`).not($row).each(function () {
        const $otherRow = $(this);
        const otherIndex = $otherRow.data("player-index");
        if (parseInt(otherIndex) !== parseInt(index)) {
          $otherRow.find(".edit-player").addClass("disabled").attr("aria-disabled", "true");
        }
      });

      $(this).attr("aria-expanded", "true");
    } else {
      $row.addClass("loading");
      const startTime = Date.now();
      console.log("InterSoccer: Starting fetchPlayerData at", new Date(startTime).toISOString());
      fetchPlayerData(userId, index, function(player) {
        const endTime = Date.now();
        console.log("InterSoccer: fetchPlayerData completed at", new Date(endTime).toISOString(), "Duration:", (endTime - startTime), "ms");
        $row.removeClass("loading");
        if (!player) {
          $message.text("Error: Could not load player data for editing.").show();
          setTimeout(() => $message.hide(), 10000);
          return;
        }

        const firstName = $row.attr("data-first-name") || player.first_name || "N/A";
        const lastName = $row.attr("data-last-name") || player.last_name || "N/A";
        const dob = $row.attr("data-dob") || player.dob || "N/A";
        const dobParts = dob !== "N/A" ? dob.split("-") : ["", "", ""];
        const dobYear = dobParts[0];
        const dobMonth = dobParts[1];
        const dobDay = dobParts[2];
        const gender = $row.attr("data-gender") || player.gender || "N/A";
        const avsNumber = $row.attr("data-avs-number") || player.avs_number || "N/A";
        const eventCount = $row.attr("data-event-count") || player.event_count || 0;
        const canton = $row.data("canton") || "";
        const city = $row.data("city") || "";
        const medical = player.medical_conditions || "";
        const creationTimestamp = $row.attr("data-creation-timestamp") || (player.creation_timestamp ? player.creation_timestamp : "");

        let colIndex = 0;
        if (isAdmin) {
          $row.find("td").eq(colIndex++).html(`<a href="/wp-admin/user-edit.php?user_id=${userId}" aria-label="Edit user profile">${userId}</a>`);
          $row.find("td").eq(colIndex++).html(`<span class="display-canton">${canton}</span>`);
          $row.find("td").eq(colIndex++).html(`<span class="display-city">${city}</span>`);
        }
        $row.find("td").eq(colIndex++).html(`
          <input type="text" name="player_first_name" value="${firstName === "N/A" ? "" : firstName}" required aria-required="true" maxlength="50">
          <span class="error-message" style="display: none;"></span>
        `);
        $row.find("td").eq(colIndex++).html(`
          <input type="text" name="player_last_name" value="${lastName === "N/A" ? "" : lastName}" required aria-required="true" maxlength="50">
          <span class="error-message" style="display: none;"></span>
        `);
        $row.find("td").eq(colIndex++).html(`
          <select name="player_dob_day" required aria-required="true">
              <option value="">Day</option>
              ${Array.from({ length: 31 }, (_, i) => i + 1).map(day => `<option value="${String(day).padStart(2, "0")}" ${dobDay === String(day).padStart(2, "0") ? "selected" : ""}>${day}</option>`).join("")}
          </select>
          <select name="player_dob_month" required aria-required="true">
              <option value="">Month</option>
              ${Array.from({ length: 12 }, (_, i) => i + 1).map(month => `<option value="${String(month).padStart(2, "0")}" ${dobMonth === String(month).padStart(2, "0") ? "selected" : ""}>${new Date(2025, month - 1, 1).toLocaleString('default', { month: 'long' })}</option>`).join("")}
          </select>
          <select name="player_dob_year" required aria-required="true">
              <option value="">Year</option>
              ${Array.from({ length: 2023 - 2011 + 1 }, (_, i) => 2023 - i).map(year => `<option value="${year}" ${dobYear === String(year) ? "selected" : ""}>${year}</option>`).join("")}
          </select>
          <span class="error-message" style="display: none;"></span>
        `);
        $row.find("td").eq(colIndex++).html(`
          <select name="player_gender" required aria-required="true">
              <option value="">Select Gender</option>
              <option value="male" ${gender === "male" ? "selected" : ""}>Male</option>
              <option value="female" ${gender === "female" ? "selected" : ""}>Female</option>
              <option value="other" ${gender === "other" ? "selected" : ""}>Other</option>
          </select>
          <span class="error-message" style="display: none;"></span>
        `);
        $row.find("td").eq(colIndex++).html(`
          <input type="text" name="player_avs_number" value="${avsNumber === "N/A" ? "" : avsNumber}" required aria-required="true" maxlength="16" pattern="756\\.\\d{4}\\.\\d{4}\\.\\d{2}">
          <span class="error-message" style="display: none;"></span>
        `);
        $row.find("td").eq(colIndex++).html(`<span class="display-event-count">${eventCount}</span>`);
        if (isAdmin) {
          $row.find("td").eq(colIndex++).html(`<span class="display-medical-conditions">${medical ? medical.substring(0, 20) + (medical.length > 20 ? "..." : "") : ""}</span>`);
          $row.find("td").eq(colIndex++).html(`<span class="display-creation-date">${creationTimestamp ? new Date(creationTimestamp * 1000).toISOString().split("T")[0] : "N/A"}</span>`);
          $row.find("td").eq(colIndex++).html(`<span class="display-past-events">No past events.</span>`);
        }
        $row.find(".actions").html(`
          <a href="#" class="player-submit" aria-label="Save Player">Save</a> /
          <a href="#" class="cancel-edit" aria-label="Cancel Edit">Cancel</a> /
          <a href="#" class="delete-player" aria-label="Delete player ${firstName || ""}">Delete</a>
        `);

        // Insert medical row for the edited player with existing medical conditions
        if (intersoccerState.editingIndex === index) {
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
          $row.after($medicalRow);
          $table.find(`.medical-row[data-player-index="${index}"]:not(:first)`).remove();
        }

        // Disable edit buttons on other rows only
        $table.find(`tr[data-player-index]`).not($row).each(function () {
          const $otherRow = $(this);
          const otherIndex = $otherRow.data("player-index");
          if (parseInt(otherIndex) !== parseInt(index)) {
            $otherRow.find(".edit-player").addClass("disabled").attr("aria-disabled", "true");
          }
        });

        $(this).attr("aria-expanded", "true");
      });
    }
  });

  // Cancel edit
  $container.on("click", ".cancel-edit", function (e) {
    e.preventDefault();
    const $row = $(this).closest("tr");
    const index = $row.data("player-index");
    const userId = $row.data("user-id") || intersoccerPlayer.user_id;

    const firstName = $row.attr("data-first-name") || "N/A";
    const lastName = $row.attr("data-last-name") || "N/A";
    const dob = $row.attr("data-dob") || "N/A";
    const gender = $row.attr("data-gender") || "N/A";
    const avsNumber = $row.attr("data-avs-number") || "N/A";
    const eventCount = $row.attr("data-event-count") || 0;
    const canton = $row.data("canton") || "";
    const city = $row.data("city") || "";
    const creationTimestamp = $row.attr("data-creation-timestamp") || "";

    let colIndex = 0;
    if (isAdmin) {
      $row.find("td").eq(colIndex++).html(`<a href="/wp-admin/user-edit.php?user_id=${userId}" aria-label="Edit user profile">${userId}</a>`);
      $row.find("td").eq(colIndex++).html(`<span class="display-canton">${canton}</span>`);
      $row.find("td").eq(colIndex++).html(`<span class="display-city">${city}</span>`);
    }
    $row.find("td").eq(colIndex++).html(`<span class="display-first-name">${firstName}</span>`);
    $row.find("td").eq(colIndex++).html(`<span class="display-last-name">${lastName}</span>`);
    $row.find("td").eq(colIndex++).html(`<span class="display-dob">${dob}</span>`);
    $row.find("td").eq(colIndex++).html(`<span class="display-gender">${gender}</span>`);
    $row.find("td").eq(colIndex++).html(`<span class="display-avs-number">${avsNumber}</span>`);
    $row.find("td").eq(colIndex++).html(`<span class="display-event-count">${eventCount}</span>`);
    if (isAdmin) {
      $row.find("td").eq(colIndex++).html(`<span class="display-medical-conditions">${medical ? medical.substring(0, 20) + (medical.length > 20 ? "..." : "") : ""}</span>`);
      $row.find("td").eq(colIndex++).html(`<span class="display-creation-date">${creationTimestamp ? new Date(creationTimestamp * 1000).toISOString().split("T")[0] : "N/A"}</span>`);
      $row.find("td").eq(colIndex++).html(`<span class="display-past-events">No past events.</span>`);
    }
    $row.find(".actions").html(`
      <a href="#" class="edit-player" data-index="${index}" data-user-id="${userId}" aria-label="Edit player ${firstName || ""}" aria-expanded="false">Edit</a>
    `);
    $table.find(`.medical-row[data-player-index="${index}"]`).remove();
    intersoccerState.editingIndex = null;
    $table.find(".edit-player").removeClass("disabled").attr("aria-disabled", "false");

    if (isAdmin && typeof intersoccerApplyFilters === "function") intersoccerApplyFilters();
  });

  // Delete player
  $container.on("click", ".delete-player", function (e) {
    e.preventDefault();
    if (intersoccerState.isProcessing) return;
    const $row = $(this).closest("tr");
    const index = $row.data("player-index");
    const userId = $row.data("user-id") || intersoccerPlayer.user_id;
    if (!confirm("Are you sure you want to delete this player?")) return;

    intersoccerState.isProcessing = true;
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
        if (response.success) {
          $message.text(response.data.message).show();
          setTimeout(() => $message.hide(), 10000);
          $row.remove();
          const $medicalRow = $table.find(`tr.medical-row[data-player-index="${index}"]`);
          if ($medicalRow.length) $medicalRow.remove();
          if (!$table.find("tr[data-player-index]").length) {
            $table.find(".no-players").remove();
            $table.find(".add-player-section").before(`<tr class="no-players"><td colspan="${isAdmin ? 11 : 7}">No attendees added yet.</td></tr>`);
          }
          intersoccerState.editingIndex = null;
          $table.find(".edit-player").removeClass("disabled").attr("aria-disabled", "false");
          if (isAdmin && typeof intersoccerApplyFilters === "function") intersoccerApplyFilters();
        } else {
          console.error("InterSoccer: Delete AJAX response failure:", response.data?.message);
          $message.text(response.data.message || "Failed to delete player.").show();
          setTimeout(() => $message.hide(), 10000);
        }
      },
      error: (xhr) => {
        console.error("InterSoccer: Delete AJAX error:", xhr.status, xhr.responseText);
        if (xhr.status === 403 && !intersoccerState.nonceRetryAttempted) {
          if (debugEnabled) console.log("InterSoccer: 403 error, attempting nonce refresh (once)");
          intersoccerState.nonceRetryAttempted = true;
          intersoccerRefreshNonce().then(() => {
            if (debugEnabled) console.log("InterSoccer: Retrying AJAX with new nonce:", intersoccerPlayer.nonce);
            data.nonce = intersoccerPlayer.nonce;
            $.ajax(this);
          }).catch((error) => {
            console.error("InterSoccer: Nonce refresh failed:", error);
            $message.text("Error: Failed to refresh security token.").show();
            setTimeout(() => $message.hide(), 10000);
            intersoccerState.nonceRetryAttempted = false;
          });
        } else {
          $message.text("Error: Unable to delete player - " + (xhr.responseText || "Unknown error")).show();
          setTimeout(() => $message.hide(), 10000);
          intersoccerState.nonceRetryAttempted = false;
        }
      },
      complete: () => {
        intersoccerState.isProcessing = false;
        intersoccerState.nonceRetryAttempted = false;
      },
    });
  });
})(jQuery);
