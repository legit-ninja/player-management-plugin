/**
 * File: player-management-filters.js
 * Description: Handles filter logic for the player management table. Applies filters for Canton, Age Group, Event Type, and Gender, and initializes Select2 for filter dropdowns.
 * Dependencies: jQuery, player-management-core.js
 */

(function ($) {
  if (typeof intersoccerPlayer === "undefined") {
    console.error(
      "InterSoccer: intersoccerPlayer not defined. Filters disabled."
    );
    return;
  }

  const $table = $("#player-table");
  const isAdmin = intersoccerPlayer.is_admin === "1";
  const debugEnabled = intersoccerPlayer.debug === "1";

  // Initialize Select2 for filters
  if (isAdmin) {
    $(document).ready(function() {
      $('#filter-canton, #filter-age-group, #filter-event-type, #filter-gender').select2({
        placeholder: "Select an option",
        allowClear: true,
        width: 'resolve'
      });
      if (debugEnabled) {
        console.log("InterSoccer: Select2 initialized for filters");
      }
    });
  }

  // Apply filters to the table
  function applyFilters() {
    const cantonFilter = $("#filter-canton").val() || "";
    const ageGroupFilters = $("#filter-age-group").val() || []; // Multi-select
    const eventTypeFilter = $("#filter-event-type").val() || "";
    const genderFilter = $("#filter-gender").val() || "";

    if (debugEnabled) {
      console.log(
        "InterSoccer: Applying filters - Canton:",
        cantonFilter,
        "Age-Groups:",
        ageGroupFilters,
        "Event Type:",
        eventTypeFilter,
        "Gender:",
        genderFilter
      );
    }

    $table.find("tr[data-player-index]").each(function () {
      const $row = $(this);
      const canton = ($row.data("canton") || "").toString().trim();
      const eventAgeGroupsRaw = $row.data("event-age-groups") || "";
      const eventAgeGroups = eventAgeGroupsRaw ? [eventAgeGroupsRaw.trim()] : []; // Treat as single value
      const eventTypesRaw = $row.data("event-types") || "";
      const eventTypes = eventTypesRaw ? eventTypesRaw.split(",").map(item => item.trim()).filter(item => item) : [];
      const gender = ($row.data("gender") || "other").toString().trim().toLowerCase();

      let showRow = true;

      // Canton filter
      if (cantonFilter && canton !== cantonFilter) {
        showRow = false;
      }

      // Age-Group filter (multi-select)
      if (ageGroupFilters.length > 0 && ageGroupFilters[0] !== "") {
        const hasValidAgeGroups = eventAgeGroups.length > 0 && eventAgeGroups[0] !== "";
        const matchesAgeGroup = hasValidAgeGroups && eventAgeGroups.some(ageGroup => ageGroupFilters.includes(ageGroup));
        if (!matchesAgeGroup && hasValidAgeGroups) {
          showRow = false;
        }
      }

      // Event Type filter
      if (eventTypeFilter) {
        const hasValidEventTypes = eventTypes.length > 0;
        if (hasValidEventTypes && !eventTypes.includes(eventTypeFilter)) {
          showRow = false;
        }
        // If no event types and "All Event Types" is not selected, hide the row
        if (!hasValidEventTypes && eventTypeFilter !== "") {
          showRow = false;
        }
      }

      // Gender filter
      if (genderFilter && gender !== genderFilter) {
        showRow = false;
      }

      if (debugEnabled) {
        console.log("InterSoccer: Row data:", { canton, eventAgeGroups, eventTypes, gender, showRow });
      }
      $row.toggle(showRow);
    });
  }

  // Initialize filters
  if (isAdmin) {
    $("#filter-canton, #filter-age-group, #filter-event-type, #filter-gender").on(
      "change",
      function () {
        applyFilters();
      }
    );
    // Apply filters on page load if values are set
    applyFilters();
  }

  // Expose applyFilters for use after actions
  window.intersoccerApplyFilters = applyFilters;
})(jQuery);
