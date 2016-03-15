<?php
function dd($v) {
    echo "<pre>";
    print_r($v);
    echo "</pre>";
}

require_once('lib/easypost.php');

add_action('woocommerce_product_options_shipping', 'add_pricing_input');
add_action('save_post', 'set_customs_value');
add_action( 'add_meta_boxes', 'es_add_boxes');
add_filter('woocommerce_shipping_methods', 'add_easypost_method');


function es_add_boxes() {
    add_meta_box('easypost_data', __('EastPost Shipping Labels', 'woocommerce' ), 'woocommerce_easypost_meta_box', 'shop_order', 'normal', 'low');
}
function woocommerce_easypost_meta_box($post) {
    $data = get_post_meta( $post->ID, 'easypost_shipping_label', true);
    echo preg_replace('/(https?:\/\/.*\.(?:png|jpg))/', '<a href="$1" target="_blank" style="display:block;" download>Download</a><img style="max-width: 150px;" src="$1" /><br /><hr />', $data);
}

function add_pricing_input($content) {
    global $post;
    woocommerce_wp_text_input(array(
        'id'    => '_customs_value', 
        'class' => 'short', 
        'name'  => 'wc_customs_value', 
        'type'  => 'number',
        'label' => __( 'Customs Value', 'woocommerce' ), 
    ));
}

function set_customs_value($post) {
    if ($_POST['wc_customs_value']) {
        add_post_meta($_POST['post_ID'], '_customs_value', $_POST['wc_customs_value']);
    }
}

function add_easypost_method( $methods ) {
    $methods[] = 'ES_WC_EasyPost'; 
    return $methods;
}

