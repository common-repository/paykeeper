<?php

//Common PayKeeper payment system php class for using in PayKeeper payment modules for different CMS
class PaykeeperPayment {

    public $fiscal_cart = array(); //fz54 cart
    public $order_total = 0; //order total sum
    public $shipping_price = 0; //shipping price
    public $use_taxes = false;
    public $use_delivery = false;
    public $delivery_index = -1;
    public $single_item_index = -1;
    public $more_then_one_item_index = -1;
    public $order_params = NULL;
    public $discounts = array();
    public $back_to_order_view = false;
	
    public function setOrderParams($order_total = 0, $clientid="", $orderid="", $client_email="", 
                                    $client_phone="", $service_name="", $form_url="", $secret_key="",$user_result_callback=null)
    {
       $this->setOrderTotal($order_total);
       $this->order_params = array(
           "sum" => $order_total,
           "clientid" => $clientid,
           "orderid" => $orderid,
           "client_email" => $client_email,
           "client_phone" => $client_phone,
           "phone" => $client_phone,
           "service_name" => $service_name,
           "form_url" => $form_url,
           "secret_key" => $secret_key,
           "user_result_callback" => $user_result_callback
       );
    }

    public function getOrderParams($value)
    {
        return array_key_exists($value, $this->order_params) ? $this->order_params["$value"] : False;
    }

    public function updateFiscalCart($ftype, $name="", $price=0, $quantity=0, $sum=0, $tax="none",$item_type="goods")
    {
        //update fz54 cart
        if ($ftype === "create") {
            $name = str_replace("\n ", "", $name);
            $name = str_replace("\r ", "", $name);
        }
        $price_to_add = number_format($price, 2, ".", "");
        $sum_to_add = number_format($price_to_add*$quantity, 2, ".", "");
        $this->fiscal_cart[] = array(
            "name" => $name,
            "price" => $price_to_add,
            "quantity" => $quantity,
            "sum" => $sum_to_add,
            "tax" => $tax,
            "item_type" => $item_type
        );
    }

    public function getFiscalCart()
    {
        return $this->fiscal_cart;
    }

    public function setDiscounts($discount_enabled_flag)
    {
        $discount_modifier_value = 1;
        $shipping_included = false;
        //set discounts
        if ($discount_enabled_flag) {
            if ($this->getFiscalCartSum(false) > 0) {
                if ($this->getOrderTotal() >= $this->getShippingPrice()) {
                    if ($this->getFiscalCartSum(false) > 0) { //divide by zero error
                        $discount_modifier_value = ($this->getOrderTotal() - $this->getShippingPrice())/$this->getFiscalCartSum(false);
                    }
                }
                else {
                    if ($this->getFiscalCartSum(true) > 0) { //divide by zero error
                        $discount_modifier_value = $this->getOrderTotal()/$this->getFiscalCartSum(true);
                        $shipping_included = true;
                    }
                }

                if ($discount_modifier_value < 1) {
                    for ($pos=0; $pos<count($this->getFiscalCart()); $pos++) {//iterate fiscal cart with or without shipping
                        if (!$shipping_included && $pos == $this->delivery_index) {
                            continue;
                        }
                        if ($this->fiscal_cart[$pos]["quantity"] > 0) { //divide by zero error
                            $price = $this->fiscal_cart[$pos]["price"]*$discount_modifier_value;
                            $this->fiscal_cart[$pos]["price"] = number_format($price, 2, ".", "");
                            $sum = $this->fiscal_cart[$pos]["price"]*$this->fiscal_cart[$pos]["quantity"];
                            $this->fiscal_cart[$pos]["sum"] = number_format($sum, 2, ".", "");
                        }
                    }
                }
            }
        }
    }

