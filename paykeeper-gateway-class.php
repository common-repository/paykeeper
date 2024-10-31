<?php

if(!class_exists('WC_Payment_Gateway')) return;

require_once('paykeeper_common_class/paykeeper.class.php');

class WC_PK_Gateway extends WC_Payment_Gateway {

    protected $order = null;
    protected $transactionId = null;
    protected $transactionErrorMessage = null;
    protected $server = '';
    protected $secret = '';
    protected $force_discounts_check = false;
    protected $back_to_order_view;

	/* инициализация плагина */
    public function __construct() {

		$this->id = 'paykeeper';
        $this->has_fields = false;
		load_plugin_textdomain('paykeeper', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        $this->init_form_fields();
        $this->init_settings();
		$this->title = $this->get_option( 'title' );
		$this->icon = $this->get_option( 'icon' );
        $this->description = $this->get_option('description' );
        $this->method_title       = $this->get_option( 'title' );
        $this->method_description = $this->get_option('description' );
        $this->server = $this->get_option( 'paykeeperserver' );
        $this->secret = $this->get_option( 'paykeepersecret' );
        $this->force_discounts_check = $this->get_option( 'force_discounts_check' );
        $this->back_to_order_view = $this->get_option( 'back_to_order_view' );
        add_action('admin_notices', array(&$this, 'handle_admin_notice_msg'));
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
       	add_action('woocommerce_receipt_' . $this->id, array( $this, 'receipt_page' ) );
        add_action('woocommerce_api_wc_pk_gateway', array( $this, 'paykeeper_handler' ) );
    }

	function handle_admin_notice_msg() {}

	/* Выводим настройки в административной панели*/
    public function admin_options() {
        ?>
        <h3><?php _e('PayKeeper', 'paykeeper'); ?></h3>
        <p><?php _e('Allows Payments via the PayKeeper gateway', 'paykeeper'); ?></p>

        <table class="form-table">
            <?php
            $this->generate_settings_html();
			?>
        </table>
        <?php
    }

	/* набор настроек */
    public function init_form_fields() {

		$this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable PayKeeper Gateway', 'paykeeper'),
                'default' => 'yes'
            ),
            'paykeeperserver' => array(
                'title' => __('PayKeeper server URL', 'paykeeper'),
                'type' => 'text',
                'description' => __('Your PayKeeper server URL', 'paykeeper'),
                'default' => __('', 'paykeeper')
            ),
            'paykeepersecret' => array(
                'title' => __('PayKeeper secret word', 'paykeeper'),
                'type' => 'text',
                'description' => __('Your PayKeeper secret word', 'paykeeper'),
                'default' => __('', 'paykeeper')
            ),
            'force_discounts_check' => array(
                'title' => __('Force discounts check', 'paykeeper'),
                'type' => 'checkbox',
                'label' => __('If option is enabled, discounts will be checked anyway. Please, report about this option to support@paykeeper.ru', 'paykeeper'),
                'default' => 'no'
            ),
            'autocomplete_order' => array(
                'title' => __('Autocomplete order', 'paykeeper'),
                'type' => 'checkbox',
                'label' => __('If this option is enabled, then upon successful payment, the order will be assigned the status completed', 'paykeeper'),
                'default' => 'no'
            ),
            'back_to_order_view' => array(
                'title' => __('Back to order view', 'paykeeper'),
                'type' => 'checkbox',
                'label' => __('If this option is enabled, after payment, customer will be sent to specific page view-order', 'paykeeper'),
                'default' => 'no'
            ),
			'title' => array(
                'title' => __('Title', 'paykeeper'),
                'type' => 'safe_text',
                'description' => __('Name of the payment system when paying', 'paykeeper'),
                'default' => __('Payment by Visa/MasterCard online', 'paykeeper'),
                'desc_tip'    => true,
            ),
			'description' => array(
                'title' => __('Description', 'paykeeper'),
                'type' => 'textarea',
                'description' => __('Description of payment system', 'paykeeper'),
                'default' => __('Payments via the PayKeeper gateway', 'paykeeper'),
                'desc_tip'    => true,
            ),
			'icon' => array(
                'title' => __('Icon', 'paykeeper'),
                'type' => 'text',
                'description' => __('The icon for this checkout option', 'paykeeper'),
                'default' => __('/wp-content/plugins/woocommerce-paykeeper/visa_mastercard_wordpress.png', 'paykeeper')
            ),
			'notify_link' => array(
                'title' => __('Notify link', 'paykeeper'),
                'type' => 'text',
                'custom_attributes' => array('readonly' => 'readonly'),
                'description' => __('Copy and paste this link to your config at PayKeeper account as notify link for server-server notification', 'paykeeper'),
                'default' => __(home_url().'/?wc-api=wc_pk_gateway', 'paykeeper')
            ),
			'success_link' => array(
                'title' => __('Success link', 'paykeeper'),
                'type' => 'text',
                'custom_attributes' => array('readonly' => 'readonly'),
                'description' => __('Copy and paste this link to your config at PayKeeper account as success payment redirect', 'paykeeper'),
                'default' => __(home_url().'/?wc-api=wc_pk_gateway', 'paykeeper')
            ),
			'failed_link' => array(
                'title' => __('Failed link', 'paykeeper'),
                'type' => 'text',
                'custom_attributes' => array('readonly' => 'readonly'),
                'description' => __('Copy and paste this link to your config at PayKeeper account as failed payment redirect', 'paykeeper'),
                'default' => __(home_url().'/?wc-api=wc_pk_gateway&failed', 'paykeeper')
            ),
        );
    }

	/* вывод описания платежной системы в списке доступных платежей на странице оформления заказа */
	function payment_fields(){
        if($this -> description) echo wpautop(wptexturize($this -> description));
    }

	/* обработка заказа, очистка корзины, переход на страницу оплаты */
    public function process_payment($order_id) {

		$order = new WC_Order($order_id);

        // очистка корзины, расскомментировать по требованию
        // $wc_cart = new WC_Cart();
        // $wc_cart->empty_cart( $clear_persistent_cart = true );

		return array(
			'result' => 'success',
			'redirect' => add_query_arg('order', $order->get_id(), add_query_arg('key', $order->get_order_key(), $order->get_checkout_payment_url(true)))
		);
    }

	/* обработка серверного-ответа на стороне сайта о подтверждении платежа */
	public function paykeeper_handler() {

		if ( ! empty( $_POST ) ) {

			/* у нас магазин и мы не можем обрабатывать запросы без номера заказа, хотя orderid и не обязателен */
			$order_id = $_POST['orderid'];
			if ($order_id == ""){
				echo "Error! Order id is missing";
				exit;
			}

			$id = $_POST['id'];
			$key = $_POST['key'];
			$clientid = $_POST['clientid'];
			$sum = $_POST['sum'];
			/* проверка цифровой подписи */
			if ($key != md5 ($id . $sum.$clientid.$order_id.$this->secret)){
				echo "Error! Hash mismatch";
				exit;
			}
			$order = new WC_Order($order_id);

            if ($order->get_total() != $sum) {
                echo "Error! Incorrect order sum!";
            }

			/* если заказ уже оплачен, то уведомляем, что все хорошо */
			if ( $order->has_status( 'completed' ) ) {
                //$this->clearCart();
                //$order->update_status( 'completed' );
                $order->reduce_order_stock();
				echo "OK ".md5($id.$this->secret);
				exit;
			}

			/* платеж успешно совершен */
			$order->payment_complete();
			$order->add_order_note("PayKeeper payment completed");
			unset($_SESSION['order_awaiting_payment']);
            // file_put_contents(__DIR__.'/debug.log',print_r($order->get_status(),true),FILE_APPEND);
            //$this->clearCart();
            $order->reduce_order_stock();
			if ($this->get_option( 'autocomplete_order' )=='yes')
            {
                $order->update_status( 'completed' );
            }
			echo "OK ".md5($id.$this->secret);
			exit;
		}elseif(isset($_REQUEST['failed'])) self::paykeeper_failed();
		else self::paykeeper_success();
	}

	/* что делать при ошибке платежа */
	public function paykeeper_failed(){
		wp_redirect( get_permalink( wc_get_page_id( 'cart' ) ) );
	}

	/* при успешном платеже отправляем пользователя на страницу его аккаунта, последний оплаченный заказ будет самым первым в списке */
	public function paykeeper_success(){
		//wp_redirect( get_permalink( wc_get_page_id( 'myaccount' ) ) );
        wp_redirect($this->get_return_url());
	}

	/* вывод формы на странице оплаты */
 	function receipt_page($order_id) {

		$order = new WC_Order( $order_id );
        // $public_view_order_url = esc_url( $order->get_view_order_url() );
        // echo $public_view_order_url;
        // die;
        $pk_obj = new PaykeeperPayment();
        $back_path = ($this->back_to_order_view=="yes")?esc_url( $order->get_view_order_url()):null;
        //set order parameters
        $pk_obj->setOrderParams($order->get_total(),              //sum
                              $order->get_billing_first_name()
                              . " " .
                              $order->get_billing_last_name(),  //clientid
                              $order->get_id(),                 //orderid
                              $order->get_billing_email(),      //client_email
                              $order->get_billing_phone(),      //client_phone
                              "",                               //service_name
                              $this->server,                    //payment form url
                              $this->secret,                    //secret key
                              $back_path
        );
        //GENERATE FZ54 CART
        $item_index = 0;
        foreach ($order->get_items() as $order_item) {
            $taxes = array("tax" => "none", "tax_sum" => 0);
            if ((float) $order_item->get_data()["total"] <= 0)
                continue;
            $name = $order_item->get_data()["name"];
            $qty = floatval($order_item->get_data()["quantity"]);
            if ($qty == 1 && $pk_obj->single_item_index < 0)
                $pk_obj->single_item_index = $item_index;
            if ($qty > 1 && $pk_obj->more_then_one_item_index < 0)
                $pk_obj->more_then_one_item_index = $item_index;
            $sum = floatval($order_item->get_data()["total_tax"])+floatval($order_item->get_data()["total"]);
            $price = $sum/$qty;
            $tax_rate = $order_item->get_data()["total_tax"]/($order_item->get_data()["total"]/100);
            $taxes = (strlen($order_item->get_data()["tax_class"])>0)?$pk_obj->setTaxes($tax_rate,false):$pk_obj->setTaxes($tax_rate);
            $pk_obj->updateFiscalCart($pk_obj->getPaymentFormType(), $name, $price, $qty, $sum, $taxes["tax"]);
            $item_index++;
        }

        //add shipping parameters to cart
        $shipping_tax_rate = 0;
        $shipping_taxes = array("tax" => "none", "tax_sum" => 0);
        $pk_obj->setShippingPrice(floatval($order->get_shipping_total())+floatval($order->get_shipping_tax()));
        $shipping_name = $order->get_shipping_method();
        //check if delivery already in cart
        if (!$pk_obj->checkDeliveryIncluded($pk_obj->getShippingPrice(), $shipping_name)
            && $pk_obj->getShippingPrice() > 0) {
            $shipping_tax_rate = floatval($order->get_shipping_tax())/(floatval($order->get_shipping_total())/100);

            $shipping_taxes = $pk_obj->setTaxes($shipping_tax_rate);
            $taxes = (strlen($order_item->get_data()["tax_class"])>0)?$pk_obj->setTaxes($tax_rate,false):$pk_obj->setTaxes($tax_rate);
            $pk_obj->setUseDelivery(); //for precision correct check
            $pk_obj->updateFiscalCart($pk_obj->getPaymentFormType(),
                                $shipping_name,
                                $pk_obj->getShippingPrice(), 1,
                                $pk_obj->getShippingPrice(),
                                $shipping_taxes["tax"],
                                "service");
            $pk_obj->delivery_index = count($pk_obj->getFiscalCart())-1;
        }

        //set discounts
        $pk_obj->setDiscounts((floatval($order->get_discount_total()) > 0 || $this->force_discounts_check == 'yes'));

        //handle possible precision problem
        $pk_obj->correctPrecision();

        //generate payment form
        $to_hash = number_format($pk_obj->getOrderTotal(), 2, ".", "") .
                   $pk_obj->getOrderParams("clientid")     .
                   $pk_obj->getOrderParams("orderid")      .
                   $pk_obj->getOrderParams("service_name") .
                   $pk_obj->getOrderParams("client_email") .
                   $pk_obj->getOrderParams("client_phone") .
                   $pk_obj->getOrderParams("secret_key");
        $sign = hash ('sha256' , $to_hash);
        // echo "<pre>";
        // print_r($pk_obj);
        // die;
        echo $pk_obj->getDefaultPaymentForm($sign);
    }
}

