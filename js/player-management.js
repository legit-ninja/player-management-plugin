(function ($) {
    if (typeof intersoccerPlayer === "undefined" || !intersoccerPlayer.ajax_url || !intersoccerPlayer.nonce) {
        console.warn("InterSoccer: intersoccerPlayer is not initialized.");
        return;
    }

    const $managePlayers = $(".intersoccer-player-management");
    if (!$managePlayers.length) {
        console.warn("InterSoccer: Manage Players container not found.");
        return;
    }

    const $tableBody = $managePlayers.find("#player-table tbody");
    const $message = $managePlayers.find(".intersoccer-message");
    const debugEnabled = intersoccerPlayer.debug === "1";

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

    // Initialize Flatpickr
    try {
        flatpickr(".date-picker", {
            dateFormat: "Y-m-d",
            maxDate: "today",
            enableTime: false,
            allowInput: true,
            altInput: true,
            altFormat: "F j, Y",
            onClose: function (selectedDates, dateStr, instance) {
                instance.element.value = dateStr;
            },
        });
    } catch (e) {
        console.error("InterSoccer: Flatpickr initialization failed:", e);
    }

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

    function isMobile() {
        return window.innerWidth <= 768;
    }

    function intersoccerRefreshNonce() {
        return new Promise((resolve, reject) => {
            if (debugEnabled) console.log("InterSoccer: Attempting nonce refresh, current nonce:", intersoccerPlayer.nonce);
            $.ajax({
                url: intersoccerPlayer.nonce_refresh_url,
                type: "POST",
                data: { action: "intersoccer_refresh_nonce" },
                success: (response) => {
                    if (debugEnabled) console.log("InterSoccer: Nonce refresh response:", JSON.stringify(response));
                    if (response.success && response.data.nonce) {
                        intersoccerPlayer.nonce = response.data.nonce;
                        if (debugEnabled) console.log("InterSoccer: Nonce refreshed:", intersoccerPlayer.nonce);
                        resolve(response.data.nonce);
                    } else {
                        console.error("InterSoccer: Failed to refresh nonce:", response);
                        reject(new Error(response.data?.message || "Failed to refresh nonce"));
                    }
                },
                error: (xhr) => {
                    console.error("InterSoccer: Nonce refresh AJAX error:", xhr.status, xhr.responseText);
                    reject(new Error("Nonce refresh failed"));
                }
            });
        });
    }

    function populatePlayers(playersData = null) {
        if (debugEnabled) console.log("InterSoccer: Before populatePlayers, intersoccerPlayer:", JSON.stringify(intersoccerPlayer));
        $tableBody.empty();
        const players = playersData || (intersoccerPlayer.preload_players || []);
        if (debugEnabled) console.log("InterSoccer: Players data to populate:", JSON.stringify(players));
        const colspan = $tableBody.find('tr').length > 0 ? $tableBody.find('tr')[0].cells.length : 6; // Dynamic colspan based on table structure
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
                        data-medical-conditions="${player.medical_conditions || ''}">
                        <td class="display-first-name">${player.first_name || 'N/A'}</td>
                        <td class="display-last-name">${player.last_name || 'N/A'}</td>
                        <td class="display-dob">${player.dob || 'N/A'}</td>
                        <td class="display-gender">${translateGender(player.gender || 'N/A')}</td>
                        <td class="display-avs-number">${player.avs_number || 'N/A'}</td>
                        <td class="display-medical-conditions">${(player.medical_conditions || '').substring(0, 20) + ((player.medical_conditions || '').length > 20 ? '...' : '')}</td>
                        <td class="display-event-count">${player.event_count || 0}</td>
                        <td class="actions">
                            <a href="#" class="edit-player" data-index="${index}" data-user-id="${player.user_id || intersoccerPlayer.user_id}" aria-label="Edit player ${player.first_name || ''}" aria-expanded="false">Edit</a>
                            <a href="#" class="delete-player" data-index="${index}" aria-label="Delete player ${player.first_name || ''}">Delete</a>
                        </td>
                    </tr>
                `;
                $tableBody.append(rowHtml);
            });
        } else {
            if (debugEnabled) console.log("InterSoccer: No valid players to display, showing empty table");
            $tableBody.html(`<tr class="no-players"><td colspan="${colspan}">No attendees added yet.</td></tr>`);
        }
    }

    // Handle form submission to add a player
    $managePlayers.on("click", ".player-submit", function (e) {
        e.preventDefault();
        const $section = $(this).closest(".add-player-section");
        const isAdd = true;
        if (intersoccerState.isProcessing || intersoccerState.isAdding) {
            if (debugEnabled) console.log("InterSoccer: Save aborted, isProcessing:", intersoccerState.isProcessing, "isAdding:", intersoccerState.isAdding);
            return;
        }

        if (debugEnabled) console.log('InterSoccer: Form section HTML:', $section.html());

        const playerData = {
            player_first_name: $section.find('input[name="player_first_name"]').val()?.trim(),
            player_last_name: $section.find('input[name="player_last_name"]').val()?.trim(),
            player_dob: $section.find('input[name="player_dob"]').val()?.trim(),
            player_gender: $section.find('select[name="player_gender"]').val()?.trim(),
            player_avs_number: $section.find('input[name="player_avs_number"]').val()?.trim() || "0000",
            player_medical: $section.find('textarea[name="player_medical"]').val()?.trim() || "",
        };

        if (debugEnabled) console.log("InterSoccer: Player data before validation:", playerData);

        if (!window.intersoccerValidateRow($section, isAdd)) {
            if (debugEnabled) console.log("InterSoccer: Validation failed, row inputs:", playerData);
            return;
        }

        intersoccerState.isProcessing = true;
        intersoccerState.isAdding = true;
        const $spinner = $section.find(".spinner").show();

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

        const sendAjaxRequest = (nonce) => {
            if (debugEnabled) console.log("InterSoccer: Sending add player request with data:", JSON.stringify(playerData), "nonce:", nonce);
            $.ajax({
                url: intersoccerPlayer.ajax_url,
                type: "POST",
                data: {
                    action: "intersoccer_add_player",
                    nonce: nonce,
                    player_first_name: encodeURIComponent(playerData.player_first_name),
                    player_last_name: encodeURIComponent(playerData.player_last_name),
                    player_dob: playerData.player_dob,
                    player_gender: playerData.player_gender,
                    player_avs_number: playerData.player_avs_number,
                    player_medical: encodeURIComponent(playerData.player_medical),
                    user_id: intersoccerPlayer.user_id,
                },
                contentType: "application/x-www-form-urlencoded; charset=UTF-8",
                success: function (response) {
                    if (debugEnabled) console.log("InterSoccer: Add player response:", JSON.stringify(response));
                    $spinner.hide();
                    if (response.success) {
                        $section.find("input, select, textarea").val("");
                        $section.removeClass("active").hide();
                        $(".toggle-add-player").attr("aria-expanded", "false");
                        if (response.data.player) {
                            intersoccerPlayer.preload_players = intersoccerPlayer.preload_players || [];
                            const newIndex = intersoccerPlayer.preload_players.length.toString();
                            intersoccerPlayer.preload_players[newIndex] = response.data.player;
                            if (debugEnabled) console.log("InterSoccer: Updated preload_players after add:", JSON.stringify(intersoccerPlayer.preload_players));
                        }
                        populatePlayers();
                        $message.text(response.data.message || "Player added successfully.").show();
                        setTimeout(() => $message.hide(), 5000);
                    } else {
                        $message.text(response.data.message || "Error adding player.").show();
                        setTimeout(() => $message.hide(), 5000);
                        if (response.data.new_nonce) {
                            intersoccerPlayer.nonce = response.data.new_nonce;
                            if (debugEnabled) console.log("InterSoccer: Updated nonce from server response:", intersoccerPlayer.nonce);
                        }
                    }
                },
                error: function (xhr, status, error) {
                    console.error("InterSoccer: Add player error:", status, error, xhr.responseText, "Response JSON:", JSON.stringify(xhr.responseJSON));
                    $message.text("Error adding player: " + (xhr.responseText || "Unknown error")).show();
                    setTimeout(() => $message.hide(), 5000);
                    if (xhr.status === 403 && !intersoccerState.nonceRetryAttempted) {
                        intersoccerState.nonceRetryAttempted = true;
                        intersoccerRefreshNonce().then((newNonce) => {
                            sendAjaxRequest(newNonce);
                        }).catch((error) => {
                            console.error("InterSoccer: Nonce refresh failed:", error);
                            intersoccerState.nonceRetryAttempted = false;
                            intersoccerState.isProcessing = false;
                            intersoccerState.isAdding = false;
                            $spinner.hide();
                        });
                    } else {
                        intersoccerState.nonceRetryAttempted = false;
                        intersoccerState.isProcessing = false;
                        intersoccerState.isAdding = false;
                        $spinner.hide();
                    }
                },
                complete: function () {
                    if (!intersoccerState.nonceRetryAttempted) {
                        intersoccerState.isProcessing = false;
                        intersoccerState.isAdding = false;
                        $spinner.hide();
                    }
                    if (debugEnabled) console.log("InterSoccer: Add operation completed");
                }
            });
        };

        sendAjaxRequest(intersoccerPlayer.nonce);
    });

    // Edit player functionality
    $managePlayers.on("click", ".edit-player", function (e) {
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
        const index = $row.data("player-index").toString();
        const userId = $row.data("user-id") || intersoccerPlayer.user_id;
        intersoccerState.editingIndex = index;

        if (debugEnabled) {
            console.log("InterSoccer: Initiating edit for player index:", index, "userId:", userId);
        }

        const player = intersoccerPlayer.preload_players && intersoccerPlayer.preload_players[index] ? intersoccerPlayer.preload_players[index] : {
            first_name: $row.attr("data-first-name") || "N/A",
            last_name: $row.attr("data-last-name") || "N/A",
            dob: $row.attr("data-dob") || "N/A",
            gender: $row.attr("data-gender") || "N/A",
            avs_number: $row.attr("data-avs-number") || "N/A",
            medical_conditions: $row.attr("data-medical-conditions") || ""
        };

        if (!player.first_name || !player.last_name) {
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
        const gender = player.gender || "N/A";
        const avsNumber = player.avs_number || "N/A";
        const medicalConditions = player.medical_conditions || "";

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
        $row.find(".actions").html(`
            <a href="#" class="player-submit" aria-label="Save Player">Save</a> /
            <a href="#" class="cancel-edit" aria-label="Cancel Edit">Cancel</a>
        `);

        $tableBody.find(`tr[data-player-index]`).not($row).each(function () {
            $(this).find(".edit-player").addClass("disabled").attr("aria-disabled", "true");
        });
        $row.find('[name="player_first_name"]').focus();
        $row.addClass("editing");

        try {
            flatpickr($row.find(".date-picker"), {
                dateFormat: "Y-m-d",
                maxDate: "today",
                enableTime: false,
                allowInput: true,
                altInput: true,
                altFormat: "F j, Y",
                defaultDate: dob !== "N/A" ? dob : null,
                onClose: function (selectedDates, dateStr, instance) {
                    instance.element.value = dateStr;
                },
            });
        } catch (e) {
            console.error("InterSoccer: Flatpickr initialization for edit failed:", e);
        }

        $(this).attr("aria-expanded", "true");
        if (debugEnabled) console.log("InterSoccer: Edit form populated for player index:", index);
    });

    // Cancel edit
    $managePlayers.on("click", ".cancel-edit", function (e) {
        e.preventDefault();
        if (intersoccerState.isProcessing) {
            if (debugEnabled) console.log("InterSoccer: Cancel edit aborted, processing in progress");
            return;
        }

        const $row = $(this).closest("tr");
        const index = $row.data("player-index").toString();
        const userId = $row.data("user-id") || intersoccerPlayer.user_id;

        const player = intersoccerPlayer.preload_players && intersoccerPlayer.preload_players[index] ? intersoccerPlayer.preload_players[index] : {
            first_name: $row.attr("data-first-name") || "N/A",
            last_name: $row.attr("data-last-name") || "N/A",
            dob: $row.attr("data-dob") || "N/A",
            gender: $row.attr("data-gender") || "N/A",
            avs_number: $row.attr("data-avs-number") || "N/A",
            event_count: $row.attr("data-event-count") || 0,
            medical_conditions: $row.attr("data-medical-conditions") || "",
        };

        $row.find(".display-first-name").html(`<span>${player.first_name || 'N/A'}</span>`);
        $row.find(".display-last-name").html(`<span>${player.last_name || 'N/A'}</span>`);
        $row.find(".display-dob").html(`<span>${player.dob || 'N/A'}</span>`);
        $row.find(".display-gender").html(`<span>${translateGender(player.gender || 'N/A')}</span>`);
        $row.find(".display-avs-number").html(`<span>${player.avs_number || 'N/A'}</span>`);
        $row.find(".display-event-count").html(`<span>${player.event_count || 0}</span>`);
        $row.find(".display-medical-conditions").html(`<span>${(player.medical_conditions || '').substring(0, 20) + ((player.medical_conditions || '').length > 20 ? '...' : '')}</span>`);
        $row.find(".actions").html(`
            <a href="#" class="edit-player" data-index="${index}" data-user-id="${userId}" aria-label="Edit player ${player.first_name || ''}" aria-expanded="false">Edit</a>
            <a href="#" class="delete-player" data-index="${index}" aria-label="Delete player ${player.first_name || ''}">Delete</a>
        `);

        $row.removeClass("editing");
        $tableBody.find(`.medical-row[data-player-index="${index}"]`).remove();
        intersoccerState.editingIndex = null;
        $tableBody.find(".edit-player").removeClass("disabled").attr("aria-disabled", "false");

        $message.text("Edit canceled.").show();
        setTimeout(() => $message.hide(), 5000);
        if (debugEnabled) console.log("InterSoccer: Edit canceled for player index:", index);
    });

    // Delete player
    $managePlayers.on("click", ".delete-player", function (e) {
        e.preventDefault();
        if (intersoccerState.isProcessing) {
            if (debugEnabled) console.log("InterSoccer: Delete aborted, processing in progress");
            return;
        }

        const $row = $(this).closest("tr");
        const index = $row.data("player-index").toString();
        const userId = $row.data("user-id") || intersoccerPlayer.user_id;

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
                player_index: index
            },
            success: (response) => {
                if (response.success) {
                    $row.remove();
                    $tableBody.find(`.medical-row[data-player-index="${index}"]`).remove();
                    $message.text(response.data.message).show();
                    setTimeout(() => $message.hide(), 10000);
                    if (debugEnabled) console.log("InterSoccer: Player deleted successfully:", response.data);

                    if ($tableBody.find("tr[data-player-index]").length === 0) {
                        $tableBody.append(`<tr class="no-players"><td colspan="${colspan}">No attendees added yet.</td></tr>`);
                    }
                    if (intersoccerPlayer.preload_players) {
                        delete intersoccerPlayer.preload_players[index];
                    }
                    populatePlayers();
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
                    intersoccerState.nonceRetryAttempted = true;
                    intersoccerRefreshNonce().then((newNonce) => {
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
                $(this).removeClass("disabled").attr("aria-disabled", "false");
                if (debugEnabled) console.log("InterSoccer: Delete operation completed");
            }
        });
    });

    // Toggle add player section
    $managePlayers.off("click.toggleAdd").on("click.toggleAdd", ".toggle-add-player", function (e) {
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
            $addSection.find("input, select, textarea").val("");
            $addSection.find(".error-message").hide();
            $addSection.find('[name="player_first_name"]').focus();
            $(this).attr("aria-expanded", "true");
            if (debugEnabled) console.log("InterSoccer: Add player section shown");
        } else {
            $addSection.removeClass("active").hide();
            $(this).attr("aria-expanded", "false");
            if (debugEnabled) console.log("InterSoccer: Add player section hidden");
        }
    });

    // Cancel add
    $managePlayers.on("click", ".cancel-add", function (e) {
        e.preventDefault();
        if (intersoccerState.isProcessing) {
            if (debugEnabled) console.log("InterSoccer: Cancel add aborted, processing in progress");
            return;
        }

        const $addSection = $(".add-player-section");
        $addSection.removeClass("active").hide();
        $addSection.find("input, select, textarea").val("");
        $addSection.find(".error-message").hide();
        $(".toggle-add-player").attr("aria-expanded", "false").focus();

        $message.text("Add player canceled.").show();
        setTimeout(() => $message.hide(), 5000);
        if (debugEnabled) console.log("InterSoccer: Add player canceled");
    });

    // Validation function
    window.intersoccerValidateRow = function ($section, isAdd = false) {
        let isValid = true;
        $section.find(".error-message").hide();

        const firstName = $section.find('[name="player_first_name"]').val()?.trim();
        const lastName = $section.find('[name="player_last_name"]').val()?.trim();
        const dob = $section.find('[name="player_dob"]').val()?.trim();
        const gender = $section.find('[name="player_gender"]').val()?.trim();
        const avsNumber = $section.find('[name="player_avs_number"]').val()?.trim() || "0000";
        const medical = $section.find('[name="player_medical"]').val()?.trim() || "";

        if (debugEnabled) {
            console.log("InterSoccer: Validating row with inputs:", { firstName, lastName, dob, gender, avsNumber, medical });
        }

        // Validate first name
        if (!firstName || firstName.length > 50 || !/^[A-Za-zÀ-ÿ\s-]+$/u.test(firstName)) {
            $section.find('[name="player_first_name"]').next(".error-message")
                .text("Valid first name required (max 50 chars, letters with accents, spaces, hyphens allowed).")
                .show();
            isValid = false;
            if (debugEnabled) {
                console.log("InterSoccer: First name validation failed:", firstName, "Regex test:", /^[A-Za-zÀ-ÿ\s-]+$/u.test(firstName));
            }
        }

        // Validate last name
        if (!lastName || lastName.length > 50 || !/^[A-Za-zÀ-ÿ\s-]+$/u.test(lastName)) {
            $section.find('[name="player_last_name"]').next(".error-message")
                .text("Valid last name required (max 50 chars, letters with accents, spaces, hyphens allowed).")
                .show();
            isValid = false;
            if (debugEnabled) {
                console.log("InterSoccer: Last name validation failed:", lastName, "Regex test:", /^[A-Za-zÀ-ÿ\s-]+$/u.test(lastName));
            }
        }

        // Validate DOB
        if (isAdd || dob) {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dob) || !new Date(dob).getTime()) {
                $section.find('[name="player_dob"]').next(".error-message")
                    .text("Invalid date of birth format. Use YYYY-MM-DD.")
                    .show();
                isValid = false;
            } else {
                const dobDate = new Date(dob);
                const serverDate = new Date(intersoccerPlayer.server_time || Date.now());
                const age = serverDate.getFullYear() - dobDate.getFullYear() - 
                    (serverDate.getMonth() < dobDate.getMonth() || 
                     (serverDate.getMonth() === dobDate.getMonth() && serverDate.getDate() < dobDate.getDate()) ? 1 : 0);
                if (age < 3 || age > 13) {
                    $section.find('[name="player_dob"]').next(".error-message")
                        .text("Player must be 2-13 years old.")
                        .show();
                    isValid = false;
                }
            }
        }

        // Validate gender
        if (isAdd || gender) {
            if (!["male", "female", "other"].includes(gender)) {
                $section.find('[name="player_gender"]').next(".error-message")
                    .text("Invalid gender selection.")
                    .show();
                isValid = false;
            }
        }

        // Validate AVS number
        if (avsNumber !== "0000" && !/^(756\.\d{4}\.\d{4}\.\d{2}|[A-Za-z0-9]{4,50})$/.test(avsNumber)) {
            $section.find('[name="player_avs_number"]').next(".error-message")
                .text("Invalid AVS number. Use at least 4 characters, '0000', or Swiss AVS format (756.XXXX.XXXX.XX).")
                .show();
            isValid = false;
        }

        // Validate medical conditions
        if (medical && medical.length > 500) {
            $section.find('[name="player_medical"]').next(".error-message")
                .text("Medical conditions must be under 500 chars.")
                .show();
            isValid = false;
        }

        if (debugEnabled) {
            console.log("InterSoccer: Validation result:", { isValid });
        }

        return isValid;
    };

    // Initial fetch
    populatePlayers();
})(jQuery);