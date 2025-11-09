# CashfreeV2 Payment Gateway for Paymenter V2

A modern CashfreeV2 payment gateway extension for [Paymenter](https://paymenter.org) billing system. This extension enables seamless integration with Cashfree's Payment Gateway API v2025-01-01, featuring popup checkout, webhook support, and comprehensive payment verification.

## 🚀 Features

- ✅ **Popup Checkout** - Cashfree JS SDK v3 integration with modal payment window
- ✅ **Webhook Support** - Automatic payment confirmation via webhooks
- ✅ **Payment Verification** - Secure callback verification with order status check
- ✅ **Test & Production Mode** - Easy switching between sandbox and live environments
- ✅ **INR Currency** - Full support for Indian Rupee payments
- ✅ **Product Cart Details** - Optional product information in checkout (name, description, image)
- ✅ **Signature Verification** - HMAC SHA256 webhook signature validation
- ✅ **Error Handling** - User-friendly error messages and payment status tracking

## 📋 Requirements

### Mandatory User Profile Fields
⚠️ **IMPORTANT**: The following fields are required for CashfreeV2 payments:

1. **Phone Number** - Must be collected during user registration and stored in user properties with key `phone`
2. **Email Address** - User must have a valid email address

**Payments will fail if these fields are missing.**

### System Requirements
- Paymenter v1.x or higher
- PHP 8.0 or higher
- GuzzleHTTP client
- Laravel framework

## 📥 Installation
### Git Clone
1. Use this command to clone the repository in to the your paymenter project
Make sure run this command inside the folder `extensions/Gateways`
```bash
git clone https://github.com/username/cashfree-payment-gateway.git CashFreeV2
```
2. The extension will be automatically detected by Paymenter.

3. Navigate to **Admin Panel → Settings → Gateways** and enable CashfreeV2.

### Manual Download and Copy
1. Copy the `CashfreeV2` folder to your Paymenter extensions directory:
   ```
   /extensions/Gateways/CashfreeV2/
   ```

2. Follow steps of 2-3 of Git Clone


## ⚙️ Configuration

### 1. Get CashfreeV2 Credentials

#### For Testing (Sandbox):
1. Create a Cashfree account at [https://www.cashfree.com/](https://www.cashfree.com/)
2. Go to [Cashfree Dashboard](https://merchant.cashfree.com/)
3. Navigate to **Developers → API Keys**
4. Copy your **Test App ID** and **Test Secret Key**

#### For Production (Live):
1. Follow step 1-3 of Sandbox
2. Get your **Production App ID** and **Production Secret Key** from the same location

### 2. Configure in Paymenter

Navigate to **Admin Panel → Settings → Gateways → CashfreeV2** and configure:

| Field                           | Description                                           | Required |
| ------------------------------- | ----------------------------------------------------- | -------- |
| **Client APP ID**               | Production App ID from Cashfree                       | Yes      |
| **Client Secret Key**           | Production Secret Key from Cashfree                   | Yes      |
| **Test APP ID**                 | Sandbox App ID for testing                            | No       |
| **Test Secret Key**             | Sandbox Secret Key for testing                        | No       |
| **Test Mode**                   | Enable to use sandbox credentials                     | No       |
| **Enable Product Cart Details** | Send product name, description, and image to Cashfree | No       |

### 3. Configure Webhooks on Cashfree Dashboard

1. Go to **Cashfree Dashboard → Developers → Webhooks**
2. Add your webhook URL:
   ```
   https://yourdomain.com/extensions/gateways/cashfree/webhook
   ```
3. Select webhook events: `PAYMENT_SUCCESS_WEBHOOK`
4. Save the webhook configuration

## 🔧 Usage

### For Customers

1. Navigate to an unpaid invoice
2. Click "Pay Now"
3. Select "CashfreeV2" as payment method
4. Complete payment in the CashfreeV2 popup modal
5. Get automatic redirect back to invoice with payment confirmation

### Payment Flow

```
Invoice → Pay Button → CashfreeV2 Modal → Payment → Callback → Webhook → Invoice Paid
```

1. **Order Creation**: Creates CashfreeV2 order with customer and product details
2. **Modal Popup**: Opens CashfreeV2 checkout in popup window
3. **Payment Processing**: Customer completes payment on Cashfree
4. **Callback Verification**: System verifies payment status via API
5. **Webhook Confirmation**: CashfreeV2 sends webhook for final confirmation
6. **Invoice Update**: Payment is recorded and invoice marked as paid

## 🔒 Security Features

### Webhook Signature Verification
All webhooks are verified using HMAC SHA256 signature:
```php
$payload = $timestamp . $content;
$expected_signature = base64_encode(hash_hmac('sha256', $payload, $secretKey, true));
```

### Payment Verification
- Callback verification via Cashfree API GET request
- Duplicate payment prevention
- Order status validation (PAID, PENDING, CANCELLED, EXPIRED)

### SSL/TLS
- Supports local development with SSL verification bypass
- Production uses strict SSL verification

## 📁 File Structure

```
CashfreeV2/
├── CashfreeV2.php              # Main gateway class
├── LICENSE                   # MIT License
├── README.md                 # This file
├── routes/
│   └── web.php              # Webhook and callback routes
└── views/
    ├── error.blade.php      # Error display page
    └── pay.blade.php        # Payment checkout page with JS SDK
```

## 🛠️ API Integration

### Cashfree API Version
- **API Version**: `2025-01-01`
- **SDK**: Cashfree JS SDK v3
- **Endpoints**:
  - Sandbox: `https://sandbox.cashfree.com/pg/orders`
  - Production: `https://api.cashfree.com/pg/orders`

### Payload Structure

**Minimum Required:**
```json
{
  "order_amount": 100.00,
  "order_currency": "INR",
  "order_id": "invoice_123_1234567890",
  "customer_details": {
    "customer_id": "1",
    "customer_phone": "9876543210",
    "customer_email": "user@example.com",
    "customer_name": "John Doe"
  },
  "order_meta": {
    "return_url": "https://yourdomain.com/callback",
    "notify_url": "https://yourdomain.com/webhook"
  }
}
```

**With Cart Details (Optional):**
```json
{
  "cart_details": {
    "cart_items": [{
      "item_id": "inv_123",
      "item_name": "Premium Hosting",
      "item_description": "Monthly subscription",
      "item_image_url": "https://yourdomain.com/image.png",
      "item_original_unit_price": 100.00,
      "item_discounted_unit_price": 100.00,
      "item_quantity": 1,
      "item_currency": "INR"
    }]
  },
  "order_tags": {
    "invoice_id": "123",
    "user_id": "1",
    "package": "Premium Hosting"
  }
}
```

## 🐛 Troubleshooting

### Payment Fails with "Phone number required"
- Ensure users have phone numbers in their profile
- Check that phone is stored in user properties with key `phone`
- Make phone number mandatory during registration

### Payment Fails with "Email required"
- Verify user has valid email address
- Check user table for email field

### Payment Fails with "Invalid amount"
- Invoice total must be greater than zero
- Check invoice has items with prices

### Webhook Not Receiving
- Verify webhook URL is accessible publicly
- Check CashfreeV2 dashboard webhook configuration
- Ensure webhook URL uses HTTPS in production
- Check Laravel logs for signature verification errors

### Payment Status Shows Pending
- Wait for webhook (can take a few seconds)
- Check CashfreeV2 dashboard for actual payment status
- Verify webhook signature is correct
- Check Laravel logs for any errors

## 📝 Changelog

### Version 1.0.0 (2025-11-09)
- Initial release
- Cashfree API v2025-01-01 integration
- Popup checkout with JS SDK v3
- Webhook and callback support
- Optional cart details
- Test and production mode
- HMAC SHA256 signature verification

## 🤝 Contributing

Contributions are welcome! Please feel free to submit pull requests or open issues.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 💬 Support

For issues and questions:
- Create an issue on GitHub
- Contact Master S Jit support
- Check [Cashfree Documentation](https://docs.cashfree.com/reference/pg-new-apis-endpoint)

## 🔗 Links

- [Paymenter](https://paymenter.org)
- [CashfreeV2](https://github.com/MasterSJit/cashfree-paymenter-gateway.git)
- [Cashfree API Documentation](https://docs.cashfree.com/reference/pg-new-apis-endpoint)
- [Cashfree Dashboard](https://merchant.cashfree.com/)

## ⚠️ Disclaimer

This extension is provided as-is. While we strive for reliability, please test thoroughly in sandbox mode before using in production. Master S Jit is not responsible for any financial losses or issues arising from the use of this extension.

---

**Made with ❤️ by Master S Jit**

**Supported by ServersBay (Blazing Fast Hosting Services)**