class ES_WC_EasyPost extends WC_Shipping_Method {
    function __construct() {
        $this->id = 'easypost';
        $this->has_fields = true;

        $this->init_form_fields();   
        $this->init_settings();   

        $this->title         = __('Easy Post Integration', 'woocommerce');
        $this->usesandboxapi = strcmp($this->settings['test'], 'yes') == 0;
        $this->testApiKey    = $this->settings['test_api_key'  ];
        $this->liveApiKey    = $this->settings['live_api_key'  ];
        $this->handling      = $this->settings['handling'] ? $this->settings['handling'] : 0;
        $this->filters       = explode(",", $this->settings['filter_rates']);
        $this->secret_key    = $this->usesandboxapi ? $this->testApiKey : $this->liveApiKey;

        \EasyPost\EasyPost::setApiKey($this->secret_key);

        $this->enabled = $this->settings['enabled'];

        add_action('woocommerce_update_options_shipping_' . $this->id , array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __( 'Enable/Disable', 'woocommerce' ),
                'type' => 'checkbox',
                'label' => __( 'Enabled', 'woocommerce' ),
                'default' => 'yes'
            ),
            'filter_rates' => array(
                'title' => __( 'Filter these rates', 'woocommerce' ),
                'type' => 'text',
                'label' => __( 'Fitler (Comma Seperated)', 'woocommerce' ),
                'default' => ('LibraryMail,MediaMail'),
            ),
            'test' => array(
                'title' => __( 'Test Mode', 'woocommerce' ),
                'type' => 'checkbox',
                'label' => __( 'Enabled', 'woocommerce' ),
                'default' => 'yes'
            ),
            'test_api_key' => array(
                'title' => "Test Api Key",
                'type' => 'text',
                'label' => __( 'Test Api Key', 'woocommerce' ),
                'default' => ''
            ),
            'live_api_key' => array(
                'title' => "Live Api Key",
                'type' => 'text',
                'label' => __( 'Live Api Key', 'woocommerce' ),
                'default' => ''
            ),
            'handling' => array(
                'title' => "Handling Charge",
                'type' => 'text',
                'label' => __( 'Handling Charge', 'woocommerce' ),
                'default' => '0'
            ),
            'company' => array(
                'title' => "Company",
                'type' => 'text',
                'label' => __( 'Company', 'woocommerce' ),
                'default' => ''
            ),
            'street1' => array(
                'title' => 'Address',
                'type' => 'text',
                'label' => __( 'Address', 'woocommerce' ),
                'default' => ''
            ),
            'street2' => array(
                'title' => 'Address2',
                'type' => 'text',
                'label' => __( 'Address2', 'woocommerce' ),
                'default' => ''
            ),
            'city' => array(
                'title' => 'City',
                'type' => 'text',
                'label' => __( 'City', 'woocommerce' ),
                'default' => ''
            ),
            'state' => array(
                'title' => 'State',
                'type' => 'text',
                'label' => __( 'State', 'woocommerce' ),
                'default' => ''
            ),
            'zip' => array(
                'title' => 'Zip',
                'type' => 'text',
                'label' => __( 'ZipCode', 'woocommerce' ),
                'default' => ''
            ),
            'phone' => array(
                'title' => 'Phone',
                'type' => 'text',
                'label' => __( 'Phone', 'woocommerce' ),
                'default' => ''
            )
        );
    }

    function calculate_shipping($packages = array()) {
        global $woocommerce;
        $customer = $woocommerce->customer;

        if (!$this->enabled || !$customer->get_postcode()) {
            return;
        }

        try
        {
            $to_address = \EasyPost\Address::create(
                array(
                    "street1" => $customer->get_address(),
                    "street2" => $customer->get_address_2(),
                    "city"    => $customer->get_city(),
                    "state"   => $customer->get_state(),
                    "zip"     => $customer->get_postcode(),
                )
            );

            $from_address = \EasyPost\Address::create(
                array(
                    "company" => $this->settings['company'],
                    "street1" => $this->settings['street1'],
                    "street2" => $this->settings['street2'],
                    "city"    => $this->settings['city'],
                    "state"   => $this->settings['state'],
                    "zip"     => $this->settings['zip'],
                    "phone"   => $this->settings['phone']
                )
            );

            $rates = array();
            foreach($woocommerce->cart->get_cart() as $package) {
                $item = get_product($package['product_id']);

                list($length, $width, $height) = array_map('trim', explode('x', trim(str_replace('in', '', $item->get_dimensions()))));
                
                $weight = ceil($item->get_weight() * 16);
                $quantity = $package['quantity'];
                
                $parcel = \EasyPost\Parcel::create(
                    array(
                        "length" => $length,
                        "width"  => $width,
                        "height" => $height,
                        "weight" => $weight
                    )
                );
                
                $shipment = \EasyPost\Shipment::create(
                    array(
                        "to_address"   => $to_address,
                        "from_address" => $from_address,
                        "parcel"       => $parcel
                    )
                );

                $shipmentIds = array($item->id . '-' . $shipment->id);
                for($i = 0, $n = $quantity - 1; $i < $n; $i++) {
                    $shipment = \EasyPost\Shipment::create(
                        array(
                            "to_address"   => $to_address,
                            "from_address" => $from_address,
                            "parcel"       => $parcel
                        )
                    );
                    $shipmentIds[] = $item->id . '-' . $shipment->id;
                }

                $shipmentRates = \EasyPost\Rate::create($shipment);
                foreach ($shipmentRates as $shipmentRate) {
                    $rates[] = array(
                        'carrier'      => $shipmentRate->carrier,
                        'service'      => $shipmentRate->service,
                        'rate'         => $shipmentRate->rate,
                        'quantity'     => $quantity,
                        'shipment_ids' => $shipmentIds
                    );
                }
            }

            // remove ones that we wanted to filter out            
            $dontShow = $this->settings['filter_rates'] ? : 'LibraryMail,MediaMail';
            $dontShow = array_map('trim', explode(',', $dontShow));
            $rates = array_filter($rates, function ($rate) use ($dontShow) {
                return !in_array($rate['service'], $dontShow);
            });

            // group by carrier and service
            $rateGroups = array();
            foreach($rates as $rate) {
                $carrierServiceLabel = $rate['carrier'] . " " . $rate['service'];
                $rateGroups[$carrierServiceLabel][] = $rate;
            }

            // build final rate objects and give to woocommerce
            foreach($rateGroups as $group) {
                $r = array(
                    'id' => array(),
                    'cost' => array(),
                    'calc_tax' => 'per_item'
                );

                foreach ($group as $rate) {
                    $r['id'][]  = join('|', $rate['shipment_ids']);
                    $r['label'] = $rate['carrier'] . " " . $rate['service'];
                    $r['cost']  = array_merge($r['cost'], array_fill(0, $rate['quantity'], $rate['rate']));
                }
                $r['id'] = $r['label'] . '|' . join('|', $r['id']);
                $this->add_rate($r);
            }
        } catch(Exception $e) {
            error_log(var_export($e, 1));
        }
    }
}

