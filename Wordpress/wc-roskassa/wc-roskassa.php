<?php

/*

Plugin Name: Payment Gateway for Integration of RosKassa with WooCommerce

Plugin URI: https://Syntlex.Biz/

Description: Payment Gateway for Integration of RosKassa with WooCommerce - the best Payment Processing 

Tags: WooCommerce, WordPress, Gateways, Payments, Payment, Money, WooCommerce, WordPress, Plugin, Module, Store, Modules, Plugins, Payment system, Website, RosKassa, Syntlex Biz

Version: 1.1

Author: Syntlex Biz

Author URI: https://syntlex.biz 

Copyright: © 2021 Syntlex Biz.

License: GNU General Public License v3.0

License URI: http://www.gnu.org/licenses/gpl-3.0.html

 */



if (!defined('ABSPATH')) {

    exit;

}

// Exit if accessed directly



/**

 * Add roubles in currencies

 *

 * @since 0.3

 */

// function payment_rub_currency_symbol($currency_symbol, $currency)

// {

//     if ($currency == "RUB") {

//         $currency_symbol = 'р.';

//     } else if ($currency == "USD") {

//         $currency_symbol = '$.';

//     }



//     return $currency_symbol;

// }



// function payment_rub_currency($currencies)

// {

//     $currencies["RUB"] = 'Russian Roubles';



//     return $currencies;

// }



// add_filter('woocommerce_currency_symbol', 'payment_rub_currency_symbol', 10, 2);

// add_filter('woocommerce_currencies', 'payment_rub_currency', 10, 1);



/* Добавить собственный класс оплаты в WC

------------------------------------------------------------ */

add_action('plugins_loaded', 'woocommerce_roskassa', 0);

function woocommerce_roskassa()

