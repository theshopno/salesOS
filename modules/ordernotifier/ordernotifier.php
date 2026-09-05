<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Order Notifier
Description: Customer SMS and WhatsApp notifications suite for E-commerce orders.
Version: 1.0.0
Requires at least: 2.3.0
*/

define('ORDERNOTIFIER_MODULE_NAME', 'ordernotifier');

// Register ecomcore hooks
hooks()->add_action('ecomcore_order_created', 'ordernotifier_handle_order_created');
hooks()->add_action('ecomcore_order_confirmed', 'ordernotifier_handle_order_confirmed');
hooks()->add_action('ecomcore_order_cancelled', 'ordernotifier_handle_order_cancelled');

/**
 * Handle order created notification
 */
function ordernotifier_handle_order_created($order_id)
{
    $CI =& get_instance();
    $CI->load->model('ordernotifier/ordernotifier_model');
    $CI->ordernotifier_model->notify($order_id, 'order_created');
}

/**
 * Handle order confirmed notification
 */
function ordernotifier_handle_order_confirmed($order_id)
{
    $CI =& get_instance();
    $CI->load->model('ordernotifier/ordernotifier_model');
    $CI->ordernotifier_model->notify($order_id, 'order_confirmed');
}

/**
 * Handle order cancelled notification
 */
function ordernotifier_handle_order_cancelled($order_id)
{
    $CI =& get_instance();
    $CI->load->model('ordernotifier/ordernotifier_model');
    $CI->ordernotifier_model->notify($order_id, 'order_cancelled');
}
