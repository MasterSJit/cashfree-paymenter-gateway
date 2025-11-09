@assets
<script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
@endassets

@script
<script>
    console.log('Cashfree Payment Page Loaded');
    console.log('Payment Session ID:', '{{ $paymentSessionId }}');
    console.log('Order ID:', '{{ $orderId ?? "not set" }}');
    console.log('Test Mode:', '{{ $testMode ? "true" : "false" }}');

    const cashfree = Cashfree({
        mode: "{{ $testMode ? 'sandbox' : 'production' }}",
    });

    const checkoutOptions = {
        paymentSessionId: "{{ $paymentSessionId }}",
        redirectTarget: "_modal",
    };

    console.log('Opening Cashfree Checkout Modal...');

    cashfree.checkout(checkoutOptions).then((result) => {
        console.log('Cashfree Checkout Result:', result);
        
        if (result.error) {
            // User closed the popup or there was an error
            console.error("Payment error:", result.error);
            console.log('Redirecting to invoice page due to error...');
            window.location.href = "{{ route('invoices.show', ['invoice' => $invoice->id]) }}";
        } else if (result.redirect) {
            // Payment will be redirected (shouldn't happen with _modal)
            console.log("Payment will be redirected");
            console.log('Redirecting to invoice page...');
            window.location.href = "{{ route('invoices.show', ['invoice' => $invoice->id]) }}";
        } else if (result.paymentDetails) {
            // Payment completed - redirect to callback URL
            console.log("Payment completed successfully!");
            console.log("Payment Details:", result.paymentDetails);
            const callbackUrl = "{{ route('extensions.gateways.cashfreev2.callback', ['invoiceId' => $invoice->id]) }}?order_id=" + (result.paymentDetails.orderId || '{{ $orderId ?? "" }}');
            console.log('Redirecting to callback URL:', callbackUrl);
            window.location.href = callbackUrl;
        } else {
            // Unknown response
            console.error("Unknown response:", result);
            console.log('Redirecting to invoice page due to unknown response...');
            window.location.href = "{{ route('invoices.show', ['invoice' => $invoice->id]) }}";
        }
    }).catch((error) => {
        console.error("Checkout SDK error:", error);
        console.log('Redirecting to invoice page due to SDK error...');
        window.location.href = "{{ route('invoices.show', ['invoice' => $invoice->id]) }}";
    });
</script>
@endscript