{

    if (!class_exists('WC_Payment_Gateway')) {

        return;

    }

    // если класс платежного шлюза WC недоступен, ничего не делать

    if (class_exists('WC_ROSKASSA')) {

        return;

    }



    class WC_ROSKASSA extends WC_Payment_Gateway

    {

        public function __construct()

        {

            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id = 'roskassa';

            $this->icon = apply_filters('woocommerce_payment_icon', '' . $plugin_dir . 'roskassa.svg');

            $this->has_fields = false;

            // Загрузите настройки

            $this->init_form_fields();

            $this->init_settings();

            // Определить переменные, заданные пользователем

            $this->payment_url = $this->get_option('payment_url');

            $this->title = $this->get_option('title');

            $this->payment_merchant = $this->get_option('payment_merchant');

            $this->payment_secret_key = $this->get_option('payment_secret_key');

            $this->description = $this->get_option('description');

            // Логи

            if (($this->debug == 'yes') && (method_exists($woocommerce, 'logger'))) {

                $this->log = $woocommerce->logger();

            }

            // Событие

            add_action('valid-roskassa-standard-request' . $this->id, array($this, 'successful_request'));

            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Сохранение опций

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // Слушатель платежей / ловушка API

            add_action('woocommerce_api_wc_' . $this->id, array($this, 'check_response'));

            if (!$this->is_valid_for_use()) {

                $this->enabled = false;

            }

        }



        /**

         * Проверьте, включен ли этот шлюз и доступен ли он в стране пользователя

         */

        public function is_valid_for_use()

        {

            if (!in_array(get_option('woocommerce_currency'), array('RUB'))) {

                return false;

            }

            return true;

        }



        /**

         * Опции админ панели

         * - Варианты битов, таких как "название" и доступность для каждой страны

         * @since 0.1

         **/

        public function admin_options()

        {

            print '<h3>'._e('ROSKASSA', 'woocommerce').'</h3>';

            print '<p>'._e('Настройка приема электронных платежей через Merchant ROSKASSA.', 'woocommerce').'</p>';

            if ($this->is_valid_for_use()) {

                print '<table class="form-table">';

                $this->generate_settings_html();

                print '</table><!--/.form-table-->';

            } else {

                print '<div class="inline error"><p><strong>';

                _e('Шлюз отключен', 'woocommerce');

                print '</strong>';

                _e('ROSKASSA не поддерживает валюты Вашего магазина.', 'woocommerce');

                print '</p></div>';

            }



        } // Конец admin_options()



        /**

         * Поля формы Initialise Gateway Settings

         *

         * @access public

         * @return void

         */

        public function init_form_fields()

        {

            $a = $_SERVER['SERVER_NAME'];

            $this->form_fields = array(

                'enabled' => array(

                    'title' => __('Включить/Выключить', 'woocommerce'),

                    'type' => 'checkbox',

                    'label' => __('Включен', 'woocommerce'),

                    'default' => 'yes',

                ),

                'title' => array(

                    'title' => __('Название', 'woocommerce'),

                    'type' => 'text',

                    'description' => __('Это название, которое пользователь видит во время выбора способа оплаты.', 'woocommerce'),

                    'default' => __('Roskassa', 'woocommerce'),

                ),

                'payment_url'    => array(

                    'title'       => __( 'URL мерчанта', 'woocommerce' ),

                    'type'        => 'text',

                    'description' => __( 'url для оплаты в системе Roskassa', 'woocommerce' ),

                    'default'     => '//pay.roskassa.net/'

                ),

                'payment_merchant' => array(

                    'title' => __('Идентификатор магазина', 'woocommerce'),

                    'type' => 'text',

                    'description' => __('Идентификатор магазина, зарегистрированного в системе "Roskassa".<br/>Узнать его можно в аккаунте Roskassa".', 'woocommerce'),

                    'default' => '',

                ),

                'payment_secret_key' => array(

                    'title' => __('Секретный ключ', 'woocommerce'),

                    'type' => 'password',

                    'description' => __('Секретный ключ. <br/>Должен совпадать с секретным ключем, указанным в Roskassa".', 'woocommerce'),

                    'default' => '',

                ),

                'description' => array(

                    'title' => __('Описание', 'woocommerce'),

                    'type' => 'textarea',

                    'description' => __('Описанием метода оплаты которое клиент будет видеть на вашем сайте.', 'woocommerce'),

                    'default' => 'Оплата с помощью сервиса приема платежей Roskassa.',

                ),

                'URL'            => array(

                    'title'       => __( 'Настройка URL', 'woocommerce' ),

                    'type'        => 'hidden',

                    'description' => __( "URL оповещения: <br/>

                        http://$a/?wc-api=wc_roskassa&roskassa=result<br/>

                        URL возврата в случае успеха:<br/>

                        http://$a/?wc-api=wc_roskassa&roskassa=calltrue<br/>

                        URL возврата в случае неудачи:<br/>

                        http://$a/?wc-api=wc_roskassa&roskassa=callfalse<br/> ", 'woocommerce'),



                )

            );

        }



        /**

         * Создать ссылку на кнопку фишек

         * @param $order_id

         * @return string

         */

        public function generate_form($order_id)

        {   

            $order = new WC_Order($order_id);

            $m_url = $this->payment_url;

            $m_shop     = $this->payment_merchant;

            $m_order_id = $order_id;

            $m_amount   = intval($this->getOrderTotal($order));

            $m_currency = $order->get_currency();



            $data = array(

                'shop_id'=>$m_shop,

                'amount'=>$m_amount,

                'currency'=>$m_currency,

                'order_id'=>$m_order_id,

                //'test' => 1

            );

            ksort($data);

            $str = http_build_query($data);

            $sign = md5($str . $this->payment_secret_key);



            return

            '<form method="GET" action="' . $m_url . '">

            <input type="hidden" name="shop_id" value="' . $m_shop . '">

            <input type="hidden" name="amount" value="' . $m_amount . '">

            <input type="hidden" name="order_id" value="' . $m_order_id . '">   

            <input type="hidden" name="currency" value="' . $m_currency . '">

            <!-- <input type="hidden" name="test" value="1"> -->

            <input type="hidden" name="sign" value="' . $sign . '">  

            <input type="submit" name="m_process" value="Оплатить" />

            </form>';



        }



        /**

         * Обработайте платеж и верните результат

         * @param $order_id

         * @return array

         */

        public function process_payment($order_id)

        {

            $order = new WC_Order($order_id);

            return array(

                'result' => 'success',

                'redirect' => add_query_arg('order', $order_id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay')))),

            );

        }



        /**

         * receipt_page

         * @param $order

         */

        public function receipt_page($order)

        {

            echo '<p>' . __('Спасибо за Ваш заказ, пожалуйста, нажмите кнопку ниже, чтобы заплатить.', 'woocommerce') . '</p>';

            echo $this->generate_form($order);

        }



        /**

         * Возвращаем сумму заказа в формате 0.00

         **/

        public function getOrderTotal($order)

        {

            return number_format($order->order_total, 2, '.', '');

        }



        /**

         * Проверить ответ

         **/

        public function check_response()

        {

            if (isset($_GET['roskassa']) and $_GET['roskassa'] == 'result') {

                @ob_clean();

                $_POST = stripslashes_deep($_POST);

                $data = $_POST;

                unset($data['sign']);

                ksort($data);

                $str = http_build_query($data);

                $sign_hash = md5($str . $this->payment_secret_key);



                if ($_POST['sign'] == $sign_hash) {

                    // Добавить информацию о транзакции для roskassa

                    if ($this->debug) {

                        $this->add_transaction_info($_POST);

                    }

                    do_action('valid-roskassa-standard-request', $_POST);

                } else {

                    wp_die('Request Failure');

                }

            } else if (isset($_GET['roskassa']) and $_GET['roskassa'] == 'calltrue') {

                print_r($_POST);

                $orderId = $_GET['order_id'];

                $order = new WC_Order($orderId);

                $order->update_status('processing', __('Платеж оплачен', 'woocommerce'));

                WC()->cart->empty_cart();

                wp_redirect($this->get_return_url($order));

            } else if (isset($_GET['roskassa']) and $_GET['roskassa'] == 'callfalse') {

                $orderId = $_GET['order_id'];

                $order = new WC_Order($orderId);

                $order->update_status('failed', __('Платеж не оплачен', 'woocommerce'));

                wp_redirect($order->get_cancel_order_url());

                exit;

            }

        }



        /**

         * Добавить комментарий к заказу с информацией о транзакциях

         * @param $post

         */

        private function add_transaction_info($post)

        {

            $orderId = $post['LMI_PAYMENT_NO'];

            $order = new WC_Order($orderId);

            $message = 'Транзакция была проведена на сайте платёжной системы Roskassa. Номер транзакции: ' .

            $post['LMI_SYS_PAYMENT_ID'] . '. ' .

            'Дата платежа: ' . $post['LMI_SYS_PAYMENT_DATE'] . '. ' .

            'Валюта и сумма платежа: ' . $post['LMI_PAID_AMOUNT'] . ' ' . $post['LMI_PAID_CURRENCY'] . '. ' .

            'Способ платежа, выбранный пользователем: ' . $post['LMI_PAYMENT_METHOD'] . '. ' .

            'Идентификатор плательщика в платежной системе: ' . $post['LMI_PAYER_IDENTIFIER'] . '. ' .

            'IP адрес плательщика: ' . $post['LMI_PAYER_IP_ADDRESS'] . '.';

            $order->add_order_note($message);

            return;

        }



        /**

         * Успешный платеж!

         * @param $posted

         */

        public function successful_request($posted)

        {

            $orderID = $posted['LMI_PAYMENT_NO'];

            $order = new WC_Order($orderID);

            // Проверить заказ на завершенность

            if ($order->status == 'completed') {

                exit;

            }

            // Оплата завершена

            $order->add_order_note(__('Оплата произведена успешно!', 'woocommerce'));

            $order->update_status($this->payment_order_status, __('Заказ был оплачен успешно', 'woocommerce'));

            $order->payment_complete();

            exit;

        }



        /**

         * Logger function

         * @param  [type] $var  [description]

         * @param string $text [description]

         * @return void [type]       [description]

         */

        public function logger($var, $text = '')

        {

            // Название файла

            $loggerFile = __DIR__ . '/logger.log';

            if (is_object($var) || is_array($var)) {

                $var = (string)print_r($var, true);

            } else {

                $var = (string)$var;

            }

            $string = date("Y-m-d H:i:s") . " - " . $text . ' - ' . $var . "\n";

            file_put_contents($loggerFile, $string, FILE_APPEND);

        }





    }



    /**

     * Add the gateway to WooCommerce

     * @param $methods

     * @return array

     */

    function add_payment_gateway($methods)

    {

        $methods[] = 'WC_ROSKASSA';



        return $methods;

    }



    add_filter('woocommerce_payment_gateways', 'add_payment_gateway');

}



?>