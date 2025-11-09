<?php

namespace Paymenter\Extensions\Gateways\CashfreeV2;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Helpers\ExtensionHelper;
use App\Classes\Extension\Gateway;
use App\Models\Invoice;
use Illuminate\Support\Facades\View;

class CashfreeV2 extends Gateway
{
    public function boot()
    {
        require __DIR__ . '/routes/web.php';
        View::addNamespace('extensions.gateways.cashfreev2', __DIR__ . '/views');
    }

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'client_app_id',
                'label' => 'Client APP ID',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'client_secret_key',
                'label' => 'Client Secret Key',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'test_app_id',
                'label' => 'Test APP ID',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'test_secret_key',
                'label' => 'Test Secret Key',
                'type' => 'text',
                'required' => false,
            ],
            [
                'name' => 'test_mode',
                'label' => 'Test Mode',
                'type' => 'checkbox',
                'required' => false,
            ],
            [
                'name' => 'enable_cart_details',
                'label' => 'Enable Product Cart Details',
                'type' => 'checkbox',
                'description' => 'Enable this to send product/cart details (item name, description, image) to Cashfree payment gateway.',
                'required' => false,
            ],
            [
                'name' => 'warning_message',
                'label' => '⚠️ IMPORTANT REQUIREMENTS',
                'type' => 'placeholder',
                'description' => "To use Cashfree Payment Gateway:
                1. Phone Number is MANDATORY - Users must have a phone number in their profile.
                2. Email Address is MANDATORY - Users must have a valid email address.
                3. Make sure to collect phone numbers during user registration.
                4. Payments will fail if phone number or email is missing.",
            ],
        ];
    }

    public function pay($invoice, $total)
    {
        // Validate amount
        if ($total <= 0) {
            return view('extensions.gateways.cashfreev2::error', [
                'error' => 'Invalid payment amount. The invoice total must be greater than zero.',
            ]);
        }

        if ($invoice->currency_code !== "INR") {
            return view('extensions.gateways.cashfreev2::error', [
                'error' => 'The product currency code must be "INR" to make payments with Cashfree!',
            ]);
        }

        // Phone number is ALWAYS required by Cashfree API
        $phoneProperty = $invoice->user->properties()->where('key', 'phone')->first();
        if (!$phoneProperty || empty($phoneProperty->value)) {
            return view('extensions.gateways.cashfreev2::error', [
                'error' => 'A valid phone number is required to make payments with Cashfree. Please update your account details with a phone number.',
            ]);
        }

        // Validate phone number format (remove spaces, dashes, etc.)
        $phone = preg_replace('/[^0-9+]/', '', $phoneProperty->value);
        
        // Cashfree expects phone in format: +91XXXXXXXXXX or just XXXXXXXXXX (10 digits for India)
        if (!preg_match('/^(\+91)?[6-9]\d{9}$/', $phone)) {
            return view('extensions.gateways.cashfreev2::error', [
                'error' => 'Invalid phone number format. Please use a valid 10-digit Indian mobile number (e.g., 9876543210 or +919876543210).',
            ]);
        }

        // Email is also required
        if (empty($invoice->user->email)) {
            return view('extensions.gateways.cashfreev2::error', [
                'error' => 'A valid email address is required to make payments with Cashfree. Please update your account details.',
            ]);
        }

        $appId = $this->config('test_mode') ? $this->config('test_app_id') : $this->config('client_app_id');
        $secretKey = $this->config('test_mode') ? $this->config('test_secret_key') : $this->config('client_secret_key');
        $url = $this->config('test_mode') ? 'https://sandbox.cashfree.com/pg/orders' : 'https://api.cashfree.com/pg/orders';

        $client = new Client();

        $orderId = strtoupper(uniqid('inv_') . '_' . $invoice->id);

        // Ensure phone number starts with country code for Cashfree
        $formattedPhone = $phone;
        if (!str_starts_with($phone, '+')) {
            $formattedPhone = '+91' . $phone;
        }

        // Cashfree requires customer_id, customer_phone, customer_email, and customer_name
        $payload = [
            'order_amount' => (float)$total,
            'order_currency' => "INR",
            'order_id' => $orderId,
            'customer_details' => [
                'customer_id' => (string)$invoice->user->id,
                'customer_phone' => $formattedPhone,
                'customer_email' => $invoice->user->email,
                'customer_name' => $invoice->user->name ?? 'Customer',
            ],
            'order_meta' => [
                'return_url' => route('extensions.gateways.cashfreev2.callback', ['invoiceId' => $invoice->id]),
                'notify_url' => route('extensions.gateways.cashfreev2.webhook'),
            ],
        ];

        // Add cart details if enabled
        if ($this->config('enable_cart_details')) {
            // Get product/item details from invoice
            $itemName = 'Invoice #' . ($invoice->number ?? $invoice->id);
            $itemDesc = $invoice->description ?? '';
            $itemImage = '';

            try {
                if ($invoice->items()->count() > 0) {
                    $first = $invoice->items()->first();
                    
                    // Try to get item description
                    if ($first->description) {
                        $itemDesc = $first->description;
                    }
                    
                    // Try to get product details from reference (service -> product)
                    if ($first->reference && $first->reference instanceof \App\Models\Service) {
                        $service = $first->reference;
                        if ($service->product) {
                            $itemName = $service->product->name;
                            
                            // Get product image if available
                            if ($service->product->image) {
                                $itemImage = url(\Illuminate\Support\Facades\Storage::url($service->product->image));
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore and use fallbacks
            }

            // Add cart details
            $payload['cart_details'] = [
                'cart_items' => [
                    [
                        'item_id' => 'inv_' . $invoice->id,
                        'item_name' => $itemName,
                        'item_description' => $itemDesc,
                        'item_image_url' => $itemImage,
                        'item_original_unit_price' => (float)$total,
                        'item_discounted_unit_price' => (float)$total,
                        'item_quantity' => 1,
                        'item_currency' => 'INR',
                    ],
                ],
            ];

            $payload['order_tags'] = [
                'invoice_id' => (string)$invoice->id,
                'user_id' => (string)$invoice->user->id,
                'email' => $invoice->user->email,
                'package' => $itemName,
                'amount' => (string)$total,
            ];
        }

        try {
            $requestOptions = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-version' => '2025-01-01',
                    'x-client-id' => $appId,
                    'x-client-secret' => $secretKey,
                ],
                'json' => $payload,
            ];

            if (config('app.env') === 'local' || config('app.debug')) {
                $requestOptions['verify'] = false;
            }

            $response = $client->post($url, $requestOptions);

            $data = json_decode($response->getBody(), true);

            if (isset($data['payment_session_id'])) {
                return view('extensions.gateways.cashfreev2::pay', [
                    'invoice' => $invoice,
                    'paymentSessionId' => $data['payment_session_id'],
                    'testMode' => $this->config('test_mode'),
                    'orderId' => $orderId,
                ]);
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $errorBody = $response ? $response->getBody()->getContents() : 'No response body';
            
            // Try to parse the error message
            $errorMessage = 'Failed to create payment order. ';
            if ($response) {
                $errorData = json_decode($errorBody, true);
                if (isset($errorData['message'])) {
                    $errorMessage .= $errorData['message'];
                } elseif (isset($errorData['error_description'])) {
                    $errorMessage .= $errorData['error_description'];
                } else {
                    $errorMessage .= 'Please check your payment details and try again.';
                }
            }
            
            return view('extensions.gateways.cashfreev2::error', [
                'error' => $errorMessage,
            ]);
        } catch (\Exception $e) {
            
            throw new \Exception('Failed to create order: ' . $e->getMessage());
        }
    }

    public function webhook(Request $request)
    {
        $content = $request->getContent();
        $timestamp = $request->header('x-webhook-timestamp');
        $signature = $request->header('x-webhook-signature');
        $secretKey = $this->config('test_mode') ? $this->config('test_secret_key') : $this->config('client_secret_key');

        $payload = $timestamp . $content;
        $expected_signature = base64_encode(hash_hmac('sha256', $payload, $secretKey, true));

        if ($signature !== $expected_signature) {
            return response('Signature verification failed', 401);
        }

        $data = json_decode($content, true);

        $orderId = $data['data']['order']['order_id'] ?? null;
        $orderAmount = $data['data']['order']['order_amount'] ?? null;
        $invoice_id = isset($data['data']['order']['order_tags']['invoice_id']) ? (int)$data['data']['order']['order_tags']['invoice_id'] : null;

        if ($data['type'] === 'PAYMENT_SUCCESS_WEBHOOK' && $invoice_id && $orderId) {
            // Prevent duplicate payments - check if payment already exists
            $invoice = Invoice::find($invoice_id);
            if ($invoice) {
                $existingPayment = $invoice->transactions()->where('transaction_id', $orderId)->exists();
                
                if (!$existingPayment) {
                    ExtensionHelper::addPayment($invoice_id, 'CashfreeV2', $orderAmount, null, $orderId);
                }
            }
        }

        return response('Webhook received and processed successfully');
    }

    public function callback(Request $request, $invoiceId)
    {
        $orderId = $request->query('order_id');

        if (!$orderId) {
            return redirect()->route('invoices.show', ['invoice' => $invoiceId])->with('notification', [
                'type' => 'error',
                'message' => 'Payment verification failed. No order ID provided.',
            ]);
        }

        $appId = $this->config('test_mode') ? $this->config('test_app_id') : $this->config('client_app_id');
        $secretKey = $this->config('test_mode') ? $this->config('test_secret_key') : $this->config('client_secret_key');
        $url = $this->config('test_mode') ? "https://sandbox.cashfree.com/pg/orders/{$orderId}" : "https://api.cashfree.com/pg/orders/{$orderId}";

        try {
            $client = new Client();

            $requestOptions = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-api-version' => '2025-01-01',
                    'x-client-id' => $appId,
                    'x-client-secret' => $secretKey,
                ],
            ];

            if (config('app.env') === 'local' || config('app.debug')) {
                $requestOptions['verify'] = false;
            }

            $response = $client->get($url, $requestOptions);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Failed to verify payment status. HTTP Status: ' . $response->getStatusCode());
            }

            $data = json_decode($response->getBody(), true);

            if (!$data) {
                throw new \Exception('Invalid response from payment gateway');
            }

            $invoice = Invoice::findOrFail($invoiceId);

            if (isset($data['order_status'])) {
                $status = $data['order_status'];
                $amount = $data['order_amount'] ?? 0;

                if ($status === 'PAID') {
                    $existingPayment = $invoice->transactions()->where('transaction_id', $orderId)->exists();

                    if (!$existingPayment) {
                        ExtensionHelper::addPayment($invoiceId, 'CashfreeV2', $amount, null, $orderId);
                    }

                    return redirect()->route('invoices.show', ['invoice' => $invoice->number])->with('notification', [
                        'type' => 'success',
                        'message' => 'Your payment of ₹' . number_format($amount, 2) . ' has been processed successfully. Transaction ID: ' . $orderId,
                    ]);
                } elseif ($status === 'ACTIVE' || $status === 'PENDING') {
                    return redirect()->route('invoices.show', ['invoice' => $invoice->number])->with('notification', [
                        'type' => 'warning',
                        'message' => 'Your payment is being processed. Please wait for confirmation or check back later.',
                    ]);
                } else {
                    $errorMessage = 'Payment failed.';
                    if ($status === 'CANCELLED') {
                        $errorMessage = 'Payment was cancelled. No charges were made.';
                    } elseif ($status === 'EXPIRED') {
                        $errorMessage = 'Payment session expired. Please try again.';
                    }

                    return redirect()->route('invoices.show', ['invoice' => $invoice->number])->with('notification', [
                        'type' => 'error',
                        'message' => $errorMessage,
                    ]);
                }
            }

            throw new \Exception('Order status not found in response');

        } catch (\Exception $e) {
            return redirect()->route('invoices.show', ['invoice' => $invoiceId])->with('notification', [
                'type' => 'error',
                'message' => 'Payment verification failed. Please contact support if amount was deducted. Error: ' . $e->getMessage(),
            ]);
        }
    }
}
