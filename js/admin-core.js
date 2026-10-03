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

  /**
   * Escape text for safe use in HTML text nodes and double-quoted attributes.
   */
  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
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
        const userId = player.user_id || intersoccerPlayer.user_id;
        const firstName = player.first_name || 'N/A';
        const lastName = player.last_name || 'N/A';
        const dob = player.dob || 'N/A';
        const gender = player.gender || 'N/A';
        const avsNumber = player.avs_number || 'N/A';
        const eventCount = player.event_count || 0;
        const canton = player.canton || '';
        const city = player.city || '';
        const creationTimestamp = player.creation_timestamp || '';
        const medical = player.medical_conditions || '';
        const medicalPreview = medical.substring(0, 20) + (medical.length > 20 ? '...' : '');
        const rowHtml = `
          <tr data-player-index="${escapeHtml(index)}"
              data-user-id="${escapeHtml(userId)}"
              data-first-name="${escapeHtml(firstName)}"
              data-last-name="${escapeHtml(lastName)}"
              data-dob="${escapeHtml(dob)}"
              data-gender="${escapeHtml(gender)}"
              data-avs-number="${escapeHtml(avsNumber)}"
              data-event-count="${escapeHtml(eventCount)}"
              data-canton="${escapeHtml(canton)}"
              data-city="${escapeHtml(city)}"
              data-creation-timestamp="${escapeHtml(creationTimestamp)}"
              data-medical-conditions="${escapeHtml(encodeURIComponent(medical))}">
              <td class="display-user-id">${escapeHtml(userId)}</td>
              <td class="display-canton">${escapeHtml(canton)}</td>
              <td class="display-city">${escapeHtml(city)}</td>
              <td class="display-first-name">${escapeHtml(firstName)}</td>
              <td class="display-last-name">${escapeHtml(lastName)}</td>
              <td class="display-dob">${escapeHtml(dob)}</td>
              <td class="display-gender">${escapeHtml(translateGender(gender))}</td>
              <td class="display-avs-number">${escapeHtml(avsNumber)}</td>
              <td class="display-medical-conditions">${escapeHtml(medicalPreview)}</td>
              <td class="actions">
                  <a href="#" class="edit-player" data-index="${escapeHtml(index)}" data-user-id="${escapeHtml(userId)}" aria-label="Edit player ${escapeHtml(firstName)}" aria-expanded="false">Edit</a>
                  <a href="#" class="delete-player" data-index="${escapeHtml(index)}" data-user-id="${escapeHtml(userId)}" aria-label="Delete player ${escapeHtml(firstName)}">Delete</a>
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