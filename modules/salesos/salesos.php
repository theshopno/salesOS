<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: SalesOS - E-commerce Core
Description: Master module for the E-commerce Management Suite (owns credentials, orders, events and admin dashboards).
Version: 1.0.0
Requires at least: 2.3.4
*/

// ── Autoloader ───────────────────────────────────────────────────────────────
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    try {
        require_once __DIR__ . '/vendor/autoload.php';
    } catch (\Throwable $e) {
        log_activity('SalesOS autoloader notice: ' . $e->getMessage());
    }
}

define('SALESOS_MODULE_NAME', 'salesos');
define('SALESOS_VERSION',     '1.0.0');

// ── Lifecycle ────────────────────────────────────────────────────────────────
register_activation_hook(SALESOS_MODULE_NAME, 'salesos_activation_hook');
function salesos_activation_hook(): void
{
    $CI = &get_instance();
    require(__DIR__ . '/install.php');
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
hooks()->add_action('app_init',   'salesos_load_resources');
hooks()->add_action('admin_init', 'salesos_register_menu');
hooks()->add_action('admin_init', 'salesos_register_permissions');

function salesos_load_resources(): void
{
    $CI = &get_instance();
    $CI->load->model(SALESOS_MODULE_NAME . '/salesos_model');

    salesos_init_licensing();
}

/**
 * Initialize Licentra licensing integration for SalesOS.
 */
function salesos_init_licensing(): void
{
    if (!class_exists(\Licentra\CodeIgniter\Registry::class)) {
        return;
    }

    $apiUrl = getenv('LICENTRA_API_URL') ?: (get_option('salesos_licentra_api_url') ?: 'http://127.0.0.1:8000');
    $licenseKey = getenv('LICENTRA_LICENSE_KEY') ?: (getenv('SALESOS_LICENSE_KEY') ?: (get_option('salesos_license_key') ?: ''));
    $keyId = getenv('LICENTRA_PUBLIC_KEY_ID') ?: 'licentra-ed25519-v1';
    $publicKey = getenv('LICENTRA_PUBLIC_KEY') ?: 'base64:9lLZD6IBFplUSF4dcgqJHZ1XwBdbXdFHErfJ2XRi22M=';
    $domain = getenv('LICENTRA_DOMAIN') ?: (get_option('salesos_license_domain') ?: 'crm.bizyto.com');

    $config = new \Licentra\CodeIgniter\Config\LicentraConfig([
        'product_slug'  => 'salesos',
        'product_name'  => 'SalesOS E-commerce Core',
        'api_url'       => $apiUrl,
        'license_key'   => $licenseKey,
        'public_key_id' => $keyId,
        'public_key'    => $publicKey,
        'domain'        => $domain,
        'timeout'       => 10,
    ]);

    $service = new \Licentra\CodeIgniter\Services\LicentraService($config);
    \Licentra\CodeIgniter\Registry::register('salesos', $service);
}

/**
 * Determine if SalesOS is running in standalone / temporary internal license mode.
 * Default is true so operators never face a production lockout while Licentra is in local dev.
 */
function salesos_is_standalone_mode(): bool
{
    return get_option('salesos_license_standalone_mode') !== '0';
}

/**
 * Determine if SalesOS is currently running in local development mode.
 */
function salesos_is_local_dev(): bool
{
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        return true;
    }

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    if (strpos($host, 'crm.test') !== false || strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
        return true;
    }

    if (get_option('salesos_dev_mode') === '1' || getenv('SALESOS_DEV_MODE') === 'true') {
        return true;
    }

    return false;
}

/**
 * Check whether SalesOS has an active license.
 * In standalone temporary license mode or local development mode, returns true to allow developers
 * and operators to run without lockout.
 */
function salesos_is_licensed(): bool
{
    if (salesos_is_standalone_mode() || salesos_is_local_dev()) {
        return true;
    }

    if (!class_exists(\Licentra\CodeIgniter\Registry::class)) {
        return false;
    }

    try {
        $service = \Licentra\CodeIgniter\Registry::get('salesos');
        return $service->isValid();
    } catch (\Throwable) {
        return false;
    }
}

/**
 * Require a valid license to access SalesOS operations.
 */
function salesos_require_license(): void
{
    if (salesos_is_licensed()) {
        return;
    }

    set_alert('warning', 'A valid SalesOS license is required to access e-commerce operations. Please activate your license.');
    redirect(admin_url('salesos/settings?tab=license'));
}

// ── Permissions ──────────────────────────────────────────────────────────────
/**
 * Is the e-commerce side switched on?
 *
 * An install that only wants telephony turns this off rather than hunting
 * through Setup → Modules for nine separate modules. The modules stay installed
 * and keep their data; they just stop appearing and stop serving pages, so
 * turning it back on restores everything exactly as it was.
 */
function salesos_ecommerce_enabled(): bool
{
    return get_option('salesos_ecommerce_enabled') !== '0';
}

/**
 * Must an order be confirmed by a human before it reaches fulfilment?
 *
 * On by default. A cash-on-delivery order in this market is not trusted until
 * somebody has phoned the customer — shipping an unconfirmed one is how sellers
 * lose money on refused deliveries. Off means a channel order goes straight to
 * whatever status the storefront reports.
 */
function salesos_order_confirmation_required(): bool
{
    return salesos_ecommerce_enabled() && get_option('salesos_require_order_confirmation') !== '0';
}

/**
 * Guard for controllers that only make sense when e-commerce is on. The kernel's
 * own settings screen deliberately does NOT call this — that is where the switch
 * lives, so blocking it would leave no way back.
 */
