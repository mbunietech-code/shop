# ClickPesa Payment Setup

This project uses ClickPesa through `payment_requests` and completes the order only after ClickPesa verification confirms a paid transaction.

## Admin Setup

1. Open Admin > Business Settings > Payment Method > ClickPesa.
2. Enter the ClickPesa `client-id`, `api-key`, optional API/checksum secret, and optional webhook secret.
3. Enable the channels customers should see at checkout:
   - Airtel Money
   - M-Pesa / Vodacom
   - Mixx by Yas
   - HaloPesa
   - CRDB / Control Number
4. Save, then use `Test Connection` to verify token generation.
5. Add this webhook URL in the ClickPesa merchant dashboard:

```text
/api/payments/clickpesa/webhook
```

Use the full site URL, for example:

```text
https://example.com/api/payments/clickpesa/webhook
```

## Customer Flow

Customers choose the specific ClickPesa source at checkout, then enter their phone number. Mobile money channels initiate a USSD push. The CRDB/control-number option generates a ClickPesa bill payment number and lets the customer complete payment through supported bank or bill pay channels.

The system polls ClickPesa using the order reference and also accepts webhooks. Payment is marked successful only when ClickPesa returns `SUCCESS` or `SETTLED`, and the collected amount and currency match the payment request.

## Verification And Security

- ClickPesa credentials are stored encrypted when saved from admin.
- The integration uses a short alphanumeric `orderReference` accepted by ClickPesa.
- Webhooks are logged in `click_pesa_webhook_logs`.
- When checksum validation is enabled, webhooks without a valid checksum are rejected.
- Suspicious successful provider responses with mismatched amount or currency are marked `review_required` instead of creating an order.

## Deployment Notes

Run migrations after deployment:

```bash
php artisan migrate
```

ClickPesa documentation indicates testing is done against production credentials with small real amounts, so validate first with low-value TZS transactions.
