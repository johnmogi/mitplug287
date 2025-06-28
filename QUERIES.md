# Aviv Order Admin Queries

## Working Queries (Updated)

### Recent Orders with Rental Dates (90 days)
```sql
SELECT DISTINCT 
    o.id as order_id,
    o.date_created_gmt,
    o.billing_email,
    o.total_amount,
    o.status,
    oi.order_item_id,
    oi.order_item_name as product_name,
    oim.meta_value as rental_dates,
    oim2.meta_value as product_id,
    om.meta_value as billing_first_name,
    om2.meta_value as billing_last_name,
    om3.meta_value as billing_phone
FROM gjc_wc_orders o
JOIN gjc_woocommerce_order_items oi ON o.id = oi.order_id
LEFT JOIN gjc_woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id 
    AND oim.meta_key = 'Rental Dates'
LEFT JOIN gjc_woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id 
    AND oim2.meta_key = '_product_id'
LEFT JOIN gjc_wc_orders_meta om ON o.id = om.order_id 
    AND om.meta_key = '_billing_first_name'
LEFT JOIN gjc_wc_orders_meta om2 ON o.id = om2.order_id 
    AND om2.meta_key = '_billing_last_name'
LEFT JOIN gjc_wc_orders_meta om3 ON o.id = om3.order_id 
    AND om3.meta_key = '_billing_phone'
WHERE o.status NOT IN ('trash', 'auto-draft')
ORDER BY o.date_created_gmt DESC
LIMIT 100
```

### Orders by Client
```sql
SELECT 
    CONCAT(COALESCE(om.meta_value, ''), ' ', COALESCE(om2.meta_value, '')) as client_name,
    o.billing_email as email,
    om3.meta_value as phone,
    COUNT(DISTINCT o.id) as total_orders,
    SUM(o.total_amount) as total_spent,
    MAX(o.date_created_gmt) as last_order_date
FROM gjc_wc_orders o
LEFT JOIN gjc_wc_orders_meta om ON o.id = om.order_id 
    AND om.meta_key = '_billing_first_name'
LEFT JOIN gjc_wc_orders_meta om2 ON o.id = om2.order_id 
    AND om2.meta_key = '_billing_last_name'
LEFT JOIN gjc_wc_orders_meta om3 ON o.id = om3.order_id 
    AND om3.meta_key = '_billing_phone'
WHERE o.status NOT IN ('trash', 'auto-draft')
GROUP BY client_name, email, phone
ORDER BY total_orders DESC
LIMIT 100
```

### Products with All Rental Date Ranges
```sql
SELECT 
    p.ID as product_id,
    p.post_title as product_name,
    COUNT(DISTINCT o.id) as total_rentals,
    SUM(o.total_amount) as total_revenue,
    GROUP_CONCAT(
        CONCAT(
            DATE_FORMAT(o.date_created_gmt, '%d.%m.%Y'), ': ',
            COALESCE(oim.meta_value, 'N/A')
        ) 
        ORDER BY o.date_created_gmt DESC
        SEPARATOR '\n'
    ) as rental_date_ranges
FROM gjc_posts p
LEFT JOIN gjc_woocommerce_order_itemmeta oim2 ON p.ID = oim2.meta_value 
    AND oim2.meta_key = '_product_id'
LEFT JOIN gjc_woocommerce_order_items oi ON oim2.order_item_id = oi.order_item_id
LEFT JOIN gjc_wc_orders o ON oi.order_id = o.id
LEFT JOIN gjc_woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id 
    AND oim.meta_key = 'Rental Dates'
WHERE p.post_type = 'product'
AND o.status NOT IN ('trash', 'auto-draft')
GROUP BY p.ID, p.post_title
ORDER BY total_rentals DESC
LIMIT 100
```

## Improved Product Query for JavaScript Implementation
```sql
SELECT 
    p.ID as product_id,
    p.post_title as product_name,
    COUNT(DISTINCT o.id) as total_rentals,
    SUM(o.total_amount) as total_revenue,
    MAX(o.date_created_gmt) as last_rental_date,
    GROUP_CONCAT(
        DISTINCT CONCAT(
            'Order #', o.id, ': ',
            COALESCE(oim.meta_value, 'N/A')
        ) 
        ORDER BY o.date_created_gmt DESC
        SEPARATOR '\n'
    ) as rental_dates_list
FROM gjc_posts p
LEFT JOIN gjc_woocommerce_order_itemmeta oim2 ON p.ID = oim2.meta_value 
    AND oim2.meta_key = '_product_id'
LEFT JOIN gjc_woocommerce_order_items oi ON oim2.order_item_id = oi.order_item_id
LEFT JOIN gjc_wc_orders o ON oi.order_id = o.id
LEFT JOIN gjc_woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id 
    AND oim.meta_key = 'Rental Dates'
WHERE p.post_type = 'product'
AND o.status NOT IN ('trash', 'auto-draft')
GROUP BY p.ID, p.post_title
ORDER BY total_rentals DESC
LIMIT 100
```

## Table Structure (Confirmed)

### WooCommerce Order Tables
- `gjc_wc_orders`: Main orders table
- `gjc_woocommerce_order_items`: Order line items
- `gjc_woocommerce_order_itemmeta`: Order item metadata
- `gjc_wc_orders_meta`: Order metadata
- `gjc_posts`: Products information

### Important Meta Keys
- `Rental Dates`: Stored in order_itemmeta (format: "DD.MM.YYYY - DD.MM.YYYY")
- `_product_id`: Product ID in order_itemmeta
- `_billing_first_name`: Customer first name
- `_billing_last_name`: Customer last name
- `_billing_phone`: Customer phone number

## Notes
- Table prefix is `gjc_`
- All queries exclude trashed and auto-draft orders
- Dates are stored in the format DD.MM.YYYY - DD.MM.YYYY 
- WooCommerce status values have 'wc-' prefix in the database (e.g., 'wc-processing')
- Special rental statuses: wc-rental-confirmed, wc-rental-completed, wc-rental-cancelled
