<?php
// Plugin path
$dir = dirname(__DIR__);
// Include the Razorpay PHP SDK
require_once("{$dir}/razorpay-gravity-forms/razorpay-sdk/Razorpay.php");

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

GFForms::include_payment_addon_framework();

// Start of the payment class
class GFRazorpay extends GFPaymentAddOn {
  //Razorpay plugin config key ID and key secret
  const GF_RAZORPAY_KEY = 'gf_razorpay_key';
  const GF_RAZORPAY_SECRET = 'gf_razorpay_secret';

  //Razorpay API attributes
  const RAZORPAY_ORDER_ID = 'razorpay_order_id';
  const RAZORPAY_PAYMENT_ID = 'razorpay_payment_id';
  const RAZORPAY_SIGNATURE = 'razorpay_signature';

  //Cookie set for one day
  const COOKIE_DURATION = 86400;

  // Customer related fields
  const CUSTOMER_FIELDS_NAME = 'name';
  const CUSTOMER_FIELDS_EMAIL = 'email';
  const CUSTOMER_FIELDS_CONTACT = 'contact';

  // Declaring variables
  protected $_version = GF_RAZORPAY_VERSION;
  protected $_min_gravityforms_version = '1.9.3';
  protected $_slug = 'razorpay-gravity-forms';
  protected $_path = 'razorpay-gravity-forms/gf-razorpay.php';
  protected $_full_path = __FILE__;
  protected $_url = 'http://www.gravityforms.com';
	protected $_title = 'Gravity Forms Razorpay Add-On';
  protected $_short_title = 'Razorpay';
  protected $_supports_callbacks = true;

  // Permissions
	protected $_capabilities_settings_page = 'gravityforms_razorpay';
	protected $_capabilities_form_settings = 'gravityforms_razorpay';
  protected $_capabilities_uninstall = 'gravityforms_razorpay_uninstall';
  
  // Automatic upgrade enabled
  protected $_enable_rg_autoupgrade = false;
  
  private static $_instance = null;

