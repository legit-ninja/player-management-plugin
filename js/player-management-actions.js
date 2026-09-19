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
        $table.on('click', '.delete-player', handleDeleteClick);
        $('#save-player').on('click', handleSaveClick);
        $('#cancel-player').on('click', handleCancelClick);
    }

    // AC B5: Delete with confirmation dialog
    function handleDeleteClick(e) {
        e.preventDefault();
        const $row = $(this).closest('tr');
        const index = $row.data('player-index');
        const userId = $row.data('user-id') || intersoccerPlayer.user_id;
        const firstName = $row.data('first-name') || '';
        const lastName = $row.data('last-name') || '';
        const playerName = (firstName + ' ' + lastName).trim() || 'this player';

        showConfirmDialog(
            'Remove ' + escHtml(playerName) + '?',
            'This action cannot be undone. The player will be removed from your account.',
            function() {
                deletePlayer(index, userId, $row);
            }
        );
    }

    // Show confirm dialog (AC B5)
    function showConfirmDialog(title, message, onConfirm) {
        const $dialog = $('<div class="intersoccer-confirm-dialog" data-field="confirm-dialog">' +
            '<div class="intersoccer-confirm-dialog-content">' +
            '<h3>' + title + '</h3>' +
            '<p>' + message + '</p>' +
            '<div class="dialog-actions">' +
            '<button type="button" class="btn-cancel" data-field="cancel-btn">Cancel</button>' +
            '<button type="button" class="btn-delete" data-field="confirm-delete-btn">Remove</button>' +
            '</div>' +
            '</div>' +
            '</div>');

        $('body').append($dialog);

        $dialog.find('.btn-cancel').on('click', function() {
            $dialog.remove();
        });

        $dialog.find('.btn-delete').on('click', function() {
            $dialog.remove();
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        });

        // Close on overlay click
        $dialog.on('click', function(e) {
            if ($(e.target).hasClass('intersoccer-confirm-dialog')) {
                $dialog.remove();
            }
        });
    }

    // Delete player via AJAX
    function deletePlayer(index, userId, $row) {
        if (debugEnabled) console.log('InterSoccer: Deleting player index:', index);

        $.ajax({
            url: intersoccerPlayer.ajax_url,
            type: 'POST',
            data: {
                action: 'intersoccer_delete_player',
                nonce: intersoccerPlayer.nonce,
                user_id: userId,
                player_index: index
            },
            success: function(response) {
                if (response.success) {
                    showMessage(response.data.message || 'Player removed successfully.', 'success');
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        // Show empty state if no players left
                        if ($table.find('tbody tr[data-player-index]').length === 0) {
                            $table.find('tbody').html(
                                '<tr class="no-players"><td colspan="6" data-field="empty-state">' +
                                'No participants yet. Use Add to register a participant before booking.' +
                                '</td></tr>'
                            );
                        }
                    });
                } else {
                    showMessage(response.data?.message || 'Failed to remove player.', 'error');
                }
            },
            error: function(xhr) {
                showMessage('Network error. Please try again.', 'error');
                if (debugEnabled) console.error('InterSoccer: Delete error:', xhr);
            }
        });
    }

    // Show message helper (AC B7: clear errors)
    function showMessage(text, type) {
        $message.removeClass('success error').addClass(type).text(text).show();
        setTimeout(function() {
            $message.fadeOut();
        }, 5000);
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

    // AC B7: Validation with clear inline errors
    function intersoccerValidateForm() {
        let valid = true;
        
        // Clear all previous errors first
        $form.find('.error-message').hide().text('');
        $form.find('.form-row').removeClass('field-error');
        
        // Check required fields
        $form.find('[required]').each(function() {
            const $field = $(this);
            const $row = $field.closest('.form-row');
            const $error = $row.find('.error-message');
            const fieldName = $row.find('label').text().replace('*', '').trim();
            
            if ($field.val().trim() === '') {
                $error.text(fieldName + ' is required.').show();
                $row.addClass('field-error');
                valid = false;
            }
        });

        // Validate DOB format and age range
        const $dob = $form.find('#player_dob');
        if ($dob.length && $dob.val()) {
            const dobVal = $dob.val();
            const $dobRow = $dob.closest('.form-row');
            const $dobError = $dobRow.find('.error-message');
            
            if (!/^\d{4}-\d{2}-\d{2}$/.test(dobVal)) {
                $dobError.text('Please enter a valid date (YYYY-MM-DD).').show();
                $dobRow.addClass('field-error');
                valid = false;
            } else {
                // Check age range (3-13 years)
                const birthDate = new Date(dobVal);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                if (age < 3 || age > 13) {
                    $dobError.text('Player must be between 3 and 13 years old.').show();
                    $dobRow.addClass('field-error');
                    valid = false;
                }
            }
        }

        // Validate gender selection
        const $gender = $form.find('#player_gender');
        if ($gender.length && $gender.val() === '') {
            const $genderRow = $gender.closest('.form-row');
            $genderRow.find('.error-message').text('Please select a gender.').show();
            $genderRow.addClass('field-error');
            valid = false;
        }

        // Focus first error field
        if (!valid) {
            $form.find('.field-error').first().find('input, select, textarea').focus();
        }

        return valid;
    }

    // Escape HTML for safe display
    function escHtml(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    initializeFormState();
});