jQuery(document).ready(function($) {
    $('form.variations_form').on('show_variation', function(event, variation) {
        var details = '<div class="variation-details">';
        if (variation.term) {
            details += '<p><strong>Term:</strong> ' + variation.term + '</p>';
        }
        if (variation.age_group) {
            details += '<p><strong>Age Group:</strong> ' + variation.age_group + '</p>';
        }
        if (variation.indoor_outdoor) {
            details += '<p><strong>Indoor/Outdoor:</strong> ' + variation.indoor_outdoor + '</p>';
        }
        if (variation.availability) {
            details += '<p><strong>Availability:</strong> ' + variation.availability + '</p>';
        }
        if (variation.start_end_dates) {
            details += '<p><strong>Dates:</strong> ' + variation.start_end_dates + '</p>';
        }
        if (variation.pro_rata_price) {
            details += '<p><strong>Pro-Rata Price:</strong> ' + variation.pro_rata_price + '</p>';
        }
        details += '</div>';

        $('.variation-details').remove();
        $(this).after(details);
        });
        $('form.variations_form').on('hide_variation', function() {
            $('.variation-details').remove();
        });
});
