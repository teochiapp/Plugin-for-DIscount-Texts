<?php
/*
Plugin Name: Textos para Descuentos
Description: Plugin para cambiar textos en el single y Archive de los distintos metodos de pagos definidos de WooCommerce
Version: 1.0
Author:  Estudio Rocha y Asoc.
*/
function theme_options_init()
{
    register_setting('theme_options', 'discount_options');
}

add_action('admin_init', 'theme_options_init');

function add_theme_menu()
{
    add_menu_page(
        'Textos para Descuentos',
        'Textos para Descuentos',
        'manage_options',
        'theme_discounts',
        'theme_discounts_page',
        'dashicons-money',
        54
    );

    // Agrega estilos personalizados al menú
    add_action('admin_head', 'custom_menu_styles');
}

add_action('admin_menu', 'add_theme_menu');

function theme_discounts_page()
{
    wp_enqueue_style('styles', plugin_dir_url(__FILE__) . 'styles.css');
?>
    <div class="wrap">
        <h2>Descuentos del Tema</h2>
        <form method="post" action="options.php" id="form_payment_methods">
            <?php
            settings_fields('theme_options');
            do_settings_sections('theme_discounts');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'theme_discounts_settings');

function display_gateway_methods_suffix($suffix, $product)
{
    $active_gateways = WC()->payment_gateways->get_available_payment_gateways();

    $regular_price = floatval(get_post_meta($product->get_id(), '_regular_price', true));
    $sale_price = floatval(get_post_meta($product->get_id(), '_sale_price', true));
    $price = ($sale_price) ? $sale_price : $regular_price;

    ob_start();

    foreach ($active_gateways as $gateway) {
        $gateway_info = get_gateway_discount($gateway->id);

        if (
            $gateway_info['discount'] > 0 &&
            (
                ($gateway_info['archive_view'] == 1 && !is_single()) ||
                ($gateway_info['single_view'] == 1 && is_single())
            )
        ) {
            $discount_price = wc_price($price - ($price * ($gateway_info['discount'] / 100)));
            $title = is_single() ? $gateway_info['single_title'] : $gateway_info['archive_title'];
    ?>
            <div class="gateway-method">
                <img src="<?php echo $gateway_info['icono'] ?>" style="height: 30px; margin-right: 8px; object-fit: contain;">
                <span style="width:100%;font-size:16px; color:<?php echo $gateway_info['color'] ?>">
                    <?php echo $title . " " . $discount_price; ?>
                </span>
            </div>

        <?php
        }
    }

    return ob_get_clean() . $suffix;
}
add_filter('woocommerce_get_price_suffix', 'display_gateway_methods_suffix', 99, 2);



function discount_section_callback()
{
    echo '<p>Ingrese los descuentos, títulos, color, ícono y visibilidad para cada método de pago.</p>';
}

