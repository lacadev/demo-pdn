<?php
/**
 * contact-form-block — render.php
 * Layout: Heading + 5-field form (left) + representative image with gold frame (right)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$heading        = esc_html( $attributes['heading']    ?? 'NHẬN TƯ VẤN NGAY' );
$image_id       = intval( $attributes['imageId']      ?? 0 );
$image_url      = $image_id ? esc_url( wp_get_attachment_image_url( $image_id, 'large' ) ) : '';
$image_alt      = $image_id ? esc_attr( get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) : '';
$budget_options = is_array( $attributes['budgetOptions'] ?? [] ) ? $attributes['budgetOptions'] : [];
$button_text    = esc_html( $attributes['buttonText'] ?? 'GỬI YÊU CẦU' );

$wrapper_attrs = get_block_wrapper_attributes( [ 'class' => 'block-contact-form' ] );
?>

<section <?php echo $wrapper_attrs; ?>>
    <div class="container">
        <div class="bcf__inner">

            <!-- ── Left: Form ── -->
            <div class="bcf__left">

                <?php if ( $heading ) : ?>
                    <h2 class="bcf__heading"><?php echo $heading; ?></h2>
                <?php endif; ?>

                <form
                    class="bcf__form"
                    method="POST"
                    action="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
                    novalidate
                    data-bcf-form
                >
                    <?php wp_nonce_field( 'laca_footer_contact_nonce', 'nonce' ); ?>
                    <input type="hidden" name="action" value="laca_footer_contact_submit">

                    <!-- 1. Địa chỉ xây dựng -->
                    <div class="bcf__field">
                        <input
                            type="text"
                            name="tf_address"
                            class="bcf__input"
                            placeholder="<?php esc_attr_e( 'Địa chỉ xây dựng', 'laca' ); ?>"
                            required
                        >
                    </div>

                    <!-- 2. Quy mô xây dựng -->
                    <div class="bcf__field">
                        <input
                            type="text"
                            name="tf_scale"
                            class="bcf__input"
                            placeholder="<?php esc_attr_e( 'Quy mô xây dựng', 'laca' ); ?>"
                            required
                        >
                    </div>

                    <!-- 3. Ngân sách (dropdown) -->
                    <div class="bcf__field">
                        <select name="tf_budget" class="bcf__select" required>
                            <option value=""><?php esc_html_e( 'Ngân sách', 'laca' ); ?></option>
                            <?php foreach ( $budget_options as $opt ) :
                                if ( empty( $opt ) ) continue; ?>
                                <option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- 4. Họ tên -->
                    <div class="bcf__field">
                        <input
                            type="text"
                            name="tf_name"
                            class="bcf__input"
                            placeholder="<?php esc_attr_e( 'Họ và tên', 'laca' ); ?>"
                            required
                        >
                    </div>

                    <!-- 5. Số điện thoại -->
                    <div class="bcf__field">
                        <input
                            type="tel"
                            name="tf_phone"
                            class="bcf__input"
                            placeholder="<?php esc_attr_e( 'Số điện thoại liên hệ', 'laca' ); ?>"
                            required
                        >
                    </div>

                    <!-- Submit -->
                    <div class="bcf__submit-wrap">
                        <button type="submit" class="bcf__btn">
                            <span class="bcf__btn-text"><?php echo $button_text; ?></span>
                            <span class="bcf__btn-loader" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.42" stroke-dashoffset="10"/></svg>
                            </span>
                        </button>
                    </div>

                    <div class="bcf__msg" role="alert" hidden></div>
                </form>
            </div>

            <!-- ── Right: Image ── -->
            <div class="bcf__right">
                <?php if ( $image_url ) : ?>
                <div class="bcf__img-wrap">
                    <img
                        src="<?php echo $image_url; ?>"
                        alt="<?php echo $image_alt ?: esc_attr( get_bloginfo( 'name' ) ); ?>"
                        class="bcf__img"
                        loading="lazy"
                    >
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /.bcf__inner -->
    </div>
</section>

<?php
// ── Inline JS for form submission ─────────────────────────────────────────
$js = <<<'JS'
(function(){
    document.querySelectorAll('[data-bcf-form]').forEach(function(form){
        if(form.dataset.bcfBound) return;
        form.dataset.bcfBound='1';
        form.addEventListener('submit',function(e){
            e.preventDefault();
            var btn=form.querySelector('.bcf__btn');
            var msg=form.querySelector('.bcf__msg');
            btn.classList.add('bcf__btn--loading');
            btn.disabled=true;
            var fd=new FormData(form);
            fetch(form.action,{method:'POST',body:fd,credentials:'same-origin'})
            .then(function(r){return r.json()})
            .then(function(d){
                msg.removeAttribute('hidden');
                if(d.success){
                    msg.textContent=d.data||'Gửi thành công!';
                    msg.className='bcf__msg bcf__msg--ok';
                    form.reset();
                } else {
                    msg.textContent=d.data||'Có lỗi, vui lòng thử lại.';
                    msg.className='bcf__msg bcf__msg--err';
                }
            })
            .catch(function(){
                msg.removeAttribute('hidden');
                msg.textContent='Lỗi kết nối.';
                msg.className='bcf__msg bcf__msg--err';
            })
            .finally(function(){
                btn.classList.remove('bcf__btn--loading');
                btn.disabled=false;
            });
        });
    });
})();
JS;
wp_add_inline_script( 'jquery', $js );
