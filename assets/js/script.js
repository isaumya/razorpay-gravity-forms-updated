function invokeRZP() {
    var data = JSON.parse(razorpay_script_vars.data);

    var setDisabled = function (id, state) {
        if (typeof state === 'undefined') {
            state = true;
        }

        var elem = document.getElementById(id);

        if (state === false) {
            elem.removeAttribute('disabled');
        } else {
            elem.setAttribute('disabled', state);
        }
    };

    // Payment was closed without handler getting called
    data.modal = {
        ondismiss: function () {
            setDisabled('btn-razorpay', false);
            document.getElementById('razorpayform').action = '';
            document.razorpayform.submit();
        }
    };

    data.handler = function (payment) {
        setDisabled('btn-razorpay-cancel');

        var successMsg = document.getElementById('msg-razorpay-success');

        successMsg.style.display = 'block';
        document.body.style.overflow = 'hidden';

        document.getElementById('razorpay_payment_id').value =
            payment.razorpay_payment_id;

        document.getElementById('razorpay_signature').value =
            payment.razorpay_signature;

        document.razorpayform.submit();
    };

    var razorpayCheckout = new Razorpay(data);

    // global method
    function openCheckout() {
        // Disable the pay button
        setDisabled('btn-razorpay');

        razorpayCheckout.open();
    }

    function addEvent(element, evnt, funct) {
        if (element.attachEvent) {
            return element.attachEvent('on' + evnt, funct);
        } else {
            return element.addEventListener(evnt, funct, false);
        }
    }

    // Attach event listener
    addEvent(document.getElementById('btn-razorpay'), 'click', openCheckout);

    setTimeout(openCheckout);
}

invokeRZP();
