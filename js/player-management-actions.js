jQuery(document).ready(function($) {
    const $table = $('#player-table');
    const $form = $('#player-form');
    const $message = $('.intersoccer-message');
    const isAdmin = intersoccerPlayer.is_admin === '1';
    const debugEnabled = intersoccerPlayer.debug === '1';

    /**
     * Translate gender value for display
     * Converts database value (english) to translated display value
     */
    function translateGender(genderValue) {
        if (!genderValue || genderValue === 'N/A') {
            return 'N/A';
        }
        
        const genderNormalized = genderValue.toLowerCase();
        const translations = intersoccerPlayer.i18n && intersoccerPlayer.i18n.gender ? intersoccerPlayer.i18n.gender : {};
        
        return translations[genderNormalized] || genderValue;
    }

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

    // Encode a value for safe insertion into HTML text/attribute context
    function escHtml(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // Update/add table row after save
    function updateTable(player, index) {
        const firstName  = player.first_name  || 'N/A';
        const lastName   = player.last_name   || '';
        const dob        = player.dob         || 'N/A';
        const gender     = player.gender      || 'N/A';
        const avsNumber  = player.avs_number  || 'N/A';
        const medical    = player.medical_conditions || '';
        const eventCount = player.event_count || 0;
        const playerIdx  = player.player_index;
        const userId     = player.user_id || intersoccerPlayer.user_id;

        const name = firstName + ' ' + lastName;
        const translatedGender = translateGender(gender);

        const $row = $('<tr>')
            .attr('data-player-index',       playerIdx)
            .attr('data-user-id',            userId)
            .attr('data-first-name',         firstName)
            .attr('data-last-name',          lastName)
            .attr('data-dob',                dob)
            .attr('data-gender',             gender)
            .attr('data-avs-number',         avsNumber)
            .attr('data-medical-conditions', medical)
            .attr('data-event-count',        eventCount);

        $row.append($('<td>').addClass('display-name').attr('data-label', 'Name').text(name));
        $row.append($('<td>').addClass('display-dob').attr('data-label', 'DOB').text(dob));
        $row.append($('<td>').addClass('display-gender').attr('data-label', 'Gender').text(translatedGender));
        $row.append($('<td>').addClass('display-avs-number').attr('data-label', 'AVS Number').text(avsNumber));
        $row.append($('<td>').addClass('display-event-count').attr('data-label', 'Events').text(eventCount));

        const $editLink = $('<a>')
            .attr('href', '#')
            .addClass('edit-player')
            .attr('data-index', playerIdx)
            .attr('aria-label', 'Edit ' + escHtml(firstName))
            .text('Edit');
        $row.append($('<td>').addClass('actions').attr('data-label', 'Actions').append($editLink));

        if (index === '-1') {
            $table.append($row);
            $('.no-players').remove();
        } else {
            $table.find('tr[data-player-index="' + escHtml(String(index)) + '"]').replaceWith($row);
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