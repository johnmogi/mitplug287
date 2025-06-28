🧩 Goal
Improve how stock levels are automatically managed on a WordPress booking site, especially to restore stock once a product's rental period ends. Also, provide better admin tools to monitor stock against active bookings.

✅ Current Situation
Each product has a stock quantity (often 1, sometimes more).

Bookings are tracked by rental start and end dates stored in custom fields.

Admins manually track stock and adjust based on return dates.

There’s already a custom plugin with calendar view for order visibility.

Frontend warns users if items are low or overbooked, but backend lacks automatic stock flow.

🔧 Needed Features (for the Developer)
Auto-Restock Logic

After a booking's end date has passed, automatically return the booked quantity to the item's stock.

This should run via a cron job or a hook that checks daily for completed rentals.

Update the custom stock field or meta accordingly.

Admin Stock Tracker Tab

In the existing custom admin plugin, add a new tab/page called something like "Stock Monitor".

Show:

Product name

Total stock

Currently booked units (based on today's date and ongoing rentals)

Free units available

Optionally highlight negative or overbooked stock.

Frontend Optional Sync

Add a setting in the admin to choose whether the frontend should display:

Live stock count

Estimated availability

Low-stock alerts (as it does now)

Optional: Manual Override

Let admin manually mark a product as “returned” earlier than the scheduled return date (useful if early returns happen).

📦 Example Flow
Item A has stock of 2.

It’s booked for:

Booking #1: Jan 10–Jan 15 (1 unit)

Booking #2: Jan 12–Jan 18 (1 unit)

On Jan 16:

System should see that Booking #1 has ended → increase stock by 1.

Booking #2 is ongoing → still deduct 1 from available stock.

Available stock = 1


Auto-Restock Logic: A system that automatically returns items to inventory after their rental period ends.
Admin Stock Tracker: A new tab in your existing plugin to monitor stock levels against active bookings.
Plus two optional features: 3. Frontend display settings for stock visibility 4. Manual early-return functionality

This is absolutely feasible to implement. The example flow provides a good illustration of how the system should handle overlapping bookings.

To solve this, we would need to:

Set up a WordPress cron job or daily hook to check for ended rentals
Update stock quantities when rentals end
Add the Stock Monitor tab to your plugin
Create the settings for frontend display options


The new Stock Monitor tab in the admin interface?
Frontend display settings for stock visibility?