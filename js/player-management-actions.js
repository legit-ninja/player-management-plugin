jQuery(document).ready(function($) {
    const $table = $('#player-table');
    const $form = $('#player-form');
    const $message = $('.intersoccer-message');
    const isAdmin = intersoccerPlayer.is_admin === '1';
    const debugEnabled = intersoccerPlayer.debug === '1';

    // Initialize form state (remove old checks)
    function initializeFormState() {
        if (debugEnabled) console.log('InterSoccer: Initializing form state');
        if ($form.length === 0) {
            if (debugEnabled) console.log('InterSoccer: #player-form not found');
            return;
        }
        // Bind events
        $('.toggle-add-player').on('click', handleAddClick);
        $table.on('click', '.edit-player', handleEditClick);
        $('#save-player').on('click', handleSaveClick);
        $('#cancel-player').on('click', handleCancelClick);
    }

    // Handle add button
    function handleAddClick(e) {
        e.preventDefault();
        clearForm();
        $('#player_index').val(-1);
        $form.show();
        $('#player_first_name').focus();
        if (debugEnabled) console.log('InterSoccer: Showing form for add');
    }

    // Handle edit button
    function handleEditClick(e) {
        e.preventDefault();
        const $row = $(this).closest('tr');
        const index = $row.data('player-index');
        const userId = $row.data('user-id');
        $('#player_first_name').val($row.data('first-name'));
        $('#player_last_name').val($row.data('last-name'));
        $('#player_dob').val($row.data('dob'));
        $('#player_gender').val($row.data('gender'));
        $('#player_avs_number').val($row.data('avs-number'));
        $('#player_medical').val($row.data('medical-conditions'));
        $('#player_index').val(index);
        $('#player_user_id').val(userId);  // If needed for AJAX
        $form.show();
        $('#player_first_name').focus();
        if (debugEnabled) console.log('InterSoccer: Showing form for edit, index:', index);
    }

    // Handle save
    function handleSaveClick(e) {
        e.preventDefault();
        const index = $('#player_index').val();
        const userId = $('#player_user_id').val();
        const firstName = $('#player_first_name').val().trim();
        const lastName = $('#player_last_name').val().trim();
        const dob = $('#player_dob').val();
        const gender = $('#player_gender').val();
        const avsNumber = $('#player_avs_number').val().trim() || '0000';
        const medical = $('#player_medical').val().trim();

        // Validate (add your validation function)
        if (!intersoccerValidateForm()) return;

        const action = index === '-1' ? 'intersoccer_add_player' : 'intersoccer_edit_player';
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
            is_admin: isAdmin ? '1' : '0',
        };
        if (index !== '-1') data.player_index = index;

        if (debugEnabled) console.log('InterSoccer: Saving player, data:', data);

        $('#save-player .spinner').show();
        $.ajax({
            url: intersoccerPlayer.ajax_url,
            type: 'POST',
            data: data,
            ontentType: "application/x-www-form-urlencoded; charset=UTF-8",
            dataType: 'json',
            success: function(response) {
                $('#save-player .spinner').hide();
                if (response.success) {
                    $message.text(response.data.message).show();
                    setTimeout(() => $message.hide(), 5000);
                    updateTable(response.data.player, index);  // New function to update/add row
                    $form.hide();
                    clearForm();
                    if (debugEnabled) console.log('InterSoccer: Player saved, updated table');
                } else {
                    $message.text(response.data.message || 'Failed to save.').show();
                    setTimeout(() => $message.hide(), 5000);
                }
            },
            error: function(xhr) {
                $('#save-player .spinner').hide();
                $message.text('Error: ' + (xhr.responseText || 'Unknown')).show();
                setTimeout(() => $message.hide(), 5000);
                if (debugEnabled) console.error('InterSoccer: AJAX error:', xhr);
            }
        });
    }

    // Update/add table row after save
    function updateTable(player, index) {
        const name = (player.first_name || 'N/A') + ' ' + (player.last_name || '');
        const medicalDisplay = player.medical_conditions ? player.medical_conditions.substring(0, 20) + (player.medical_conditions.length > 20 ? '...' : '') : '';
        const html = `
            <tr data-player-index="${player.player_index}" data-user-id="${player.user_id || intersoccerPlayer.user_id}"
                data-first-name="${player.first_name || 'N/A'}" data-last-name="${player.last_name || 'N/A'}" data-dob="${player.dob || 'N/A'}"
                data-gender="${player.gender || 'N/A'}" data-avs-number="${player.avs_number || 'N/A'}"
                data-medical-conditions="${player.medical_conditions || ''}" data-event-count="${player.event_count || 0}">
                <!-- Admin columns if needed -->
                <td class="display-name" data-label="Name">${name}</td>
                <td class="display-dob" data-label="DOB">${player.dob || 'N/A'}</td>
                <td class="display-gender" data-label="Gender">${player.gender || 'N/A'}</td>
                <td class="display-avs-number" data-label="AVS Number">${player.avs_number || 'N/A'}</td>
                <td class="display-event-count" data-label="Events">${player.event_count || 0}</td>
                <!-- Admin medical/creation/past -->
                <td class="actions" data-label="Actions">
                    <a href="#" class="edit-player" data-index="${player.player_index}" aria-label="Edit ${player.first_name || ''}">Edit</a>
                </td>
            </tr>
        `;
        if (index === '-1') {
            $table.append(html);
            $('.no-players').remove();  // Remove no players row if present
        } else {
            $table.find(`tr[data-player-index="${index}"]`).replaceWith(html);
        }
    }

    // Clear form
    function clearForm() {
        $form.find('input[type="text"], input[type="date"], select, textarea').val('');
        $form.find('.error-message').hide();
    }

    // Handle cancel
    function handleCancelClick(e) {
        e.preventDefault();
        $form.hide();
        clearForm();
        if (debugEnabled) console.log('InterSoccer: Canceled form');
    }

    // Your validation function (adapt as needed)
    function intersoccerValidateForm() {
        let valid = true;
        // Add checks for required fields, show errors
        $form.find('[required]').each(function() {
            if ($(this).val().trim() === '') {
                $(this).next('.error-message').text('This field is required.').show();
                valid = false;
            } else {
                $(this).next('.error-message').hide();
            }
        });
        return valid;
    }

    initializeFormState();
});