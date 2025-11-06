jQuery(document).ready(function($) {
    "use strict";

    // Get all the buttons for CM
    const wfs_buttons = $('.wfs-button');

    // Open Dialog
    wfs_buttons.on('click', function(e) {
        e.preventDefault();
        const id = $(this).attr('id');
        const dialogId = id.substring(0, id.lastIndexOf('-')) + '-dialog';
        const dialog = document.getElementById(dialogId);
        dialog.showModal();
        dialog.addEventListener('click', lightDismiss)
    });

    const lightDismiss = ({target:dialog}) => {
        if (dialog.nodeName === 'DIALOG')
            dialog.close('dismiss')
    }
})