    public function correctPrecision()
    {
        //handle possible precision problem
        $fiscal_cart_sum = $this->getFiscalCartSum(true);
        $total_sum = $this->getOrderTotal();
        $diff_value = $total_sum - $fiscal_cart_sum;
        //debug_info
        //echo "\ntotal: $total_sum - cart: $fiscal_cart_sum - diff: $diff_sum";
        if (abs($diff_value) >= 0.005) {
            $diff_sum = number_format($diff_value, 2, ".", "");
            if ($this->getUseDelivery()) { //delivery is used
                $this->correctPriceOfCartItem($diff_sum, count($this->fiscal_cart)-1);
            }
            else {
                if ($this->single_item_index >= 0) { //we got single cart element
                    $this->correctPriceOfCartItem($diff_sum, $this->single_item_index);
                }
                else if ($this->more_then_one_item_index >= 0) { //we got cart element with more then one quantity
                    $this->splitCartItem($this->more_then_one_item_index);
                    //add diff_sum to the last element (just separated) of fiscal cart
                    $this->correctPriceOfCartItem($diff_sum, count($this->fiscal_cart)-1);
                }
                else { //we only got cart elements with less than one quantity
                    $modify_value = ($diff_sum > 0) ? $total_sum/$fiscal_cart_sum : $fiscal_cart_sum/$total_sum;
                    if ($diff_sum > 0) {
                        if ($fiscal_cart_sum > 0) { //divide by zero error
                            $modify_value = $total_sum/$fiscal_cart_sum;
                        }
                    }
                    else {
                        if ($total_sum > 0) { //divide by zero error
                            $modify_value = $fiscal_cart_sum/$total_sum;
                        }
                    }
                    for ($pos=0; $pos<count($this->getFiscalCart()); $pos++) {
                        if ($this->fiscal_cart[$pos]["quantity"] > 0) { //divide by zero error
                            $sum = $this->fiscal_cart[$pos]["sum"]*$modify_value;
                            $this->fiscal_cart[$pos]["sum"] *= number_format($sum, 2, ".", "");
                            $price = $this->fiscal_cart[$pos]["sum"]/$this->fiscal_cart[$pos]["quantity"];
                            $this->fiscal_cart[$pos]["price"] = number_format($price, 2, ".", "");
                        }
                    }
                }
            }
        }
    }

    public function setOrderTotal($value)
    {
        $this->order_total = $value;
    }

    public function getOrderTotal()
    {
        return $this->order_total;
    }

    public function setShippingPrice($value)
    {
        $this->shipping_price = $value;
    }

    public function getShippingPrice()
    {
        return $this->shipping_price;
    }

    public function getPaymentFormType()
    {
        if (strpos($this->order_params["form_url"], "/order/inline") == True)
            return "order";
        else
            return "create";
    }

    public function setUseTaxes()
    {
        $this->use_taxes = True;
    }

    public function getUseTaxes()
    {
        return $this->use_taxes;
    }

    public function setUseDelivery()
    {
        $this->use_delivery = True;
    }

    public function getUseDelivery()
    {
        return $this->use_delivery;
    }

    //$zero_value_as_none: if variable is set, then when tax_rate is zero, tax is equal to none
    public function setTaxes($tax_rate, $zero_value_as_none = true)
    {
        $taxes = array("tax" => "none", "tax_sum" => 0);
        switch(number_format(floatval($tax_rate), 0, ".", "")) {
            case 0:
                if (!$zero_value_as_none) {
                    $taxes["tax"] = "vat0";
                }
                break;
            case 10:
                $taxes["tax"] = "vat10";
                break;
            case 18:
                $taxes["tax"] = "vat18";
                break;
            case 20:
                $taxes["tax"] = "vat20";
                break;
        }
        return $taxes;
    }

    public function checkDeliveryIncluded($delivery_price, $delivery_name) {
        $index = 0;
        foreach ($this->getFiscalCart() as $item) {
            if ($item["name"] == $delivery_name
                && $item["price"] == $delivery_price
                && $item["quantity"] == 1) {
                $this->delivery_index = $index;
                return true;
            }
            $index++;
        }
        return false;
    }

    public function getFiscalCartSum($delivery_included) {
        $fiscal_cart_sum = 0;
        $index = 0;
        foreach ($this->getFiscalCart() as $item) {
            if (!$delivery_included && $index == $this->delivery_index)
                continue;
                $fiscal_cart_sum += $item["price"]*$item["quantity"];
            $index++;
        }
        return number_format($fiscal_cart_sum, 2, ".", "");
    }

    public function showDebugInfo($obj_to_debug)
    {
        echo "<pre>";
        var_dump($obj_to_debug);
        echo "</pre>";
    }

    public function correctPriceOfCartItem($corr_price_to_add, $item_position)
    { //$corr_price_to_add is always with 2 gigits after dot
        $item_sum = 0;
        $this->fiscal_cart[$item_position]["price"] += $corr_price_to_add;
        $item_sum = $this->fiscal_cart[$item_position]["price"]*$this->fiscal_cart[$item_position]["quantity"];
        $this->fiscal_cart[$item_position]["sum"] = number_format($item_sum, 2, ".", "");
    }

