(function (Drupal) {
  'use strict';

  Drupal.behaviors.eventRsvp = {
    attach: function (context, settings) {
      const buttons = context.querySelectorAll('.event-rsvp-button:not(.event-rsvp-processed)');
      
      buttons.forEach(function(button) {
        button.classList.add('event-rsvp-processed');
        
        button.addEventListener('click', function(e) {
          e.preventDefault();
          
          const url = this.getAttribute('href');
          const status = this.getAttribute('data-status');
          
          // Disable all buttons during request.
          document.querySelectorAll('.event-rsvp-button').forEach(function(btn) {
            btn.disabled = true;
          });
          
          // Make fetch request.
          fetch(url, {
            method: 'GET',
            headers: {
              'X-Requested-With': 'XMLHttpRequest'
            }
          })
          .then(function(response) {
            return response.json();
          })
          .then(function(data) {
            if (data.success) {
              // Update button states.
              document.querySelectorAll('.event-rsvp-button').forEach(function(btn) {
                btn.classList.remove('active');
              });
              button.classList.add('active');
              
              // Update counts.
              if (data.counts) {
                const goingCount = document.querySelector('.count-going');
                const maybeCount = document.querySelector('.count-maybe');
                const notGoingCount = document.querySelector('.count-not-going');
                
                if (goingCount) goingCount.textContent = '(' + data.counts.going + ')';
                if (maybeCount) maybeCount.textContent = '(' + data.counts.maybe + ')';
                if (notGoingCount) notGoingCount.textContent = '(' + data.counts.not_going + ')';
              }
              
              // Reload the page to update the user lists.
              location.reload();
            }
          })
          .catch(function(error) {
            alert('Error updating RSVP. Please try again.');
            console.error('RSVP Error:', error);
          })
          .finally(function() {
            document.querySelectorAll('.event-rsvp-button').forEach(function(btn) {
              btn.disabled = false;
            });
          });
        });
      });
    }
  };

})(Drupal);