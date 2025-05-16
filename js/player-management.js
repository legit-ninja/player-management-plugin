/**
 * File: player-management.js
 * Description: Manages the player management form on the WooCommerce My Account page at /my-account/manage-players/. Supports adding players via a form, quick editing via inline fields, and deleting players, all with AJAX for dynamic updates. Uses text hyperlinks for Edit/Delete actions and Flatpickr for date picking.
 * Dependencies: jQuery, Flatpickr
 */

jQuery(document).ready(function ($) {
  // Validate intersoccerCheckout
  if (!intersoccerCheckout || !intersoccerCheckout.ajax_url) {
    console.error("intersoccerCheckout not initialized.");
    return;
  }

  // Check for container
  const $container = $(".intersoccer-player-management");
  if (!$container.length) {
    console.error(
      "Player management container (.intersoccer-player-management) not found."
    );
    return;
  }

  const $form = $("#player-form");
  const $table = $("#players-table");
  const $message = $form.find(".message");

  // Initialize Flatpickr
  $(".date-picker").flatpickr({
    dateFormat: "Y-m-d",
    maxDate: "today",
  });

  // Add player
  $form.on("submit", function (e) {
    e.preventDefault();
    const firstName = $("#player_first_name").val().trim();
    const lastName = $("#player_last_name").val().trim();
    const dob = $("#player_dob").val();
    const gender = $("#player_gender").val();
    const medical = $("#player_medical").val();
    const region = $("#player_region").val();

    if (!firstName || !lastName) {
      $message.text("Please enter first and last names.").show();
      setTimeout(() => $message.hide(), 5000);
      return;
    }

    $.ajax({
      url: intersoccerCheckout.ajax_url,
      type: "POST",
      data: {
        action: "intersoccer_add_player",
        nonce: intersoccerCheckout.nonce,
        user_id: intersoccerCheckout.user_id,
        player_first_name: firstName,
        player_last_name: lastName,
        player_dob: dob,
        player_gender: gender,
        player_medical: medical,
        player_region: region,
      },
      success: function (response) {
        if (response.success) {
          $message.text(response.data.message).show();
          setTimeout(() => $message.hide(), 5000);
          const player = response.data.player;
          const index = $table.find("tr").length / 2; // Account for quick-edit rows
          const row = `
                      <tr data-player-index="${index}">
                          <td class="display-first-name">${
                            player.first_name || "N/A"
                          }</td>
                          <td class="display-last-name">${
                            player.last_name || "N/A"
                          }</td>
                          <td class="display-dob">${player.dob || "N/A"}</td>
                          <td class="display-gender">${
                            player.gender || "N/A"
                          }</td>
                          <td class="display-medical">${
                            player.medical_conditions || "None"
                          }</td>
                          <td class="display-region">${
                            player.region || "N/A"
                          }</td>
                          <td>
                              <a href="#" class="quick-edit-player" data-index="${index}" aria-label="Quick Edit player ${
            player.first_name || ""
          }">Edit</a> /
                              <a href="#" class="delete-player" data-index="${index}" aria-label="Delete player ${
            player.first_name || ""
          }">Delete</a>
                          </td>
                      </tr>
                      <tr class="quick-edit-row" data-player-index="${index}" style="display: none;">
                          <td><input type="text" class="edit-first-name" value="${
                            player.first_name || ""
                          }" required></td>
                          <td><input type="text" class="edit-last-name" value="${
                            player.last_name || ""
                          }" required></td>
                          <td><input type="text" class="edit-dob date-picker" value="${
                            player.dob || ""
                          }" placeholder="YYYY-MM-DD"></td>
                          <td>
                              <select class="edit-gender">
                                  <option value="" ${
                                    !player.gender ? "selected" : ""
                                  }>Select Gender</option>
                                  <option value="male" ${
                                    player.gender === "male" ? "selected" : ""
                                  }>Male</option>
                                  <option value="female" ${
                                    player.gender === "female" ? "selected" : ""
                                  }>Female</option>
                                  <option value="other" ${
                                    player.gender === "other" ? "selected" : ""
                                  }>Other</option>
                              </select>
                          </td>
                          <td><textarea class="edit-medical">${
                            player.medical_conditions || ""
                          }</textarea></td>
                          <td><input type="text" class="edit-region" value="${
                            player.region || ""
                          }"></td>
                          <td>
                              <button class="button save-player" data-index="${index}">Save</button>
                              <button class="button cancel-edit">Cancel</button>
                          </td>
                      </tr>
                  `;
          $table.find('tr:contains("No players added yet")').remove();
          $table.append(row);
          $form[0].reset();
          $(".date-picker").flatpickr({
            dateFormat: "Y-m-d",
            maxDate: "today",
          });
        } else {
          $message
            .text(response.data.message || "Failed to add player.")
            .show();
          setTimeout(() => $message.hide(), 5000);
        }
      },
      error: function (xhr) {
        $message.text("Error: " + (xhr.responseText || "Unknown error")).show();
        setTimeout(() => $message.hide(), 5000);
      },
    });
  });

  // Quick Edit
  $table.on("click", ".quick-edit-player", function (e) {
    e.preventDefault();
    const index = $(this).data("index");
    const $row = $table.find(
      `tr[data-player-index="${index}"]:not(.quick-edit-row)`
    );
    const $editRow = $table.find(
      `tr.quick-edit-row[data-player-index="${index}"]`
    );
    $row.hide();
    $editRow.show();
    $editRow
      .find(".date-picker")
      .flatpickr({ dateFormat: "Y-m-d", maxDate: "today" });
  });

  // Save Quick Edit
  $table.on("click", ".save-player", function (e) {
    e.preventDefault();
    const index = $(this).data("index");
    const $editRow = $table.find(
      `tr.quick-edit-row[data-player-index="${index}"]`
    );
    const firstName = $editRow.find(".edit-first-name").val().trim();
    const lastName = $editRow.find(".edit-last-name").val().trim();
    const dob = $editRow.find(".edit-dob").val();
    const gender = $editRow.find(".edit-gender").val();
    const medical = $editRow.find(".edit-medical").val();
    const region = $editRow.find(".edit-region").val();

    if (!firstName || !lastName) {
      $message.text("Please enter first and last names.").show();
      setTimeout(() => $message.hide(), 5000);
      return;
    }

    $.ajax({
      url: intersoccerCheckout.ajax_url,
      type: "POST",
      data: {
        action: "intersoccer_edit_player",
        nonce: intersoccerCheckout.nonce,
        user_id: intersoccerCheckout.user_id,
        player_index: index,
        player_first_name: firstName,
        player_last_name: lastName,
        player_dob: dob,
        player_gender: gender,
        player_medical: medical,
        player_region: region,
      },
      success: function (response) {
        if (response.success) {
          $message.text(response.data.message).show();
          setTimeout(() => $message.hide(), 5000);
          const player = response.data.player;
          const $row = $table.find(
            `tr[data-player-index="${index}"]:not(.quick-edit-row)`
          );
          $row.find(".display-first-name").text(player.first_name || "N/A");
          $row.find(".display-last-name").text(player.last_name || "N/A");
          $row.find(".display-dob").text(player.dob || "N/A");
          $row.find(".display-gender").text(player.gender || "N/A");
          $row
            .find(".display-medical")
            .text(player.medical_conditions || "None");
          $row.find(".display-region").text(player.region || "N/A");
          $row.show();
          $editRow.hide();
        } else {
          $message
            .text(response.data.message || "Failed to update player.")
            .show();
          setTimeout(() => $message.hide(), 5000);
        }
      },
      error: function (xhr) {
        $message.text("Error: " + (xhr.responseText || "Unknown error")).show();
        setTimeout(() => $message.hide(), 5000);
      },
    });
  });

  // Cancel Quick Edit
  $table.on("click", ".cancel-edit", function (e) {
    e.preventDefault();
    const $editRow = $(this).closest(".quick-edit-row");
    const index = $editRow.data("player-index");
    $editRow.hide();
    $table.find(`tr[data-player-index="${index}"]:not(.quick-edit-row)`).show();
  });

  // Delete player
  $table.on("click", ".delete-player", function (e) {
    e.preventDefault();
    const index = $(this).data("index");
    if (!confirm("Are you sure you want to delete this player?")) {
      return;
    }

    $.ajax({
      url: intersoccerCheckout.ajax_url,
      type: "POST",
      data: {
        action: "intersoccer_delete_player",
        nonce: intersoccerCheckout.nonce,
        user_id: intersoccerCheckout.user_id,
        player_index: index,
      },
      success: function (response) {
        if (response.success) {
          $message.text(response.data.message).show();
          setTimeout(() => $message.hide(), 5000);
          $table.find(`tr[data-player-index="${index}"]`).remove();
          if (!$table.find("tr:not(.quick-edit-row)").length) {
            $table.append(
              '<tr><td colspan="7">No players added yet.</td></tr>'
            );
          }
        } else {
          $message
            .text(response.data.message || "Failed to delete player.")
            .show();
          setTimeout(() => $message.hide(), 5000);
        }
      },
      error: function (xhr) {
        $message
          .text(
            "Error deleting player: " + (xhr.responseText || "Unknown error")
          )
          .show();
        setTimeout(() => $message.hide(), 5000);
      },
    });
  });
});

