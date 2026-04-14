(function ($, Drupal, once) {
    Drupal.behaviors.toggleRows = {
        attach: function (context) {
            once('radioSelection', $('input[name="selected_organisation"]', context)).forEach((radio) => {
                $(radio).on('change', function () {
                    if ($('input[name="selected_organisation"]:checked').length > 0) {
                        $('.action-button-2').fadeIn(); // Show button
                    } else {
                        $('.action-button-2').fadeOut(); // Hide button
                    }
                });
            });
        }
    };
})(jQuery, Drupal, once);

// ✅ Function to send selected organisation to OrganizationValidationController
// eslint-disable-next-line no-unused-vars
function submitSelectedOrganisation() {
    console.log("test");
    let selectedRadio = $('input[name="selected_organisation"]:checked');

    if (selectedRadio.length === 0) {
        alert("Please select an organisation first.");
        return;
    }

    let orgData = selectedRadio.val();

    console.log(orgData);

    $.ajax({
        url: '/organization-validation/manageSelectedOrganisations', // Route to controller
        type: 'POST',
        data: { organisation_data: orgData }, // Send combined data
        success: function (response) {
            if (response.redirect) {
                window.location.href = response.redirect; // Redirect on success
            } else {
                alert("Submission successful, but no redirect URL was provided.");
            }
        },
        error: function () {
            alert("Error processing your request.");
        }
    });
}
