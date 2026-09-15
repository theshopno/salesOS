<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Order Notifier
Description: Customer SMS and WhatsApp notifications suite for E-commerce orders.
Version: 1.0.0
Requires at least: 2.3.0
*/

define('ORDERNOTIFIER_MODULE_NAME', 'ordernotifier');

register_activation_hook(ORDERNOTIFIER_MODULE_NAME, 'ordernotifier_activation_hook');
function ordernotifier_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// Register salesos hooks
hooks()->add_action('salesos_order_created', 'ordernotifier_handle_order_created');
hooks()->add_action('salesos_order_confirmed', 'ordernotifier_handle_order_confirmed');
hooks()->add_action('salesos_order_cancelled', 'ordernotifier_handle_order_cancelled');

/**
 * Handle order created notification
 */
function ordernotifier_handle_order_created($order_id)
{
    $CI =& get_instance();
    if (!$CI->app_modules->is_active('salesos') || !$CI->app_modules->is_active(ORDERNOTIFIER_MODULE_NAME)) {
        return;
    }
    $CI->load->model('ordernotifier/ordernotifier_model');
    $CI->ordernotifier_model->notify($order_id, 'order_created');
}

/**
 * Handle order confirmed notification
 */
function ordernotifier_handle_order_confirmed($order_id)
{
    $CI =& get_instance();
    if (!$CI->app_modules->is_active('salesos') || !$CI->app_modules->is_active(ORDERNOTIFIER_MODULE_NAME)) {
        return;
    }
    $CI->load->model('ordernotifier/ordernotifier_model');
    $CI->ordernotifier_model->notify($order_id, 'order_confirmed');
}

/**
 * Handle order cancelled notification
 */
function ordernotifier_handle_order_cancelled($order_id)
{
    $CI =& get_instance();
    if (!$CI->app_modules->is_active('salesos') || !$CI->app_modules->is_active(ORDERNOTIFIER_MODULE_NAME)) {
        return;
    }
    $CI->load->model('ordernotifier/ordernotifier_model');
    $CI->ordernotifier_model->notify($order_id, 'order_cancelled');
}
