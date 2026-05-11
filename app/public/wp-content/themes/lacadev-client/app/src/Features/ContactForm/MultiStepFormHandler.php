<?php
namespace App\Features\ContactForm;

use App\Databases\ContactFormTable;

/**
 * Multi-step Lead Form
 *
 * Wraps the existing ContactForm into a multi-step UI.
 * Steps are defined by adding a `step_break` marker field when building the form
 * in the admin UI, OR via the `lacadev/lead-form/steps` filter.
 *
 * Shortcode: [laca_lead_form id="X"]
 *
 * Steps are determined client-side: fields between `step_break` markers form
 * a logical step.  All fields are submitted together at the final step using
 * the existing `laca_contact_submit` AJAX action — no extra endpoint needed.
 *
 * @package App\Features\ContactForm
 */
class MultiStepFormHandler
{
    public function init(): void
    {
        add_shortcode('laca_lead_form', [$this, 'renderShortcode']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);
        // Register step_break as a recognised field type in the form builder UI
        add_filter('lacadev/contact-form/field-types', [$this, 'registerStepBreakType']);
    }

    /** Add step_break to the admin field-type list */
    public function registerStepBreakType(array $types): array
    {
        $types['step_break'] = 'Ngăn bước (Step Break)';
        return $types;
    }

    public function enqueueAssets(): void
    {
        // JS module is already bundled into theme.js — nothing to do here.
        // SCSS is imported into the main theme stylesheet.
    }

    // ─────────────────────────────────────────────────────────────────────
    // Shortcode renderer
    // ─────────────────────────────────────────────────────────────────────

    public function renderShortcode(array $atts): string
    {
        $atts = shortcode_atts([
            'id'         => 0,
            'step_names' => '', // Comma-separated: "Thông tin,Dự án,Ngân sách"
            'btn_next'   => 'Tiếp theo →',
            'btn_prev'   => '← Quay lại',
            'btn_submit' => 'Gửi thông tin',
        ], $atts, 'laca_lead_form');

        $formId = absint($atts['id']);
        if (!$formId) {
            return '<!-- laca_lead_form: missing id -->';
        }

        $form = ContactFormTable::getForm($formId);
        if (!$form) {
            return '<!-- laca_lead_form: form not found -->';
        }

        $allFields  = $this->extractRawFlatFields($form);
        $steps      = $this->splitIntoSteps($allFields);
        $stepNames  = $atts['step_names']
            ? array_map('trim', explode(',', $atts['step_names']))
            : [];
        $totalSteps = count($steps);

        $nonce = wp_create_nonce('laca_contact_submit_nonce');

        ob_start();
        ?>
        <div class="laca-multistep-form" data-form-id="<?php echo esc_attr($formId); ?>" data-total-steps="<?php echo esc_attr($totalSteps); ?>">

            <?php /* ── Progress bar ─────────────────────────────────────── */ ?>
            <div class="lmsf__progress" role="progressbar" aria-valuemin="1" aria-valuemax="<?php echo $totalSteps; ?>" aria-valuenow="1">
                <div class="lmsf__progress-track">
                    <div class="lmsf__progress-fill" style="width: <?php echo round(100 / $totalSteps); ?>%"></div>
                </div>
                <ul class="lmsf__steps-indicator">
                    <?php for ($i = 0; $i < $totalSteps; $i++): ?>
                        <li class="lmsf__step-dot <?php echo $i === 0 ? 'is-active' : ''; ?>" data-step="<?php echo $i; ?>">
                            <span class="lmsf__step-number"><?php echo $i + 1; ?></span>
                            <?php if (!empty($stepNames[$i])): ?>
                                <span class="lmsf__step-name"><?php echo esc_html($stepNames[$i]); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>
                </ul>
            </div>

            <?php /* ── Messages ─────────────────────────────────────────── */ ?>
            <div class="lmsf__notice" role="alert" aria-live="polite" hidden></div>

            <?php /* ── Form ─────────────────────────────────────────────── */ ?>
            <form class="lmsf__form" novalidate>
                <input type="hidden" name="_nonce"   value="<?php echo esc_attr($nonce); ?>">
                <input type="hidden" name="form_id"  value="<?php echo esc_attr($formId); ?>">

                <?php foreach ($steps as $stepIndex => $stepFields): ?>
                    <div class="lmsf__panel <?php echo $stepIndex === 0 ? 'is-active' : ''; ?>" data-panel="<?php echo $stepIndex; ?>" <?php echo $stepIndex !== 0 ? 'hidden' : ''; ?>>
                        <?php echo $this->renderFields($stepFields); ?>
                    </div>
                <?php endforeach; ?>

                <?php /* ── Navigation ──────────────────────────────────── */ ?>
                <div class="lmsf__nav">
                    <button type="button" class="lmsf__btn lmsf__btn--prev" hidden>
                        <?php echo esc_html($atts['btn_prev']); ?>
                    </button>
                    <button type="button" class="lmsf__btn lmsf__btn--next">
                        <?php echo esc_html($atts['btn_next']); ?>
                    </button>
                    <button type="submit" class="lmsf__btn lmsf__btn--submit" hidden>
                        <?php echo esc_html($atts['btn_submit']); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php $this->printInlineScript(); ?>
        <?php
        return ob_get_clean();
    }

