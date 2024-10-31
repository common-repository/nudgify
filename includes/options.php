<?php
defined('ABSPATH') or die("Restricted access!");
?>
<div class="wrap">
    <h1>Connect Nudgify to your website</h1>

    <hr />

    <h2>Settings</h2>

    <form id="nudgify-form-options" name="dofollow" action="options.php" method="post">
        <?php settings_errors(); ?>
        <?php echo nudgify_feedback_message('connect'); ?>

        <?php settings_fields(NudgifyOptions::OPTIONS_GROUP); ?>
        <?php do_settings_sections(NudgifyOptions::OPTIONS_GROUP); ?>
        <input type="hidden" name="<?= NudgifyOptions::SAVED ?>" id="<?= NudgifyOptions::SAVED ?>" data-initial="<?= get_option(NudgifyOptions::SAVED, time()) ?>" value="<?= get_option(NudgifyOptions::SAVED, time()) ?>" />

        <?php if (get_option(NudgifyOptions::CONNECTED)) : ?>
            <div class="notice notice-success">
                <p><strong>Your site is connected to Nudgify.</strong></p>
            </div>
        <?php endif; ?>


        <table class="form-table">
            <tr>
                <th>
                    <label for="<?= NudgifyOptions::ENABLED ?>">Enable Nudgify</label>
                </th>
                <td>
                    <select id="<?= NudgifyOptions::ENABLED ?>" name="<?= NudgifyOptions::ENABLED ?>" data-initial="<?= get_option(NudgifyOptions::ENABLED) ?>" style="width: 10em;">
                        <option value="1" <?= get_option(NudgifyOptions::ENABLED) == 1 ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= get_option(NudgifyOptions::ENABLED) == 0 ? 'selected' : '' ?>>No</option>
                    </select>
                </td>
            </tr>
            <tr class="depends-on-enabled <?= get_option(NudgifyOptions::ENABLED) ? '' : 'hidden' ?>">
                <th>
                    <label for="<?= NudgifyOptions::SITE_KEY ?>">Site Key</label>
                </th>
                <td>
                    <input id="<?= NudgifyOptions::SITE_KEY ?>" class="regular-text code" name="<?= NudgifyOptions::SITE_KEY ?>" type="text" value="<?= esc_html(get_option(NudgifyOptions::SITE_KEY)); ?>" />

                    <p class="description">
                        Go to your the <a href="https://app.nudgify.com/integrations/wordpress" target="_blank" rel="noopener">Wordpress Integration page</a> for your current site to find your Site Key.
                    </p>
                </td>
            </tr>
            <tr class="depends-on-enabled <?= get_option(NudgifyOptions::ENABLED) ? '' : 'hidden' ?>">
                <th>
                    <label for="<?= NudgifyOptions::API_TOKEN ?>">API Key</label>
                </th>
                <td>
                    <input id="<?= NudgifyOptions::API_TOKEN ?>" class="regular-text code" name="<?= NudgifyOptions::API_TOKEN ?>" type="text" value="<?= esc_html(get_option(NudgifyOptions::API_TOKEN)); ?>" />

                    <p class="description">
                        Go to your <a href="https://app.nudgify.com/settings" target="_blank" rel="noopener">Profile Details</a> to find your API Key.
                    </p>
                </td>
            </tr>
            <tr class="depends-on-enabled <?= get_option(NudgifyOptions::ENABLED) ? '' : 'hidden' ?>">
                <th>
                    <label for="<?= NudgifyOptions::AUTOSYNC ?>">Sync new orders to Nudgify</label>
                </th>
                <td>
                    <select id="<?= NudgifyOptions::AUTOSYNC ?>" name="<?= NudgifyOptions::AUTOSYNC ?>" style="width: 10em;">
                        <option value="1" <?= get_option(NudgifyOptions::AUTOSYNC) == 1 ? 'selected' : '' ?>>Yes</option>
                        <option value="0" <?= get_option(NudgifyOptions::AUTOSYNC) == 0 ? 'selected' : '' ?>>No</option>
                    </select>
                    <p class="description">
                        Enable this option to automatically sync new WooCommerce orders with Nudgify
                    </p>
                </td>
            </tr>
            <tr>
                <th></th>
                <td>
                    <p class="depends-on-enabled submit <?= get_option(NudgifyOptions::ENABLED) ? '' : 'hidden' ?>">
                        <input type="submit" name="submit" id="nudgify-setup" class="button button-primary" value="Set up connection">
                    </p>
                    <p class="depends-on-enabled submit <?= get_option(NudgifyOptions::ENABLED) ? 'hidden' : '' ?>">
                        <input type="submit" name="submit" id="nudgify-submit" class="button button-primary" value="Save settings">
                    </p>
                </td>
            </tr>
        </table>
    </form>

    <?php if (nudgify_woocommerce_enabled()) : ?>
        <div class="depends-on-enabled <?= get_option(NudgifyOptions::ENABLED) ? '' : 'hidden' ?>">
        <hr>
        <h2>Sync orders</h2>
        <?php echo nudgify_feedback_message('manualsync'); ?>
        <form action="<?php echo admin_url('admin.php'); ?>" method="post" id="nudgify-form-manualsync">
            <input type="hidden" name="action" value="<?php echo NudgifyOptions::DO_MANUAL_SYNC; ?>" />
            <input type="hidden" name="nudgify_manual_sync_nonce" value="<?php echo wp_create_nonce('nudgify_manual_sync_nonce') ?>" />
            <table class="form-table">
                <tr>
                    <th></th>
                    <td>
                        <?php submit_button('Manually sync orders'); ?>
                        <p class="description">
                            This will send the last 30 orders to Nudgify
                        </p>
                    </td>
                </tr>
                <tr>
                    <td></td>
                    <td class="nudgify-feedback"></td>
                </tr>
            </table>
        </form>
        </div>
    <?php endif; ?>
    <div class="nudgify-rating-wrapper" style="background: #fff; border: 1px solid #c3c4c7; border-left-width: 4px; box-shadow: 0 1px 1px rgb(0 0 0 / 4%); padding: 1px 12px; border-left-color: #f4c056;">
        <div class="nudgify-rating-inner-container" style="display: flex;">
            <div class="nudgify-rating-icon" style="padding: 20px;">
                <svg fill="#F4C056" height="40" width="40" version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 511.987 511.987" xml:space="preserve">
                    <g>
                        <g>
                            <path
                                d="M510.991,185.097c-2.475-7.893-9.301-13.632-17.515-14.72l-148.843-19.84L275.087,11.316
                    c-7.211-14.464-30.976-14.464-38.187,0l-69.547,139.221l-148.843,19.84c-8.213,1.088-15.04,6.827-17.515,14.72
                    c-2.496,7.872-0.213,16.512,5.867,22.101l107.392,98.923L85.604,486.857c-1.365,8.469,2.517,16.96,9.835,21.483
                    c7.339,4.501,16.661,4.203,23.616-0.811l136.939-97.792l136.939,97.792c3.691,2.667,8.043,3.989,12.395,3.989
                    c3.883,0,7.765-1.067,11.221-3.179c7.317-4.523,11.2-13.013,9.835-21.483l-28.651-180.736l107.392-98.923
                    C511.204,201.609,513.487,192.969,510.991,185.097z"
                            ></path>
                        </g>
                    </g>
                </svg>
            </div>
            <div class="nudgify-rating-info" style="margin-left:20px;">
                <h3 style="margin-bottom: 10px !important;">Your site is connected to Nudgify.</h3>
                <span>
                    If you like the Nudgify plugin, we'd love to hear from you! Please consider leaving a review to help more people discover Nudgify. We really appreciate it!
                </span>
            </div>
            <div class="nudgify-rating-btn" style="margin:auto; margin-left:40px;">
                <a href="https://wordpress.org/plugins/nudgify/#reviews" target="_blank" class="button button-primary">Rate Nudgify</a>
            </div>
        </div>
    </div>
</div>
<script>
    jQuery('#nudgify-form-manualsync').on('submit', function(event) {
        event.preventDefault();
        var form = jQuery('#nudgify-form-manualsync');
        var button = form.find('input[type=submit]');
        var buttonText = button.val();
        var feedback = form.find('.nudgify-feedback');
        
		jQuery.ajax({
            type: "POST",
            url: form.attr('action'),
            data: form.serialize(),
            beforeSend: function() {
                button.prop('disabled', true);
                button.val('Syncing orders ...');
                feedback.html('');
            },
            success: function(data) {
                button.prop('disabled', false);
                button.val(buttonText);
                feedback.html(data);
            }
        });
    });

    jQuery('#nudgify-form-options').on('submit', function() {
        var button = jQuery('#nudgify-form-options').find('#nudgify-setup');
        button.prop('disabled', true);
        button.val('Connecting ...');
    });

    jQuery('#<?= NudgifyOptions::SITE_KEY ?>,#<?= NudgifyOptions::API_TOKEN ?>').on('change', function(event) {
        jQuery('#<?= NudgifyOptions::SAVED ?>').val(Date.now());
    });

    jQuery('#<?= NudgifyOptions::ENABLED ?>').on('change', function(event) {
        if (event.target.value) {
            jQuery('#<?= NudgifyOptions::SAVED ?>').val(Date.now());
        }
        jQuery('.depends-on-enabled').toggleClass('hidden');
    });
</script>