function theme_discount_callback($gateway_id, $gateway_title)
{
	$options                   = get_option( 'discount_options' );
	$gateway_info              = get_gateway_discount( $gateway_id );
	$gateway_archive_view      = isset( $options[ $gateway_id . '_archive_view' ] ) ? $options[ $gateway_id . '_archive_view' ] : 0;
	$gateway_single_view       = isset( $options[ $gateway_id . '_single_view' ] ) ? $options[ $gateway_id . '_single_view' ] : 0;
	$archive_title_value       = isset( $options[ $gateway_id . '_archive_title' ] ) ? $options[ $gateway_id . '_archive_title' ] : '';
	$single_title_value        = isset( $options[ $gateway_id . '_single_title' ] ) ? $options[ $gateway_id . '_single_title' ] : '';
	$image_url                 = isset( $options[ $gateway_id . '_image_url' ] ) ? $options[ $gateway_id . '_image_url' ] : '';

    if (isset($options[$gateway_id])) {
        $discount_value = is_numeric($options[$gateway_id]) ? $options[$gateway_id] : 0;
        ?>
        <span class="label-discount">Descuento (%)</span>
        <input type="text" id="<?php echo $gateway_id; ?>_discount" name="discount_options[<?php echo $gateway_id; ?>]" value="<?php echo esc_attr($discount_value); ?>" placeholder="Descuento (%)" class="mb-10" />
    <?php
    }

    $color_value = isset($options[$gateway_id . '_color']) ? $options[$gateway_id . '_color'] : '#000000';
    ?>

    <label for="<?php echo $gateway_id; ?>_color">Color:</label>
    <input type="color" id="<?php echo $gateway_id; ?>_color" name="discount_options[<?php echo $gateway_id; ?>_color]" value="<?php echo esc_attr($color_value); ?>" class="mb-10" />

    <span class="label-discount">Texto para el Archivo:</span>
    <input type="text" id="<?php echo $gateway_id; ?>_archive_title" name="discount_options[<?php echo $gateway_id; ?>_archive_title]" value="<?php echo esc_attr($archive_title_value); ?>" class="mb-10" />

    <fieldset class="mb-10">
        <input type="checkbox" id="<?php echo $gateway_id; ?>_archive_view" name="discount_options[<?php echo $gateway_id; ?>_archive_view]" value="1" <?php checked(1, $gateway_archive_view, false); ?> />
        <label for="<?php echo $gateway_id; ?>_archive_view">¿Mostrar en el archivo?</label>
    </fieldset>

    <span class="label-discount">Texto para el Producto Individual:</span>
    <input type="text" id="<?php echo $gateway_id; ?>_single_title" name="discount_options[<?php echo $gateway_id; ?>_single_title]" value="<?php echo esc_attr($single_title_value); ?>" class="mb-10" />

    <fieldset class="mb-10">
        <input type="checkbox" id="<?php echo $gateway_id; ?>_single_view" name="discount_options[<?php echo $gateway_id; ?>_single_view]" value="1" <?php checked(1, $gateway_single_view, false); ?> />
        <label for="<?php echo $gateway_id; ?>_single_view">¿Mostrar en el producto individual?</label>
    </fieldset>

    <label for="<?php echo $gateway_id; ?>_image_url">URL del ícono:</label>
    <input type="url" id="<?php echo $gateway_id; ?>_image_url" name="discount_options[<?php echo $gateway_id; ?>_image_url]" value="<?php echo esc_url($image_url); ?>" class="mb-10" />

<?php
}



function theme_discounts_settings()
{
    add_settings_section('discount_section', 'Configuración de Descuentos', 'discount_section_callback', 'theme_discounts');

    // Obtener solo los métodos de pago habilitados
    $active_gateways = WC()->payment_gateways->get_available_payment_gateways();

    foreach ($active_gateways as $gateway) {
        $callback_function = $gateway->id . '_discount_callback';
        $gateway_title = $gateway->get_title();

        add_settings_field(
            $gateway->id . '_discount',
            'Descuento para ' . $gateway_title,
            function () use ($gateway, $gateway_title) {
                theme_discount_callback($gateway->id, $gateway_title);
            },
            'theme_discounts',
            'discount_section'
        );
    }
}

add_action('admin_init', 'theme_discounts_settings');

function get_gateway_discount($gateway_id)
{
    $options = get_option('discount_options');

    $gateway_specific_keys = array(
        'discount' => $gateway_id,
        'title' => $gateway_id . '_title',
        'archive_title' => $gateway_id . '_archive_title',
        'single_title' => $gateway_id . '_single_title',
        'archive_view' => $gateway_id . '_archive_view',
        'single_view' => $gateway_id . '_single_view',
        'color' => $gateway_id . '_color',
        'icono' => $gateway_id . '_image_url'
    );

    $discount_info = array();

    foreach ($gateway_specific_keys as $key => $option_key) {
        $discount_info[$key] = isset($options[$option_key]) ? $options[$option_key] : '';
    }

    return $discount_info;
}
