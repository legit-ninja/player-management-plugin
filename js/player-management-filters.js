/**
 * File: player-management-filters.js
 * Description: Handles filter logic for the player management table. Applies pre-populated server-side filters for Canton and Gender, and name search in real-time.
 * Dependencies: jQuery, player-management-core.js, Select2
 */

(function ($) {
  if (typeof intersoccerPlayer === "undefined") {
    console.error("InterSoccer: intersoccerPlayer not defined. Filters disabled.");
    return;
  }

  const $table = $("#player-table");
  const isAdmin = intersoccerPlayer.is_admin === "1";
  const debugEnabled = intersoccerPlayer.debug === "1";

  // Initialize Select2 for filters if admin
  if (isAdmin) {
    $(document).ready(function() {
      $('#filter-canton, #filter-gender').select2({
        placeholder: "Select an option",
        allowClear: true,
        width: 'resolve'
      });
      if (debugEnabled) {
        console.log("InterSoccer: Select2 initialized for filters");
      }
    });
  }

  // Apply all filters in real-time
  function applyFilters() {
    const cantonFilter = $("#filter-canton").val() || "";
    const genderFilter = $("#filter-gender").val() || "";
    const searchTerm = $("#player-search").val().toLowerCase().trim();

    if (debugEnabled) {
      console.log("InterSoccer: Applying filters - Canton:", cantonFilter, "Gender:", genderFilter, "Search:", searchTerm);
    }

    $table.find("tr[data-player-index]").each(function () {
      const $row = $(this);
      const canton = ($row.data("canton") || "").toString().trim();
      const gender = ($row.data("gender") || "other").toString().trim().toLowerCase();
      const firstName = $row.find(".display-first-name").text().toLowerCase();
      const lastName = $row.find(".display-last-name").text().toLowerCase();

      let showRow = true;

      // Canton filter
      if (cantonFilter && canton !== cantonFilter) {
        showRow = false;
      }

      // Gender filter
      if (genderFilter && gender !== genderFilter) {
        showRow = false;
      }

      // Name search filter
      if (searchTerm && !firstName.includes(searchTerm) && !lastName.includes(searchTerm)) {
        showRow = false;
      }

      if (debugEnabled) {
        console.log("InterSoccer: Row data:", { canton, gender, firstName, lastName, showRow });
      }
      $row.toggle(showRow);
    });
  }

  // Initialize filters and event listeners
  if (isAdmin) {
    $(document).ready(function () {
      // Real-time filter application
      $("#filter-canton, #filter-gender").on("change", applyFilters);
      $("#player-search").on("input", applyFilters);

      // Apply filters on page load
      applyFilters();
    });
  }

  // Expose applyFilters for use after actions
  window.intersoccerApplyFilters = applyFilters;
})(jQuery);
