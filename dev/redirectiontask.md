e

@mitnafun-upro 
THERE ARE A FEW2 ISSUES
FIRST WHEN I AM ON THE CHECKOUT REVIEW ORDER AND I REMOVE ALL ITEMS FROM MY CART THERE IS A REDIRECTION INTO A BROKEN BASKET PAGE
THEN THAT PAGE GETS REDIRECTED TO

@basket_redirects.log 

Revert
I'll help you investigate the basket redirection issue. Let's first check the basket_redirects.log file to understand what's happening during the redirection process.

Analyzed
basket_redirects.log#L1-50
I'll create a plan to tackle the basket redirection issue.

Planned for 16s

View Plan
Completed
Inspect basket_redirects.log for redirect pattern and messages.
I'll search for the code that's triggering the redirect to the /basket/ page. Let's look for any relevant WooCommerce hooks or functions in the theme.

Searched codebase
1 result
Searched inc:\Users\USUARIO\Documents\SITES\BMIT\app\public\wp-content\themes\mitnafun-uprowith queryRedirecting back to referrer
I found the function causing the redirection loop. Let's check the 
mitnafun_redirect_basket_to_previous
 function in 
functions.php
 to understand why it's creating an infinite loop when the cart is emptied during checkout.

Analyzed
functions.php#L2000-2100
I've identified the issue in the 
mitnafun_redirect_basket_to_previous
 function. The problem is that when you remove all items from the cart on the checkout page, it redirects to the basket page, which then tries to redirect back to the checkout page, creating an infinite loop.

Here's what's happening:

You remove the last item from the cart on the checkout page
WooCommerce redirects to /basket/ with a ?removed_item=1 parameter
The 
mitnafun_redirect_basket_to_previous
 function sees the empty cart and tries to redirect back to the checkout page
This creates an infinite loop between the basket and checkout pages
Let me fix this by modifying the function to handle this specific case: