<?php
/**
 * @package LacadevClient
 */

namespace App\Contracts;

interface HookNames
{
    // ── Assets ────────────────────────────────────────────────────────────
    const ASSETS_SCRIPTS        = 'lacadev/assets/scripts';
    const ASSETS_STYLES         = 'lacadev/assets/styles';

    // ── Security / Auth ───────────────────────────────────────────────────
    const SUPER_USER_LOGINS     = 'lacadev_super_user_logins';
    const IS_SUPER_USER         = 'lacadev_is_super_user';
    const SECURITY_LOGIN_FAILED = 'lacadev/security/login_failed';

    // ── Contact Form ──────────────────────────────────────────────────────
    const FORM_SUBMITTED        = 'lacadev/contact-form/submitted';
    const FORM_DATA_FILTER      = 'lacadev/contact-form/data';
    const FORM_EMAIL_ARGS       = 'lacadev/contact-form/email/args';

    // ── Custom Post Types ─────────────────────────────────────────────────
    const CPT_REGISTERED        = 'lacadev/cpt/registered';
    const TAX_REGISTERED        = 'lacadev/taxonomy/registered';

    // ── Theme Options ─────────────────────────────────────────────────────
    const THEME_OPTIONS_TABS    = 'lacadev/theme-options/tabs';

    // ── Block Sync (Hub → Client) ─────────────────────────────────────────
    const BLOCK_SYNC_RECEIVED   = 'lacadev/block-sync/received';
    const BLOCK_SYNC_BEFORE     = 'lacadev/block-sync/before-send';

    // ── Client Tracker ────────────────────────────────────────────────────
    const TRACKER_PAYLOAD       = 'lacadev_tracker_payload';
}