add_action('woocommerce_checkout_order_processed', 'purchase_order');
function purchase_order($order_id) {
    $settings = get_option('woocommerce_easypost_settings', array());

    $apiKey = $settings['test_api_key'];
    if ($settings['test'] != 'yes') {
        $apiKey = $settings['live_api_key'];
    }

    if ($settings['enabled'] != 'yes') {
        return;
    }

    \EasyPost\EasyPost::setApiKey($apiKey);

    try
    {
        global $woocommerce;

        $order                = &new WC_Order($order_id);
        $shippingAddress      = $order->get_shipping_address();
        $shippingMethodObject = $order->get_shipping_methods();

        // get the first shipping method
        $shippingMethodObject = array_shift($shippingMethodObject);

        // break apart into the needed parts
        $shippingMethodPieces      = array_map("trim", explode('|', $shippingMethodObject['method_id']));
        $shippingCarrierAndService = array_shift($shippingMethodPieces);
        $shipmentKeys              = $shippingMethodPieces;

        // break apart the carrier and service for buying the shipments
        list($shippingCarrier, $shippingService) = array_map('trim', explode(' ', $shippingCarrierAndService));

        // create a new "To" address
        $to_address = \EasyPost\Address::create(array(
            'name'    => sprintf("%s %s", $order->shipping_first_name, $order->shipping_last_name),
            'company' => $order->shipping_company,
            'street1' => $order->shipping_address_1,
            'street2' => $order->shipping_address_2,
            'city'    => $order->shipping_city,
            'state'   => $order->shipping_state,
            'zip'     => $order->shipping_postcode,
            'phone'   => $order->billing_phone
        ));
        
        // buy the shipments
        $labels = array();
        foreach ($shipmentKeys as $shipmentKey) {
            // separate prodcut ids & product shipment keys
            list($productId, $shipmentId) = array_map('trim', explode('-', $shipmentKey));
                
            // load product
            $product = get_product($productId);

            // load shipment by id
            $shipment = \EasyPost\Shipment::retrieve($shipmentId);

            // create a new shipment with the new "To" Address
            $newShipment = \EasyPost\Shipment::create(array(
                'from_address' => $shipment->from_address,
                'to_address'   => $to_address,
                'parcel'       => $shipment->parcel
            ));

            // buy the postage
            $result = $newShipment->buy($newShipment->lowest_rate(array($shippingCarrier), array($shippingService)));

            // keep an array of shipping labels
            $labels[] = "<strong>" . $product->get_title() . "</strong>" . "\n" . $newShipment->postage_label->label_url;
        }

        // update the order with the postage
        update_post_meta($order_id, 'easypost_shipping_label', join("\n\n", $labels));
        $order->add_order_note(sprintf("Shipping label(s) available at: %s", join("\n\n", $labels)));
    } catch(Exception $e) {
        dd($e);
        die('Exception');
    }
}