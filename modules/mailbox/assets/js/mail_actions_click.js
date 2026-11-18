/**
 * Mail Actions - Click to Show/Hide
 * Handles three-dots menu click functionality
 */
jQuery(document).ready(function($) {
    // Close all popups when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.mail-actions-dropdown').length) {
            $('.mail-actions-popup').removeClass('show');
        }
    });
    
    // Toggle popup on trigger button click
    $(document).on('click', '.mail-actions-trigger', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        var $dropdown = $(this).closest('.mail-actions-dropdown');
        var $popup = $dropdown.find('.mail-actions-popup');
        
        // Close all other popups
        $('.mail-actions-popup').not($popup).removeClass('show');
        
        // Toggle this popup
        $popup.toggleClass('show');
    });
    
    // Prevent popup from closing when clicking inside it
    $(document).on('click', '.mail-actions-popup', function(e) {
        e.stopPropagation();
    });
    
    // Close popup after action is clicked
    $(document).on('click', '.mail-action-item', function() {
        $(this).closest('.mail-actions-popup').removeClass('show');
    });
});