    public function splitCartItem($cart_item_position)
    {
        $item_sum = 0;
        $item_price = 0;
        $item_quantity = 0;
        $item_price = $this->fiscal_cart[$cart_item_position]["price"];
        $item_quantity = $this->fiscal_cart[$cart_item_position]["quantity"]-1;
        $this->fiscal_cart[$cart_item_position]["quantity"] = $item_quantity; //decreese quantity by one
        $this->fiscal_cart[$cart_item_position]["sum"] = $item_price*$item_quantity; //new sum
        //add one cart item to the end of cart
        $this->updateFiscalCart(
            $this->getPaymentFormType(),
            $this->fiscal_cart[$cart_item_position]["name"],
            $item_price, 1, $item_price,
            $this->fiscal_cart[$cart_item_position]["tax"]);
    }

    public function getFiscalCartEncoded() {
        return json_encode($this->getFiscalCart());
    }

    public function getDefaultPaymentForm($payment_form_sign) {
        $form = "";
        if ($this->getPaymentFormType() == "create") { //create form
            $form = '
                <h3>Сейчас Вы будете перенаправлены на страницу банка.</h3> 
                <form name="pay_form" id="pay_form" action="'.$this->getOrderParams("form_url").'" accept-charset="utf-8" method="post">
                <input type="hidden" name="sum" value = "'.$this->getOrderTotal().'"/>
                <input type="hidden" name="orderid" value = "'.$this->getOrderParams("orderid").'"/>
                <input type="hidden" name="clientid" value = "'.$this->getOrderParams("clientid").'"/>
                <input type="hidden" name="client_email" value = "'.$this->getOrderParams("client_email").'"/>
                <input type="hidden" name="client_phone" value = "'.$this->getOrderParams("client_phone").'"/>
                <input type="hidden" name="service_name" value = "'.$this->getOrderParams("service_name").'"/>
                <input type="hidden" name="user_result_callback" value = "'.$this->getOrderParams("user_result_callback").'"/>
                <input type="hidden" name="cart" value = \''.htmlentities($this->getFiscalCartEncoded(),ENT_QUOTES).'\' />
                <input type="hidden" name="sign" value = "'.$payment_form_sign.'"/>
                </form>
                <script>
                    document.forms["pay_form"].submit();
                </script>';
        }
        else { //order form
            $payment_parameters = array(
                "clientid"=>$this->getOrderParams("clientid"), 
                "orderid"=>$this->getOrderParams('orderid'), 
                "sum"=>$this->getOrderTotal(), 
                "client_phone"=>$this->getOrderParams("phone"), 
                "phone"=>$this->getOrderParams("phone"), 
                "client_email"=>$this->getOrderParams("client_email"), 
                "cart"=>$this->getFiscalCartEncoded());
            $query = http_build_query($payment_parameters);
            $err_num = $err_text = NULL;
            if( function_exists( "curl_init" )) { //using curl
                $CR = curl_init();
                curl_setopt($CR, CURLOPT_URL, $this->getOrderParams("form_url"));
                curl_setopt($CR, CURLOPT_POST, 1);
                curl_setopt($CR, CURLOPT_FAILONERROR, true); 
                curl_setopt($CR, CURLOPT_POSTFIELDS, $query);
                curl_setopt($CR, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($CR, CURLOPT_SSL_VERIFYPEER, 0);
                $result = curl_exec( $CR );
                $error = curl_error( $CR );
                if( !empty( $error )) {
                    $form = "<br/><span class=message>"."INTERNAL ERROR:".$error."</span>";
                    return false;
                }
                else {
                    $form = $result;
                }
                curl_close($CR);
            }
            else { //using file_get_contents
                if (!ini_get('allow_url_fopen')) {
                    $form_html = "<br/><span class=message>"."INTERNAL ERROR: Option allow_url_fopen is not set in php.ini"."</span>";
                }
                else {
                    $query_options = array("https"=>array(
                    "method"=>"POST",
                    "header"=>
                    "Content-type: application/x-www-form-urlencoded",
                    "content"=>$payment_parameters
                    ));
                    $context = stream_context_create($query_options);
                    $form = file_get_contents($this->getOrderParams("form_url"), false, $context);
                }
            }
        }
        if ($form  == "") {
            $form = '<h3>Произошла ошибка при инциализации платежа</h3><p>$err_num: '.htmlspecialchars($err_text).'</p>';
        }

		return $form;
	}
}

