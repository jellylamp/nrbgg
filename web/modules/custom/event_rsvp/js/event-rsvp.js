(function ($, Drupal) {
  'use strict';

  Drupal.behaviors.eventRsvp = {
    attach: function (context, settings) {
      // Handle logged-in user RSVP buttons.
      $('.event-rsvp-button:not(.event-rsvp-anon-button)', context).once('event-rsvp').on('click', function (e) {
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
      
      // Handle anonymous user RSVP buttons.
      $('.event-rsvp-anon-button', context).once('event-rsvp-anon').on('click', function (e) {
        e.preventDefault();
        
        var $button = $(this);
        var url = $button.attr('href');
        var $container = $('.anonymous-rsvp-form-container');
        var $formDiv = $('#anonymous-rsvp-form');
        
        // Show the form container.
        $container.show();
        
        // Load the form via AJAX.
        $formDiv.html('<p>Loading form...</p>');
        
        $.ajax({
          url: url,
          type: 'GET',
          success: function (response) {
            $formDiv.html(response);
            
            // Scroll to the form.
            $('html, body').animate({
              scrollTop: $container.offset().top - 100
            }, 500);
          },
          error: function () {
            $formDiv.html('<p class="error">Error loading form. Please try again.</p>');
          }
        });
      });
    }
  };

})(jQuery, Drupal);