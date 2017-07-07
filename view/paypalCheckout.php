<script src="https://www.paypalobjects.com/api/checkout.js"></script>

<script type="text/javascript">
    var CREATE_PAYMENT_URL = '<?=ipConfig()->baseUrl()?>paypal/create-payment?track=<?=$trackId?>';
    var EXECUTE_PAYMENT_URL = '<?=ipConfig()->baseUrl()?>paypal/execute-payment?track=<?=$trackId?>';

    paypal.Button.render({

        env: 'sandbox', // Or 'production'

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
            }).then(function () {
                location.reload();

                // The payment is complete!
                // You can now show a confirmation message to the customer
            });
        },

        onCancel: function (data, actions) {
            console.log('cancelled');
            console.log(data);
            console.log(actions);
        }

    }, '#paypal-button');
</script>