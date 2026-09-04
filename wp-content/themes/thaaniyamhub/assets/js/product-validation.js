jQuery(document).ready(function($) {
    // Watch for Razorpay processing message display to hide preceding text & buttons on all browsers
    function checkRazorpayProcessingState() {
        var $msg = $('#msg-razorpay-success');
        if ($msg.length) {
            var isVisible = $msg.is(':visible') || window.getComputedStyle($msg[0]).display !== 'none';
            if (isVisible) {
                $msg.prevAll('p').hide();
                $('#btn-razorpay, #btn-razorpay-cancel').hide().closest('p').hide();
            } else {
                $msg.prevAll('p').show();
                $('#btn-razorpay, #btn-razorpay-cancel').closest('p').show();
            }
        }
    }
    checkRazorpayProcessingState();

    if (typeof MutationObserver !== 'undefined') {
        var msgTarget = document.getElementById('msg-razorpay-success');
        if (msgTarget) {
            var observer = new MutationObserver(function() {
                checkRazorpayProcessingState();
            });
            observer.observe(msgTarget, { attributes: true, attributeFilter: ['style'] });
        }
    }
});
