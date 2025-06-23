jQuery(document).ready(function ($) {
  // Check if intersoccerCheckout is initialized
  if (
    typeof intersoccerCheckout === "undefined" ||
    !intersoccerCheckout.ajax_url
  ) {
    console.warn(
      "intersoccerCheckout is not initialized. Player management will not work."
    );
    return;
  }

  // Target the Manage Players section
  const $managePlayers = $(".intersoccer-player-management");
  if (!$managePlayers.length) {
    console.warn("Manage Players container not found on the page.");
    return;
  }

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
    console.error("Flatpickr initialization failed:", e);
  }

  // Fetch and display players
  function fetchUserPlayers() {
    $.ajax({
      url: intersoccerCheckout.ajax_url,
      type: "POST",
      data: {
        action: "intersoccer_get_user_players",
        nonce: intersoccerCheckout.nonce,
        user_id: intersoccerCheckout.user_id,
      },
      contentType: "application/x-www-form-urlencoded; charset=UTF-8",
      success: function (response) {
        console.log("Players fetch response:", response);
        if (
          response.success &&
          response.data.players &&
          Array.isArray(response.data.players)
        ) {
          const $tableBody = $managePlayers.find(".wp-list-table tbody");
          if (!$tableBody.length) {
            console.warn("Table body not found in Manage Players section.");
            return;
          }
          $tableBody.empty();
          response.data.players.forEach((player, index) => {
            if (!player.name) {
              console.warn("Player data missing name at index:", index);
              return;
            }
            $tableBody.append(`
                            <tr data-player-index="${index}">
                                <td>${player.name}</td>
                                <td>${player.dob || "N/A"}</td>
                                <td>${player.gender || "N/A"}</td>
                                <td>${player.medical_conditions || "None"}</td>
                                <td>
                                    <button class="button edit-player" data-index="${index}" aria-label="Edit player ${
              player.name
            }">
                                        Edit
                                    </button>
                                    <button class="button delete-player" data-index="${index}" aria-label="Delete player ${
              player.name
            }">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        `);
          });
        } else {
          $managePlayers
            .find(".wp-list-table tbody")
            .html('<tr><td colspan="5">No players found.</td></tr>');
        }
      },
      error: function (xhr, status, error) {
        console.error("Players fetch error:", status, error, xhr.responseText);
        $managePlayers
          .find(".wp-list-table tbody")
          .html(
            '<tr><td colspan="5">Error loading players. Please try again.</td></tr>'
          );
      },
    });
  }

  // Handle form submission to add a player
  $managePlayers.on("submit", "#add-player-form", function (e) {
    e.preventDefault();
    const $form = $(this);
    const $spinner = $form.find(".spinner");
    const playerData = {
      player_name: $form.find("#player_name").val(),
      player_dob: $form.find("#player_dob").val(),
      player_gender: $form.find("#player_gender").val(),
      player_medical: $form.find("#player_medical").val(),
    };

    // Validate required fields
    if (!playerData.player_name || !playerData.player_dob) {
      alert("Player name and date of birth are required.");
      return;
    }

    if (
      !/^\d{4}-\d{2}-\d{2}$/.test(playerData.player_dob) ||
      !new Date(playerData.player_dob).getTime()
    ) {
      alert("Invalid date of birth format. Use YYYY-MM-DD.");
      return;
    }

    // Refresh nonce before sending AJAX
    refreshNonce(function (newNonce) {
      if (!newNonce) {
        console.error("Nonce refresh failed.");
        return;
      }
      $spinner.show();
      $.ajax({
        url: intersoccerCheckout.ajax_url,
        type: "POST",
        data: {
          action: "intersoccer_add_player",
          nonce: newNonce || intersoccerCheckout.nonce,
          player_name: playerData.player_name,
          player_dob: playerData.player_dob,
          player_gender: playerData.player_gender,
          player_medical: playerData.player_medical,
          user_id: intersoccerCheckout.user_id,
        },
        contentType: "application/x-www-form-urlencoded; charset=UTF-8",
        success: function (response) {
          console.log("Add player response:", response);
          $spinner.hide();
          if (response.success) {
            $form[0].reset();
            fetchUserPlayers();
          } else {
            alert(response.data.message || "Error adding player.");
            if (response.data.new_nonce) {
              intersoccerCheckout.nonce = response.data.new_nonce;
              console.log("Updated nonce due to failure: ", intersoccerCheckout.nonce);
            }
          }
        },
        error: function (xhr, status, error) {
          console.error("Add player error:", status, error, xhr.responseText);
          $spinner.hide();
          alert("Error adding player: " + (xhr.responseText || "Unknown error") + " (Status: " + xhr.status + ")");
          refreshNonce(function (newNonce) {
            if (newNonce) {
              intersoccerCheckout.nonce = newNonce;
              console.log("Refreshed nonce after error: ", intersoccerCheckout.nonce);
            }
          });
        },
      });
    });
  });

  // Edit player functionality
  $managePlayers.on("click", ".edit-player", function () {
    const index = $(this).data("index");
    const $row = $(this).closest("tr");
    const playerData = $row
      .find("td")
      .map(function () {
        return $(this).text();
      })
      .get();

    const editForm = `
            <tr class="edit-form">
                <td colspan="5">
                    <form class="update-player-form">
                        <p>
                            <label for="edit-player-name">Name:</label>
                            <input type="text" id="edit-player-name" name="name" value="${
                              playerData[0]
                            }" required>
                        </p>
                        <p>
                            <label for="edit-player-dob">Date of Birth (YYYY-MM-DD):</label>
                            <input type="text" id="edit-player-dob" name="dob" class="date-picker" value="${
                              playerData[1] !== "N/A" ? playerData[1] : ""
                            }" pattern="\\d{4}-\\d{2}-\\d{2}" placeholder="YYYY-MM-DD">
                        </p>
                        <p>
                            <label for="edit-player-gender">Gender:</label>
                            <select id="edit-player-gender" name="gender">
                                <option value="" ${
                                  playerData[2] === "N/A" ? "selected" : ""
                                }>Select Gender</option>
                                <option value="male" ${
                                  playerData[2] === "male" ? "selected" : ""
                                }>Male</option>
                                <option value="female" ${
                                  playerData[2] === "female" ? "selected" : ""
                                }>Female</option>
                                <option value="other" ${
                                  playerData[2] === "other" ? "selected" : ""
                                }>Other</option>
                            </select>
                        </p>
                        <p>
                            <label for="edit-player-medical">Medical Conditions:</label>
                            <textarea id="edit-player-medical" name="medical_conditions">${
                              playerData[3] !== "None" ? playerData[3] : ""
                            }</textarea>
                        </p>
                        <p>
                            <button type="submit" class="button update-player" data-index="${index}">Update</button>
                            <button type="button" class="button cancel-edit">Cancel</button>
                        </p>
                    </form>
                </td>
            </tr>
        `;
    $row.after(editForm);
    $row.hide();

    try {
      flatpickr("#edit-player-dob", {
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
      console.error("Flatpickr initialization for edit form failed:", e);
    }
  });

  // Cancel edit
  $managePlayers.on("click", ".cancel-edit", function () {
    const $editRow = $(this).closest("tr.edit-form");
    const $playerRow = $editRow.prev("tr");
    $editRow.remove();
    $playerRow.show();
  });

  // Update player
  $managePlayers.on("submit", ".update-player-form", function (e) {
    e.preventDefault();
    const $form = $(this);
    const index = $form.find(".update-player").data("index");
    const updatedPlayer = {
      name: $form.find('input[name="name"]').val(),
      dob: $form.find('input[name="dob"]').val(),
      gender: $form.find('select[name="gender"]').val(),
      medical_conditions: $form
        .find('textarea[name="medical_conditions"]')
        .val(),
    };

    if (!updatedPlayer.name) {
      alert("Player name is required.");
      return;
    }

    refreshNonce(function (newNonce) {
      if (!newNonce) {
        console.error("Nonce refresh failed.");
        return;
      }
      $.ajax({
        url: intersoccerCheckout.ajax_url,
        type: "POST",
        data: {
          action: "intersoccer_update_player",
          nonce: newNonce || intersoccerCheckout.nonce,
          user_id: intersoccerCheckout.user_id,
          player_index: index,
          player_data: JSON.stringify(updatedPlayer),
        },
        contentType: "application/x-www-form-urlencoded; charset=UTF-8",
        beforeSend: function () {
          console.log("Sending update player request with data:", {
            action: "intersoccer_update_player",
            nonce: newNonce || intersoccerCheckout.nonce,
            user_id: intersoccerCheckout.user_id,
            player_index: index,
            player_data: updatedPlayer,
          });
        },
        success: function (response) {
          console.log("Update player response:", response);
          if (response.success) {
            fetchUserPlayers();
          } else {
            alert(response.data.message || "Error updating player.");
            if (response.data.new_nonce) {
              intersoccerCheckout.nonce = response.data.new_nonce;
              console.log("Updated nonce due to failure: ", intersoccerCheckout.nonce);
            }
          }
        },
        error: function (xhr, status, error) {
          console.error("Update player error:", status, error, xhr.responseText);
          alert(
            "Error updating player: " + (xhr.responseText || "Unknown error")
          );
          refreshNonce(function (newNonce) {
            if (newNonce) {
              intersoccerCheckout.nonce = newNonce;
              console.log("Refreshed nonce after error: ", intersoccerCheckout.nonce);
            }
          });
        },
      });
    });
  });

  // Delete player
  $managePlayers.on("click", ".delete-player", function () {
    const index = $(this).data("index");
    if (!confirm("Are you sure you want to delete this player?")) {
      return;
    }

    refreshNonce(function (newNonce) {
      if (!newNonce) {
        console.error("Nonce refresh failed.");
        return;
      }
      $.ajax({
        url: intersoccerCheckout.ajax_url,
        type: "POST",
        data: {
          action: "intersoccer_delete_player",
          nonce: newNonce || intersoccerCheckout.nonce,
          user_id: intersoccerCheckout.user_id,
          index: index,
        },
        contentType: "application/x-www-form-urlencoded; charset=UTF-8",
        success: function (response) {
          console.log("Delete player response:", response);
          if (response.success) {
            fetchUserPlayers();
          } else {
            alert(response.data.message || "Error deleting player.");
            if (response.data.new_nonce) {
              intersoccerCheckout.nonce = response.data.new_nonce;
              console.log("Updated nonce due to failure: ", intersoccerCheckout.nonce);
            }
          }
        },
        error: function (xhr, status, error) {
          console.error("Delete player error:", status, error, xhr.responseText);
          alert("Error deleting player. Please try again.");
          refreshNonce(function (newNonce) {
            if (newNonce) {
              intersoccerCheckout.nonce = newNonce;
              console.log("Refreshed nonce after error: ", intersoccerCheckout.nonce);
            }
          });
        },
      });
    });
  });

  // Initial fetch
  fetchUserPlayers();

  // Refresh nonce function with callback
  function refreshNonce(callback) {
    $.get(intersoccerCheckout.nonce_refresh_url, function (response) {
      if (response.success) {
        const newNonce = response.data.nonce;
        intersoccerCheckout.nonce = newNonce;
        console.log("Nonce refreshed: ", newNonce);
        if (callback) callback(newNonce);
      } else {
        console.error("Nonce refresh failed:", response);
        if (callback) callback(null);
      }
    }).fail(function (xhr, status, error) {
      console.error("Nonce refresh error:", status, error);
      if (callback) callback(null);
    });
  }
});

jQuery(document).ready(function($) {
    if ($('#intersoccer-first-login').length) {
        $('#player-modal').show();
        $('#add-player-form').submit(function(e) {
            e.preventDefault();
            refreshNonce(function (newNonce) {
                if (!newNonce) {
                    console.error("Nonce refresh failed.");
                    return;
                }
                $.post(intersoccerCheckout.ajax_url, {
                    action: 'intersoccer_add_player',
                    nonce: newNonce || intersoccerCheckout.nonce,
                    first_name: $('#player-first-name').val(),
                    last_name: $('#player-last-name').val()
                }, function(response) {
                    if (response.success) {
                        window.location.reload();
                    } else {
                        alert(response.data.message || "Error adding player.");
                        if (response.data.new_nonce) {
                            intersoccerCheckout.nonce = response.data.new_nonce;
                            console.log("Updated nonce due to failure: ", intersoccerCheckout.nonce);
                        }
                    }
                }).fail(function (xhr, status, error) {
                    console.error("Add player error:", status, error, xhr.responseText);
                    alert("Error adding player: " + (xhr.responseText || "Unknown error"));
                    refreshNonce(function (newNonce) {
                        if (newNonce) {
                            intersoccerCheckout.nonce = newNonce;
                            console.log("Refreshed nonce after error: ", intersoccerCheckout.nonce);
                        }
                    });
                });
            });
        });
    }
});
