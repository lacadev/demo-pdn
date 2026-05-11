<?php

namespace App\Features\ContactForm;

use App\Databases\ContactFormTable;

/**
 * ContactFormAjaxHandler
 *
 * Xử lý frontend AJAX submission và đăng ký shortcode.
 *
 * Shortcode: [laca_contact_form id="X"]
 *   → Render HTML form và JS validation (Pristine.js)
 *
 * AJAX endpoint: wp_ajax_nopriv_laca_contact_submit (cả logged-in lẫn guest)
 *   → Validate → Lưu DB → Gửi email → Trả JSON
 */
class ContactFormAjaxHandler
{
    public function init(): void
    {
        add_action('wp_ajax_laca_contact_submit',        [$this, 'handleSubmit']);
        add_action('wp_ajax_nopriv_laca_contact_submit', [$this, 'handleSubmit']);
        add_shortcode('laca_contact_form', [$this, 'renderShortcode']);
    }

    // =========================================================================
    // AJAX SUBMIT HANDLER
    // =========================================================================

    public function handleSubmit(): void
    {
        // 1. Nonce check
        if (!check_ajax_referer('laca_contact_submit_nonce', '_nonce', false)) {
            wp_send_json_error(['message' => 'Phiên làm việc hết hạn. Vui lòng tải lại trang.'], 403);
        }

        // 2. Form ID
        $formId = absint($_POST['form_id'] ?? 0);
        if (!$formId) {
            wp_send_json_error(['message' => 'Form không hợp lệ.'], 400);
        }

        $form = ContactFormTable::getForm($formId);
        if (!$form) {
            wp_send_json_error(['message' => 'Form không tồn tại.'], 404);
        }

        $fields = self::extractFlatFields($form);

        // 3. Validate & Sanitize từng field
        $data   = [];
        $errors = [];

        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'step_break') {
                continue;
            }

            if (!self::isFieldConditionMatched($field, $_POST)) {
                continue;
            }

            $name     = $field['name'];
            $label    = $field['label'];
            $required = !empty($field['required']);
            $type     = $field['type'];

            // Lấy giá trị raw từ POST
            $rawValue = $_POST[$name] ?? '';

            // Multiselect / checkbox gửi dạng array
            if (in_array($type, ['multiselect', 'checkbox'], true)) {
                $rawValue = is_array($rawValue) ? $rawValue : [];
            }

            // Validate required
            if ($required) {
                $isEmpty = is_array($rawValue) ? empty($rawValue) : (trim((string) $rawValue) === '');
                if ($isEmpty) {
                    $errors[] = $label . ' là bắt buộc.';
                    continue;
                }
            }

            // Sanitize theo type
            $cleanValue = self::sanitizeByType($type, $rawValue, $field);

            // Validate format
            $formatError = self::validateFormat($type, $cleanValue, $label);
            if ($formatError) {
                $errors[] = $formatError;
                continue;
            }

