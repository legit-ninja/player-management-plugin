(function ($) {
  // Check if intersoccerPlayer is initialized
  if (
    typeof intersoccerPlayer === "undefined" ||
    !intersoccerPlayer.ajax_url ||
    !intersoccerPlayer.nonce
  ) {
    console.warn("InterSoccer: intersoccerPlayer is not initialized. Player management will not work.");
    return;
  }

  // Target the Manage Players section
  const $managePlayers = $(".intersoccer-player-management");
  if (!$managePlayers.length) {
    console.warn("InterSoccer: Manage Players container not found on the page.");
    return;
  }

  const $tableBody = $managePlayers.find(".wp-list-table tbody");
  const $message = $managePlayers.find(".intersoccer-message");
  const debugEnabled = intersoccerPlayer.debug === "1";

  // Initialize Flatpickr on date inputs with error handling
  try {
    flatpickr(".date-picker", {
      dateFormat: "Y-m-d",
      maxDate: "today",
      enableTime: false,
      allowInput: true,
      clickOpens: true,
      altInput: true,
      altFormat: "F j, Y",
      onClose: function (selectedDates, dateStr, instance) {
        instance.element.value = dateStr;
      },
    });
  } catch (e) {
    console.error("InterSoccer: Flatpickr initialization failed:", e);
  }

  // Populate table with preloaded players
  function populatePlayers() {
    if (
      intersoccerPlayer.preload_players &&
      Object.keys(intersoccerPlayer.preload_players).length > 0
    ) {
      console.log("InterSoccer: Populating table with preloaded players:", intersoccerPlayer.preload_players);
      $tableBody.empty();
      Object.keys(intersoccerPlayer.preload_players).forEach((index) => {
        const player = intersoccerPlayer.preload_players[index];
        if (!player.first_name || !player.last_name) {
          console.warn("InterSoccer: Player data missing first_name or last_name at index:", index);
          return;
        }
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
              data-city="${player.city || ''}">
            <td class="display-first-name">${player.first_name || 'N/A'}</td>
            <td class="display-last-name">${player.last_name || 'N/A'}</td>
            <td class="display-dob">${player.dob || 'N/A'}</td>
            <td class="display-gender">${player.gender || 'N/A'}</td>
            <td class="display-avs-number">${player.avs_number || 'N/A'}</td>
            <td class="display-event-count">${player.event_count || 0}</td>
            <td class="actions">
              <a href="#" class="edit-player" data-index="${index}" data-user-id="${player.user_id || intersoccerPlayer.user_id}" aria-label="Edit player ${player.first_name || ''}" aria-expanded="false">Edit</a>
            </td>
          </tr>
        `;
        $tableBody.append(rowHtml);
      });
      if (!Object.keys(intersoccerPlayer.preload_players).length) {
        $tableBody.html('<tr class="no-players"><td colspan="7">No attendees added yet.</td></tr>');
      }
    } else {
      console.log("InterSoccer: No preloaded players, falling back to AJAX fetch");
      fetchUserPlayers();
    }
  }

  // Fetch players via AJAX (fallback)
  function fetchUserPlayers() {
    $.ajax({
      url: intersoccerPlayer.ajax_url,
      type: "POST",
      data: {
        action: "intersoccer_get_user_players",
        nonce: intersoccerPlayer.nonce,
        user_id: intersoccerPlayer.user_id,
      },
      contentType: "application/x-www-form-urlencoded; charset=UTF-8",
      beforeSend: function () {
        $tableBody.addClass("loading");
        if (debugEnabled) console.log("InterSoccer: Fetching players via AJAX");
      },
      success: function (response) {
        console.log("InterSoccer: Players fetch response:", response);
        if (
          response.success &&
          response.data.players &&
          Array.isArray(response.data.players)
        ) {
          $tableBody.empty();
          response.data.players.forEach((player, index) => {
            if (!player.first_name || !player.last_name) {
              console.warn("InterSoccer: Player data missing first_name or last_name at index:", index);
              return;
            }
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
                  data-city="${player.city || ''}">
                <td class="display-first-name">${player.first_name || 'N/A'}</td>
                <td class="display-last-name">${player.last_name || 'N/A'}</td>
                <td class="display-dob">${player.dob || 'N/A'}</td>
                <td class="display-gender">${player.gender || 'N/A'}</td>
                <td class="display-avs-number">${player.avs_number || 'N/A'}</td>
                <td class="display-event-count">${player.event_count || 0}</td>
                <td class="actions">
                  <a href="#" class="edit-player" data-index="${index}" data-user-id="${player.user_id || intersoccerPlayer.user_id}" aria-label="Edit player ${player.first_name || ''}" aria-expanded="false">Edit</a>
                </td>
              </tr>
            `;
            $tableBody.append(rowHtml);
          });
          if (!response.data.players.length) {
            $tableBody.html('<tr class="no-players"><td colspan="7">No attendees added yet.</td></tr>');
          }
        } else {
          $tableBody.html('<tr><td colspan="7">Error loading players. Please try again.</td></tr>');
          $message.text("Error loading players. Please try again.").show();
          setTimeout(() => $message.hide(), 5000);
        }
      },
      error: function (xhr, status, error) {
        console.error("InterSoccer: Players fetch error:", status, error, xhr.responseText);
        $tableBody.html('<tr><td colspan="7">Error loading players. Please try again.</td></tr>');
        $message.text("Error loading players: " + (xhr.responseText || "Unknown error")).show();
        setTimeout(() => $message.hide(), 5000);
      },
      complete: function () {
        $tableBody.removeClass("loading");
      },
    });
  }

  // Handle form submission to add a player
  $managePlayers.on("click", ".player-submit", function (e) {
    e.preventDefault();
    const $row = $(this).closest("tr");
    const isAdd = $row.hasClass("add-player-section");
    if (intersoccerState.isProcessing || (isAdd && intersoccerState.isAdding)) return;

    if (!intersoccerValidateRow($row, isAdd)) return;

    intersoccerState.isProcessing = true;
    if (isAdd) intersoccerState.isAdding = true;
    const $spinner = $row.find(".spinner").show();
    const playerData = {
      player_first_name: $row.find("#player_first_name").val(),
      player_last_name: $row.find("#player_last_name").val(),
      player_dob: `${$row.find("#player_dob_year").val()}-${$row.find("#player_dob_month").val()}-${$row.find("#player_dob_day").val()}`,
      player_gender: $row.find("#player_gender").val(),
      player_avs_number: $row.find("#player_avs_number").val() || "0000",
      player_medical: $row.next(".add-player-medical").find("#player_medical").val() || "",
    };

    // Validate required fields
    if (!playerData.player_first_name || !playerData.player_last_name || !playerData.player_dob || !playerData.player_gender) {
      $message.text("First name, last name, date of birth, and gender are required.").show();
      setTimeout(() => $message.hide(), 5000);
      intersoccerState.isProcessing = false;
      intersoccerState.isAdding = false;
      $spinner.hide();
      return;
    }

    if (!/^\d{4}-\d{2}-\d{2}$/.test(playerData.player_dob) || !new Date(playerData.player_dob).getTime()) {
      $message.text("Invalid date of birth format. Use YYYY-MM-DD.").show();
      setTimeout(() => $message.hide(), 5000);
      intersoccerState.isProcessing = false;
      intersoccerState.isAdding = false;
      $spinner.hide();
      return;
    }

    refreshNonce(function (newNonce) {
      if (!newNonce) {
        console.error("InterSoccer: Nonce refresh failed.");
        $message.text("Error: Failed to refresh security token.").show();
        setTimeout(() => $message.hide(), 5000);
        intersoccerState.isProcessing = false;
        intersoccerState.isAdding = false;
        $spinner.hide();
        return;
      }
      if (debugEnabled) console.log("InterSoccer: Sending add player request with data:", JSON.stringify(playerData));
      $.ajax({
        url: intersoccerPlayer.ajax_url,
        type: "POST",
        data: {
          action: "intersoccer_add_player",
          nonce: newNonce || intersoccerPlayer.nonce,
          player_first_name: playerData.player_first_name,
          player_last_name: playerData.player_last_name,
          player_dob: playerData.player_dob,
          player_gender: playerData.player_gender,
          player_avs_number: playerData.player_avs_number,
          player_medical: playerData.player_medical,
          user_id: intersoccerPlayer.user_id,
        },
        contentType: "application/x-www-form-urlencoded; charset=UTF-8",
        success: function (response) {
          if (debugEnabled) console.log("InterSoccer: Add player response:", JSON.stringify(response));
          $spinner.hide();
          if (response.success) {
            $row.find("input, select, textarea").val("");
            $row.next(".add-player-medical").find("textarea").val("");
            $row.removeClass("active");
            $(".toggle-add-player").attr("aria-expanded", "false");
            populatePlayers();
            $message.text(response.data.message || "Player added successfully.").show();
            setTimeout(() => $message.hide(), 5000);
          } else {
            $message.text(response.data.message || "Error adding player.").show();
            setTimeout(() => $message.hide(), 5000);
            if (response.data.new_nonce) {
              intersoccerPlayer.nonce = response.data.new_nonce;
              console.log("InterSoccer: Updated nonce due to failure:", intersoccerPlayer.nonce);
            }
          }
        },
        error: function (xhr, status, error) {
          console.error("InterSoccer: Add player error:", status, error, xhr.responseText, "Response JSON:", JSON.stringify(xhr.responseJSON));
          $message.text("Error adding player: " + (xhr.responseText || "Unknown error")).show();
          setTimeout(() => $message.hide(), 5000);
          refreshNonce(function (newNonce) {
            if (newNonce) {
              intersoccerPlayer.nonce = newNonce;
              console.log("InterSoccer: Refreshed nonce after error:", intersoccerPlayer.nonce);
            }
          });
        },
        complete: function () {
          intersoccerState.isProcessing = false;
          intersoccerState.isAdding = false;
          $spinner.hide();
        },
      });
    });
  });

  // Edit player functionality
  $managePlayers.on("click", ".edit-player", function (e) {
    // Handled by player-management-actions.js
  });

  // Cancel edit
  $managePlayers.on("click", ".cancel-edit", function (e) {
    // Handled by player-management-actions.js
  });

  // Delete player
  $managePlayers.on("click", ".delete-player", function (e) {
    // Handled by player-management-actions.js
  });

  // Handle first login modal
  if ($('#intersoccer-first-login').length) {
    $('#player-modal').show();
    $('#add-player-form').submit(function (e) {
      e.preventDefault();
      const playerData = {
        player_first_name: $('#player-first-name').val(),
        player_last_name: $('#player-last-name').val(),
        player_dob: $('#player-dob').val(),
        player_gender: $('#player-gender').val(),
        player_avs_number: $('#player-avs-number').val() || "0000",
        player_medical: $('#player-medical').val(),
      };

      if (!playerData.player_first_name || !playerData.player_last_name || !playerData.player_dob || !playerData.player_gender) {
        $message.text("First name, last name, date of birth, and gender are required.").show();
        setTimeout(() => $message.hide(), 5000);
        return;
      }

      refreshNonce(function (newNonce) {
        if (!newNonce) {
          console.error("InterSoccer: Nonce refresh failed.");
          $message.text("Error: Failed to refresh security token.").show();
          setTimeout(() => $message.hide(), 5000);
          return;
        }
        if (debugEnabled) console.log("InterSoccer: Sending add player request for first login, data:", JSON.stringify(playerData));
        $.post(intersoccerPlayer.ajax_url, {
          action: 'intersoccer_add_player',
          nonce: newNonce || intersoccerPlayer.nonce,
          player_first_name: playerData.player_first_name,
          player_last_name: playerData.player_last_name,
          player_dob: playerData.player_dob,
          player_gender: playerData.player_gender,
          player_avs_number: playerData.player_avs_number,
          player_medical: playerData.player_medical,
          user_id: intersoccerPlayer.user_id,
        }, function (response) {
          if (debugEnabled) console.log("InterSoccer: First login add player response:", JSON.stringify(response));
          if (response.success) {
            window.location.reload();
          } else {
            $message.text(response.data.message || "Error adding player.").show();
            setTimeout(() => $message.hide(), 5000);
            if (response.data.new_nonce) {
              intersoccerPlayer.nonce = response.data.new_nonce;
              console.log("InterSoccer: Updated nonce due to failure:", intersoccerPlayer.nonce);
            }
          }
        }).fail(function (xhr, status, error) {
          console.error("InterSoccer: Add player error:", status, error, xhr.responseText, "Response JSON:", JSON.stringify(xhr.responseJSON));
          $message.text("Error adding player: " + (xhr.responseText || "Unknown error")).show();
          setTimeout(() => $message.hide(), 5000);
          refreshNonce(function (newNonce) {
            if (newNonce) {
              intersoccerPlayer.nonce = newNonce;
              console.log("InterSoccer: Refreshed nonce after error:", intersoccerPlayer.nonce);
            }
          });
        });
      });
    });
  }

  // Initial fetch
  populatePlayers();

  // Refresh nonce function with callback
  function refreshNonce(callback) {
    $.get(intersoccerPlayer.nonce_refresh_url, function (response) {
      if (response.success) {
        const newNonce = response.data.nonce;
        intersoccerPlayer.nonce = newNonce;
        console.log("InterSoccer: Nonce refreshed:", newNonce);
        if (callback) callback(newNonce);
      } else {
        console.error("InterSoccer: Nonce refresh failed:", response);
        if (callback) callback(null);
      }
    }).fail(function (xhr, status, error) {
      console.error("InterSoccer: Nonce refresh error:", status, error);
      if (callback) callback(null);
    });
  }
})(jQuery);