  public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFRazorpay();
		}

		return self::$_instance;
  }
  
  private function __clone() {
  } /* do nothing */
  
  public function init_frontend() {
		parent::init_frontend();

    add_filter( 'gform_disable_post_creation', array( $this, 'delay_post' ), 10, 3 );
		add_filter( 'gform_disable_notification', array( $this, 'delay_notification' ), 10, 4 );
  }

  public function delay_post( $is_disabled, $form, $entry ) {

		$feed            = $this->get_payment_feed( $entry );
		$submission_data = $this->get_submission_data( $feed, $form, $entry );

		if ( ! $feed || empty( $submission_data['payment_amount'] ) ) {
			return $is_disabled;
		}

		return ! rgempty( 'delayPost', $feed['meta'] );
	}

	public function delay_notification( $is_disabled, $notification, $form, $entry ) {
		if ( rgar( $notification, 'event' ) != 'form_submission' ) {
			return $is_disabled;
		}

		$feed            = $this->get_payment_feed( $entry );
		$submission_data = $this->get_submission_data( $feed, $form, $entry );

		if ( ! $feed || empty( $submission_data['payment_amount'] ) ) {
			return $is_disabled;
		}

		$selected_notifications = is_array( rgar( $feed['meta'], 'selectedNotifications' ) ) ? rgar( $feed['meta'], 'selectedNotifications' ) : array();

		return isset( $feed['meta']['delayNotification'] ) && in_array( $notification['id'], $selected_notifications ) ? true : $is_disabled;
	}
  
  //----- SETTINGS PAGES ----------//
  public function plugin_settings_fields(){
    return array(
      array(
        'title'           => 'Razorpay Settings',
        'fields'          => array(
          array(
            'name'        => 'gf_razorpay_company_name',
            'label'       => esc_html__('Comapny Name', $this->_slug),
            'type'        => 'text',
            'class'       => 'medium',
          ),
          array(
            'name'        => self::GF_RAZORPAY_KEY,
            'label'       => esc_html__('Test Razorpay Key', $this->_slug),
            'type'        => 'text',
            'class'       => 'medium',
          ),
          array(
            'name'        => self::GF_RAZORPAY_SECRET,
            'label'       => esc_html__('Test Razorpay Secret', $this->_slug),
            'type'        => 'text',
            'class'       => 'medium',
          )
        ),
      ),
    );
  }

  public function feed_list_no_item_message() {
		$settings = $this->get_plugin_settings();
		if ( ! rgar( $settings, self::GF_RAZORPAY_KEY ) || ! rgar( $settings, self::GF_RAZORPAY_SECRET ) ) {
			return sprintf( esc_html__( 'To get started, please configure your %Razorpay Settings%s!', $this->_slug ), '<a href="' . admin_url( 'admin.php?page=gf_settings&subview=' . $this->_slug ) . '">', '</a>' );
		} else {
			return parent::feed_list_no_item_message();
		}
  }

  public function get_customer_fields($form, $feed, $entry) {
    $fields = array();

    $billing_fields = $this->billing_info_fields();

    foreach ($billing_fields as $field) {
      $field_id = $feed['meta']['billingInformation_' . $field['name']];

      $value = $this->get_field_value($form, $entry, $field_id);

      $fields[$field['name']] = $value;
    }

    return $fields;
  }

  public function redirect_url( $feed, $submission_data, $form, $entry ) {
    //updating lead's payment_status to Processing
		GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Processing' );
    
    $this->generate_razorpay_order($entry, $form);
  }

  public function generate_razorpay_order($entry, $form) {
    //gravity form method to get value of payment_amount key from entry
    $paymentAmount = rgar($entry, 'payment_amount');

    //It will be null first time in the entry
    if (empty($paymentAmount) === true) {
      $paymentAmount = GFCommon::get_order_total($form, $entry);
      gform_update_meta($entry['id'], 'payment_amount', $paymentAmount);
      $entry['payment_amount'] = $paymentAmount;
    }

    $key = $this->get_plugin_setting(self::GF_RAZORPAY_KEY);

    $secret = $this->get_plugin_setting(self::GF_RAZORPAY_SECRET);

    $api = new Api($key, $secret);

    $data = array(
      'receipt'         => $entry['id'],
      'amount'          => $paymentAmount * 100,
      'currency'        => $entry['currency'],
      'payment_capture' => 1
    );

    $razorpayOrder = $api->order->create($data);

    gform_update_meta($entry['id'], self::RAZORPAY_ORDER_ID, $razorpayOrder['id']);

    $entry[self::RAZORPAY_ORDER_ID] = $razorpayOrder['id'];

    GFAPI::update_entry($entry);

    setcookie(
      self::RAZORPAY_ORDER_ID,
      $entry[self::RAZORPAY_ORDER_ID],
      time() + self::COOKIE_DURATION,
      COOKIEPATH,
      COOKIE_DOMAIN,
      false,
      true
    );

    echo $this->generate_razorpay_form($entry, $form);
  }

  public function generate_razorpay_form($entry, $form) {
    //updating lead's payment_status to Processing
    GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Processing' );

    $feed = $this->get_payment_feed($entry, $form);

    $customerFields = $this->get_customer_fields($form, $feed, $entry);

    $key = $this->get_plugin_setting(self::GF_RAZORPAY_KEY);

    $razorpayArgs = [
      'key'         => $key,
      'name'        => $this->get_plugin_setting('gf_razorpay_company_name'),
      'amount'      => $entry['payment_amount'] * 100,
      'currency'    => $entry['currency'],
      'description' => $form['description'],
      'prefill'     => [
        'name'    => $customerFields[self::CUSTOMER_FIELDS_NAME],
        'email'   => $customerFields[self::CUSTOMER_FIELDS_EMAIL],
        'contact' => $customerFields[self::CUSTOMER_FIELDS_CONTACT],
      ],
      'notes'       => [
        'gravity_forms_order_id' => $entry['id']
      ],
      'order_id'    => $entry[self::RAZORPAY_ORDER_ID],
    ];

    wp_enqueue_style( 'gf-razorpay-style', plugin_dir_url(__FILE__) . 'assets/css/style.css', array(), null, 'all' );

    wp_enqueue_script(
      'razorpay_script',
      plugin_dir_url(__FILE__) . 'assets/js/script.min.js',
      array('checkout')
    );

    wp_localize_script(
      'razorpay_script',
      'razorpay_script_vars',
      array(
        'data' => json_encode($razorpayArgs)
      )
    );

    wp_register_script(
      'checkout',
      'https://checkout.razorpay.com/v1/checkout.js',
      null,
      null
    );

    wp_enqueue_script('checkout');

    $redirect_url = '?page=' . $this->_slug;

    return $this->generate_order_form($redirect_url);
  }

  public function generate_order_form($redirect_url) {
    $html = "<form id ='razorpayform' name='razorpayform' action='{$redirect_url}' method='POST'>
      <input type='hidden' name='razorpay_payment_id' id='razorpay_payment_id'>
      <input type='hidden' name='razorpay_signature'  id='razorpay_signature' >
    </form>
    <div id='msg-razorpay-success' class='rzp-payment-success-msg' display='none'>
      <div class='rzp-make-center'>
        <div class='rzp-loading-animation'>
          <?xml version='1.0' encoding='UTF-8' standalone='no'?><svg xmlns:svg='http://www.w3.org/2000/svg' xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' version='1.0' width='64px' height='64px' viewBox='0 0 128 128' xml:space='preserve'><g><path d='M64 0L40.08 21.9a10.98 10.98 0 0 0-5.05 8.75C34.37 44.85 64 60.63 64 60.63V0z' fill='#ffb118'/><path d='M128 64l-21.88-23.9a10.97 10.97 0 0 0-8.75-5.05C83.17 34.4 67.4 64 67.4 64H128z' fill='#80c141'/><path d='M63.7 69.73a110.97 110.97 0 0 1-5.04-20.54c-1.16-8.7.68-14.17.68-14.17h38.03s-4.3-.86-14.47 10.1c-3.06 3.3-19.2 24.58-19.2 24.58z' fill='#cadc28'/><path d='M64 128l23.9-21.88a10.97 10.97 0 0 0 5.05-8.75C93.6 83.17 64 67.4 64 67.4V128z' fill='#cf171f'/><path d='M58.27 63.7a110.97 110.97 0 0 1 20.54-5.04c8.7-1.16 14.17.68 14.17.68v38.03s.86-4.3-10.1-14.47c-3.3-3.06-24.58-19.2-24.58-19.2z' fill='#ec1b21'/><path d='M0 64l21.88 23.9a10.97 10.97 0 0 0 8.75 5.05C44.83 93.6 60.6 64 60.6 64H0z' fill='#018ed5'/><path d='M64.3 58.27a110.97 110.97 0 0 1 5.04 20.54c1.16 8.7-.68 14.17-.68 14.17H30.63s4.3.86 14.47-10.1c3.06-3.3 19.2-24.58 19.2-24.58z' fill='#00bbf2'/><path d='M69.73 64.34a111.02 111.02 0 0 1-20.55 5.05c-8.7 1.14-14.15-.7-14.15-.7V30.65s-.86 4.3 10.1 14.5c3.3 3.05 24.6 19.2 24.6 19.2z' fill='#f8f400'/><circle cx='64' cy='64' r='2.03'/><animateTransform attributeName='transform' type='rotate' from='0 64 64' to='-360 64 64' dur='2700ms' repeatCount='indefinite'></animateTransform></g></svg>
        </div>
        <h2 class='rzp-message'>Please wait while we are processing your payment.</h2>
      </div>
    </div>
    <p style='display:none'>
      <button id='btn-razorpay'>Pay With Razorpay</button>
      <button id='btn-razorpay-cancel' onclick='document.razorpayform.submit()'>Cancel</button>
    </p>";
    return $html;
  }

  public function callback() {
    $razorpayOrderId = $_COOKIE[self::RAZORPAY_ORDER_ID];

    $key = $this->get_plugin_setting(self::GF_RAZORPAY_KEY);

    $secret = $this->get_plugin_setting(self::GF_RAZORPAY_SECRET);

    $api = new Api($key, $secret);

    try {
      $order = $api->order->fetch($razorpayOrderId);
    } catch (\Exception $e) {
      $action = array(
        'type'  => 'fail_payment',
        'error' => $e->getMessage()
      );

      return $action;
    }

    $entryId = $order['receipt'];

    $entry = GFAPI::get_entry($entryId);

    $attributes = $this->get_callback_attributes();

    $action = array(
      'id'             => $attributes[self::RAZORPAY_PAYMENT_ID],
      'type'           => 'fail_payment',
      'transaction_id' => $attributes[self::RAZORPAY_PAYMENT_ID],
      'amount'         => $entry['payment_amount'],
      'entry_id'       => $entry['id'],
      'error'          => 'Payment Failed',
    );

    $success = false;

    if ((empty($entry) === false) and (empty($attributes[self::RAZORPAY_PAYMENT_ID]) === false) and (empty($attributes[self::RAZORPAY_SIGNATURE]) === false)
    ) {
      try {
        $api->utility->verifyPaymentSignature($attributes);

        $success = true;
      } catch (SignatureVerificationError $e) {
        $action['error'] = $e->getMessage();

        return $action;
      }
    }

    if ($success === true) {
      $action['type'] = 'complete_payment';

      $action['error'] = null;
    }

    return $action;
  }

  public function get_callback_attributes() {
    return array(
      self::RAZORPAY_ORDER_ID   => $_COOKIE[self::RAZORPAY_ORDER_ID],
      self::RAZORPAY_PAYMENT_ID => sanitize_text_field(rgpost(self::RAZORPAY_PAYMENT_ID)),
      self::RAZORPAY_SIGNATURE  => sanitize_text_field(rgpost(self::RAZORPAY_SIGNATURE)),
    );
  }

  public function post_callback($callback_action, $callback_result) {
    if ( is_wp_error( $callback_action ) || ! $callback_action ) {
			return false;
    }
    
    // Run the necessary Hooks
    $entry          = GFAPI::get_entry( $callback_action['entry_id'] );
		$feed           = $this->get_payment_feed( $entry );
		$transaction_id = rgar( $callback_action, 'transaction_id' );
		$amount         = rgar( $callback_action, 'amount' );
		$id             = rgar( $callback_action, 'id' );
		$status         = rgar( $callback_action, 'type' );
    
    if ( rgar( $callback_action, 'type' ) === 'complete_payment' ) {
      do_action('gform_razorpay_complete_payment', $callback_action['transaction_id'], $callback_action['amount'], $entry, $feed);
      if ( has_filter( 'gform_razorpay_complete_payment' ) ) {
        $this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_razorpay_complete_payment.' );
      }
      ?>
      <div style="text-align:center;">
        <h3>🎉 Payment Successful! Your order has been successfully placed. 🎉</h3>
        <p style="font-size:17px;">➡ Go back to the <strong><a href="<?php echo home_url(); ?>">Home Page</a></strong> and check your email inbox. 📧
          <br>🕵 In case you don't see any email in your inbox, do check your <strong>Spam</strong> folder. 🤖
        </p>
        <br>
        <table style="border:1px solid black; border-collapse: collapse; width: 400px; margin-right:auto; margin-left:auto;">
        <tr>
            <td style="border:1px solid black; height:30px; width:50%; text-align:center;" colspan="2"><strong>Please Note</strong></td>
          </tr>
          <tr>
            <td style="border:1px solid black; height:30px; width:50%; text-align:center;"><strong>Transaction ID</strong></td>
            <td style="border:1px solid black; height:30px; width:50%; text-align:center;"><strong><?php echo rgar( $callback_action, 'transaction_id' ) ?></strong></td>
          </tr>
        </table>
        <small><strong>Pass this transaction id over the email if you need any payment related support.</strong></small>
        <p><strong>Note:</strong> This page will automatically redirected to the <strong>home page</strong> in <span id="rzp_refresh_timer"></span> seconds.</p>
      </div>
      <script type="text/javascript">var rzp_refresh_time=15,rzp_actual_refresh_time=rzp_refresh_time+1;setTimeout(function(){window.location.href="<?php echo home_url(); ?>"},1e3*rzp_refresh_time),setInterval(function(){rzp_actual_refresh_time>0?(rzp_actual_refresh_time--,document.getElementById("rzp_refresh_timer").innerText=rzp_actual_refresh_time):clearInterval(rzp_actual_refresh_time)},1e3);</script>
      <?php
			$this->fulfill_order( $entry, $transaction_id, $amount, $feed );
		} else {
      GFAPI::update_entry_property( $entry['id'], 'payment_status', 'Failed' );
      do_action('gform_razorpay_fail_payment', $entry, $feed);
      if ( has_filter( 'gform_razorpay_fail_payment' ) ) {
        $this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_razorpay_fail_payment.' );
      }
    }
  }

  public function fulfill_order( &$entry, $transaction_id, $amount, $feed = null ) {

		if ( ! $feed ) {
			$feed = $this->get_payment_feed( $entry );
		}

		$form = GFFormsModel::get_form_meta( $entry['form_id'] );
		if ( rgars( $feed, 'meta/delayPost' ) ) {
			$this->log_debug( __METHOD__ . '(): Creating post.' );
			$entry['post_id'] = GFFormsModel::create_post( $form, $entry );
			$this->log_debug( __METHOD__ . '(): Post created.' );
		}

		if ( rgars( $feed, 'meta/delayNotification' ) ) {
			//sending delayed notifications
			$notifications = $this->get_notifications_to_send( $form, $feed );
			GFCommon::send_notifications( $notifications, $form, $entry, true, 'form_submission' );
		}

		do_action( 'gform_razorpay_fulfillment', $entry, $feed, $transaction_id, $amount );
		if ( has_filter( 'gform_razorpay_fulfillment' ) ) {
			$this->log_debug( __METHOD__ . '(): Executing functions hooked to gform_razorpay_fulfillment.' );
		}

	}

  public function is_callback_valid() {
    // Will check if the return url is valid
    if (rgget('page') !== $this->_slug) {
      return false;
    }

    return true;
  }

  public function billing_info_fields() {
    $fields = array(
      array('name' => self::CUSTOMER_FIELDS_NAME, 'label' => esc_html__('Name', 'gravityforms'), 'required' => false),
      array('name' => self::CUSTOMER_FIELDS_EMAIL, 'label' => esc_html__('Email', 'gravityforms'), 'required' => false),
      array('name' => self::CUSTOMER_FIELDS_CONTACT, 'label' => esc_html__('Phone', 'gravityforms'), 'required' => false),
    );

    return $fields;
  }

  public function init() {

		add_filter( 'gform_notification_events', array( $this, 'notification_events' ), 10, 2 );
		add_filter( 'gform_post_payment_action', array( $this, 'post_payment_action' ), 10, 2 );

		// Supports frontend feeds.
		$this->_supports_frontend_feeds = true;

		parent::init();

	}

  // Custom Events
  public function notification_events($notification_events, $form) {
    $has_razorpay_feed = function_exists( 'gf_razorpay' ) ? gf_razorpay()->get_feeds( $form['id'] ) : false;

    if ($has_razorpay_feed) {
      $payment_events = array(
        'complete_payment'          => __('Payment Completed', 'gravityforms'),
        'refund_payment'            => __('Payment Refunded', 'gravityforms'),
        'fail_payment'              => __('Payment Failed', 'gravityforms'),
        'add_pending_payment'       => __('Payment Pending', 'gravityforms'),
        'void_authorization'        => __('Authorization Voided', 'gravityforms'),
        'create_subscription'       => __('Subscription Created', 'gravityforms'),
        'cancel_subscription'       => __('Subscription Canceled', 'gravityforms'),
        'expire_subscription'       => __('Subscription Expired', 'gravityforms'),
        'add_subscription_payment'  => __('Subscription Payment Added', 'gravityforms'),
        'fail_subscription_payment' => __('Subscription Payment Failed', 'gravityforms'),
      );

      return array_merge($notification_events, $payment_events);
    }

    return $notification_events;

  }

  public function post_payment_action($entry, $action) {
    $form = GFAPI::get_form( $entry['form_id'] );
    GFAPI::send_notifications( $form, $entry, rgar( $action, 'type' ) );
  }
}

?>