            $data[$name] = $cleanValue;
        }

        if (!empty($errors)) {
            wp_send_json_error(['message' => implode('<br>', $errors), 'errors' => $errors], 422);
        }

        // 3.5. Verify reCAPTCHA
        $isRecaptchaEnabled = function_exists('getOption') ? getOption('enable_recaptcha_contact') : false;
        if ($isRecaptchaEnabled) {
            $token  = $_POST['laca_recaptcha_response'] ?? '';
            $verify = apply_filters('laca_verify_recaptcha', true, $token);
            if (is_wp_error($verify)) {
                wp_send_json_error(['message' => $verify->get_error_message()], 400);
            }
        }

        // 4. Lấy IP
        $ip = self::getClientIp();

        // 5. Lưu DB
        ContactFormTable::insertSubmission($formId, $data, $ip);

        // 6. Gửi email
        ContactFormEmailService::sendAll($form, $data, $ip);

        wp_send_json_success(['message' => 'Gửi thành công! Chúng tôi sẽ liên hệ lại sớm.']);
    }

    // =========================================================================
    // SHORTCODE RENDERER
    // =========================================================================

    public function renderShortcode(array $atts): string
    {
        $atts   = shortcode_atts(['id' => 0, 'class' => ''], $atts, 'laca_contact_form');
        $formId = absint($atts['id']);

        if (!$formId) {
            return '<p class="laca-cf-error">Thiếu ID form. Dùng: [laca_contact_form id="X"]</p>';
        }

        $form = ContactFormTable::getForm($formId);
        if (!$form || !$form['is_active']) {
            return '<p class="laca-cf-error">Form không tồn tại hoặc đã bị tắt.</p>';
        }

        $rawData    = json_decode($form['fields'] ?? '[]', true) ?: [];
        $isRowBased = !empty($rawData) && isset($rawData[0]['cols']);
        $nonce      = wp_create_nonce('laca_contact_submit_nonce');
        $ajaxUrl    = admin_url('admin-ajax.php');
        $extraClass = sanitize_html_class($atts['class']);
        $formElId   = 'laca-cf-form-' . $formId;
        $wrapId     = 'laca-cf-' . $formId;

        // Build scoped CSS vars từ style_settings
        $styleSettings = json_decode($form['style_settings'] ?? '{}', true) ?: [];
        $scopedCss     = self::buildScopedCss($wrapId, $styleSettings);

        // Enqueue inline CSS once
        if (!wp_style_is('laca-contact-form', 'done')) {
            add_action('wp_footer', [__CLASS__, 'printInlineCss'], 5);
        }

        if (self::shouldRenderMultiStep($rawData, $styleSettings)) {
            return $this->renderMultiStepForm(
                $rawData,
                $formId,
                $nonce,
                $ajaxUrl,
                $extraClass,
                $formElId,
                $wrapId,
                $styleSettings,
                $scopedCss
            );
        }

        ob_start();
        ?>
        <?php if ($scopedCss): ?>
        <style><?php echo $scopedCss; // Đã sanitize qua esc_attr trên từng giá trị ?></style>
        <?php endif; ?>
        <div class="laca-contact-form-wrap <?php echo esc_attr($extraClass); ?>" id="<?php echo esc_attr($wrapId); ?>">
            <form class="laca-contact-form" id="<?php echo esc_attr($formElId); ?>" novalidate>
                <input type="hidden" name="_nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($formId); ?>">
                <input type="hidden" name="action" value="laca_contact_submit">
                <?php if (function_exists('getOption') && getOption('enable_recaptcha_contact')): ?>
                    <input type="hidden" name="laca_recaptcha_response" class="laca-recaptcha-response" value="">
                <?php endif; ?>

                <?php if ($isRowBased): ?>
                    <?php foreach ($rawData as $row): ?>
                        <?php
                        // Skip rows that have no fields at all
                        $hasAnyField = false;
                        foreach ($row['cols'] as $col) {
                            if (!empty($col['fields'])) { $hasAnyField = true; break; }
                        }
                        if (!$hasAnyField) continue;

                        // Build CSS grid-template-columns from col spans
                        $gridCols = implode(' ', array_map(
                            fn($c) => $c['span'] . 'fr',
                            $row['cols']
                        ));
                        ?>
                        <div class="laca-cf-layout-row" style="display:grid;grid-template-columns:<?php echo esc_attr($gridCols); ?>;gap:12px;align-items:start">
                            <?php foreach ($row['cols'] as $col): ?>
                                <?php if (!empty($col['fields'])): ?>
                                    <div class="laca-cf-col-group" style="display:flex;flex-direction:column;gap:12px">
                                        <?php foreach ($col['fields'] as $field): ?>
                                            <?php $this->renderField($field); ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($rawData as $field): ?>
                        <?php $this->renderField($field); ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="laca-cf-form-row laca-cf-submit-row">
                    <button type="submit" class="laca-cf-submit-btn" aria-busy="false">
                        <span class="laca-cf-btn-text">Gửi thông tin</span>
                        <span class="laca-cf-btn-loading" hidden aria-hidden="true">
                            <svg class="laca-cf-spinner" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="31.4"/>
                            </svg>
                            Đang gửi...
                        </span>
                    </button>
                </div>
                <p class="laca-cf-fallback-msg" role="status" aria-live="polite" hidden></p>
            </form>
        </div>

        <script<?php echo self::getCspNonceAttribute(); ?>>
        (function() {
            const FORM_ID  = '<?php echo esc_js($formElId); ?>';
            const AJAX_URL = '<?php echo esc_js($ajaxUrl); ?>';

            // Wait for DOM + theme.js to expose window.Swal
            function boot() {
                const formEl = document.getElementById(FORM_ID);
                if (!formEl) return;

                // ── Helpers ──────────────────────────────────────────────────

                const getThemeColors = () => ({
                    background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                    color:      document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff'    : '#000',
                });

                const showSwal = (opts) => {
                    if (typeof window.Swal !== 'undefined') {
                        window.Swal.fire({ ...opts, ...getThemeColors() });
                    } else {
                        // Fallback khi Swal chưa load: dùng banner trong form, không dùng native alert.
                        const banner = formEl.querySelector('.laca-cf-fallback-msg');
                        if (banner) {
                            const type = opts.icon === 'success' ? 'success' : 'error';
                            banner.className = 'laca-cf-fallback-msg laca-cf-fallback-msg--' + type;
                            banner.textContent = opts.text || opts.title || 'Đã có lỗi xảy ra. Vui lòng thử lại.';
                            banner.hidden = false;
                            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }
                };

                // Show inline error dưới field
                const showFieldError = (fieldEl, message) => {
                    if (!fieldEl) return;
                    fieldEl.classList.add('laca-cf-field-invalid');
                    fieldEl.setAttribute('aria-invalid', 'true');
                    const row = fieldEl.closest('.laca-cf-form-row');
                    const errEl = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (errEl) { errEl.textContent = message; errEl.hidden = false; }
                };

                const clearFieldError = (fieldEl) => {
                    if (!fieldEl) return;
                    fieldEl.classList.remove('laca-cf-field-invalid');
                    fieldEl.setAttribute('aria-invalid', 'false');
                    const row = fieldEl.closest('.laca-cf-form-row');
                    const errEl = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (errEl) { errEl.textContent = ''; errEl.hidden = true; }
                };

                const clearAllErrors = () => {
                    formEl.querySelectorAll('.laca-cf-field-invalid').forEach(clearFieldError);
                };

                const getFieldValue = (name) => {
                    const fields = Array.from(formEl.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]'));
                    if (!fields.length) return '';
                    if (fields[0].type === 'radio') {
                        const checked = fields.find((field) => field.checked);
                        return checked ? checked.value : '';
                    }
                    if (fields[0].type === 'checkbox') {
                        return fields.filter((field) => field.checked).map((field) => field.value);
                    }
                    if (fields[0].tagName === 'SELECT' && fields[0].multiple) {
                        return Array.from(fields[0].selectedOptions).map((option) => option.value);
                    }
                    return fields[0].value || '';
                };

                const conditionMatches = (row) => {
                    const field = row.dataset.conditionField;
                    if (!field) return true;
                    const operator = row.dataset.conditionOperator || 'equals';
                    const expected = row.dataset.conditionValue || '';
                    const value = getFieldValue(field);
                    const values = Array.isArray(value) ? value : [value];
                    const valueString = values.join(', ');

                    switch (operator) {
                        case 'not_equals':
                            return !values.includes(expected);
                        case 'contains':
                            return expected !== '' && valueString.includes(expected);
                        case 'not_empty':
                            return valueString.trim() !== '';
                        case 'empty':
                            return valueString.trim() === '';
                        default:
                            return values.includes(expected);
                    }
                };

                const syncConditionalFields = () => {
                    formEl.querySelectorAll('.laca-cf-form-row[data-condition-field]').forEach(function(row) {
                        const visible = conditionMatches(row);
                        row.classList.toggle('laca-cf-conditional-hidden', !visible);
                        row.hidden = !visible;
                        row.querySelectorAll('input, select, textarea').forEach(function(input) {
                            input.disabled = !visible;
                            if (!visible) {
                                clearFieldError(input);
                            }
                        });
                    });
                };

                // ── Client-side validation ────────────────────────────────────

                const isPhoneValid = (value) => {
                    const normalized = String(value || '').trim();
                    const digits = normalized.replace(/\D/g, '');
                    return /^\+?[0-9\s().-]+$/.test(normalized) && digits.length >= 8 && digits.length <= 15;
                };

                const getFieldLabel = (row) => {
                    const label = row ? row.querySelector('.laca-cf-label') : null;
                    return (label && label.textContent ? label.textContent.replace('*', '').trim() : '') || 'Trường này';
                };

                const getFormatError = (input, row) => {
                    if (!input || input.disabled || input.type === 'hidden') return '';
                    const value = String(input.value || '').trim();
                    if (!value) return '';
                    const label = getFieldLabel(row);

                    if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        return label + ' không hợp lệ.';
                    }
                    if (input.type === 'url' && input.validity && !input.validity.valid) {
                        return label + ' không hợp lệ.';
                    }
                    if (input.type === 'tel' && !isPhoneValid(value)) {
                        return label + ' không hợp lệ. Vui lòng nhập tối thiểu 8 chữ số.';
                    }
                    if (input.type === 'number' && (input.validity?.badInput || Number.isNaN(Number(value)))) {
                        return label + ' phải là số hợp lệ.';
                    }

                    return '';
                };

                const isControlEmpty = (control) => {
                    if (!control) return true;
                    if (control.type === 'checkbox' || control.type === 'radio') {
                        const row = control.closest('.laca-cf-form-row');
                        return !row || !row.querySelector('input:checked');
                    }
                    if (control.tagName === 'SELECT' && control.multiple) {
                        return control.selectedOptions.length === 0;
                    }
                    return !String(control.value || '').trim();
                };

                const validateForm = () => {
                    syncConditionalFields();
                    clearAllErrors();
                    let valid = true;
                    let firstInvalid = null;

                    formEl.querySelectorAll('.laca-cf-form-row').forEach(function(row) {
                        if (!row || row.hidden || row.classList.contains('laca-cf-conditional-hidden')) {
                            return;
                        }

                        const controls = Array.from(row.querySelectorAll('input, select, textarea')).filter(function(input) {
                            return !input.disabled && input.type !== 'hidden';
                        });
                        if (!controls.length) {
                            return;
                        }

                        const firstControl = controls[0];
                        const isRequired = !!row.querySelector('[data-required="true"], [required]');
                        if (isRequired && isControlEmpty(firstControl)) {
                            showFieldError(firstControl, getFieldLabel(row) + ' là bắt buộc.');
                            if (!firstInvalid) firstInvalid = firstControl;
                            valid = false;
                        }

                        controls.forEach(function(input) {
                            const message = getFormatError(input, row);
                            if (!message) return;
                            showFieldError(input, message);
                            if (!firstInvalid) firstInvalid = input;
                            valid = false;
                        });
                    });

                    if (firstInvalid && firstInvalid.focus) {
                        firstInvalid.focus({ preventScroll: true });
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }

                    return valid;
                };

                // ── Real-time clear errors on input ───────────────────────────

                formEl.querySelectorAll('input, select, textarea').forEach(function(el) {
                    el.addEventListener('input', function() {
                        clearFieldError(el);
                        syncConditionalFields();
                    });
                    el.addEventListener('change', syncConditionalFields);
                    el.addEventListener('blur', function() {
                        if (el.getAttribute('data-required') === 'true' && !el.value.trim()) {
                            showFieldError(el, 'Trường này là bắt buộc.');
                        } else {
                            clearFieldError(el);
                        }
                    });
                });

                syncConditionalFields();

                // ── Submit handler ────────────────────────────────────────────

                formEl.addEventListener('submit', function(e) {
                    e.preventDefault();

                    if (!validateForm()) return;

                    const btn     = formEl.querySelector('.laca-cf-submit-btn');
                    const btnText = btn.querySelector('.laca-cf-btn-text');
                    const btnLoad = btn.querySelector('.laca-cf-btn-loading');

                    // Loading state
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                    btnText.hidden = true;
                    btnLoad.hidden = false;

                    fetch(AJAX_URL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: new FormData(formEl),
                    })
                    .then(function(res) { return res.json(); })
                    .then(function(json) {
                        if (json.success) {
                            showSwal({
                                title: '✓ Thành công!',
                                text: json.data.message || 'Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi sớm nhất!',
                                icon: 'success',
                                confirmButtonText: 'Đóng',
                            });
                            formEl.reset();
                            clearAllErrors();
                        } else {
                            const msg = (json.data && json.data.message)
                                ? json.data.message
                                : 'Đã có lỗi xảy ra. Vui lòng thử lại.';
                            showSwal({
                                title: '✕ Thất bại',
                                html: '<p>' + msg + '</p>',
                                icon: 'error',
                                confirmButtonText: 'Thử lại',
                            });
                        }
                    })
                    .catch(function() {
                        showSwal({
                            title: '✕ Lỗi kết nối',
                            text: 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra kết nối internet.',
                            icon: 'error',
                            confirmButtonText: 'Đã hiểu',
                        });
                    })
                    .finally(function() {
                        btn.disabled = false;
                        btn.setAttribute('aria-busy', 'false');
                        btnText.hidden = false;
                        btnLoad.hidden = true;
                    });
                });
            }

            // Boot sau khi DOM ready — Swal sẽ available vì theme.js chạy trước
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    private function renderMultiStepForm(
        array $rawData,
        int $formId,
        string $nonce,
        string $ajaxUrl,
        string $extraClass,
        string $formElId,
        string $wrapId,
        array $styleSettings,
        string $scopedCss
    ): string {
        $steps = self::splitRowsIntoSteps($rawData);
        $totalSteps = max(1, count($steps));
        $nextText = $styleSettings['step_next_text'] ?? 'Tiếp theo';
        $prevText = $styleSettings['step_prev_text'] ?? 'Quay lại';
        $submitText = $styleSettings['step_submit_text'] ?? ($styleSettings['btn_text'] ?? 'Gửi thông tin');

        ob_start();
        ?>
        <?php if ($scopedCss): ?>
        <style><?php echo $scopedCss; ?></style>
        <?php endif; ?>
        <div class="laca-contact-form-wrap laca-contact-form-wrap--multistep <?php echo esc_attr($extraClass); ?>" id="<?php echo esc_attr($wrapId); ?>">
            <form class="laca-contact-form laca-contact-form--multistep" id="<?php echo esc_attr($formElId); ?>" novalidate data-total-steps="<?php echo esc_attr($totalSteps); ?>">
                <input type="hidden" name="_nonce" value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($formId); ?>">
                <input type="hidden" name="action" value="laca_contact_submit">
                <?php if (function_exists('getOption') && getOption('enable_recaptcha_contact')): ?>
                    <input type="hidden" name="laca_recaptcha_response" class="laca-recaptcha-response" value="">
                <?php endif; ?>

                <div class="laca-cf-step-progress" role="progressbar" aria-valuemin="1" aria-valuemax="<?php echo esc_attr($totalSteps); ?>" aria-valuenow="1">
                    <div class="laca-cf-step-progress__track">
                        <div class="laca-cf-step-progress__fill" style="width:<?php echo esc_attr((string) round(100 / $totalSteps)); ?>%"></div>
                    </div>
                    <ol class="laca-cf-step-list">
                        <?php foreach ($steps as $index => $step): ?>
                            <li class="laca-cf-step-dot <?php echo $index === 0 ? 'is-active' : ''; ?>" data-step-dot="<?php echo esc_attr((string) $index); ?>">
                                <span><?php echo esc_html((string) ($index + 1)); ?></span>
                                <strong><?php echo esc_html($step['label']); ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <p class="laca-cf-step-notice" role="alert" aria-live="polite" hidden></p>

                <?php foreach ($steps as $index => $step): ?>
                    <section class="laca-cf-step-panel <?php echo $index === 0 ? 'is-active' : ''; ?>" data-step-panel="<?php echo esc_attr((string) $index); ?>" <?php echo $index === 0 ? '' : 'hidden'; ?>>
                        <?php foreach ($step['rows'] as $row): ?>
                            <?php $this->renderLayoutRow($row); ?>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>

                <div class="laca-cf-step-actions">
                    <button type="button" class="laca-cf-step-btn laca-cf-step-btn--prev" hidden><?php echo esc_html($prevText); ?></button>
                    <button type="button" class="laca-cf-step-btn laca-cf-step-btn--next"><?php echo esc_html($nextText); ?></button>
                    <button type="submit" class="laca-cf-submit-btn laca-cf-step-btn--submit" aria-busy="false" hidden>
                        <span class="laca-cf-btn-text"><?php echo esc_html($submitText); ?></span>
                        <span class="laca-cf-btn-loading" hidden aria-hidden="true">
                            <svg class="laca-cf-spinner" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="31.4"/>
                            </svg>
                            Đang gửi...
                        </span>
                    </button>
                </div>
                <p class="laca-cf-fallback-msg" role="status" aria-live="polite" hidden></p>
            </form>
        </div>
        <?php $this->printMultiStepScript($formElId, $ajaxUrl); ?>
        <?php
        return ob_get_clean();
    }

    private function renderLayoutRow(array $row): void
    {
        $cols = $row['cols'] ?? [];
        $hasAnyField = false;
        foreach ($cols as $col) {
            foreach ($col['fields'] ?? [] as $field) {
                if (($field['type'] ?? '') !== 'step_break') {
                    $hasAnyField = true;
                    break 2;
                }
            }
        }

        if (!$hasAnyField) {
            return;
        }

        $gridCols = implode(' ', array_map(
            fn($c) => ((int) ($c['span'] ?? 12)) . 'fr',
            $cols
        ));
        ?>
        <div class="laca-cf-layout-row" style="display:grid;grid-template-columns:<?php echo esc_attr($gridCols); ?>;gap:12px;align-items:start">
            <?php foreach ($cols as $col): ?>
                <?php if (!empty($col['fields'])): ?>
                    <div class="laca-cf-col-group" style="display:flex;flex-direction:column;gap:12px">
                        <?php foreach ($col['fields'] as $field): ?>
                            <?php $this->renderField($field); ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    // =========================================================================
    // RENDER FIELD HELPERS
    // =========================================================================

    public static function renderSingleField(array $field): string
    {
        ob_start();
        (new self())->renderField($field);
        return ob_get_clean();
    }

    private function renderField(array $field): void
    {
        if (($field['type'] ?? '') === 'step_break') {
            return;
        }

        $name        = esc_attr($field['name']);
        $label       = esc_html($field['label']);
        $placeholder = esc_attr($field['placeholder'] ?? '');
        $required    = !empty($field['required']);
        $type        = $field['type'];
        $rawCol      = $field['col_width'] ?? '12';
        $colWidth    = in_array($rawCol, ['12','6','4','3'], true) ? $rawCol : '12';
        $reqAttr     = $required ? 'required data-required="true"' : 'data-required="false"';
        $reqMark     = $required ? ' <span class="laca-cf-required" aria-hidden="true">*</span>' : '';
        $fieldId     = 'laca-cf-field-' . esc_attr($name) . '-' . uniqid('', true);
        $conditionAttrs = self::buildConditionAttributes($field);
        ?>
        <div class="laca-cf-form-row laca-cf-type-<?php echo esc_attr($type); ?> laca-cf-col-<?php echo esc_attr($colWidth); ?>"<?php echo $conditionAttrs; ?>>
            <?php if ($type !== 'hidden'): ?>
                <label for="<?php echo esc_attr($fieldId); ?>" class="laca-cf-label">
                    <?php echo $label . $reqMark; ?>
                </label>
            <?php endif; ?>

            <?php
            switch ($type) {
                case 'textarea':
                    echo '<textarea id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-textarea" placeholder="' . $placeholder . '" rows="4" ' . $reqAttr . '></textarea>';
                    break;

                case 'select':
                    $options = $field['options'] ?? [];
                    echo '<select id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-select" ' . $reqAttr . '>';
                    echo '<option value="">— Chọn ' . $label . ' —</option>';
                    foreach ($options as $opt) {
                        echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                    }
                    echo '</select>';
                    break;

                case 'multiselect':
                    $options = $field['options'] ?? [];
                    echo '<select id="' . esc_attr($fieldId) . '" name="' . $name . '[]" class="laca-cf-select laca-cf-multiselect" multiple size="4" ' . $reqAttr . '>';
                    foreach ($options as $opt) {
                        echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                    }
                    echo '</select>';
                    echo '<p class="laca-cf-hint">Giữ Ctrl / Cmd để chọn nhiều.</p>';
                    break;

                case 'radio':
                    $options = $field['options'] ?? [];
                    echo '<div class="laca-cf-radio-group" id="' . esc_attr($fieldId) . '" ' . $reqAttr . '>';
                    foreach ($options as $idx => $opt) {
                        $optId = esc_attr($fieldId . '-' . $idx);
                        echo '<label class="laca-cf-radio-label"><input type="radio" id="' . $optId . '" name="' . $name . '" value="' . esc_attr($opt) . '"> ' . esc_html($opt) . '</label>';
                    }
                    echo '</div>';
                    break;

                case 'checkbox':
                    $options = $field['options'] ?? [];
                    if (count($options) <= 1) {
                        // Single checkbox
                        $singleOpt = $options[0] ?? 'yes';
                        echo '<label class="laca-cf-checkbox-label"><input type="checkbox" id="' . esc_attr($fieldId) . '" name="' . $name . '" value="' . esc_attr($singleOpt) . '" ' . $reqAttr . '> ' . esc_html($singleOpt) . '</label>';
                    } else {
                        // Multiple checkboxes
                        echo '<div class="laca-cf-checkbox-group" id="' . esc_attr($fieldId) . '">';
                        foreach ($options as $idx => $opt) {
                            $optId = esc_attr($fieldId . '-' . $idx);
                            echo '<label class="laca-cf-checkbox-label"><input type="checkbox" id="' . $optId . '" name="' . $name . '[]" value="' . esc_attr($opt) . '" data-required="' . ($required ? 'true' : 'false') . '"> ' . esc_html($opt) . '</label>';
                        }
                        echo '</div>';
                    }
                    break;

                case 'date':
                    echo '<input type="date" id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-input" ' . $reqAttr . '>';
                    break;

                case 'datetime':
                    echo '<input type="datetime-local" id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-input" ' . $reqAttr . '>';
                    break;

                case 'hidden':
                    echo '<input type="hidden" name="' . $name . '" value="' . $placeholder . '">';
                    break;

                default:
                    // text, email, phone, number, url
                    $inputType = match ($type) {
                        'email'  => 'email',
                        'phone'  => 'tel',
                        'number' => 'number',
                        'url'    => 'url',
                        default  => 'text',
                    };
                    $autocomplete = match ($type) {
                        'email' => 'email',
                        'phone' => 'tel',
                        'text'  => 'on',
                        default => 'off',
                    };
                    $extraAttrs = match ($type) {
                        'phone' => ' inputmode="tel" pattern="\\+?[0-9\\s().-]{8,24}" minlength="8" maxlength="24"',
                        'number' => ' inputmode="decimal"',
                        default => '',
                    };
                    echo '<input type="' . esc_attr($inputType) . '" id="' . esc_attr($fieldId) . '" name="' . $name . '" class="laca-cf-input" placeholder="' . $placeholder . '" autocomplete="' . esc_attr($autocomplete) . '" ' . $reqAttr . $extraAttrs . '>';
            }
            ?>
            <span class="laca-cf-field-error" hidden aria-live="polite"></span>
        </div>
        <?php
    }

    // =========================================================================
    // INLINE CSS
    // =========================================================================

    private function printMultiStepScript(string $formElId, string $ajaxUrl): void
    {
        ?>
        <script<?php echo self::getCspNonceAttribute(); ?>>
        (function() {
            const SCRIPT_EL = document.currentScript;
            const FORM_ID = '<?php echo esc_js($formElId); ?>';
            const AJAX_URL = '<?php echo esc_js($ajaxUrl); ?>';

            function boot() {
                const scopedWrap = SCRIPT_EL ? SCRIPT_EL.previousElementSibling : null;
                const formEl = scopedWrap && scopedWrap.querySelector
                    ? scopedWrap.querySelector('#' + FORM_ID + '.laca-contact-form--multistep')
                    : document.getElementById(FORM_ID);
                if (!formEl) return;
                if (formEl.dataset.lacaMultiStepReady === '1') return;
                formEl.dataset.lacaMultiStepReady = '1';

                const panels = Array.from(formEl.querySelectorAll('.laca-cf-step-panel'));
                if (!panels.length) return;
                const btnPrev = formEl.querySelector('.laca-cf-step-btn--prev');
                const btnNext = formEl.querySelector('.laca-cf-step-btn--next');
                const btnSubmit = formEl.querySelector('.laca-cf-step-btn--submit');
                const notice = formEl.querySelector('.laca-cf-step-notice');
                const fill = formEl.querySelector('.laca-cf-step-progress__fill');
                const progress = formEl.querySelector('.laca-cf-step-progress');
                let current = 0;

                const getThemeColors = () => ({
                    background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a1a' : '#fff',
                    color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#fff' : '#000',
                });

                const showSwal = (opts) => {
                    if (typeof window.Swal !== 'undefined') {
                        window.Swal.fire({ ...opts, ...getThemeColors() });
                    } else {
                        const banner = formEl.querySelector('.laca-cf-fallback-msg');
                        if (banner) {
                            const type = opts.icon === 'success' ? 'success' : 'error';
                            banner.className = 'laca-cf-fallback-msg laca-cf-fallback-msg--' + type;
                            banner.textContent = opts.text || opts.title || 'Đã có lỗi xảy ra. Vui lòng thử lại.';
                            banner.hidden = false;
                            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }
                };

                const showNotice = (message) => {
                    if (!notice) return;
                    notice.textContent = message;
                    notice.hidden = false;
                    notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                };

                const clearNotice = () => {
                    if (!notice) return;
                    notice.textContent = '';
                    notice.hidden = true;
                };

                const getFieldValue = (name) => {
                    const fields = Array.from(formEl.querySelectorAll('[name="' + name + '"], [name="' + name + '[]"]'));
                    if (!fields.length) return '';
                    if (fields[0].type === 'radio') {
                        const checked = fields.find((field) => field.checked);
                        return checked ? checked.value : '';
                    }
                    if (fields[0].type === 'checkbox') {
                        return fields.filter((field) => field.checked).map((field) => field.value);
                    }
                    if (fields[0].tagName === 'SELECT' && fields[0].multiple) {
                        return Array.from(fields[0].selectedOptions).map((option) => option.value);
                    }
                    return fields[0].value || '';
                };

                const conditionMatches = (row) => {
                    const field = row.dataset.conditionField;
                    if (!field) return true;
                    const operator = row.dataset.conditionOperator || 'equals';
                    const expected = row.dataset.conditionValue || '';
                    const value = getFieldValue(field);
                    const values = Array.isArray(value) ? value : [value];
                    const valueString = values.join(', ');

                    switch (operator) {
                        case 'not_equals':
                            return !values.includes(expected);
                        case 'contains':
                            return expected !== '' && valueString.includes(expected);
                        case 'not_empty':
                            return valueString.trim() !== '';
                        case 'empty':
                            return valueString.trim() === '';
                        default:
                            return values.includes(expected);
                    }
                };

                const syncConditionalFields = () => {
                    formEl.querySelectorAll('.laca-cf-form-row[data-condition-field]').forEach((row) => {
                        const visible = conditionMatches(row);
                        row.classList.toggle('laca-cf-conditional-hidden', !visible);
                        row.hidden = !visible;
                        row.querySelectorAll('input, select, textarea').forEach((input) => {
                            input.disabled = !visible;
                            if (!visible) {
                                input.classList.remove('laca-cf-field-invalid');
                                input.setAttribute('aria-invalid', 'false');
                                const err = row.querySelector('.laca-cf-field-error');
                                if (err) {
                                    err.textContent = '';
                                    err.hidden = true;
                                }
                            }
                        });
                    });
                };

                const showFieldError = (fieldEl, message) => {
                    if (!fieldEl) return;
                    fieldEl.classList.add('laca-cf-field-invalid');
                    fieldEl.setAttribute('aria-invalid', 'true');
                    const row = fieldEl.closest('.laca-cf-form-row');
                    const err = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (err) {
                        err.textContent = message;
                        err.hidden = false;
                    }
                };

                const clearFieldError = (fieldEl) => {
                    if (!fieldEl) return;
                    fieldEl.classList.remove('laca-cf-field-invalid');
                    fieldEl.setAttribute('aria-invalid', 'false');
                    const row = fieldEl.closest('.laca-cf-form-row');
                    const err = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (err) {
                        err.textContent = '';
                        err.hidden = true;
                    }
                };

                const isPhoneValid = (value) => {
                    const normalized = String(value || '').trim();
                    const digits = normalized.replace(/\D/g, '');
                    return /^\+?[0-9\s().-]+$/.test(normalized) && digits.length >= 8 && digits.length <= 15;
                };

                const getFieldLabel = (row) => {
                    const label = row ? row.querySelector('.laca-cf-label') : null;
                    return (label && label.textContent ? label.textContent.replace('*', '').trim() : '') || 'Trường này';
                };

                const getFormatError = (input, row) => {
                    if (!input || input.disabled || input.type === 'hidden') return '';
                    const value = String(input.value || '').trim();
                    if (!value) return '';
                    const label = getFieldLabel(row);

                    if (input.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                        return label + ' không hợp lệ.';
                    }
                    if (input.type === 'url' && input.validity && !input.validity.valid) {
                        return label + ' không hợp lệ.';
                    }
                    if (input.type === 'tel' && !isPhoneValid(value)) {
                        return label + ' không hợp lệ. Vui lòng nhập tối thiểu 8 chữ số.';
                    }
                    if (input.type === 'number' && (input.validity?.badInput || Number.isNaN(Number(value)))) {
                        return label + ' phải là số hợp lệ.';
                    }

                    return '';
                };

                const isControlEmpty = (control) => {
                    if (!control) return true;
                    if (control.type === 'checkbox' || control.type === 'radio') {
                        const row = control.closest('.laca-cf-form-row');
                        return !row || !row.querySelector('input:checked');
                    }
                    if (control.tagName === 'SELECT' && control.multiple) {
                        return control.selectedOptions.length === 0;
                    }
                    return !String(control.value || '').trim();
                };

                const validatePanel = (panel) => {
                    syncConditionalFields();
                    let valid = true;
                    let firstInvalid = null;

                    panel.querySelectorAll('.laca-cf-field-error').forEach((err) => {
                        err.textContent = '';
                        err.hidden = true;
                    });
                    panel.querySelectorAll('.laca-cf-field-invalid').forEach(clearFieldError);

                    panel.querySelectorAll('.laca-cf-form-row').forEach((row) => {
                        if (!row || row.hidden || row.classList.contains('laca-cf-conditional-hidden')) {
                            return;
                        }

                        const controls = Array.from(row.querySelectorAll('input, select, textarea')).filter((input) => {
                            return !input.disabled && input.type !== 'hidden';
                        });
                        if (!controls.length) {
                            return;
                        }

                        const firstControl = controls[0];
                        const isRequired = !!row.querySelector('[data-required="true"], [required]');
                        if (isRequired && isControlEmpty(firstControl)) {
                            showFieldError(firstControl, getFieldLabel(row) + ' là bắt buộc.');
                            if (!firstInvalid) {
                                firstInvalid = firstControl;
                            }
                            valid = false;
                        }

                        controls.forEach((input) => {
                            const message = getFormatError(input, row);
                            if (!message) return;
                            showFieldError(input, message);
                            if (!firstInvalid) {
                                firstInvalid = input;
                            }
                            valid = false;
                        });
                    });

                    if (firstInvalid) {
                        firstInvalid.focus({ preventScroll: true });
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }

                    return valid;
                };

                const showStep = (step) => {
                    panels.forEach((panel, index) => {
                        const active = index === step;
                        panel.hidden = !active;
                        panel.classList.toggle('is-active', active);
                    });

                    btnPrev && (btnPrev.hidden = step === 0);
                    btnNext && (btnNext.hidden = step >= panels.length - 1);
                    btnSubmit && (btnSubmit.hidden = step < panels.length - 1);

                    const pct = Math.round(((step + 1) / panels.length) * 100);
                    if (fill) fill.style.width = pct + '%';
                    if (progress) progress.setAttribute('aria-valuenow', String(step + 1));
                    formEl.querySelectorAll('.laca-cf-step-dot').forEach((dot, index) => {
                        dot.classList.toggle('is-active', index === step);
                        dot.classList.toggle('is-done', index < step);
                    });

                    clearNotice();
                    syncConditionalFields();

                    const first = panels[step] ? panels[step].querySelector('input:not([type="hidden"]), select, textarea') : null;
                    if (first) first.focus({ preventScroll: true });
                };

                formEl.querySelectorAll('input, select, textarea').forEach((el) => {
                    el.addEventListener('input', () => {
                        clearFieldError(el);
                        syncConditionalFields();
                    });
                    el.addEventListener('change', syncConditionalFields);
                });

                const goNext = () => {
                    if (!validatePanel(panels[current])) {
                        showNotice('Vui lòng kiểm tra lại các trường được đánh dấu.');
                        return;
                    }
                    current = Math.min(panels.length - 1, current + 1);
                    showStep(current);
                };

                const goPrev = () => {
                    current = Math.max(0, current - 1);
                    showStep(current);
                };

                formEl.addEventListener('click', function(e) {
                    const nextButton = e.target.closest('.laca-cf-step-btn--next');
                    const prevButton = e.target.closest('.laca-cf-step-btn--prev');
                    if (nextButton && formEl.contains(nextButton)) {
                        e.preventDefault();
                        goNext();
                    }
                    if (prevButton && formEl.contains(prevButton)) {
                        e.preventDefault();
                        goPrev();
                    }
                });

                formEl.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!validatePanel(panels[current])) {
                        showNotice('Vui lòng kiểm tra lại các trường được đánh dấu.');
                        return;
                    }

                    const btnText = btnSubmit.querySelector('.laca-cf-btn-text');
                    const btnLoad = btnSubmit.querySelector('.laca-cf-btn-loading');
                    btnSubmit.disabled = true;
                    btnSubmit.setAttribute('aria-busy', 'true');
                    btnText.hidden = true;
                    btnLoad.hidden = false;

                    fetch(AJAX_URL, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: new FormData(formEl),
                    })
                    .then((res) => res.json())
                    .then((json) => {
                        if (json.success) {
                            showSwal({
                                title: 'Thành công',
                                text: json.data.message || 'Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi sớm nhất.',
                                icon: 'success',
                                confirmButtonText: 'Đóng',
                            });
                            formEl.reset();
                            current = 0;
                            showStep(current);
                        } else {
                            showSwal({
                                title: 'Thất bại',
                                html: '<p>' + ((json.data && json.data.message) ? json.data.message : 'Đã có lỗi xảy ra. Vui lòng thử lại.') + '</p>',
                                icon: 'error',
                                confirmButtonText: 'Thử lại',
                            });
                        }
                    })
                    .catch(function() {
                        showSwal({
                            title: 'Lỗi kết nối',
                            text: 'Không thể kết nối đến máy chủ. Vui lòng kiểm tra kết nối internet.',
                            icon: 'error',
                            confirmButtonText: 'Đã hiểu',
                        });
                    })
                    .finally(function() {
                        btnSubmit.disabled = false;
                        btnSubmit.setAttribute('aria-busy', 'false');
                        btnText.hidden = false;
                        btnLoad.hidden = true;
                    });
                });

                showStep(0);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', boot);
            } else {
                boot();
            }
        })();
        </script>
        <?php
    }

    public static function printInlineCss(): void
    {
        ?>
        <style id="laca-contact-form-css">
        .laca-contact-form-wrap { max-width: 700px; }
        /* New row-based layout: flex column of layout rows */
        .laca-contact-form { display: flex; flex-direction: column; gap: 16px; align-items: stretch; }
        /* Each layout row uses CSS grid (inline style sets grid-template-columns) */
        .laca-cf-layout-row { align-items: start; }
        /* Mobile: force single column */
        @media (max-width: 640px) {
            .laca-cf-layout-row { grid-template-columns: 1fr !important; }
        }
        /* Old flat-format fields (fallback) */
        .laca-cf-col-12  { grid-column: span 12; }
        .laca-cf-col-6   { grid-column: span 6; }
        .laca-cf-col-4   { grid-column: span 4; }
        .laca-cf-col-3   { grid-column: span 3; }
        .laca-cf-form-row { display: flex; flex-direction: column; gap: 5px; }
        .laca-cf-label { font-weight: 600; font-size: 14px; }
        .laca-cf-required { color: #d9534f; margin-left: 2px; }
        .laca-cf-input,
        .laca-cf-textarea,
        .laca-cf-select {
            width: 100%; padding: 10px 14px; border: 1px solid #ccc;
            border-radius: 6px; font-size: 14px; font-family: inherit;
            transition: border-color 0.2s, box-shadow 0.2s; box-sizing: border-box;
        }
        .laca-cf-input:focus,
        .laca-cf-textarea:focus,
        .laca-cf-select:focus {
            outline: none;
            border-color: var(--cf-primary, var(--primary-color, #2271b1));
            box-shadow: 0 0 0 3px rgba(34,113,177,.15);
        }
        .laca-cf-label { color: var(--cf-label-color, inherit); display: var(--cf-label-display, block); }
        .laca-cf-input, .laca-cf-textarea, .laca-cf-select {
            border-color: var(--cf-input-border, #ccc) !important;
            border-radius: var(--cf-input-radius, 6px) !important;
            padding: var(--cf-input-spacing, 10px 14px) !important;
        }
        .laca-cf-field-invalid { border-color: #d9534f !important; box-shadow: 0 0 0 3px rgba(217,83,79,.15) !important; }
        .laca-cf-field-error { color: #d9534f; font-size: 12px; margin-top: 2px; }
        .laca-cf-conditional-hidden { display: none !important; }
        .laca-cf-radio-group, .laca-cf-checkbox-group { display: flex; flex-direction: column; gap: 8px; }
        .laca-cf-radio-label, .laca-cf-checkbox-label { display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 14px; }
        .laca-cf-multiselect { padding: 4px; }
        .laca-cf-hint { margin: 4px 0 0; font-size: 12px; color: #888; }
        .laca-contact-form-wrap--multistep { max-width: 760px; }
        .laca-contact-form--multistep { gap: 20px; }
        .laca-cf-step-progress { display: grid; gap: 14px; margin-bottom: 4px; }
        .laca-cf-step-progress__track { background: #e5e7eb; border-radius: 999px; height: 6px; overflow: hidden; }
        .laca-cf-step-progress__fill { background: var(--cf-primary, var(--primary-color, #2271b1)); border-radius: inherit; height: 100%; transition: width .2s ease; }
        .laca-cf-step-list { display: grid; gap: 10px; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); list-style: none; margin: 0; padding: 0; }
        .laca-cf-step-dot { align-items: center; color: #64748b; display: flex; font-size: 12px; font-weight: 600; gap: 8px; min-width: 0; }
        .laca-cf-step-dot span { align-items: center; background: #f8fafc; border: 1px solid #dbe3ef; border-radius: 999px; display: inline-flex; height: 24px; justify-content: center; width: 24px; }
        .laca-cf-step-dot strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .laca-cf-step-dot.is-active { color: var(--cf-primary, var(--primary-color, #2271b1)); }
        .laca-cf-step-dot.is-active span,
        .laca-cf-step-dot.is-done span { background: var(--cf-primary, var(--primary-color, #2271b1)); border-color: var(--cf-primary, var(--primary-color, #2271b1)); color: #fff; }
        .laca-cf-step-panel { animation: laca-cf-step-in .18s ease; display: flex; flex-direction: column; gap: 16px; }
        @keyframes laca-cf-step-in { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        .laca-cf-step-notice { background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; color: #991b1b; margin: 0; padding: 10px 12px; }
        .laca-cf-step-actions { align-items: center; display: flex; gap: 10px; justify-content: flex-end; }
        .laca-cf-step-btn { border-radius: var(--cf-btn-radius, 6px); cursor: pointer; font-size: 15px; font-weight: 600; padding: 11px 22px; }
        .laca-cf-step-btn--prev { background: #fff; border: 1px solid #d1d5db; color: #374151; margin-right: auto; }
        .laca-cf-step-btn--next { background: var(--cf-primary, var(--primary-color, #2271b1)); border: 0; color: #fff; }
        .laca-cf-step-btn--next:hover,
        .laca-cf-step-btn--submit:hover { background: var(--cf-secondary, var(--secondary-color, #1a5a9e)); }
        /* Submit row */
        .laca-cf-submit-row { flex-direction: row; align-items: center; justify-content: flex-end; }
        .laca-cf-submit-btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 11px 28px; background: var(--cf-primary, var(--primary-color, #2271b1));
            color: #fff; border: none; border-radius: var(--cf-btn-radius, 6px); font-size: 15px;
            font-weight: 600; cursor: pointer;
            transition: background 0.2s, transform 0.1s;
        }
        .laca-cf-submit-btn:hover  { background: var(--cf-secondary, var(--secondary-color, #1a5a9e)); }
        .laca-cf-submit-btn:active { transform: scale(0.98); }
        .laca-cf-submit-btn:disabled { opacity: 0.65; cursor: not-allowed; transform: none; }
        /* hidden attribute must not be overridden by display:flex */
        [hidden] { display: none !important; }
        .laca-cf-btn-loading { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; }
        /* Spinner */
        @keyframes laca-spin { to { stroke-dashoffset: -31.4; } }
        .laca-cf-spinner circle {
            animation: laca-spin 0.8s linear infinite;
            transform-origin: center;
        }
        /* Fallback message (no Swal) */
        .laca-cf-fallback-msg {
            margin-top: 12px; padding: 12px 14px; border-radius: 8px; font-size: 14px;
            border: 1px solid transparent;
        }
        .laca-cf-fallback-msg:not([hidden]) { display: block; }
        .laca-cf-fallback-msg--success { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .laca-cf-fallback-msg--error { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .laca-cf-error { color: #d9534f; font-style: italic; }
        </style>
        <?php
    }

    private static function getCspNonceAttribute(): string
    {
        return defined('LACA_CSP_NONCE') ? ' nonce="' . esc_attr(LACA_CSP_NONCE) . '"' : '';
    }

    // =========================================================================
    // DATA HELPERS
    // =========================================================================

    /**
     * Extract a flat list of field objects from a form row.
     * Handles both old flat format and new row-based format.
     */
    public static function extractFlatFields(array $form): array
    {
        $raw = json_decode($form['fields'] ?? '[]', true) ?: [];
        if (empty($raw)) {
            return [];
        }
        // Old flat format: first item has 'type' and no 'cols'
        if (isset($raw[0]['type']) && !isset($raw[0]['cols'])) {
            return array_values(array_filter($raw, fn($field) => ($field['type'] ?? '') !== 'step_break'));
        }
        // New row-based format
        $fields = [];
        foreach ($raw as $row) {
            foreach ($row['cols'] ?? [] as $col) {
                foreach ($col['fields'] ?? [] as $field) {
                    if (($field['type'] ?? '') === 'step_break') {
                        continue;
                    }
                    $fields[] = $field;
                }
            }
        }
        return $fields;
    }

    private static function shouldRenderMultiStep(array $rawData, array $styleSettings): bool
    {
        if (($styleSettings['form_mode'] ?? 'standard') === 'multi_step') {
            return true;
        }

        foreach (self::flattenRawFields($rawData) as $field) {
            if (($field['type'] ?? '') === 'step_break') {
                return true;
            }
        }

        return false;
    }

    private static function flattenRawFields(array $rawData): array
    {
        if (isset($rawData[0]['type']) && !isset($rawData[0]['cols'])) {
            return $rawData;
        }

        $fields = [];
        foreach ($rawData as $row) {
            foreach ($row['cols'] ?? [] as $col) {
                foreach ($col['fields'] ?? [] as $field) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    private static function splitRowsIntoSteps(array $rawData): array
    {
        $rows = isset($rawData[0]['cols'])
            ? $rawData
            : array_map(fn($field) => [
                'id' => $field['id'] ?? uniqid('row_', true),
                'cols' => [[
                    'id' => uniqid('col_', true),
                    'span' => 12,
                    'fields' => [$field],
                ]],
            ], $rawData);

        $steps = [];
        $currentRows = [];
        $currentLabel = 'Bước 1';

        foreach ($rows as $row) {
            $marker = self::getStepMarker($row);
            if ($marker !== null) {
                if (!empty($currentRows)) {
                    $steps[] = [
                        'label' => $currentLabel,
                        'rows' => $currentRows,
                    ];
                    $currentRows = [];
                }

                $fallback = 'Bước ' . (count($steps) + 2);
                $currentLabel = trim((string) ($marker['label'] ?? '')) ?: $fallback;

                $rowWithoutMarkers = self::stripStepMarkersFromRow($row);
                if (self::rowHasRenderableFields($rowWithoutMarkers)) {
                    $currentRows[] = $rowWithoutMarkers;
                }

                continue;
            }

            if (self::rowHasRenderableFields($row)) {
                $currentRows[] = $row;
            }
        }

        if (!empty($currentRows) || $steps === []) {
            $steps[] = [
                'label' => $currentLabel,
                'rows' => $currentRows,
            ];
        }

        return $steps;
    }

    private static function getStepMarker(array $row): ?array
    {
        foreach ($row['cols'] ?? [] as $col) {
            foreach ($col['fields'] ?? [] as $field) {
                if (($field['type'] ?? '') === 'step_break') {
                    return $field;
                }
            }
        }

        return null;
    }

    private static function stripStepMarkersFromRow(array $row): array
    {
        foreach ($row['cols'] ?? [] as $colIndex => $col) {
            $row['cols'][$colIndex]['fields'] = array_values(array_filter(
                $col['fields'] ?? [],
                fn($field) => ($field['type'] ?? '') !== 'step_break'
            ));
        }

        return $row;
    }

    private static function rowHasRenderableFields(array $row): bool
    {
        foreach ($row['cols'] ?? [] as $col) {
            foreach ($col['fields'] ?? [] as $field) {
                if (($field['type'] ?? '') !== 'step_break') {
                    return true;
                }
            }
        }

        return false;
    }

    private static function buildConditionAttributes(array $field): string
    {
        $condition = $field['condition'] ?? [];
        if (empty($condition['field'])) {
            return '';
        }

        $operator = $condition['operator'] ?? 'equals';
        if (!in_array($operator, ['equals', 'not_equals', 'contains', 'not_empty', 'empty'], true)) {
            $operator = 'equals';
        }

        return sprintf(
            ' data-condition-field="%s" data-condition-operator="%s" data-condition-value="%s"',
            esc_attr($condition['field']),
            esc_attr($operator),
            esc_attr($condition['value'] ?? '')
        );
    }

    private static function isFieldConditionMatched(array $field, array $source): bool
    {
        $condition = $field['condition'] ?? [];
        if (empty($condition['field'])) {
            return true;
        }

        $operator = $condition['operator'] ?? 'equals';
        $expected = (string) ($condition['value'] ?? '');
        $actual = $source[$condition['field']] ?? '';

        if (is_array($actual)) {
            $actualValues = array_map('strval', $actual);
            $actualString = implode(', ', $actualValues);
        } else {
            $actualValues = [(string) $actual];
            $actualString = (string) $actual;
        }

        return match ($operator) {
            'not_equals' => !in_array($expected, $actualValues, true),
            'contains' => $expected !== '' && str_contains($actualString, $expected),
            'not_empty' => trim($actualString) !== '',
            'empty' => trim($actualString) === '',
            default => in_array($expected, $actualValues, true),
        };
    }

    // =========================================================================
    // VALIDATION / SANITIZATION HELPERS
    // =========================================================================

    private static function sanitizeByType(string $type, mixed $value, array $field): mixed
    {
        if (in_array($type, ['multiselect', 'checkbox'], true) && is_array($value)) {
            $allowed = $field['options'] ?? [];
            return array_filter($value, fn($v) => in_array($v, $allowed, true));
        }

        $value = (string) $value;

        return match ($type) {
            'email'  => sanitize_email($value),
            'url'    => esc_url_raw($value),
            'number' => sanitize_text_field($value),
            'date', 'datetime' => sanitize_text_field($value),
            'textarea' => sanitize_textarea_field($value),
            'select', 'radio' => in_array($value, $field['options'] ?? [], true) ? sanitize_text_field($value) : '',
            default   => sanitize_text_field($value),
        };
    }

    private static function validateFormat(string $type, mixed $value, string $label): string
    {
        $hasValue = trim((string) $value) !== '';

        if ($type === 'email' && $hasValue && !is_email($value)) {
            return $label . ': Địa chỉ email không hợp lệ.';
        }
        if ($type === 'url' && $hasValue && !filter_var($value, FILTER_VALIDATE_URL)) {
            return $label . ': Đường dẫn URL không hợp lệ.';
        }
        if ($type === 'phone' && $hasValue) {
            $digits = preg_replace('/\D+/', '', (string) $value);
            if (
                !preg_match('/^\+?[0-9\s().-]+$/', (string) $value) ||
                strlen($digits) < 8 ||
                strlen($digits) > 15
            ) {
                return $label . ': Số điện thoại không hợp lệ.';
            }
        }
        if ($type === 'number' && $hasValue && !is_numeric($value)) {
            return $label . ': Giá trị phải là số hợp lệ.';
        }
        return '';
    }

    /**
     * Sinh CSS variables scoped theo wrap ID từ style_settings.
     */
    private static function buildScopedCss(string $wrapId, array $s): string
    {
        if (empty($s)) {
            return '';
        }

        $allowed = [
            'primary_color'      => '--cf-primary',
            'secondary_color'    => '--cf-secondary',
            'input_border_color' => '--cf-input-border',
            'label_color'        => '--cf-label-color',
        ];

        $vars = [];
        foreach ($allowed as $key => $var) {
            if (!empty($s[$key])) {
                $val    = preg_replace('/[^a-zA-Z0-9#()\s,%.+-]/', '', $s[$key]);
                $vars[] = $var . ':' . $val;
            }
        }

        // Numeric properties (px values)
        foreach (['btn_border_radius' => '--cf-btn-radius', 'input_border_radius' => '--cf-input-radius'] as $key => $var) {
            if (isset($s[$key])) {
                $val    = (int) $s[$key];
                $vars[] = $var . ':' . $val . 'px';
            }
        }

        // Spacing
        if (!empty($s['input_spacing'])) {
            $val = preg_replace('/[^0-9px\s]/', '', $s['input_spacing']);
            if ($val) {
                $vars[] = '--cf-input-spacing:' . $val;
            }
        }

        // Show label
        if (isset($s['show_label']) && !$s['show_label']) {
            $vars[] = '--cf-label-display:none';
        }

        $css = '';
        if (!empty($vars)) {
            $css .= '#' . $wrapId . '{' . implode(';', $vars) . '}';
        }

        // Custom CSS
        if (!empty($s['custom_css'])) {
            $custom = wp_strip_all_tags($s['custom_css']);
            $custom = str_replace('__FORM__', '#' . $wrapId, $custom);
            $css .= "\n" . $custom;
        }

        return $css;
    }

    private static function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return 'unknown';
    }
}
