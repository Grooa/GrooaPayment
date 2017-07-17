<script src="https://www.paypalobjects.com/api/checkout.js"></script>

<div class="notifier"></div>

<script type="text/javascript">
    var notifier = document.querySelector('.notifier');
//    notifier.style.display = 'none'; // Initial value

    notifier.addEventListener('click', function() {
        notifier.className = 'notifier';
        notifier.style.display = 'none'
    });

    var CREATE_PAYMENT_URL = '<?=ipConfig()->baseUrl()?>paypal/create-payment?track=<?=$trackId?>';
    var EXECUTE_PAYMENT_URL = '<?=ipConfig()->baseUrl()?>paypal/execute-payment?track=<?=$trackId?>';

    paypal.Button.render({

        env: '<?=$useSandbox ? 'sandbox' : 'production'?>',

        commit: true, // Show a 'Pay Now' button

        style: {
            size: 'responsive',
            color: 'blue',
            shape: 'pill',
            label: 'checkout'
        },

        payment: function () {
            return paypal.request.post(CREATE_PAYMENT_URL).then(function (data) {
                return data.paymentID; // Returned from local REST-API
            });
        },

        onAuthorize: function (data) {
            return paypal.request.post(EXECUTE_PAYMENT_URL, {
                paymentID: data.paymentID,
                payerID: data.payerID
            }).then(function (data) {
                location.reload(); // Reload page with the correct information
            });
        },

        onCancel: function (data, actions) {
            console.log(data);
            console.log(actions);

            notifier.className += ' error';
            notifier.innerHTML = "Your purchase was cancelled";

            setTimeout(function() {
                notifier.className = 'notifier';
            }, 3000);

        }

    }, '#paypal-button');
</script>