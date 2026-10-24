# MazSen Munch & Sip (PHP, Tailwind CSS, MySQL)

## XAMPP setup

1. Copy this project into `C:\xampp\htdocs\Mazsen Munch`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`, choose **Import**, select `database/schema.sql`, then import it. This single SQL file creates or updates the one `munch_db` database, all application tables, and the initial menu. Reimporting it preserves existing accounts and orders.
4. Check the MySQL settings in `includes/db.php`. The defaults match a fresh XAMPP install (`root`, blank password).
5. Open `http://localhost/Mazsen%20Munch/`.

To open the customer welcome page directly, visit `http://localhost/Mazsen%20Munch/customer/welcome.php`.

## Rider website

- Rider sign-in: `http://localhost/Mazsen%20Munch/rider-login.php`
- Rider sign-up: `http://localhost/Mazsen%20Munch/rider-signup.php`
- Rider dashboard (after sign-in): `http://localhost/Mazsen%20Munch/rider-home.php`
- Admin rider roster and delivery assignment: `http://localhost/Mazsen%20Munch/admin/logistics.php`

Riders can sign up from the rider sign-up page. New accounts stay inactive until the admin approves them in the Logistics roster; after approval, riders can sign in to see assigned deliveries and share GPS. Admins can also create rider accounts directly in the roster. The private, per-order GPS link at `http://localhost/Mazsen%20Munch/rider.php?token=<tracking-token>` remains available for direct tracking as well.

The PHP API in `includes/api.php` loads the menu and saves/retrieves orders from MySQL. The cart remains in browser local storage. Tailwind CSS and the delivery map are loaded from CDNs, so the browser needs internet access to show the full interface and map; the project stylesheet is in `assets/css/` and JavaScript is in `assets/js/`.

## Customer accounts and ordering

Customers can browse the menu without signing in. To place an order, they must create an account at `customer/signup.php` or sign in at `customer/login.php`. Signup requires their full name, email, phone number, complete address, and a password. Passwords are stored as secure hashes. Orders are associated with the signed-in customer and include a copy of their name, phone, and delivery address for the order record; customers only see their own order history. New orders begin as `Pending`; a customer may cancel their own order while it remains pending. Checkout can share the customer phone’s current location to help select the closest rider. The assigned rider must accept the offer before the owner can advance that order to `On the way`. Customers see the step tracker in order history, which refreshes periodically. When an order is on the way, the rider shares GPS from the rider app; the customer then sees the rider on the order map. GPS requires HTTPS on the live site (localhost works for local development), and the rider app needs to stay open during offers and location sharing. The private per-order tracking link remains available as a fallback. Tracking stops and the link is revoked when the order is marked Delivered. Each product's Buy button asks for confirmation and submits exactly one of that item.

### Google and Facebook sign-in

Social sign-in uses server-side OAuth and needs credentials from your own provider apps. In Google Cloud Console create an OAuth client with application type **Web application**. In Meta for Developers create an app with **Facebook Login** enabled and add its Valid OAuth Redirect URI. Add these exact callback URLs to the matching provider app:

- Google: `http://localhost/Mazsen%20Munch/customer/google-auth.php`
- Facebook: `http://localhost/Mazsen%20Munch/customer/facebook-auth.php`

Set these four Apache environment variables in `C:\xampp\apache\conf\httpd.conf`, replacing the quoted examples with your app credentials, then restart Apache:

```apache
SetEnv MAZSEN_GOOGLE_CLIENT_ID "your-google-client-id"
SetEnv MAZSEN_GOOGLE_CLIENT_SECRET "your-google-client-secret"
SetEnv MAZSEN_FACEBOOK_APP_ID "your-facebook-app-id"
SetEnv MAZSEN_FACEBOOK_APP_SECRET "your-facebook-app-secret"
```

For local testing, add your Google account as a test user if the OAuth consent screen is in testing mode, and add your Facebook account as an app tester/developer. Reimport `database/schema.sql` once so the customer table has unique Google and Facebook account IDs. Social sign-up creates the customer with their provider name and email; they can add phone and delivery address in account settings before checkout. On the public site, configure HTTPS callback URLs using the site's real domain in both provider consoles. Keep the app secrets private and never put them in browser JavaScript.

## Owner analytics

After importing the schema, open `http://localhost/Mazsen%20Munch/admin/signup.php` to create the first owner account. Signup closes automatically once that account exists. Use `http://localhost/Mazsen%20Munch/admin/` for later sign-ins. The dashboard calculates revenue, order count, average order value, daily sales, best-selling items, and recent orders from saved order data. The owner can toggle the store between open and closed from the dashboard; customers see the current status, and the server rejects new orders while closed. If you already imported the database before adding this feature, reimport `database/schema.sql` once to create the settings table; subsequent imports keep its current status.

## Database contents

The `database/schema.sql` file creates the `munch_db` database and its tables, and inserts the starter menu. Reimporting it preserves customer accounts and orders while adding any missing customer, rider, and logistics columns. The dashboard analytics are calculated from `orders` and `order_items`, so they reflect actual saved purchases.

## Logistics and rider dispatch

Open `http://localhost/Mazsen%20Munch/admin/logistics.php` after signing in as the owner. Reimport `database/schema.sql` once to add rider offers, GPS coordinates, and payout settings to an older database; existing customer, rider, and order records are preserved. The initial fixed rider payout is ₱50 per delivery and can be changed in Logistics; each offer saves a payout snapshot. When the customer places an order, checkout optionally asks to share the customer phone’s location and offers the order to the closest active rider with a GPS update from the last five minutes, measured by straight-line distance. If customer location is declined or there is no recent rider GPS, dispatch falls back to rider availability and workload. Riders can accept or decline the offer in `rider-home.php`; the popup shows the order total and that rider’s delivery pay. Enable phone alerts and keep the rider app open to receive offers; offers move to the next rider if unanswered for 60 seconds. The live map and customer order tracker update from rider GPS. Assignment runs locally without an external AI service or API key.
