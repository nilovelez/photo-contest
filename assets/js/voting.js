jQuery(document).ready(function($) {
    // Handle vote button clicks
    $('.vote-button').on('click', function() {
        const button = $(this);
        const value = button.data('value');
        const photoId = $('.photo-contest-voting').data('photo-id');

        // Disable all buttons during submission
        $('.vote-button').prop('disabled', true);

        $.ajax({
            url: photoContestVoting.ajaxurl,
            type: 'POST',
            data: {
                action: 'submit_vote',
                nonce: photoContestVoting.nonce,
                photo_id: photoId,
                vote_value: value
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.next_photo) {
                        // Reload page to show next photo
                        window.location.reload();
                    } else {
                        // No more photos, show message
                        $('.photo-contest-voting').html(`
                            <h2>${photoContestVoting.i18n.noMorePhotos}</h2>
                            <p>${photoContestVoting.i18n.thankYou}</p>
                        `);
                    }
                } else {
                    alert(response.data);
                    $('.vote-button').prop('disabled', false);
                }
            },
            error: function() {
                alert(photoContestVoting.i18n.voteError);
                $('.vote-button').prop('disabled', false);
            }
        });
    });
}); 