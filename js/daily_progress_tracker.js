(function($, Drupal) {
    Drupal.behaviors.dailyProgressTracker = {
      attach: function(context, settings) {
        // Make charts responsive
        $(window).once('dailyProgressTracker').on('resize', function() {
          $('.chart-container').each(function() {
            $(this).data('chart').resize();
          });
        });
      }
    };
  })(jQuery, Drupal);