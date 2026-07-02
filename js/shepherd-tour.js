/**
 * InterSoccer Player Management Shepherd.js Tour
 * 
 * Provides a guided tour for users who haven't registered any players yet
 */

(function($) {
    'use strict';

    // Check if tour configuration is available
    if (typeof intersoccerPlayerTour === 'undefined') {
        return;
    }

    const config = intersoccerPlayerTour;
    
    // Check if user has dismissed the tour
    function hasDismissedTour() {
        const dismissed = localStorage.getItem('intersoccer_player_tour_dismissed');
        return dismissed === 'true';
    }

    // Mark tour as dismissed
    function dismissTour() {
        localStorage.setItem('intersoccer_player_tour_dismissed', 'true');
    }

    // Check if user has players (from server-side check)
    if (config.hasPlayers) {
        return; // User already has players, no need for tour
    }

    // Check if tour was dismissed
    if (hasDismissedTour()) {
        return; // User dismissed the tour
    }

    // Wait for Shepherd.js to load
    if (typeof Shepherd === 'undefined') {
        console.warn('InterSoccer: Shepherd.js not loaded');
        return;
    }

    // Find the target element
    const $target = $('.intersoccer-player-link[data-shepherd-target="yes"]');
    if (!$target.length) {
        return; // Target element not found
    }

    // Create tour instance
    const tour = new Shepherd.Tour({
        useModalOverlay: true,
        defaultStepOptions: {
            cancelIcon: {
                enabled: true
            },
            classes: 'intersoccer-shepherd-tour',
            scrollTo: {
                behavior: 'smooth',
                block: 'center'
            }
        }
    });

    // Step 1: Introduction
    tour.addStep({
        id: 'intro',
        title: config.i18n.tourTitle,
        text: config.i18n.tourIntro,
        buttons: [
            {
                text: config.i18n.tourDismiss,
                action: function() {
                    dismissTour();
                    tour.complete();
                },
                classes: 'shepherd-button-secondary'
            },
            {
                text: config.i18n.tourNext,
                action: tour.next
            }
        ],
        attachTo: {
            element: $target[0],
            on: 'bottom'
        }
    });

    // Step 2: Highlight the link
    tour.addStep({
        id: 'highlight-link',
        title: config.i18n.tourTitle,
        text: config.i18n.tourHighlight,
        buttons: [
            {
                text: config.i18n.tourBack,
                action: tour.back,
                classes: 'shepherd-button-secondary'
            },
            {
                text: config.i18n.tourNext,
                action: tour.next
            }
        ],
        attachTo: {
            element: $target[0],
            on: 'bottom'
        },
        highlightClass: 'intersoccer-shepherd-highlight'
    });

    // Step 3: Action prompt
    tour.addStep({
        id: 'action',
        title: config.i18n.tourTitle,
        text: config.i18n.tourAction,
        buttons: [
            {
                text: config.i18n.tourBack,
                action: tour.back,
                classes: 'shepherd-button-secondary'
            },
            {
                text: config.i18n.tourComplete,
                action: function() {
                    dismissTour();
                    tour.complete();
                }
            }
        ],
        attachTo: {
            element: $target[0],
            on: 'bottom'
        }
    });

    // Handle tour completion
    tour.on('complete', function() {
        // Optional: Track tour completion
        if (typeof console !== 'undefined' && console.log) {
            console.log('InterSoccer: Player management tour completed');
        }
    });

    // Handle tour cancellation
    tour.on('cancel', function() {
        dismissTour();
    });

    // Start tour if auto-start is enabled
    if (config.autoStart) {
        const delay = (config.delay || 0) * 1000;
        
        // Wait for page to be fully loaded
        $(document).ready(function() {
            setTimeout(function() {
                // Double-check that target is still visible
                if ($target.is(':visible')) {
                    tour.start();
                }
            }, delay);
        });
    }

    // Expose tour instance for manual triggering if needed
    window.intersoccerPlayerTourInstance = tour;

})(jQuery);


