(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.eventRsvp = {
    attach: function (context, settings) {
      $('.event-rsvp-button', context).once('event-rsvp').on('click', function (e) {
        e.preventDefault();
        
        var $button = $(this);
        var url = $button.attr('href');
        var status = $button.data('status');
        
        // Disable all buttons during request.
        $('.event-rsvp-button').prop('disabled', true);
        
        // Make AJAX request.
        $.ajax({
          url: url,
          type: 'GET',
          dataType: 'json',
          success: function (response) {
            if (response.success) {
              // Update button states.
              $('.event-rsvp-button').removeClass('active');
              $button.addClass('active');
              
              // Update counts.
              if (response.counts) {
                $('.count-going').text('(' + response.counts.going + ')');
                $('.count-maybe').text('(' + response.counts.maybe + ')');
                $('.count-not-going').text('(' + response.counts.not_going + ')');
              }
              
              // Reload the page to update the user lists.
              location.reload();
            }
          },
          error: function () {
            alert('Error updating RSVP. Please try again.');
          },
          complete: function () {
            $('.event-rsvp-button').prop('disabled', false);
          }
        });
      });
    }
  };

})(jQuery, Drupal);