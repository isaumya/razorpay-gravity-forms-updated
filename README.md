# Razorpay Gravity Forms Plugin for WordPress - Updated
This is an updated version of Razorpay gravity forms plugin which the Razorpay team didn't updated for years.

### ✅ Here are a list of things that has been fixed:
Please checkout the changelog below to know about the changes made in the plugin. Please check the bottom of the readme file for the changelog.

### ❌ Still existing problem
* Due to the weired nature of Razorpay API, some design issues are there. I have fixed some design issues in v1.1.1. Check the changelog and screenshot section for more info.
* Once the payment gets done it doesn't come back to the form page and instead shows `Callback is complete.` Tried a lot of fix it but couldn't. I've added some extra info and auto redirect to home page though.
* **Doesn't support subscription payment.** - Only Razorpay Team can fix this issue
* Under the Feed settings no matter what you choose it doesn't reflect anything in the actual payment system.

### 🖥 How to Install
Inside your WordPress install's `wp-content/plugins/` folder create a new folder named `razorpay-gravity-forms`. Download the source ZIP from the release section and upload the files inside the ZIP to the `razorpay-gravity-forms` folder.

If you already have the `razorpay-gravity-forms` folder, simply overwrite the existing files with the one inside the release ZIP.

Now Simply go to your `WordPress Plugin` section and activate the plugin.

## 📸 Screenshots
Here are some screenshot of the payment flow so that you guys can understand how the system works and can also see how much better it looks than the gargabe unsuported plugin by Razorpay Team. No matter how many times you ask them to make the plugin good, they will pay no head to it.

| <img src="https://i.imgur.com/YFOUjZ4.jpg"> | <img src="https://i.imgur.com/hW9KeGV.jpg"> | <img src="https://i.imgur.com/WlmPh9I.jpg"> |
|:-------------------------:|:-------------------------:|:-------------------------:|
|<img src="https://i.imgur.com/WxL0iOv.jpg"> | <img src="https://i.imgur.com/aVgB9KT.jpg">| <img src="https://i.imgur.com/qjotdf6.jpg?1"> |

## 🙏 Humble Note 🙏
If any gravity form dev or Razorpay team is looking into this repo, please for god sake update your plugins to make them compete with paypal, stripe plugins. This is crazy that you guys barely support your plugins and your plugin quality is so bad.

## Changelog 💻
All the changes that has been made to this pplugin on version to version basis.

### v1.2.0 & v1.2.1 - 22/08/2019
* Fixed some typos
* Added IP Stack API support - one of the major problem with Razorpay payment form (specially for international payments) in the the phone number field, it doesn't specify in which style the number needs to be entered. For example you can type +919830098300 or you can type 9830098300. IIn the second case,  Razorpay automatically adds +91 to your number ince your international clients won'tr get any SMS. With the help of thsi feature, the +Country Code will automatically be added before the number. - This feature uses ipstack.com API.

### v1.1.1 - 02/06/2019
* When the user Submit the Gravity Form, a sweet preloader with animation will fill up the page while making the payment instead of the ugly ways it has been done in the original plugin developed by Razorpay Team.
* The Email ID and Phone Number entered by the user in the form nor properly gets prefilled in the payment poup, which wasn't the case earlier.
* Once the payment is done, the preloader will remain at the page so that the user can't do anything else while the callback is being processed
* After the callback is finished, instead of just showing `Callback processed successfully`, It will also show a proper wlcome greeting along with letting the user know that the payment is done. On this page the user will also see the Transaction ID for future reference in a nice designed manner. Finally along with having a hyperlink to go back to the home page, the page will automatically be redirected to the Home Page of your site after 15 seconds.
* Now this plugin is somewhat product site capable.

### v1.0 - 1.1
* Payment event has been added properly. So, when a payment is get made, the payment status gets set to **PAID**, so you can send email notification only if payment has been made.
* As soon as a payment gets processed the payment status gets set to **Processing** and if an entry has a payment status processing that means it has not been completed yet.
* The Pay and Cancel button doesn't show anymore as the payment popup auto opens
* Updated the PHP SDK to the currently available latest version v2.5
* Supports all currency payment
