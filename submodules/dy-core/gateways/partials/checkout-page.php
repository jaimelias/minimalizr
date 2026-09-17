<?php
if (!defined('WPINC')) exit;
$columns = $view['show_unit'] ? 3 : 2;
?>
<hr/>
<div class="clearfix relative small text-right">
    <a class="pure-button rounded pure-button-bordered bottom-20" href="<?php echo esc_url($view['url']); ?>"><span class="dashicons dashicons-arrow-left"></span> <?php esc_html_e('Go back', 'dycore'); ?></a>
    <?php echo $view['tools']; ?>
</div>
<hr/>
<?php echo $view['notices']; ?>
<div class="pure-g gutters">
    <div class="pure-u-1 pure-u-md-1-3"><div class="bottom-20"><?php echo $view['details']; ?></div></div>
    <div class="pure-u-1 pure-u-md-2-3">
        <?php if ($view['show_table']): ?>
        <table id="dynamic_table" class="text-center pure-table pure-table-bordered width-100">
            <thead><tr><th><?php esc_html_e('Description', 'dycore'); ?></th><?php if ($view['show_unit']): ?><th><?php echo esc_html($view['unit_label']); ?></th><?php endif; ?><th><?php esc_html_e('Subtotal', 'dycore'); ?></th></tr></thead>
            <tbody>
                <?php foreach ($view['rows'] as [$label, $unit, $subtotal]): ?>
                <tr><td><?php echo esc_html($label); ?></td><?php if ($view['show_unit']): ?><td><?php echo esc_html($unit); ?></td><?php endif; ?><td><?php echo esc_html($subtotal); ?></td></tr>
                <?php endforeach; ?>
                <?php foreach ($view['notes'] as $label => $text): ?>
                <tr><td colspan="<?php echo $columns; ?>"><p class="small text-left"><strong><?php echo esc_html($label); ?>:</strong> <?php echo esc_html($text); ?></p></td></tr>
                <?php endforeach; ?>
            </tbody>
            <?php if ($view['add_ons'] !== ''): ?>
            <thead><tr><th colspan="<?php echo $columns - 1; ?>"><?php esc_html_e('Add-ons', 'dycore'); ?></th><th><?php esc_html_e('Include?', 'dycore'); ?></th></tr></thead>
            <tbody><?php echo $view['add_ons']; ?></tbody>
            <?php endif; ?>
            <tfoot class="text-center strong">
                <?php foreach ($view['totals'] as $total): ?><tr><td colspan="<?php echo $columns; ?>"><?php echo $total; ?></td></tr><?php endforeach; ?>
            </tfoot>
        </table>
        <div class="text-muted large strong text-center bottom-20"><?php echo $view['outstanding']; ?></div>
        <?php endif; ?>
        <hr/><?php echo $view['checkout']; ?>
    </div>
</div>