function salesos_require_ecommerce(): void
{
    if (salesos_ecommerce_enabled()) {
        return;
    }

    set_alert('warning', 'E-commerce features are turned off. You can turn them back on in SalesOS → Settings.');
    redirect(admin_url('salesos/settings'));
}

function salesos_register_permissions(): void
{
    register_staff_capabilities('salesos', [
        'capabilities' => [
            'view'     => 'View E-commerce Dashboard',
            'edit'     => 'Edit / Manage Orders',
            'settings' => 'Manage Channels & Credentials',
        ],
    ], 'E-commerce Management');
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function salesos_register_menu(): void
{
    if (!staff_can('view', SALESOS_MODULE_NAME) && !staff_can('settings', SALESOS_MODULE_NAME)) { return; }
    $CI = &get_instance();

    // Top-level Orders Manage Menu
    if (staff_can('view', SALESOS_MODULE_NAME) && salesos_ecommerce_enabled()) {
        $CI->app_menu->add_sidebar_menu_item('salesos-orders-main', [
            'slug'     => 'salesos-orders-main',
            'name'     => 'Orders Manage',
            'icon'     => 'fa fa-shopping-basket',
            'href'     => admin_url('salesos/orders'),
            'position' => 28,
        ]);
    }

    $CI->app_menu->add_sidebar_menu_item(SALESOS_MODULE_NAME, [
        'name'     => 'SalesOS',
        'icon'     => 'fa fa-shopping-cart',
        'position' => 30,
    ]);

    // Main navigation carries only what an operator opens during a normal
    // working day, ordered by how often that happens. Positions 1-9 are reserved
    // for those screens; anything configured once and then left alone lives
    // behind Settings instead (see salesos/views/settings.php).
    // With e-commerce switched off only Settings remains, so the switch itself
    // stays reachable — hiding it too would be a one-way door.
    if (staff_can('view', SALESOS_MODULE_NAME) && salesos_ecommerce_enabled()) {
        $CI->app_menu->add_sidebar_children_item(SALESOS_MODULE_NAME, [
            'slug'     => 'salesos-dashboard',
            'name'     => 'Dashboard',
            'href'     => admin_url('salesos'),
            'position' => 1,
        ]);

        // Only when orders are actually held for a call — otherwise the queue is
        // permanently empty and just takes up a row.
        if (salesos_order_confirmation_required()) {
            $CI->app_menu->add_sidebar_children_item(SALESOS_MODULE_NAME, [
                'slug'     => 'salesos-confirmations',
                'name'     => 'Confirmations',
                'href'     => admin_url('salesos/confirmations'),
                'position' => 3,
            ]);
        }
    }

    if (staff_can('settings', SALESOS_MODULE_NAME)) {
        $CI->app_menu->add_sidebar_children_item(SALESOS_MODULE_NAME, [
            'slug'     => 'salesos-settings',
            'name'     => 'Settings',
            'href'     => admin_url('salesos/settings'),
            'position' => 99,
        ]);
    }
}

/**
 * Format e-commerce price or numbers dynamically based on system setting:
 * remove_decimals_on_zero (Remove decimals on numbers/money with zero decimals).
 */
if (!function_exists('salesos_format_number')) {
    function salesos_format_number($number, $decimals = null)
    {
        if (!is_numeric($number)) {
            return $number;
        }
        
        if ($decimals === null) {
            $decimals = function_exists('get_decimal_places') ? get_decimal_places() : 2;
        }

        if (get_option('remove_decimals_on_zero') == 1) {
            if (round($number, $decimals) == (int)$number) {
                $decimals = 0;
            }
        }

        $decimal_separator  = get_option('decimal_separator') ?: '.';
        $thousand_separator = get_option('thousand_separator') ?: '';

        return number_format((float)$number, $decimals, $decimal_separator, $thousand_separator);
    }
}

// ── Dashboard Widgets ────────────────────────────────────────────────────────
hooks()->add_filter('get_dashboard_widgets', 'salesos_add_dashboard_widgets');

/**
 * Register SalesOS E-commerce widgets into Perfex CRM Admin Dashboard.
 *
 * @param array $widgets
 * @return array
 */
function salesos_add_dashboard_widgets(array $widgets): array
{
    if (staff_can('view', SALESOS_MODULE_NAME) && salesos_ecommerce_enabled()) {
        // 1. Executive KPI Metric Cards (Full Width Top)
        $widgets[] = [
            'path'      => 'salesos/widgets/salesos_top_stats',
            'container' => 'top-12',
        ];

        // 2. Quick Operations Launcher Bar
        $widgets[] = [
            'path'      => 'salesos/widgets/salesos_quick_launcher',
            'container' => 'top-12',
        ];

        // 3. 7-Day Sales Trend Line Chart (Main Left Area)
        $widgets[] = [
            'path'      => 'salesos/widgets/salesos_sales_chart',
            'container' => 'left-8',
        ];

        // 4. Recent Live Orders Stream (Main Left Area)
        $widgets[] = [
            'path'      => 'salesos/widgets/salesos_recent_orders',
            'container' => 'left-8',
        ];

        // 5. Omni-Channel Share & Pipeline Conversion Funnel (Sidebar Right)
        $widgets[] = [
            'path'      => 'salesos/widgets/salesos_omni_channel',
            'container' => 'right-4',
        ];

        // 6. Action Center: Fraud Alerts & Low Stock Warning (Sidebar Right)
        $widgets[] = [
            'path'      => 'salesos/widgets/salesos_action_center',
            'container' => 'right-4',
        ];

        // 7. Courier & Cashflow Summary (Sidebar Right)
        $widgets[] = [
            'path'      => 'salesos/widgets/salesos_courier_summary',
            'container' => 'right-4',
        ];
    }

    return $widgets;
}