    private function printInlineScript(): void
    {
        ?>
        <script<?php echo defined('LACA_CSP_NONCE') ? ' nonce="' . esc_attr(LACA_CSP_NONCE) . '"' : ''; ?>>
        (function() {
            const SCRIPT_EL = document.currentScript;

            function boot() {
                const root = SCRIPT_EL ? SCRIPT_EL.previousElementSibling : null;
                if (!root || !root.classList || !root.classList.contains('laca-multistep-form')) {
                    return;
                }
                if (root.dataset.lacaLeadReady === '1') {
                    return;
                }
                root.dataset.lacaLeadReady = '1';

                const form = root.querySelector('.lmsf__form');
                const panels = Array.from(root.querySelectorAll('.lmsf__panel'));
                const btnPrev = root.querySelector('.lmsf__btn--prev');
                const btnNext = root.querySelector('.lmsf__btn--next');
                const btnSubmit = root.querySelector('.lmsf__btn--submit');
                const notice = root.querySelector('.lmsf__notice');
                const ajaxUrl = (window.themeData && window.themeData.ajaxurl) || '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                let current = 0;

                if (!form || !panels.length) {
                    return;
                }

                const showNotice = (message, type = 'error') => {
                    if (!notice) {
                        return;
                    }
                    notice.textContent = message;
                    notice.className = 'lmsf__notice lmsf__notice--' + type;
                    notice.hidden = false;
                    notice.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                };

                const clearNotice = () => {
                    if (!notice) {
                        return;
                    }
                    notice.textContent = '';
                    notice.hidden = true;
                };

                const clearFieldError = (input) => {
                    input.classList.remove('is-invalid', 'laca-cf-field-invalid');
                    input.setAttribute('aria-invalid', 'false');
                    const row = input.closest('.laca-cf-form-row');
                    const error = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (error) {
                        error.textContent = '';
                        error.hidden = true;
                    }
                };

                const showFieldError = (input, message) => {
                    input.classList.add('is-invalid', 'laca-cf-field-invalid');
                    input.setAttribute('aria-invalid', 'true');
                    const row = input.closest('.laca-cf-form-row');
                    const error = row ? row.querySelector('.laca-cf-field-error') : null;
                    if (error) {
                        error.textContent = message;
                        error.hidden = false;
                    }
                };

                const validatePanel = (panel) => {
                    let valid = true;
                    let firstInvalid = null;

                    panel.querySelectorAll('[data-required="true"], [required]').forEach((input) => {
                        const row = input.closest('.laca-cf-form-row');
                        if (!row || row.hidden || input.disabled) {
                            return;
                        }

                        let empty = false;
                        if (input.type === 'checkbox' || input.type === 'radio') {
                            empty = !form.querySelector('[name="' + input.name + '"]:checked');
                        } else if (input.tagName === 'SELECT' && input.multiple) {
                            empty = input.selectedOptions.length === 0;
                        } else {
                            empty = !String(input.value || '').trim();
                        }

                        if (empty) {
                            const label = row.querySelector('.laca-cf-label')?.textContent?.replace('*', '').trim() || 'Trường này';
                            showFieldError(input, label + ' là bắt buộc.');
                            if (!firstInvalid) {
                                firstInvalid = input;
                            }
                            valid = false;
                        } else {
                            clearFieldError(input);
                        }
                    });

                    if (firstInvalid) {
                        firstInvalid.focus({ preventScroll: true });
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }

                    return valid;
                };

                const showStep = (step) => {
                    current = Math.max(0, Math.min(panels.length - 1, step));
                    panels.forEach((panel, index) => {
                        const active = index === current;
                        panel.hidden = !active;
                        panel.classList.toggle('is-active', active);
                    });
                    if (btnPrev) {
                        btnPrev.hidden = current === 0;
                    }
                    if (btnNext) {
                        btnNext.hidden = current >= panels.length - 1;
                    }
                    if (btnSubmit) {
                        btnSubmit.hidden = current < panels.length - 1;
                    }
                    const pct = Math.round(((current + 1) / panels.length) * 100);
                    const fill = root.querySelector('.lmsf__progress-fill');
                    const progress = root.querySelector('.lmsf__progress');
                    if (fill) {
                        fill.style.width = pct + '%';
                    }
                    if (progress) {
                        progress.setAttribute('aria-valuenow', String(current + 1));
                    }
                    root.querySelectorAll('.lmsf__step-dot').forEach((dot, index) => {
                        dot.classList.toggle('is-active', index === current);
                        dot.classList.toggle('is-done', index < current);
                    });
                    clearNotice();
                };

                form.addEventListener('click', function(event) {
                    const next = event.target.closest('.lmsf__btn--next');
                    const prev = event.target.closest('.lmsf__btn--prev');
                    if (next && form.contains(next)) {
                        event.preventDefault();
                        if (!validatePanel(panels[current])) {
                            showNotice('Vui lòng điền đầy đủ thông tin bắt buộc.');
                            return;
                        }
                        showStep(current + 1);
                    }
                    if (prev && form.contains(prev)) {
                        event.preventDefault();
                        showStep(current - 1);
                    }
                });

                form.querySelectorAll('input, select, textarea').forEach((input) => {
                    input.addEventListener('input', () => clearFieldError(input));
                    input.addEventListener('change', () => clearFieldError(input));
                });

                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    if (!validatePanel(panels[current])) {
                        showNotice('Vui lòng điền đầy đủ thông tin bắt buộc.');
                        return;
                    }

                    const formData = new FormData(form);
                    formData.append('action', 'laca_contact_submit');

                    if (btnSubmit) {
                        btnSubmit.disabled = true;
                        btnSubmit.dataset.labelOriginal = btnSubmit.dataset.labelOriginal || btnSubmit.textContent;
                        btnSubmit.textContent = 'Đang gửi...';
                    }

                    fetch(ajaxUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: formData,
                    })
                        .then((response) => response.json())
                        .then((json) => {
                            if (json.success) {
                                showNotice((json.data && json.data.message) || 'Gửi thành công.', 'success');
                                form.reset();
                                showStep(0);
                            } else {
                                showNotice((json.data && json.data.message) || 'Có lỗi xảy ra. Vui lòng thử lại.');
                            }
                        })
                        .catch(() => {
                            showNotice('Không thể kết nối máy chủ. Vui lòng thử lại.');
                        })
                        .finally(() => {
                            if (btnSubmit) {
                                btnSubmit.disabled = false;
                                btnSubmit.textContent = btnSubmit.dataset.labelOriginal || 'Gửi thông tin';
                            }
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

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Split a flat field list into steps at every `step_break` marker.
     *
     * @param  array<array<string,mixed>> $fields
     * @return array<array<array<string,mixed>>>  Array of per-step field arrays
     */
    private function splitIntoSteps(array $fields): array
    {
        $steps   = [];
        $current = [];

        foreach ($fields as $field) {
            if (($field['type'] ?? '') === 'step_break') {
                if (!empty($current)) {
                    $steps[]  = $current;
                    $current  = [];
                }
                continue;
            }
            $current[] = $field;
        }

        if (!empty($current)) {
            $steps[] = $current;
        }

        // Fallback: no step_break → treat entire form as one step
        return $steps ?: [[]];
    }

    private function extractRawFlatFields(array $form): array
    {
        $raw = json_decode($form['fields'] ?? '[]', true) ?: [];
        if (empty($raw)) {
            return [];
        }

        if (isset($raw[0]['type']) && !isset($raw[0]['cols'])) {
            return $raw;
        }

        $fields = [];
        foreach ($raw as $row) {
            foreach ($row['cols'] ?? [] as $col) {
                foreach ($col['fields'] ?? [] as $field) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Render fields for one step (delegates to existing field renderer).
     * Uses the same markup ContactFormAjaxHandler outputs.
     */
    private function renderFields(array $fields): string
    {
        ob_start();
        foreach ($fields as $field) {
            echo ContactFormAjaxHandler::renderSingleField($field); // see below
        }
        return ob_get_clean();
    }
}
