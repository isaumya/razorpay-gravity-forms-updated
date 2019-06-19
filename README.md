# Razorpay Gravity Forms Plugin for WordPress - Updated
This is an updated version of Razorpay gravity forms plugin which the Razorpay team didn't updated for years.

### Here are a list of things that has been fixed:
* Payment event has been added properly. So, when a payment is get made, the payment status gets set to **PAID**, so you can send email notification only if payment has been made.
* As soon as a payment gets processed the payment status gets set to **Processing** and if an entry has a payment status processing that means it has not been completed yet.
* The Pay and cancel button doesn't show anymore as the payment popup auto opens
* Updated the PHP SDK to the currently available latest version v2.5
* Supports all currency payment

### Still existing problem
* Due to the weired nature of Razorpay API, processing payment and other design issues are there. I didn't have anymore time to look into them.
* Once the payment gets done it doesn't come back to the form page and instead shows `Callback is complete.` Tried a lot of fix it but couldn't. I've added some extra info though.
* Doesn't support subscription payment.
* Under the Feed settings no matter what you choose it doesn't reflect anything in the actual payment system.

## Humble Note
If any gravity form dev or Razorpay team is looking into this repo, please for god sake update your plugins to make them compete with paypal, stripe plugins. This is crazy that you guys barely support your plugins and your plugin quality is so bad.
