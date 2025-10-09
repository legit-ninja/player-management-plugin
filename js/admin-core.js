jQuery(document).ready(function($) {
  const debugEnabled = window.intersoccerPlayer && intersoccerPlayer.debug === "1";
  if (!window.intersoccerPlayer || !intersoccerPlayer.ajax_url || !intersoccerPlayer.nonce || typeof intersoccerValidateRow === "undefined") {
    console.error("InterSoccer: Dependencies not loaded for admin actions. Details:", {
      intersoccerPlayer: typeof intersoccerPlayer !== "undefined" ? intersoccerPlayer : "undefined",
      ajax_url: intersoccerPlayer ? intersoccerPlayer.ajax_url : "undefined",
      nonce: intersoccerPlayer ? intersoccerPlayer.nonce : "undefined",
      intersoccerValidateRow: typeof intersoccerValidateRow !== "undefined" ? "defined" : "undefined",
      intersoccerApplyFilters: typeof intersoccerApplyFilters !== "undefined" ? "defined" : "undefined"
    });
    return;
  }

  const $container = $(".intersoccer-player-management");
  const $table = $("#player-table");
  const $message = $container.find(".intersoccer-message");

  // State management
  const intersoccerState = {
    isProcessing: false,
    isAdding: false,
    editingIndex: null,
    lastClickTime: 0,
    lastEditClickTime: 0,
    lastToggleClickTime: 0,
    clickDebounceMs: 1000,
    editClickDebounceMs: 1000,
    toggleDebounceMs: 1000,
    nonceRetryAttempted: false
  };

  // Refresh nonce
  window.intersoccerRefreshNonce = function() {
    return new Promise((resolve, reject) => {
      $.ajax({
        url: intersoccerPlayer.nonce_refresh_url,
        type: "POST",
        data: { action: "intersoccer_refresh_nonce" },
        success: (response) => {
          if (response.success && response.data.nonce) {
            intersoccerPlayer.nonce = response.data.nonce;
            if (debugEnabled) console.log("InterSoccer: Nonce refreshed:", intersoccerPlayer.nonce);
            resolve();
          } else {
            console.error("InterSoccer: Failed to refresh nonce:", response.data?.message);
            reject(new Error(response.data?.message || "Failed to refresh nonce"));
          }
        },
        error: (xhr) => {
          console.error("InterSoccer: Nonce refresh AJAX error:", xhr.status, xhr.responseText);
          reject(new Error("Nonce refresh failed"));
        }
      });
    });
  };

  // Fetch player data via AJAX
  function fetchPlayerData(userId, index, callback) {
    const startTime = Date.now();
    if (debugEnabled) {
      console.log("InterSoccer: Starting fetchPlayerData for userId:", userId, "index:", index, "at", new Date(startTime).toISOString());
    }
    $.ajax({
      url: intersoccerPlayer.ajax_url,
      type: "POST",
      data: {
        action: "intersoccer_get_player",
        nonce: intersoccerPlayer.nonce,
        user_id: userId,
        player_index: index,
        is_admin: "1"
      },
      success: function(response) {
        const endTime = Date.now();
        if (debugEnabled) {
          console.log("InterSoccer: fetchPlayerData completed at", new Date(endTime).toISOString(), "Duration:", (endTime - startTime), "ms", "Response:", JSON.stringify(response));
        }
        if (response.success && response.data.player) {
          if (debugEnabled) console.log("InterSoccer: Fetched player data:", response.data.player);
          callback(response.data.player);
        } else {
          console.error("InterSoccer: Failed to fetch player data:", response.data?.message || "Unknown error");
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

  // Admin-specific populatePlayers
  function populatePlayers(playersData = null) {
    if (debugEnabled) console.log("InterSoccer: Before populatePlayers, intersoccerPlayer:", JSON.stringify(intersoccerPlayer));
    const $tableBody = $table.find("tbody");
    $tableBody.empty();
    const players = playersData || (intersoccerPlayer.preload_players || []);
    if (debugEnabled) console.log("InterSoccer: Players data to populate:", JSON.stringify(players));
    if (players && Array.isArray(players) && players.length > 0) {
      players.forEach((player, index) => {
        if (!player || typeof player !== 'object' || !player.first_name || !player.last_name) {
          console.warn("InterSoccer: Invalid or missing player data at index:", index, JSON.stringify(player));
          return;
        }
        if (debugEnabled) console.log('InterSoccer: Player data for index ' + index + ':', JSON.stringify(player));
        const rowHtml = `
          <tr data-player-index="${index}"
              data-user-id="${player.user_id || intersoccerPlayer.user_id}"
              data-first-name="${player.first_name || 'N/A'}"
              data-last-name="${player.last_name || 'N/A'}"
              data-dob="${player.dob || 'N/A'}"
              data-gender="${player.gender || 'N/A'}"
              data-avs-number="${player.avs_number || 'N/A'}"
              data-event-count="${player.event_count || 0}"
              data-canton="${player.canton || ''}"
              data-city="${player.city || ''}"
              data-creation-timestamp="${player.creation_timestamp || ''}"
              data-medical-conditions="${encodeURIComponent(player.medical_conditions || '')}">
              <td class="display-user-id">${player.user_id || intersoccerPlayer.user_id}</td>
              <td class="display-canton">${player.canton || ''}</td>
              <td class="display-city">${player.city || ''}</td>
              <td class="display-first-name">${player.first_name || 'N/A'}</td>
              <td class="display-last-name">${player.last_name || 'N/A'}</td>
              <td class="display-dob">${player.dob || 'N/A'}</td>
              <td class="display-gender">${player.gender || 'N/A'}</td>
              <td class="display-avs-number">${player.avs_number || 'N/A'}</td>
              <td class="display-medical-conditions">${(player.medical_conditions || '').substring(0, 20) + ((player.medical_conditions || '').length > 20 ? '...' : '')}</td>
              <td class="actions">
                  <a href="#" class="edit-player" data-index="${index}" data-user-id="${player.user_id || intersoccerPlayer.user_id}" aria-label="Edit player ${player.first_name || ''}" aria-expanded="false">Edit</a>
                  <a href="#" class="delete-player" data-index="${index}" data-user-id="${player.user_id || intersoccerPlayer.user_id}" aria-label="Delete player ${player.first_name || ''}">Delete</a>
              </td>
          </tr>
        `;
        $tableBody.append(rowHtml);
      });
    } else {
      if (debugEnabled) console.log("InterSoccer: No valid players to display, showing empty table");
      $tableBody.html('<tr class="no-players"><td colspan="10">No players added yet.</td></tr>');
    }
  }

  // Initial fetch for admin
  if (intersoccerPlayer.is_admin === "1" && intersoccerPlayer.context !== "user_profile") {
    if (debugEnabled) console.log("InterSoccer: Admin context confirmed, rendering full table");
    populatePlayers();
  }
});