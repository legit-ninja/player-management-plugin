"use strict";
(function ($) {
  if (typeof $ === "undefined") {
    console.error("InterSoccer: jQuery is not loaded. Filters disabled.");
    return;
  }

  if (typeof intersoccerPlayer === "undefined" || typeof intersoccerPlayer !== "object") {
    console.error("InterSoccer: intersoccerPlayer not initialized. Filters disabled.");
    return;
  }

  const debugEnabled = intersoccerPlayer.debug === "1";
  const isAdmin = intersoccerPlayer.is_admin === "1";

  window.intersoccerApplyFilters = function() {
    if (!isAdmin) {
      if (debugEnabled) console.log("InterSoccer: Filters not applied in non-admin context");
      return;
    }

    const searchTerm = $("#player-search").val().toLowerCase().trim();
    const $rows = $("#player-table tr[data-player-index]");

    $rows.each(function() {
      const $row = $(this);
      const firstName = $row.find(".display-first-name").text().toLowerCase();
      const lastName = $row.find(".display-last-name").text().toLowerCase();
      const isVisible = !searchTerm || firstName.includes(searchTerm) || lastName.includes(searchTerm);
      $row.toggle(isVisible);
    });

    if (debugEnabled) console.log("InterSoccer: Applied name search filter, term:", searchTerm);
  };

  if (isAdmin) {
    $("#player-search").on("input", function() {
      intersoccerApplyFilters();
    });
    if (debugEnabled) console.log("InterSoccer: Initialized name search filter");
  }
})(jQuery);
