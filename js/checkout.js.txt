/**
 * Checkout JavaScript for Player Assignment
 * Changes:
 * - Removed redundant AJAX call to populate player dropdowns, as handled in PHP (2025-05-06).
 * - Kept click event handler to ensure dropdowns are clickable on desktop.
 * - Simplified script by removing DOM retry logic and excessive logging.
 * Testing:
 * - Verify player dropdowns are populated on page load (handled by PHP).
 * - Ensure dropdowns are clickable on desktop devices.
 * - Confirm no JavaScript errors in console.
 * - Test checkout process: select players, submit form, ensure no validation hang.
 */

jQuery(document).ready(function ($) {
  // Ensure dropdowns are clickable
  $(".player-select").each(function () {
    $(this)
      .off("click")
      .on("click", function (e) {
        e.stopPropagation();
      });
  });
});

