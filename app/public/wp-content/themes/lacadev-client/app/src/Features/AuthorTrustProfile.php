<?php
namespace App\Features;

/**
 * Author Trust Profile — E-E-A-T signals
 *
 * Adds rich author meta fields (expertise, credentials, social links) to
 * user profiles and injects JSON-LD Person schema on single posts.
 *
 * Also provides a [laca_author_bio] shortcode that renders a rich bio box
 * suitable for use in single post templates.
 *
 * Meta fields stored on wp_usermeta:
 *   laca_author_role         — Job title / expertise label
 *   laca_author_credentials  — Qualifications, certifications
 *   laca_author_linkedin     — LinkedIn URL
 *   laca_author_twitter      — Twitter/X URL
 *   laca_author_website      — Personal website
 *   laca_author_years_exp    — Years of experience (integer)
 *
 * @package App\Features
 */
class AuthorTrustProfile
{
    public function init(): void
    {
        // Admin: user profile extra fields
        add_action('show_user_profile',   [$this, 'renderProfileFields']);
        add_action('edit_user_profile',   [$this, 'renderProfileFields']);
        add_action('personal_options_update',  [$this, 'saveProfileFields']);
        add_action('edit_user_profile_update', [$this, 'saveProfileFields']);

        // Frontend: inject JSON-LD on single posts
        add_action('wp_head', [$this, 'injectSchemaOrg'], 5);

        // Shortcode: [laca_author_bio]
        add_shortcode('laca_author_bio', [$this, 'renderShortcode']);

        // Filter: append author bio box to single post content (optional, off by default)
        if (get_option('laca_author_bio_auto_append', '0')) {
            add_filter('the_content', [$this, 'appendBioBox'], 20);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Admin profile fields
    // ─────────────────────────────────────────────────────────────────────

    public function renderProfileFields(\WP_User $user): void
    {
        if (!current_user_can('edit_user', $user->ID)) {
            return;
        }
        $meta = $this->getMeta($user->ID);
        ?>
        <h2>Trust Profile (E-E-A-T)</h2>
        <p class="description">Thông tin này xuất hiện trong bio box và JSON-LD schema, giúp cải thiện E-E-A-T.</p>
        <table class="form-table" role="presentation">
            <?php $this->profileRow('Chức danh / Chuyên môn', 'laca_author_role', $meta['role']); ?>
            <?php $this->profileRow('Bằng cấp / Chứng chỉ', 'laca_author_credentials', $meta['credentials']); ?>
            <?php $this->profileRow('Số năm kinh nghiệm', 'laca_author_years_exp', $meta['years_exp'], 'number'); ?>
            <?php $this->profileRow('LinkedIn URL', 'laca_author_linkedin', $meta['linkedin'], 'url'); ?>
            <?php $this->profileRow('Twitter / X URL', 'laca_author_twitter', $meta['twitter'], 'url'); ?>
            <?php $this->profileRow('Website cá nhân', 'laca_author_website', $meta['website'], 'url'); ?>
        </table>
        <?php
        wp_nonce_field('laca_author_trust_save', '_laca_atp_nonce');
    }

    private function profileRow(string $label, string $key, string $value, string $type = 'text'): void
    {
        ?>
        <tr>
            <th><label for="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
            <td><input type="<?php echo esc_attr($type); ?>" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text"></td>
        </tr>
        <?php
    }

    public function saveProfileFields(int $userId): void
    {
        if (!check_admin_referer('laca_author_trust_save', '_laca_atp_nonce')) {
            return;
        }
        if (!current_user_can('edit_user', $userId)) {
            return;
        }

        $fields = ['laca_author_role', 'laca_author_credentials', 'laca_author_linkedin', 'laca_author_twitter', 'laca_author_website'];
        foreach ($fields as $field) {
            update_user_meta($userId, $field, sanitize_text_field($_POST[$field] ?? ''));
        }
        update_user_meta($userId, 'laca_author_years_exp', absint($_POST['laca_author_years_exp'] ?? 0));
    }

    // ─────────────────────────────────────────────────────────────────────
    // JSON-LD schema
    // ─────────────────────────────────────────────────────────────────────

    public function injectSchemaOrg(): void
    {
        if (!is_single()) {
            return;
        }

        $post   = get_post();
        $author = $post ? get_userdata($post->post_author) : null;
        if (!$author) {
            return;
        }

        $meta = $this->getMeta($author->ID);
        $avatarUrl = get_avatar_url($author->ID, ['size' => 96]);

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Article',
            'headline'    => get_the_title($post->ID),
            'datePublished' => get_the_date('c', $post->ID),
            'dateModified'  => get_the_modified_date('c', $post->ID),
            'url'           => get_permalink($post->ID),
            'author'      => array_filter([
                '@type'       => 'Person',
                'name'        => $author->display_name,
                'url'         => get_author_posts_url($author->ID),
                'image'       => $avatarUrl ?: null,
                'jobTitle'    => $meta['role']        ?: null,
                'description' => $author->description ?: null,
                'knowsAbout'  => $meta['credentials'] ? [$meta['credentials']] : null,
                'sameAs'      => array_values(array_filter([
                    $meta['linkedin'],
                    $meta['twitter'],
                    $meta['website'],
                ])),
            ]),
        ];

        if (has_post_thumbnail($post->ID)) {
            $img = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'large');
            if ($img) {
                $schema['image'] = ['@type' => 'ImageObject', 'url' => $img[0], 'width' => $img[1], 'height' => $img[2]];
            }
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }

    // ─────────────────────────────────────────────────────────────────────
    // Bio box shortcode / auto-append
    // ─────────────────────────────────────────────────────────────────────

    public function renderShortcode(array $atts): string
    {
        $atts   = shortcode_atts(['user_id' => 0], $atts, 'laca_author_bio');
        $userId = $atts['user_id'] ? absint($atts['user_id']) : (get_the_author_meta('ID') ?: get_current_user_id());

        if (!$userId) {
            return '';
        }

        return $this->buildBioBox($userId);
    }

    public function appendBioBox(string $content): string
    {
        if (!is_single() || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        $userId = get_the_author_meta('ID');
        if (!$userId) {
            return $content;
        }
        return $content . $this->buildBioBox((int) $userId);
    }

    private function buildBioBox(int $userId): string
    {
        $user = get_userdata($userId);
        if (!$user) {
            return '';
        }

        $meta        = $this->getMeta($userId);
        $avatarUrl   = get_avatar_url($userId, ['size' => 80]);
        $name        = esc_html($user->display_name);
        $role        = esc_html($meta['role']);
        $bio         = esc_html($user->description);
        $years       = absint($meta['years_exp']);
        $creds       = esc_html($meta['credentials']);
        $authorUrl   = get_author_posts_url($userId);
        $socials     = '';
        if ($meta['linkedin']) {
            $socials .= '<a href="' . esc_url($meta['linkedin']) . '" class="laca-atp__social laca-atp__social--linkedin" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">in</a>';
        }
        if ($meta['twitter']) {
            $socials .= '<a href="' . esc_url($meta['twitter']) . '" class="laca-atp__social laca-atp__social--twitter" target="_blank" rel="noopener noreferrer" aria-label="Twitter/X">𝕏</a>';
        }

        ob_start();
        ?>
        <div class="laca-author-trust-profile laca-atp" itemscope itemtype="https://schema.org/Person">
            <div class="laca-atp__avatar">
                <?php if ($avatarUrl): ?>
                    <img src="<?php echo esc_url($avatarUrl); ?>" alt="<?php echo $name; ?>" width="80" height="80" loading="lazy" itemprop="image">
                <?php endif; ?>
            </div>
            <div class="laca-atp__info">
                <div class="laca-atp__header">
                    <a href="<?php echo esc_url($authorUrl); ?>" class="laca-atp__name" itemprop="name"><?php echo $name; ?></a>
                    <?php if ($role): ?><span class="laca-atp__role" itemprop="jobTitle"><?php echo $role; ?></span><?php endif; ?>
                    <?php if ($years): ?><span class="laca-atp__exp"><?php echo $years; ?>+ năm kinh nghiệm</span><?php endif; ?>
                </div>
                <?php if ($bio): ?><p class="laca-atp__bio" itemprop="description"><?php echo $bio; ?></p><?php endif; ?>
                <?php if ($creds): ?><p class="laca-atp__creds"><?php echo $creds; ?></p><?php endif; ?>
                <?php if ($socials): ?><div class="laca-atp__socials"><?php echo $socials; ?></div><?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function getMeta(int $userId): array
    {
        return [
            'role'        => (string) get_user_meta($userId, 'laca_author_role', true),
            'credentials' => (string) get_user_meta($userId, 'laca_author_credentials', true),
            'linkedin'    => (string) get_user_meta($userId, 'laca_author_linkedin', true),
            'twitter'     => (string) get_user_meta($userId, 'laca_author_twitter', true),
            'website'     => (string) get_user_meta($userId, 'laca_author_website', true),
            'years_exp'   => (string) get_user_meta($userId, 'laca_author_years_exp', true),
        ];
    }
}
