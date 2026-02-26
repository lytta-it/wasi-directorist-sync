<?php
/**
 * Provide a contact/support view for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h2><span class="dashicons dashicons-money-alt"></span> <?php esc_html_e('Support & Licensing PRO - Wasi Sync by Lytta', 'lytta-wasi-sync'); ?></h2>
    
    <h2 class="nav-tab-wrapper">
        <a href="?page=lytta-wasi-sync&tab=settings" class="nav-tab"><?php esc_html_e('Base API Settings', 'lytta-wasi-sync'); ?></a>
        <a href="?page=lytta-wasi-sync&tab=contact" class="nav-tab nav-tab-active"><?php esc_html_e('Contact, Support & PRO License', 'lytta-wasi-sync'); ?></a>
    </h2>

    <div style="display:flex; gap: 20px; flex-wrap: wrap; margin-top:20px;">
        <div style="flex: 1; min-width: 400px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3>🚀 <?php esc_html_e('Upgrade to Wasi Sync PRO!', 'lytta-wasi-sync'); ?></h3>
            <p><?php echo wp_kses_post(__('The free version is great for testing the infrastructure, but it is <strong>limited to importing only 10 global properties*</strong> and downloads only the preview image (preventing server memory exhaustion without guarantees).', 'lytta-wasi-sync')); ?></p>
            
            <p><strong><?php esc_html_e('What the Premium/PRO version unlocks:', 'lytta-wasi-sync'); ?></strong></p>
            <ul style="list-style-type: square; margin-left:20px;">
                <li><?php echo wp_kses_post(__('Synchronization of an <strong>UNLIMITED number</strong> of properties', 'lytta-wasi-sync')); ?></li>
                <li><?php echo wp_kses_post(__('Full import of <strong>Image Galleries</strong>', 'lytta-wasi-sync')); ?></li>
                <li><?php esc_html_e('Removal of limit filters and support for intensive crons (hourly)', 'lytta-wasi-sync'); ?></li>
                <li><?php esc_html_e('Universal ACF (Advanced Custom Fields) integration active', 'lytta-wasi-sync'); ?></li>
            </ul>

            <div style="background: #e5f5fa; border-left: 4px solid #00a0d2; padding: 15px; margin: 20px 0;">
                <h4><?php esc_html_e('Purchase your key (120€/year incl. VAT or 10€/month)', 'lytta-wasi-sync'); ?></h4>
                <p><?php echo wp_kses_post(__('Email us to generate the invoice and get the Upgrade Token: <strong><a href="mailto:info@lytta.it">info@lytta.it</a></strong>', 'lytta-wasi-sync')); ?></p>
            </div>
            
            <p><small><?php esc_html_e('*The GitHub Auto-Updater will continue to fix security bugs for free, even for the Free version.', 'lytta-wasi-sync'); ?></small></p>
        </div>

        <div style="flex: 1; min-width: 400px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
            <h3>🛠 <?php esc_html_e('Custom Development', 'lytta-wasi-sync'); ?></h3>
            <p><?php esc_html_e('Wasi-Sync supports market-leading frameworks (Directorist and generic standard ACF fields). But the real estate world is vast.', 'lytta-wasi-sync'); ?></p>
            <p><?php esc_html_e('Does your site use complex all-in-one themes? Contact Lytta to request the conversion script (Adapter) for your specific theme:', 'lytta-wasi-sync'); ?></p>
            <ul style="list-style-type: disc; margin-left: 20px;">
                <li><?php esc_html_e('Houzez Theme', 'lytta-wasi-sync'); ?></li>
                <li><?php esc_html_e('WP Residence Theme', 'lytta-wasi-sync'); ?></li>
                <li><?php esc_html_e('Goya Real Estate', 'lytta-wasi-sync'); ?></li>
                <li><?php esc_html_e('Listify', 'lytta-wasi-sync'); ?></li>
            </ul>

            <p style="margin-top:20px">
                <a href="mailto:info@lytta.it?subject=Richiesta Adattatore Wasi Personalizzato" class="button button-primary button-large">
                    <?php esc_html_e('Request a Quote (info@lytta.it)', 'lytta-wasi-sync'); ?>
                </a>
            </p>
        </div>
    </div>
</div>
